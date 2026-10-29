<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;
use MXWebMail\Security\CredentialCrypt;

/**
 * Façade MailSo : ouvre une connexion IMAP à la demande avec les
 * credentials chiffrés de la session, expose les opérations haut niveau.
 */
final class MailService
{
	public const SPECIAL_ROLES = ['sent', 'drafts', 'trash', 'junk', 'archive'];

	private ?\MailSo\Mail\MailClient $client = null;
	private ?\MailSo\Sieve\SieveClient $sieveClient = null;
	private ?array $specialMap = null;
	private ?array $userFolderSet = null;
	private ?array $folderRows = null;

	public function __construct(private App $app) {}

	/**
	 * Credentials de session → réglages MailSo. Compte mot de passe :
	 * passphrase = mot de passe IMAP déchiffré. Compte OAuth2 : passphrase =
	 * access token frais (refresh échangé à la volée) + mécanismes SASL
	 * OAUTHBEARER/XOAUTH2 — les mécanismes par défaut échoueraient le token.
	 */
	private function applyAuth(\MailSo\Net\ConnectSettings $settings): void
	{
		$settings->username = $settings->fixUsername($_SESSION['email']);
		if (($_SESSION['acct_auth'] ?? 'password') !== 'password') {
			$settings->passphrase = $this->app->oauth()->accessToken(
				(int) $_SESSION['account_id'], (int) $_SESSION['user_id']);
			$settings->SASLMechanisms = ['OAUTHBEARER', 'XOAUTH2'];
			return;
		}
		$password = CredentialCrypt::decrypt($_SESSION['imap_pass'], $this->app->storageDir());
		if ($password === null) {
			throw new \RuntimeException('Session invalide');
		}
		$settings->passphrase = $password;
	}

	/** MailClient connecté + authentifié (lazy). */
	public function client(): \MailSo\Mail\MailClient
	{
		if ($this->client === null) {
			if (empty($_SESSION['email']) || empty($_SESSION['imap_pass'])) {
				throw new \RuntimeException('Non authentifié');
			}
			$domain = $this->app->auth()->domainFor(
				$_SESSION['domain'] ?? '', !empty($_SESSION['acct_secondary']));

			$settings = AuthService::imapSettings($domain);
			$this->applyAuth($settings);

			$client = new \MailSo\Mail\MailClient();
			$client->SetLogger(\MXWebMail\API::Logger());

			$imap = $client->ImapClient();
			$imap->Settings = $settings;
			$this->app->hooks()->run('imap.before-connect', $imap, $settings);
			$imap->Connect($settings);
			$imap->Login($settings);

			$this->client = $client;
		}
		return $this->client;
	}

	public function disconnect(): void
	{
		try { $this->client?->ImapClient()->Disconnect(); } catch (\Throwable) {}
		$this->client = null;
	}

	// ─── Dossiers ───

	public function folders(): array
	{
		if ($this->folderRows !== null) {
			return $this->folderRows;
		}
		$collection = $this->client()->Folders('', '*', true);
		$out = [];
		$exists = [];
		foreach ($collection ?? [] as $folder) {
			$out[] = [
				'fullName'     => $folder->FullName,
				'name'         => $folder->Name(),
				'delimiter'    => $folder->Delimiter(),
				'isSubscribed' => $folder->IsSubscribed(),
				'isSelectable' => $folder->Selectable(),
				'role'         => $folder->Role(),
				'total'        => $folder->MESSAGES,
				'unread'       => $folder->UNSEEN,
			];
			$exists[$folder->FullName] = true;
		}
		// 2e passe : rôle effectif (assignation > special-use IMAP)
		$claimed = [];
		foreach ($out as &$f) {
			$f['special'] = $this->effectiveRole($f, $exists);
			if ($f['special'] !== null) {
				$claimed[$f['special']] = true;
			}
		}
		unset($f);
		// 3e passe : repli sur les noms usuels pour les rôles non résolus — ces
		// dossiers sont déjà utilisés fonctionnellement (specialFolder()/
		// junkFolder()), le classement suit. Exemptés : les dossiers créés via
		// l'interface (un « Archives » fait main reste un dossier personnel)
		// et les conteneurs non sélectionnables.
		$map = $this->specialFolderMap();
		$userCreated = $this->userFolderSet();
		foreach (self::FOLDER_FALLBACKS as $role => $names) {
			// 'none' = rôle désactivé ; un rôle assigné à un dossier existant est
			// déjà dans $claimed ; un override périmé retombe en détection.
			if (isset($claimed[$role]) || ($map[$role] ?? null) === 'none') {
				continue;
			}
			foreach ($names as $candidate) {
				$c = mb_strtolower($candidate);
				$hit = false;
				foreach ($out as $i => $f) {
					if ($f['special'] === null && $f['isSelectable'] !== false
						&& !isset($userCreated[$f['fullName']])
						&& mb_strtolower((string) $f['name']) === $c) {
						$out[$i]['special'] = $role;
						$hit = true;
						break;
					}
				}
				if ($hit) {
					break;
				}
			}
		}
		foreach ($out as &$f) {
			$f['system'] = strcasecmp((string) $f['fullName'], 'INBOX') === 0
				|| $f['special'] !== null;
		}
		unset($f);
		return $this->folderRows = $out;
	}

	// ─── Messages ───

	public function messages(string $folder, int $offset, int $limit, string $search = '', string $sort = 'REVERSE DATE', bool $threads = false, int $threadUid = 0): array
	{
		$params = new \MailSo\Mail\MessageListParams();
		$params->sFolderName = $folder;
		$params->iOffset = $offset;
		$params->iLimit = $limit;
		$params->sSearch = $search;
		$params->sSort = $sort;
		$params->bUseSort = true;
		$params->bUseThreads = $threads || $threadUid > 0;
		$params->iThreadUid = $threadUid;

		$collection = $this->client()->MessageList($params);
		$out = [
			'total'    => $collection->totalEmails,
			'folder'   => $collection->FolderName,
			'messages' => [],
			'threadsSupported' => (bool) $this->client()->ImapClient()->CapabilityValue('THREAD'),
			'totalThreads'     => $collection->totalThreads,
		];
		foreach ($collection as $msg) {
			$meta = self::messageMeta($msg);
			$meta = $this->app->hooks()->filter('message.meta', $meta, $msg);
			$out['messages'][] = $meta;
		}
		return $out;
	}

	/**
	 * Recherche sur tous les dossiers sélectionnables+abonnés : même syntaxe
	 * de requête que messages(), résultats fusionnés triés date desc.
	 * Plafonds : 50 dossiers scannés, 50 résultats par dossier.
	 */
	public function searchAllFolders(string $search, int $limit): array
	{
		$folders = array_values(array_filter($this->folders(),
			fn($f) => !empty($f['isSelectable']) && !empty($f['isSubscribed'])));
		$truncated = count($folders) > 50;
		$all = [];
		foreach (array_slice($folders, 0, 50) as $f) {
			$params = new \MailSo\Mail\MessageListParams();
			$params->sFolderName = $f['fullName'];
			$params->iOffset = 0;
			$params->iLimit = 50;
			$params->sSearch = $search;
			$params->sSort = 'REVERSE DATE';
			$params->bUseSort = true;
			try {
				$collection = $this->client()->MessageList($params);
			} catch (\Throwable) {
				continue; // dossier en échec : les autres restent servis
			}
			foreach ($collection as $msg) {
				$all[] = $this->app->hooks()->filter('message.meta',
					self::messageMeta($msg), $msg);
			}
		}
		usort($all, fn($a, $b) => ($b['date'] ?? 0) <=> ($a['date'] ?? 0));
		$truncated = $truncated || count($all) > $limit;
		return [
			'total' => count($all),
			'folder' => '*',
			'truncated' => $truncated,
			'messages' => array_slice($all, 0, $limit),
			'threadsSupported' => false,
		];
	}

	public function message(string $folder, int $uid): ?array
	{
		$msg = $this->client()->Message($folder, $uid, true);
		if (!$msg) {
			return null;
		}
		$data = self::messageFull($msg);
		$this->app->hooks()->runArgs('message.view', [&$data, $msg]);
		return $data;
	}

	public function flags(string $folder, int $uid, string $flag, bool $set): void
	{
		$range = new \MailSo\Imap\SequenceSet((string) $uid);
		$this->client()->MessageSetFlag($folder, $range, $flag, $set);
	}

	/** Variante multi-UIDs : un seul STORE sur la sequence « 1,5,9 ». */
	public function flagsMany(string $folder, array $uids, string $flag, bool $set): void
	{
		$this->client()->MessageSetFlag($folder,
			new \MailSo\Imap\SequenceSet(array_map('intval', $uids)), $flag, $set);
	}

	public function move(string $from, string $to, int $uid): void
	{
		$this->client()->MessageMove($from, $to, new \MailSo\Imap\SequenceSet((string) $uid));
	}

	public function copy(string $from, string $to, int $uid): void
	{
		$this->client()->MessageCopy($from, $to, new \MailSo\Imap\SequenceSet((string) $uid));
	}

	/**
	 * « Supprimer » déplace vers la corbeille ; suppression définitive
	 * (flag \Deleted + expunge) uniquement depuis la corbeille ou sans
	 * dossier corbeille détecté.
	 */
	public function delete(string $folder, int $uid): array
	{
		$trash = $this->trashFolder();
		if ($trash !== null && $folder !== $trash) {
			$this->move($folder, $trash, $uid);
			return ['strategy' => 'move', 'folder' => $trash];
		}
		$this->client()->MessageDelete($folder, new \MailSo\Imap\SequenceSet((string) $uid));
		return ['strategy' => 'expunge', 'folder' => null];
	}

	/**
	 * Le serveur supporte-t-il le keyword perso $Junk (PERMANENTFLAGS \*) ?
	 */
	/**
	 * Le dossier permet-il de persister ce flag/keyword (PERMANENTFLAGS) ?
	 */
	public function supportsKeyword(string $folder, string $keyword): bool
	{
		return $this->client()->ImapClient()->FolderSelect($folder)
			->IsFlagSupported($keyword);
	}

	public function supportsJunkKeyword(string $folder): bool
	{
		return $this->supportsKeyword($folder, \MailSo\Imap\Enumerations\MessageFlag::JUNK);
	}

	/**
	 * Marque/unmarque un message comme spam.
	 * Stratégie 1 : déplacement vers/depuis le dossier Junk.
	 * Stratégie 2 (fallback) : keyword IMAP $Junk si aucun dossier Junk.
	 */
	public function markSpam(string $folder, int $uid, bool $spam): array
	{
		// Hook : apprentissage antispam (plugin spam-learn) — émis avant le
		// move : le nouvel UID n'est pas remonté, le FETCH doit viser la
		// source. Tire aussi si l'action échoue ensuite (409) — accepté.
		$this->app->hooks()->run('message.spam', $folder, $uid, $spam);

		$junk = $this->junkFolder();

		if ($spam) {
			if ($junk !== null && $folder === $junk) {
				return ['spam' => true, 'strategy' => 'move', 'folder' => $junk];
			}
			if ($junk !== null) {
				$this->move($folder, $junk, $uid);
				return ['spam' => true, 'strategy' => 'move', 'folder' => $junk];
			}
			if ($this->supportsJunkKeyword($folder)) {
				$this->flags($folder, $uid, \MailSo\Imap\Enumerations\MessageFlag::JUNK, true);
				return ['spam' => true, 'strategy' => 'keyword', 'folder' => null];
			}
		} else {
			if ($junk !== null && $folder === $junk) {
				$this->move($folder, 'INBOX', $uid);
				return ['spam' => false, 'strategy' => 'move', 'folder' => 'INBOX'];
			}
			if ($this->supportsJunkKeyword($folder)) {
				$this->flags($folder, $uid, \MailSo\Imap\Enumerations\MessageFlag::JUNK, false);
				return ['spam' => false, 'strategy' => 'keyword', 'folder' => null];
			}
		}

		throw new \RuntimeException('Action spam non supportée : aucun dossier Junk ni keyword IMAP disponible', 409);
	}

	// ─── Dossiers (actions) ───

	public function folderCreate(string $name, string $parent = ''): void
	{
		$this->client()->FolderCreate($name, $parent);
		$this->folderRows = null;
	}

	public function folderDelete(string $name): void
	{
		$this->client()->FolderDelete($name);
		$this->folderRows = null;
	}

	public function folderRename(string $from, string $to): void
	{
		$this->client()->FolderRename($from, $to);
		$this->folderRows = null;
	}

	public function folderSubscribe(string $name, bool $subscribe): void
	{
		$subscribe
			? $this->client()->FolderSubscribe($name)
			: $this->client()->FolderUnsubscribe($name);
		$this->folderRows = null;
	}

	public function folderClear(string $name): void
	{
		$this->client()->FolderClear($name);
	}

	/** Marque tous les messages du dossier comme lus (UID STORE 1:* +FLAGS \Seen). */
	public function folderMarkRead(string $name): void
	{
		$this->client()->MessageSetFlag($name,
			new \MailSo\Imap\SequenceSet('1:*'), '\\Seen', true);
	}

	public function quota(): ?array
	{
		// GETQUOTAROOT INBOX : forme compatible Dovecot (GETQUOTA "" reçoit
		// souvent « NO Quota root doesn't exist »). Un NO = quota absent →
		// silencieux, la barre est masquée côté UI.
		try {
			return $this->client()->ImapClient()->QuotaRoot('INBOX');
		} catch (\MailSo\Imap\Exceptions\NegativeResponseException) {
			return null;
		}
	}

	// ─── Dossiers spéciaux ───

	private const FOLDER_FALLBACKS = [
		'drafts'  => ['Drafts', 'Brouillons', 'Draft'],
		'sent'    => ['Sent', 'Sent Items', 'Envoyés', 'Courrier envoyé'],
		'trash'   => ['Trash', 'Deleted', 'Corbeille', 'Deleted Items'],
		'junk'    => ['Junk', 'Spam', 'Courrier indésirable'],
		'archive' => ['Archive', 'Archives', 'All Mail'],
	];

	/**
	 * Assignations des dossiers système du compte actif :
	 * role => 'none'|fullName ('auto' n'est jamais stocké).
	 */
	public function specialFolderMap(): array
	{
		if ($this->specialMap === null) {
			$raw = $this->app->settings()->get(
				(int) $_SESSION['user_id'], 'special_folders', []);
			$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
			$this->specialMap = (array) ($all[(string) ($_SESSION['account_id'] ?? 0)] ?? []);
		}
		return $this->specialMap;
	}

	public function setSpecialFolderMap(array $map): void
	{
		// Ne stocker que les overrides : 'auto' = pas d'assignation, filtré pour
		// que isset($map[$role]) signifie « override explicite ».
		$map = array_filter($map,
			fn($v) => is_string($v) && $v !== '' && $v !== 'auto');
		$uid = (int) $_SESSION['user_id'];
		$raw = $this->app->settings()->get($uid, 'special_folders', []);
		$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
		$all[(string) ($_SESSION['account_id'] ?? 0)] = $map;
		$this->app->settings()->set($uid, 'special_folders', $all);
		$this->specialMap = $map;
	}

	/**
	 * Set des fullName des dossiers créés via l'interface (compte actif) :
	 * exemptés du repli par nom usuel dans folders() — un « Archives » créé
	 * à la main reste un dossier personnel, pas un dossier de base.
	 */
	public function userFolderSet(): array
	{
		if ($this->userFolderSet === null) {
			$raw = $this->app->settings()->get(
				(int) $_SESSION['user_id'], 'user_folders', []);
			$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
			$names = array_map('strval',
				(array) ($all[(string) ($_SESSION['account_id'] ?? 0)] ?? []));
			$this->userFolderSet = array_fill_keys($names, true);
		}
		return $this->userFolderSet;
	}

	/** Trace des dossiers créés via l'interface (une écriture pour le lot). */
	public function markUserFolders(array $fullNames): void
	{
		if ($fullNames === []) {
			return;
		}
		$this->updateUserFolders(
			fn(array $names) => array_merge($names, $fullNames));
	}

	/** Purge les trackés absents du serveur (suppression, entrées obsolètes). */
	public function pruneUserFolders(): void
	{
		if ($this->userFolderSet() === []) {
			return;
		}
		$existing = array_fill_keys(
			array_column($this->folders(), 'fullName'), true);
		$this->updateUserFolders(fn(array $names)
			=> array_values(array_filter($names, fn($n) => isset($existing[$n]))));
	}

	/** Provenance conservée au renommage : $from et ses descendants → $to. */
	public function renameUserFolder(string $from, string $to, string $delimiter): void
	{
		if ($this->userFolderSet() === []) {
			return;
		}
		$prefix = $delimiter !== '' ? $from . $delimiter : null;
		$this->updateUserFolders(fn(array $names) => array_map(
			fn($n) => $n === $from ? $to
				: ($prefix !== null && str_starts_with($n, $prefix)
					? $to . substr($n, strlen($from)) : $n),
			$names));
	}

	private function updateUserFolders(callable $fn): void
	{
		$uid = (int) $_SESSION['user_id'];
		$raw = $this->app->settings()->get($uid, 'user_folders', []);
		$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
		$key = (string) ($_SESSION['account_id'] ?? 0);
		$names = array_map('strval', (array) ($all[$key] ?? []));
		$all[$key] = array_values(array_unique(array_map('strval', $fn($names))));
		// array_filter vide = plus aucun compte tracké → ligne supprimée.
		$this->app->settings()->set($uid, 'user_folders',
			array_filter($all) === [] ? null : $all);
		$this->userFolderSet = null;
	}

	/**
	 * Dossiers surveillés pour les notifications, compte actif :
	 * INBOX en tête + abonnements stockés. Pas de validation sur l'existant
	 * ici (évite un LIST IMAP) : le frontend intersecte avec la liste live et
	 * les noms obsolètes sont purgés à la prochaine sauvegarde.
	 */
	public function notifyFolderList(): array
	{
		$raw = $this->app->settings()->get((int) $_SESSION['user_id'], 'notify_folders', []);
		$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
		$names = array_values(array_filter(array_map('strval',
			(array) ($all[(string) ($_SESSION['account_id'] ?? 0)] ?? [])),
			fn($n) => $n !== '' && strcasecmp($n, 'INBOX') !== 0));
		return array_values(array_unique(array_merge(['INBOX'], $names)));
	}

	public function setNotifyFolderList(array $names): void
	{
		$existing = array_column($this->folders(), 'fullName');
		$names = array_values(array_filter(array_unique(array_map('strval', $names)),
			fn($n) => in_array($n, $existing, true) && strcasecmp($n, 'INBOX') !== 0));
		$uid = (int) $_SESSION['user_id'];
		$raw = $this->app->settings()->get($uid, 'notify_folders', []);
		$all = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
		$all[(string) ($_SESSION['account_id'] ?? 0)] = $names;
		$this->app->settings()->set($uid, 'notify_folders', $all);
	}

	/**
	 * Rôle effectif d'un dossier : assignation > special-use IMAP.
	 * $exists = set des fullName présents : un override dont la cible a
	 * disparu est ignoré (le rôle IMAP est conservé).
	 */
	private function effectiveRole(array $folder, array $exists): ?string
	{
		$map = $this->specialFolderMap();
		foreach ($map as $role => $target) {
			if ($target === $folder['fullName']) {
				return $role;
			}
		}
		$role = strtolower(ltrim((string) $folder['role'], '\\'));
		if ($role === '') {
			return null;
		}
		if (in_array($role, self::SPECIAL_ROLES, true)) {
			$override = $map[$role] ?? null;
			if ($override === 'none' || ($override !== null && isset($exists[$override]))) {
				return null;
			}
		}
		return $role;
	}

	/**
	 * Résout le dossier d'un rôle special-use (\drafts, \sent, \trash, \junk, \archive)
	 * via l'assignation utilisateur, les métadonnées du serveur, puis les noms
	 * usuels. null si le rôle est explicitement désactivé ('none').
	 */
	public function specialFolder(string $role): ?string
	{
		$role = strtolower(ltrim($role, '\\'));
		$folders = $this->folders();
		$override = $this->specialFolderMap()[$role] ?? null;
		if ($override === 'none') {
			return null;
		}
		if ($override !== null) {
			foreach ($folders as $f) {
				if ($f['fullName'] === $override) {
					return $f['fullName'];
				}
			}
			// Dossier assigné disparu : retombe sur la détection auto
		}
		return $this->detectedSpecialFolder($role);
	}

	/**
	 * Dossier du rôle résolu par la seule détection (special-use puis noms
	 * usuels), en ignorant toute assignation utilisateur — ce que « auto »
	 * utiliserait. Retourne un nom deviné si rien n'existe encore.
	 */
	public function detectedSpecialFolder(string $role): ?string
	{
		$role = strtolower(ltrim($role, '\\'));
		$folders = $this->folders();
		foreach ($folders as $f) {
			if (strtolower(ltrim((string) $f['role'], '\\')) === $role) {
				return $f['fullName'];
			}
		}
		$names = array_map('mb_strtolower', array_column($folders, 'fullName'));
		foreach (self::FOLDER_FALLBACKS[$role] ?? [] as $candidate) {
			$idx = array_search(mb_strtolower($candidate), $names, true);
			if ($idx !== false) {
				return $folders[$idx]['fullName'];
			}
		}
		return self::FOLDER_FALLBACKS[$role][0] ?? 'INBOX';
	}

	/**
	 * Dossier Junk réellement existant (assignation, special-use \junk ou nom
	 * usuel), sinon null. Contrairement à specialFolder(), ne renvoie pas de
	 * nom deviné.
	 */
	public function junkFolder(): ?string
	{
		if (($this->specialFolderMap()['junk'] ?? null) === 'none') {
			return null;
		}
		foreach ($this->folders() as $f) {
			if ($f['special'] === 'junk') {
				return $f['fullName'];
			}
		}
		$junkNames = array_map('mb_strtolower', self::FOLDER_FALLBACKS['junk']);
		foreach ($this->folders() as $f) {
			if (in_array(mb_strtolower((string) $f['name']), $junkNames, true)) {
				return $f['fullName'];
			}
		}
		return null;
	}

	/**
	 * Dossier Corbeille réellement existant (assignation, special-use \trash
	 * ou nom usuel), sinon null — jamais de nom deviné.
	 */
	public function trashFolder(): ?string
	{
		if (($this->specialFolderMap()['trash'] ?? null) === 'none') {
			return null;
		}
		foreach ($this->folders() as $f) {
			if ($f['special'] === 'trash') {
				return $f['fullName'];
			}
		}
		$trashNames = array_map('mb_strtolower', self::FOLDER_FALLBACKS['trash']);
		foreach ($this->folders() as $f) {
			if (in_array(mb_strtolower((string) $f['name']), $trashNames, true)) {
				return $f['fullName'];
			}
		}
		return null;
	}

	/**
	 * 'inbox'  → le nom désigne la boîte de réception ;
	 * 'system' → dossier « par défaut » (INBOX, rôle effectif ou assigné)
	 * sur lequel renommage, ACL et suppression sont interdits.
	 */
	public function folderFlags(string $name): array
	{
		$inbox = strcasecmp($name, 'INBOX') === 0;
		$system = $inbox;
		if (!$system) {
			foreach ($this->folders() as $f) {
				if ($f['fullName'] === $name) {
					$system = !empty($f['special']);
					break;
				}
			}
		}
		return ['inbox' => $inbox, 'system' => $system];
	}

	/** Ajoute un message brut (EML) dans un dossier via IMAP APPEND. Retourne l'UID. */
	public function appendMessage(string $rawMessage, string $folder, array $flags = ['\\Seen'], int $internalDate = 0): int
	{
		$tmpDir = $this->app->storageDir() . '/tmp';
		is_dir($tmpDir) || mkdir($tmpDir, 0700, true);
		$tmp = tempnam($tmpDir, 'append_');
		file_put_contents($tmp, $rawMessage);
		try {
			return $this->client()->MessageAppendFile($tmp, $folder, $flags, $internalDate);
		} finally {
			@unlink($tmp);
		}
	}

	/**
	 * Remplace le message $uid par $rawMessage dans $folder (RFC 8508
	 * UID REPLACE ; sinon append + expunge ciblé — MessageReplaceStream
	 * gère le fallback). $uid < 1 → simple APPEND. Retourne l'UID du
	 * message résultant, ou null si le serveur ne le publie pas (pas de
	 * UIDPLUS sur le chemin append).
	 */
	public function replaceMessage(string $rawMessage, string $folder, int $uid, array $flags = ['\\Seen']): ?int
	{
		$tmpDir = $this->app->storageDir() . '/tmp';
		is_dir($tmpDir) || mkdir($tmpDir, 0700, true);
		$tmp = tempnam($tmpDir, 'append_');
		file_put_contents($tmp, $rawMessage);
		$fp = fopen($tmp, 'rb');
		try {
			$imap = $this->client()->ImapClient();
			$new = $imap->MessageReplaceStream($folder, $uid, $fp, filesize($tmp), $flags);
			// REPLACE conserve l'UID ; fallback append → nouvel UID (ou
			// null sans UIDPLUS → la réf client n'est juste pas mise à jour).
			return $new ?? ($uid > 0 && $imap->hasCapability('REPLACE') ? $uid : null);
		} finally {
			is_resource($fp) && fclose($fp);
			@unlink($tmp);
		}
	}

	/**
	 * Suppression définitive sans sémantique corbeille (contrairement à
	 * delete()) : flag \Deleted + UID EXPUNGE ciblé. Pour les brouillons
	 * consommés par un envoi — une copie en corbeille serait du bruit.
	 */
	public function deletePermanent(string $folder, int $uid): void
	{
		$this->client()->MessageDelete($folder, new \MailSo\Imap\SequenceSet((string) $uid));
	}

	// ─── Filtres Sieve ───

	/** SieveClient connecté + authentifié (mêmes credentials IMAP). */
	public function sieve(): \MailSo\Sieve\SieveClient
	{
		if ($this->sieveClient === null) {
			$domain = $this->app->auth()->domainFor(
				$_SESSION['domain'] ?? '', !empty($_SESSION['acct_secondary']));
			if (empty($domain['sieve_host'])) {
				throw new \RuntimeException(
					$this->t('errors.sieve_not_configured', ['domain' => $domain['name'] ?? '']), 400);
			}
			$settings = AuthService::sieveSettings($domain);
			$this->applyAuth($settings);

			$this->sieveClient = new \MailSo\Sieve\SieveClient();
			$this->sieveClient->SetLogger(\MXWebMail\API::Logger());
			$this->app->hooks()->run('sieve.before-connect', $this->sieveClient, $settings);
			$this->sieveClient->Connect($settings);
			$this->sieveClient->Login($settings);
		}
		return $this->sieveClient;
	}

	/** Scripts + modules Sieve annoncés par le serveur (l'assistant les
	 *  utilise pour masquer les actions/opérateurs non supportés). */
	public function filtersList(): array
	{
		$out = [];
		foreach ($this->sieve()->ListScripts() as $name => $active) {
			$out[] = ['name' => $name, 'active' => (bool) $active];
		}
		return ['scripts' => $out, 'modules' => $this->sieve()->Modules()];
	}

	public function filterGet(string $name): string
	{
		return $this->sieve()->GetScript($name);
	}

	public function filterCheck(string $source): void
	{
		$this->sieve()->CheckScript($source);
	}

	/** $oldName non null ≠ $name : renommage (PUT nouveau + DELETE ancien —
	 *  ne dépend pas de la capacité RENAMESCRIPT, optionnelle). SETACTIVE
	 *  avant DELETE si l'ancien était actif : DELETESCRIPT refuse un script
	 *  actif et l'état actif est ainsi conservé. */
	public function filterSave(string $name, string $source, ?string $oldName = null): void
	{
		$this->sieve()->PutScript($name, $source);
		if ($oldName !== null && $oldName !== $name
			&& array_key_exists($oldName, $scripts = $this->sieve()->ListScripts())) {
			if ($scripts[$oldName]) {
				$this->sieve()->SetActiveScript($name);
			}
			$this->sieve()->DeleteScript($oldName);
		}
	}

	public function filterActivate(string $name): void
	{
		$this->sieve()->SetActiveScript($name);
	}

	/** SETACTIVE "" : désactive le script actif (RFC 5804). */
	public function filterDeactivate(): void
	{
		$this->sieve()->SetActiveScript('');
	}

	public function filterDelete(string $name): void
	{
		$this->sieve()->DeleteScript($name);
	}

	/**
	 * Configuration du répondeur d'absence (Sieve vacation) : lue dans le
	 * doc géré (marqueur MXWM-RULES) du script actif. 'managed' indique si
	 * le script actif est géré par l'assistant — un script externe n'est
	 * jamais réécrit silencieusement.
	 */
	public function vacationGet(): array
	{
		$sieve = $this->sieve();
		$scripts = $sieve->ListScripts();
		$active = null;
		$managed = [];
		foreach (array_keys($scripts) as $name) {
			$doc = \MXWebMail\Sieve\ScriptBuilder::parseMarker(
				$sieve->GetScript((string) $name));
			if ($doc !== null) {
				$managed[] = $name;
			}
			if ($scripts[$name]) {
				$active = $name;
			}
		}
		$vacation = null;
		if ($active !== null && in_array($active, $managed, true)) {
			$doc = \MXWebMail\Sieve\ScriptBuilder::parseMarker(
				$sieve->GetScript((string) $active));
			$vacation = $doc['vacation'] ?? null;
		}
		return [
			'script'  => $active,
			'managed' => $active !== null && in_array($active, $managed, true),
			'vacation'=> $vacation,
			'modules' => $sieve->Modules(),
		];
	}

	/**
	 * Écrit la config vacation dans le document géré : script actif géré
	 * s'il existe, sinon premier script géré activé, sinon nouveau script
	 * 'mxwebmail' activé. Script actif non géré → refus (409).
	 */
	public function vacationSave(array $vacation): void
	{
		$sieve = $this->sieve();
		if (!in_array('vacation', $sieve->Modules(), true)) {
			throw new \RuntimeException(
				$this->t('errors.sieve_module', ['module' => 'vacation']), 400);
		}
		$scripts = $sieve->ListScripts();
		$active = null;
		$managed = [];
		foreach (array_keys($scripts) as $name) {
			$doc = \MXWebMail\Sieve\ScriptBuilder::parseMarker(
				$sieve->GetScript((string) $name));
			if ($doc !== null) {
				$managed[(string) $name] = $doc;
			}
			if ($scripts[$name]) {
				$active = (string) $name;
			}
		}
		if ($active !== null) {
			if (!isset($managed[$active])) {
				throw new \RuntimeException($this->t('errors.sieve_unmanaged'), 409);
			}
			$target = $active;
		} else {
			$target = $managed ? (string) array_key_first($managed) : 'mxwebmail';
			$managed[$target] ??= ['rules' => []];
		}
		$doc = $managed[$target];
		$doc['vacation'] = $vacation;
		$source = \MXWebMail\Sieve\ScriptBuilder::compile($doc);
		$this->filterCheck($source);
		$sieve->PutScript($target, $source);
		// Activation uniquement si le répondeur est actif et le script ne l'est pas
		if (!empty($vacation['enabled']) && $active !== $target) {
			$sieve->SetActiveScript($target);
		}
	}

	// ─── ACL dossiers ───

	/** 400 explicite si le serveur n'a pas la capacité ACL (sinon réponse vide
	 *  indistinguable de « aucun droit »). */
	private function requireAcl(): void
	{
		if (!$this->client()->ImapClient()->hasCapability('ACL')) {
			throw new \RuntimeException(
				$this->app->locale()->t('folders.acl_unsupported', ['msg' => 'capability ACL absente']), 400);
		}
	}

	public function folderAcl(string $name): array
	{
		$this->requireAcl();
		return $this->client()->FolderGetACL($name);
	}

	public function folderSetAcl(string $name, string $identifier, string $rights): void
	{
		$this->requireAcl();
		$this->client()->FolderSetACL($name, $identifier, $rights);
	}

	public function folderDeleteAcl(string $name, string $identifier): void
	{
		$this->requireAcl();
		$this->client()->FolderDeleteACL($name, $identifier);
	}

	// ─── Streaming ───

	/**
	 * Stream d'une partie MIME (pièce jointe, ou message entier si mimeIndex='').
	 * Le callback reçoit une ressource PHP ouverte.
	 */
	public function mimeStream(string $folder, int $uid, string $mimeIndex, callable $cb): bool
	{
		return $this->client()->MessageMimeStream(
			function ($resource) use ($cb) {
				if (is_resource($resource)) {
					$cb($resource);
				}
			},
			$folder,
			$uid,
			$mimeIndex
		);
	}

	// ─── SMTP ───

	public function send(\MailSo\Mime\Message $message): void
	{
		$domain = $this->app->auth()->domainFor(
			$_SESSION['domain'] ?? '', !empty($_SESSION['acct_secondary']));
		$settings = AuthService::smtpSettings($domain);
		$this->applyAuth($settings);

		$smtp = new \MailSo\Smtp\SmtpClient();
		$smtp->SetLogger(\MXWebMail\API::Logger());
		$smtp->Settings = $settings;
		$this->app->hooks()->run('smtp.before-connect', $smtp, $settings);
		try {
			$smtp->Connect($settings);
			$smtp->Login($settings);

			$this->app->hooks()->run('message.before-send', $message);
			// Bcc retiré du DATA : l'en-tête ne doit pas fuiter vers les destinataires.
			$stream = $message->ToStream(true);
			$size = fstat($stream)['size'] ?? 0;
			$from = $message->GetFrom();
			$smtp->MailFrom($from ? $from->GetEmail() : $settings->username, $size);
			foreach ($message->GetRcpt() as $rcpt) {
				$smtp->Rcpt($rcpt->GetEmail());
			}
			$smtp->DataWithStream($stream);
		} catch (\MailSo\Smtp\Exceptions\ResponseException $e) {
			// Réponse brute du serveur utile au diagnostic (quota, adresse
			// refusée) — n'affichée que si labs.smtp_show_server_errors l'active.
			$msg = $this->t('errors.send_failed');
			if ($settings->viewErrors) {
				$msg .= ' : ' . $e->getMessage();
			} else {
				$this->app->log('error', 'Envoi SMTP refusé : ' . $e->getMessage());
			}
			throw new \RuntimeException($msg, 502, $e);
		} catch (\Throwable $e) {
			// Connexion, timeout, socket… : ne pas fuiter le détail technique.
			$this->app->log('error', 'Envoi SMTP échoué : ' . $e->getMessage());
			throw new \RuntimeException($this->t('errors.send_failed'), 502, $e);
		} finally {
			// En cas d'exception mid-dialogue la socket resterait ouverte.
			try { $smtp->Disconnect(); } catch (\Throwable) {}
		}

		// Copie dans le dossier Éléments envoyés (l'échec ne doit pas faire
		// échouer l'envoi). Bcc conservé ici — ToString(false) : l'expéditeur
		// doit retrouver ses destinataires Cci dans sa copie.
		try {
			$sent = $this->specialFolder('sent');
			if ($sent !== null) {
				$this->appendMessage($message->ToString(false), $sent, ['\\Seen']);
			}
		} catch (\Throwable $e) {
			$this->app->log('warning', 'Append Sent échoué: ' . $e->getMessage());
		}
	}

	// ─── Sérialisation ───

	private static function flagsOf(\MailSo\Mail\Message $msg): array
	{
		return $msg->jsonSerialize()['flags'] ?? [];
	}

	private static function messageMeta(\MailSo\Mail\Message $msg): array
	{
		$d = $msg->jsonSerialize();
		$flags = $d['flags'] ?? [];
		return [
			'uid'       => $d['uid'],
			'folder'    => $d['folder'],
			'subject'   => $d['subject'],
			'from'      => $d['from'],
			'date'      => $d['dateTimestamp'],
			'size'      => $d['size'],
			'isSeen'    => in_array('\seen', $flags, true),
			'isFlagged' => in_array('\flagged', $flags, true),
			'isAnswered'=> in_array('\answered', $flags, true),
			'isDraft'   => in_array('\draft', $flags, true),
			'hasAttach' => !empty($d['attachments']),
			'encrypted' => !empty($d['encrypted']),
			'isSpam'    => !empty($d['isSpam']),
			'spamScore' => $d['spamScore'] ?? 0,
			'isJunk'    => in_array('$junk', $flags, true),
			'threads'      => $d['threads'] ?? [],
			'threadUnseen' => $d['threadUnseen'] ?? [],
		];
	}

	/** Cibles List-Unsubscribe (+ one-click RFC 8058) lues dans les en-têtes
	 *  du message : {mailto, url, post} ou null si absent. */
	private static function parseUnsubscribe(?\MailSo\Mime\HeaderCollection $h): ?array
	{
		if (!$h) {
			return null;
		}
		$raw = $h->ValueByName(\MailSo\Mime\Enumerations\Header::LIST_UNSUBSCRIBE);
		if ($raw === '') {
			return null;
		}
		$mailto = null; $url = null;
		preg_match_all('/<([^>]+)>/', $raw, $m);
		foreach ($m[1] ?? [] as $target) {
			if (stripos($target, 'mailto:') === 0 && $mailto === null) {
				$mailto = $target;
			} else if (preg_match('#^https?://#i', $target) && $url === null) {
				$url = $target;
			}
		}
		if (!$mailto && !$url) {
			return null;
		}
		return [
			'mailto' => $mailto,
			'url'    => $url,
			'post'   => stripos($h->ValueByName(\MailSo\Mime\Enumerations\Header::LIST_UNSUBSCRIBE_POST), 'List-Unsubscribe=One-Click') !== false,
		];
	}

	private static function messageFull(\MailSo\Mail\Message $msg): array
	{
		$d = $msg->jsonSerialize();
		$attachments = [];
		foreach ($d['attachments'] ?? [] as $att) {
			// Attachment est JsonSerializable : (array) exposerait les props
			// privées sous des clés mangled (« \0Class\0prop ») — toutes les
			// métadonnées (mimeIndex, cId, fileName) deviendraient null.
			$att = is_array($att) ? $att
				: ($att instanceof \JsonSerializable ? $att->jsonSerialize() : (array) $att);
			$attachments[] = [
				'name'      => $att['fileName'] ?? 'piece-jointe',
				'mime'      => $att['mimeType'] ?? 'application/octet-stream',
				'size'      => $att['estimatedSize'] ?? 0,
				'mimeIndex' => $att['mimeIndex'] ?? '',
				'cid'       => $att['cId'] ?? null,
				'location'  => $att['contentLocation'] ?? null,
				'isInline'  => $att['isInline'] ?? false,
			];
		}
		return array_merge(self::messageMeta($msg), [
			'to'          => $d['to'],
			'cc'          => $d['cc'],
			'bcc'         => $d['bcc'],
			'replyTo'     => $d['replyTo'],
			'bodyHtml'    => $d['html'] ?? '',
			'bodyPlain'   => $d['plain'] ?? '',
			'attachments' => $attachments,
			'messageId'   => $d['messageId'] ?? '',
			'inReplyTo'   => $d['inReplyTo'] ?? '',
			'references'  => $d['references'] ?? '',
			'pgpEncrypted'=> $d['pgpEncrypted'] ?? null,
			'pgpSigned'   => $d['pgpSigned'] ?? null,
			'smimeSigned' => $d['smimeSigned'] ?? null,
			// Accusé de réception demandé (RFC 3798) + déjà honoré ($MDNSent).
			'dispositionTo' => trim((string) ($msg->Headers
				?->ValueByName(\MailSo\Mime\Enumerations\Header::DISPOSITION_NOTIFICATION_TO) ?? '')),
			'mdnSent'     => in_array('$mdnsent', $d['flags'] ?? [], true),
			'unsubscribe' => self::parseUnsubscribe($msg->Headers ?? null),
		]);
	}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}
}
