<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Cache\FileCache;
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
		$max = max(1, $this->configInt('max_results', 10));
		if (mb_strlen($q) < 2) {
			return [];
		}
		// TTL 5 min (mtime du fichier) : l'endpoint est frappé à chaque
		// frappe (debounce 250 ms) - sans cache chaque saisie ouvrirait
		// une connexion IMAP + SEARCH.
		$cache = new FileCache(
			$this->app->storageDir() . '/cache/contacts.imap');
		// L'identité du compte fait partie de la clé : l'index de cache est
		// partagé - sans elle deux comptes se serviraient leurs suggestions.
		$file = md5(
			$this->app->sessionAccountId() . '|' . $this->app->sessionEmail()
			. '|' . $this->configString('folder', 'INBOX') . '|' . $q . '|' . $max)
			. '.json';
		if ($cache->fresh($file, 300)
			&& is_array($hit = $cache->getJson($file))) {
			return $hit['d'] ?? [];
		}
		$imap = null;
		try {
			$imap = $this->app->mail()->client()->ImapClient();
			$imap->FolderSelect($this->configString('folder', 'INBOX'));

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
			$out = array_slice($out, 0, $max);
			$cache->setJson($file, ['d' => $out]);
			return $out;
		} catch (\Throwable $e) {
			$this->app->log('warning', 'imap-contacts-suggestions: ' . $e->getMessage());
			return [];
		} finally {
			// Le client IMAP est celui de la session : la sélection résiduelle
			// ne doit pas fuiter vers l'appelant (UNSELECT = état neutre,
			// chaque consommateur re-sélectionne son dossier).
			if ($imap) {
				try { $imap->FolderUnselect(); } catch (\Throwable) {}
			}
		}
	}
}
