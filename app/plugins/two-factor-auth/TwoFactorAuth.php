<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMailLib\QRCode;
use MXWebMailLib\TOTP;

/**
 * Double authentification TOTP.
 * - auth.post-login : vérifie le code TOTP si le compte l'a activé
 * - app.data        : ajoute l'onglet "Double authentification" dans Paramètres
 * - admin.mounts    : sous-onglet de provisioning dans Admin → Sécurité
 * - admin.post-login: exige le TOTP admin si admin_totp_secret est configuré
 *                     (plugin désactivé = non exigé)
 * API user  : /api/plugin/two-factor-auth/{status|setup|enable|disable}
 * API admin : /api/admin/plugin/two-factor-auth/{status|setup|enable|disable}
 */
final class TwoFactorAuth extends PluginBase
{
	private const KEY_ACTIVE   = 'plugin.2fa.secret';
	private const KEY_PENDING  = 'plugin.2fa.pending';
	private const KEY_RECOVERY = 'plugin.2fa.recovery';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('auth.post-login', function (array $user, ?string $code) {
			$secret = $this->app->settings()->get((int) $user['id'], self::KEY_ACTIVE);
			if (!$secret) {
				return; // 2FA non activée pour ce compte
			}
			if (!$code || !TOTP::Verify($secret, (string) $code)) {
				// Repli : codes de secours (consommés à l'usage) — saisis
				// dans le même champ que le code TOTP.
				if ($code && $this->consumeRecovery((int) $user['id'], (string) $code)) {
					return;
				}
				throw new \MXWebMail\Security\TotpRequiredException(
					$this->t('errors.totp_required'), 401);
			}
		});

		$hooks->on('app.data', function (array $data) {
			$data['pluginTabs'][] = [
				'id'    => 'two-factor-auth',
				'label' => 'plugin.two-factor-auth.tab',
				'icon'  => 'fa-shield-halved',
				'js'    => 'two-factor.js',
				'plugin'=> 'two-factor-auth',
			];
			return $data;
		});

		// Sous-onglet « Double authentification » dans Admin → Sécurité
		$hooks->on('admin.mounts', fn() => [
			'js'      => 'two-factor.js',
			'plugin'  => 'two-factor-auth',
			'section' => 'security',
			'tab'     => '2fa',
			'label'   => 'plugin.two-factor-auth.tab',
		]);

		// TOTP du compte admin local : admin_totp_secret en config.php.
		// Émis par AdminController::login après la vérif du mot de passe.
		$hooks->on('admin.post-login', function (string $code) {
			$secret = (string) $this->app->config()->get('admin_totp_secret', '');
			if ($secret !== '' && !TOTP::Verify($secret, $code)) {
				throw new \MXWebMail\Security\TotpRequiredException(
					$this->t('errors.totp_required'), 401);
			}
		});
		$hooks->on('admin.totp-required', fn() =>
			(string) $this->app->config()->get('admin_totp_secret', '') !== '');
	}

	// ─── API (POST /api/plugin/two-factor-auth/<action>) ───

	public function apiStatus(Request $request, ?array $user): array
	{
		$recovery = $this->app->settings()->get((int) $user['id'], self::KEY_RECOVERY);
		return [
			'enabled' => (bool) $this->app->settings()->get((int) $user['id'], self::KEY_ACTIVE),
			'recovery' => is_array($recovery) ? count($recovery) : 0,
		];
	}

	/** Génère (ou régénère) un secret de provisioning + la matrice QR. */
	public function apiSetup(Request $request, ?array $user): array
	{
		$secret = TOTP::CreateSecret();
		$this->app->settings()->set((int) $user['id'], self::KEY_PENDING, $secret);
		return $this->provisionPayload($secret, (string) ($user['username'] ?? 'user'));
	}

	/** Active la 2FA si le code fourni correspond au secret pending. */
	public function apiEnable(Request $request, ?array $user): array
	{
		$pending = $this->app->settings()->get((int) $user['id'], self::KEY_PENDING);
		$code = (string) $request->input('code', '');
		if (!$pending) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.no_pending'), 400);
		}
		if (!TOTP::Verify($pending, $code)) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.code_invalid'), 400);
		}
		$this->app->settings()->set((int) $user['id'], self::KEY_ACTIVE, $pending);
		$this->app->settings()->set((int) $user['id'], self::KEY_PENDING, null);
		// Codes de secours affichés une seule fois — seuls les hash persistent.
		$recovery = self::recoveryGenerate();
		$this->app->settings()->set((int) $user['id'], self::KEY_RECOVERY,
			array_map(static fn(string $c) => hash('sha256', $c), $recovery));
		return ['enabled' => true, 'recovery' => $recovery];
	}

	public function apiDisable(Request $request, ?array $user): array
	{
		$secret = $this->app->settings()->get((int) $user['id'], self::KEY_ACTIVE);
		$code = (string) $request->input('code', '');
		if ($secret && !TOTP::Verify($secret, $code)) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.code_invalid_disable'), 400);
		}
		$this->app->settings()->set((int) $user['id'], self::KEY_ACTIVE, null);
		$this->app->settings()->set((int) $user['id'], self::KEY_RECOVERY, null);
		return ['enabled' => false];
	}

	/** Régénère les codes de secours (2FA active exigée). */
	public function apiRecovery(Request $request, ?array $user): array
	{
		if (!$this->app->settings()->get((int) $user['id'], self::KEY_ACTIVE)) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.no_pending'), 400);
		}
		$recovery = self::recoveryGenerate();
		$this->app->settings()->set((int) $user['id'], self::KEY_RECOVERY,
			array_map(static fn(string $c) => hash('sha256', $c), $recovery));
		return ['recovery' => $recovery];
	}

	/** 8 codes « xxxx-xxxx » — le hash sha256 du code est stocké, jamais le code. */
	private static function recoveryGenerate(): array
	{
		$codes = [];
		for ($i = 0; $i < 8; $i++) {
			$h = bin2hex(random_bytes(4));
			$codes[] = substr($h, 0, 4) . '-' . substr($h, 4);
		}
		return $codes;
	}

	/** Consomme un code de secours valide (retrait + persistance). */
	private function consumeRecovery(int $userId, string $code): bool
	{
		$hashes = $this->app->settings()->get($userId, self::KEY_RECOVERY);
		if (!is_array($hashes) || !$hashes) {
			return false;
		}
		$hash = hash('sha256', strtolower(trim($code)));
		$key = array_search($hash, $hashes, true);
		if ($key === false) {
			return false;
		}
		unset($hashes[$key]);
		$this->app->settings()->set($userId, self::KEY_RECOVERY, array_values($hashes));
		return true;
	}

	// ─── API admin (/api/admin/plugin/two-factor-auth/<action>) ───
	// Compte admin local : secret dans config.php (admin_totp_secret) via
	// ConfigWriter ; pending en session (pas de ligne users pour y rattacher
	// un secret). requireAdmin a fermé la session : elle est ré-ouverte via
	// App::startSession() (session_name custom) pour écrire/purger le pending.
	// Défense en profondeur : $_SESSION['admin'] est revérifié ici — la garde
	// ne doit pas reposer uniquement sur le routage.

	private function requireAdminSession(): void
	{
		if (empty($_SESSION['admin'])) {
			throw new \RuntimeException($this->t('errors.admin_required'), 403);
		}
	}

	public function apiAdminStatus(Request $request): array
	{
		$this->requireAdminSession();
		$cfg = $this->app->config();
		return [
			'enabled'  => (string) $cfg->get('admin_totp_secret', '') !== '',
			'local'    => (string) $cfg->get('admin_user', '') !== ''
				&& (string) $cfg->get('admin_password_hash', '') !== '',
			'writable' => (new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))->writable(),
		];
	}

	public function apiAdminSetup(Request $request): array
	{
		$this->requireAdminSession();
		$secret = TOTP::CreateSecret();
		$this->app->startSession();
		$_SESSION['admin_totp_pending'] = $secret;
		session_write_close();
		$label = (string) $this->app->config()->get('admin_user', '') ?: 'admin';
		return $this->provisionPayload($secret, $label);
	}

	public function apiAdminEnable(Request $request): array
	{
		$this->requireAdminSession();
		$pending = (string) ($_SESSION['admin_totp_pending'] ?? '');
		$code = (string) $request->input('code', '');
		if ($pending === '') {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.no_pending'), 400);
		}
		if (!TOTP::Verify($pending, $code)) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.code_invalid'), 400);
		}
		(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
			->write(['admin_totp_secret' => $pending], $this->app->config());
		$this->app->startSession();
		unset($_SESSION['admin_totp_pending']);
		session_write_close();
		return ['enabled' => true];
	}

	public function apiAdminDisable(Request $request): array
	{
		$this->requireAdminSession();
		$secret = (string) $this->app->config()->get('admin_totp_secret', '');
		$code = (string) $request->input('code', '');
		if ($secret !== '' && !TOTP::Verify($secret, $code)) {
			throw new \RuntimeException($this->t('plugin.two-factor-auth.code_invalid_disable'), 400);
		}
		(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
			->write(['admin_totp_secret' => ''], $this->app->config());
		return ['enabled' => false];
	}

	/** Secret + URI otpauth + matrice QR, partagé user/admin. */
	private function provisionPayload(string $secret, string $label): array
	{
		$issuer = rawurlencode(trim((string) ($this->config['issuer'] ?? ''))
			?: (string) $this->app->config()->get('app_name', 'MXWebMail'));
		if (($this->config['label'] ?? 'email') === 'local') {
			$label = strstr($label, '@', true) ?: $label;
		}
		$otpauth = "otpauth://totp/{$issuer}:" . rawurlencode($label)
			. "?secret={$secret}&issuer={$issuer}";
		// Paramètre non standard image= : Aegis/andOTP/FreeOTP — Authy l'ignore.
		// Vide ou invalide → favicon publique de l'instance.
		$icon = trim((string) ($this->config['icon_url'] ?? ''));
		if (!preg_match('#^https?://#i', $icon)) {
			$icon = $this->app->publicUrl('/favicon.ico');
		}
		$otpauth .= '&image=' . rawurlencode($icon);

		$qr = QRCode::getMinimumQRCode($otpauth, 1); // ECC L
		$count = $qr->getModuleCount();
		$matrix = [];
		for ($r = 0; $r < $count; $r++) {
			$row = [];
			for ($c = 0; $c < $count; $c++) {
				$row[] = $qr->isDark($r, $c);
			}
			$matrix[] = $row;
		}
		return ['secret' => $secret, 'otpauth' => $otpauth, 'qr' => $matrix];
	}
}
