<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Suggestions de contacts depuis les expéditeurs d'un dossier IMAP :
 * SEARCH FROM <requête> puis FETCH des en-têtes From: des premiers résultats.
 * Réutilise la connexion IMAP de session (pas de credentials en plus).
 * Portage du plugin legacy imap-contacts-suggestions.
 */
final class ImapContactsSuggestions extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('contacts.provider', function (string $q) {
			return $this->search($q);
		});
	}

	public function search(string $q): array
	{
		$q = trim($q);
		$max = max(1, (int) ($this->config['max_results'] ?? 10));
		if (mb_strlen($q) < 2) {
			return [];
		}
		try {
			$imap = $this->app->mail()->client()->ImapClient();
			$imap->FolderSelect((string) ($this->config['folder'] ?? 'INBOX'));

			$q = \MailSo\Imap\SearchCriterias::escapeSearchString($imap, $q);
			$uids = array_slice($imap->MessageSearch("FROM {$q}"), 0, $max);
			if (!$uids) {
				return [];
			}

			$out = [];
			$seen = [];
			foreach ($imap->Fetch(
				['BODY.PEEK[HEADER.FIELDS (FROM)]'], implode(',', $uids), true
			) as $response) {
				$headers = new \MailSo\Mime\HeaderCollection($response->GetHeaderFieldsValue());
				foreach ($headers->GetAsEmailCollection('From') ?? [] as $mail) {
					$email = $mail->GetEmail();
					if ($email !== '' && !isset($seen[$email])) {
						$seen[$email] = true;
						$out[] = [
							'name'   => $mail->GetDisplayName(),
							'email'  => $email,
							'source' => 'imap',
						];
					}
				}
			}
			return array_slice($out, 0, $max);
		} catch (\Throwable $e) {
			$this->app->log('warning', 'imap-contacts-suggestions: ' . $e->getMessage());
			return [];
		}
	}
}
