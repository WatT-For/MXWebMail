<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\Wildcard;

/**
 * Quota d'envoi par compte webmail : borne le nombre de messages/heure
 * et/ou par jour, et le nombre de destinataires/jour. Amortit le degat
 * d'un compte compromis (spam sortant). Ne borne QUE le webmail : un
 * client IMAP/SMTP direct contourne - le noter dans la doc du domaine.
 * Compteur en table plugin_sendrate de la base applicative.
 */
final class SendRateLimit extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('message.before-send', function (\MailSo\Mime\Message $message) {
			$uid = $this->app->sessionUserId();
			$email = $this->app->sessionEmail();
			if ($uid <= 0) {
				return;
			}
			$exempt = $this->configTrim('exempt');
			if ($exempt !== '' && Wildcard::match(strtolower($email), $exempt)) {
				return;
			}
			$maxHour = max(0, $this->configInt('max_msgs_hour', 0));
			$maxDay  = max(0, $this->configInt('max_msgs_day', 0));
			$maxRcpts= max(0, $this->configInt('max_rcpts_day', 0));
			if (!$maxHour && !$maxDay && !$maxRcpts) {
				return;
			}
			$rcpts = count(iterator_to_array($message->GetRcpt()));
			$pdo = $this->pdo();
			$now = time();
			$cnt = function (int $window) use ($pdo, $uid, $now): array {
				$st = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(rcpts),0) r
					FROM plugin_sendrate WHERE user_id = :u AND ts > :t');
				$st->execute(['u' => $uid, 't' => $now - $window]);
				$r = $st->fetch(\PDO::FETCH_ASSOC);
				return [(int) $r['c'], (int) $r['r']];
			};
			if ($maxHour) {
				[$c] = $cnt(3600);
				if ($c >= $maxHour) {
					throw new \RuntimeException(
						$this->t('plugin.send-rate-limit.exceeded_hour', ['n' => $maxHour]), 429);
				}
			}
			if ($maxDay || $maxRcpts) {
				[$c, $r] = $cnt(86400);
				if ($maxDay && $c >= $maxDay) {
					throw new \RuntimeException(
						$this->t('plugin.send-rate-limit.exceeded_day', ['n' => $maxDay]), 429);
				}
				if ($maxRcpts && $r + $rcpts > $maxRcpts) {
					throw new \RuntimeException(
						$this->t('plugin.send-rate-limit.exceeded_rcpts', ['n' => $maxRcpts]), 429);
				}
			}
			$pdo->prepare('INSERT INTO plugin_sendrate (user_id, ts, rcpts)
				VALUES (:u, :t, :r)')
				->execute(['u' => $uid, 't' => $now, 'r' => $rcpts]);
			// Purge opportuniste (1/20) des lignes > 7 jours.
			if (random_int(1, 20) === 1) {
				$pdo->prepare('DELETE FROM plugin_sendrate WHERE ts < :t')
					->execute(['t' => $now - 604800]);
			}
		});
	}
	// Table plugin_sendrate : schéma géré par migrations.php (PluginManager).
}
