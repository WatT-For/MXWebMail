<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Alerte par mail sur connexion depuis une IP inconnue (modele Gmail).
 * auth.post-login : compare l'IP aux IP connues du compte (table
 * plugin_login_ips), memorise un pending en settings. app.data (bootstrap,
 * session IMAP disponible) : livre l'alerte en INBOX par APPEND - non lue,
 * sans copie Sent ni RTT SMTP ; le pending est consomme avant l'envoi
 * (pas de rejeu en boucle si APPEND echoue).
 * Couvre aussi le login admin via fallback IMAP (meme AuthService::login) ;
 * pas de tir sur resumeFromRemember (restauration cookie = meme poste).
 */
final class LoginNotify extends PluginBase
{
	private const K_PENDING = 'plugin.login-notify.pending';
	private const K_ENABLED = 'plugin.login-notify.enabled';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('auth.post-login', function (array $user, ?string $code) {
			$uid = (int) $user['id'];
			$ip = $this->app->clientIp();
			if ($ip === '' || $this->configBool('ignore_private') && !filter_var(
				$ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return;
			}
			if (!$this->enabled($uid)) {
				return;
			}
			$pdo = $this->pdo();
			$st = $pdo->prepare('SELECT ip FROM plugin_login_ips
				WHERE user_id = :u AND ip = :i');
			$st->execute(['u' => $uid, 'i' => $ip]);
			$now = time();
			if ($st->fetch()) {
				$pdo->prepare('UPDATE plugin_login_ips SET last_seen = :t,
					hits = hits + 1 WHERE user_id = :u AND ip = :i')
					->execute(['t' => $now, 'u' => $uid, 'i' => $ip]);
				return;
			}
			$pdo->prepare('INSERT INTO plugin_login_ips
				(user_id, ip, first_seen, last_seen, hits) VALUES (:u,:i,:t,:t,1)')
				->execute(['u' => $uid, 'i' => $ip, 't' => $now]);
			// Premiere IP enregistree du compte = reference, pas d'alerte.
			if ((int) $pdo->query('SELECT COUNT(*) FROM plugin_login_ips
					WHERE user_id = ' . $uid)->fetchColumn() > 1) {
				$ua = substr(str_replace(["\r", "\n"], ' ',
					(string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 200);
				$this->app->settings()->set($uid, self::K_PENDING,
					['ip' => $ip, 'ua' => $ua, 'ts' => $now]);
			}
		});

		$hooks->on('app.data', function (array $data, ?array $user) {
			if ($this->configBool('user_control', true)) {
				$data['pluginTabs'][] = $this->userTab('login-notify.js', 'fa-bell');
			}
			$pending = $this->app->settings()->get((int) ($user['id'] ?? 0), self::K_PENDING);
			if (is_array($pending) && !empty($pending['ip'])) {
				$this->app->settings()->set((int) $user['id'], self::K_PENDING, null);
				try {
					$this->deliver($pending);
				} catch (\Throwable $e) {
					$this->app->log('warning', 'login-notify: ' . $e->getMessage());
				}
			}
			return $data;
		});
	}

	private function enabled(int $uid): bool
	{
		if (!$this->configBool('user_control', true)) {
			return true;
		}
		return $this->app->settings()->get($uid, self::K_ENABLED, '1') !== '0';
	}

	/** Alerte deposee non lue dans le dossier configure (defaut INBOX). */
	private function deliver(array $pending): void
	{
		$me = $this->app->sessionEmail();
		if ($me === '') {
			return;
		}
		$ip = (string) $pending['ip'];
		if ($this->configBool('mask_ip')) {
			$ip = str_contains($ip, ':')
				? preg_replace('/:[0-9a-f]*(:[0-9a-f]*)?$/i', '::*', $ip)
				: preg_replace('/\.\d+$/', '.x', $ip);
		}
		$body = $this->t('plugin.login-notify.body', [
			'ip'   => $ip,
			'date' => date(\DateTimeInterface::RFC2822, (int) ($pending['ts'] ?? time())),
			'ua'   => $this->configBool('include_ua', true)
				? (string) ($pending['ua'] ?? '') : '',
		]);
		$subject = mb_encode_mimeheader($this->t('plugin.login-notify.subject'), 'UTF-8');
		$raw = "From: {$me}\r\nTo: {$me}\r\nSubject: {$subject}\r\n"
			. 'Date: ' . date(\DateTimeInterface::RFC2822) . "\r\n"
			. 'Message-ID: <' . bin2hex(random_bytes(8)) . '@'
				. \MailSo\Smtp\SmtpClient::EhloHelper() . ">\r\n"
			. "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n"
			. "Content-Transfer-Encoding: quoted-printable\r\n"
			. "Auto-Submitted: auto-generated\r\n\r\n"
			. quoted_printable_encode($body);
		$folder = $this->configTrim('folder', 'INBOX') ?: 'INBOX';
		$this->app->mail()->appendMessage($raw, $folder, []);
	}

	// ─── API utilisateur (/api/plugin/login-notify/<action>) ───
	// Table plugin_login_ips : schéma géré par migrations.php (PluginManager).

	public function apiStatus(Request $request, ?array $user): array
	{
		return ['enabled' => $this->enabled((int) $user['id'])];
	}

	public function apiSet(Request $request, ?array $user): array
	{
		$on = $request->input('enabled') ? '1' : '0';
		$this->app->settings()->set((int) $user['id'], self::K_ENABLED, $on);
		return ['enabled' => $on === '1'];
	}
}
