<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\Wildcard;

/**
 * Ajoute des destinataires Cc ou Bcc systematiques a chaque envoi
 * (archive de conformite, adresse de suivi). Bcc = invisible pour les
 * autres destinataires (enveloppe uniquement) ; Cc = visible par tous.
 * "senders" = wildcards d'expediteurs concernes (vide/* = tous).
 */
final class AlwaysCcBcc extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('message.before-send', function (\MailSo\Mime\Message $message) {
			$raw = trim((string) ($this->config['addresses'] ?? ''));
			if ($raw === '') {
				return;
			}
			$me = strtolower((string) ($_SESSION['email'] ?? ''));
			if (!Wildcard::match($me, (string) ($this->config['senders'] ?? '*'))) {
				return;
			}
			[$extra, $bad] = \MailSo\Mime\EmailCollection::ParseStrict($raw);
			if ($bad) {
				throw new \RuntimeException(
					$this->t('plugin.always-cc-bcc.bad_addresses'), 400);
			}
			$cc = (string) ($this->config['mode'] ?? 'bcc') === 'cc';
			$target = ($cc ? $message->GetCc() : $message->GetBcc())
				?? new \MailSo\Mime\EmailCollection();
			$have = [$me => true];
			foreach ($target as $e) {
				$have[strtolower((string) $e->GetEmail())] = true;
			}
			$added = false;
			foreach ($extra as $e) {
				$em = strtolower((string) $e->GetEmail());
				if ($em === '' || isset($have[$em])) {
					continue;
				}
				$have[$em] = true;
				$target->append($e);
				$added = true;
			}
			if ($added) {
				$cc ? $message->SetCc($target) : $message->SetBcc($target);
			}
		});
	}
}
