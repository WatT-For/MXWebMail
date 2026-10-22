<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Détection des dossiers systèmes (sent/drafts/junk/trash/archive).
 * La détection par métadonnées SPECIAL-USE et par noms usuels est déjà
 * faite par MailService::specialFolder() — ce plugin ajoute la création
 * automatique des dossiers manquants (autocreate_system_folders),
 * une fois par utilisateur (flag user_settings « plugin.mailbox_detect.done »).
 * Portage du plugin legacy mailbox-detect.
 */
final class MailboxDetect extends PluginBase
{
	private const FLAG = 'plugin.mailbox_detect.done';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data, ?array $user) {
			if (!$user || empty($this->config['autocreate_system_folders'])) {
				return $data;
			}
			$userId = (int) $user['id'];
			if ($this->app->settings()->get($userId, self::FLAG)) {
				return $data;
			}
			try {
				$this->autocreate();
				$this->app->settings()->set($userId, self::FLAG, 1);
			} catch (\Throwable $e) {
				// IMAP indisponible : on réessaiera au prochain appdata
				$this->app->log('warning', 'mailbox-detect: ' . $e->getMessage());
			}
			return $data;
		});
	}

	/** Crée les dossiers systèmes absents (ni rôle, ni nom usuel trouvé). */
	private function autocreate(): void
	{
		$mail = $this->app->mail();
		$folders = $mail->folders();
		$existing = array_map(
			fn(array $f) => strtolower((string) $f['fullName']), $folders);
		$roles = [];
		foreach ($folders as $f) {
			$role = strtolower(ltrim((string) $f['role'], '\\'));
			if ($role !== '') {
				$roles[$role] = true;
			}
		}
		foreach (['sent', 'drafts', 'junk', 'trash', 'archive'] as $role) {
			if (isset($roles[$role])) {
				continue;
			}
			// Nom existant (fallback par nom) ou nom canonique à créer
			$target = $mail->specialFolder($role);
			if ($target === null) {
				continue; // rôle désactivé dans les réglages de l'utilisateur
			}
			if (!\in_array(strtolower($target), $existing, true)) {
				$mail->folderCreate($target);
				$mail->folderSubscribe($target, true);
				$this->app->log('info', "mailbox-detect: dossier « {$target} » créé ({$role})");
				$existing[] = strtolower($target);
			}
		}
	}
}
