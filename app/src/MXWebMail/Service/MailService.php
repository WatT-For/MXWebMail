<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;
use MXWebMail\Cache\FileCache;
use MXWebMail\Files;
use MXWebMail\Locale\Translates;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\UserFacingException;

/**
 * Façade MailSo : ouvre une connexion IMAP à la demande avec les
 * credentials chiffrés de la session, expose les opérations haut niveau.
 */
final class MailService
{
	use Translates;

	public const SPECIAL_ROLES = ['sent', 'drafts', 'trash', 'junk', 'archive'];

	private ?\MailSo\Mail\MailClient $client = null;
	private ?\MailSo\Sieve\SieveClient $sieveClient = null;
	private ?array $specialMap = null;
	private ?array $userFolderSet = null;
	private ?array $folderRows = null;
	private ?FileCache $fc = null;

	public function __construct(private App $app) {}

	/**
	 * Credentials de session > réglages MailSo. Compte mot de passe :
	 * passphrase = mot de passe IMAP déchiffré. Compte OAuth2 : passphrase =
	 * access token frais (refresh échangé à la volée) + mécanismes SASL
	 * OAUTHBEARER/XOAUTH2 - les mécanismes par défaut échoueraient le token.
	 */
	private function applyAuth(\MailSo\Net\ConnectSettings $settings): void
	{
		$settings->username = $settings->fixUsername($this->app->sessionEmail());
		if (($this->app->sessionAuthProvider()) !== 'password') {
			$settings->passphrase = $this->app->oauth()->accessToken(
				$this->app->sessionAccountId(), $this->app->sessionUserId());
			$settings->SASLMechanisms = ['OAUTHBEARER', 'XOAUTH2'];
			return;
		}
		$password = CredentialCrypt::decrypt($this->app->sessionImapPass(), $this->app->storageDir());
		if ($password === null) {
			throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
		}
		$settings->passphrase = $password;
	}

	/** MailClient connecté + authentifié (lazy). */
	public function client(): \MailSo\Mail\MailClient
	{
		if ($this->client === null) {
			if ($this->app->sessionEmail() === '' || $this->app->sessionImapPass() === '') {
				throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
			}
			$domain = $this->app->auth()->domainFor(
				$this->app->sessionDomain(), $this->app->isSecondaryAccount());

			$settings = AuthService::imapSettings($domain);
			$this->applyAuth($settings);

			$client = new \MailSo\Mail\MailClient();
			$client->SetLogger(\MXWebMail\API::Logger());

			$imap = $client->ImapClient();
			$imap->Settings = $settings;
			$this->app->hooks()->run('imap.before-connect', $imap, $settings);
			$this->connectClient($imap, $settings, 'errors.imap_unreachable');

			$this->client = $client;
		}
		return $this->client;
	}

	/**
	 * Coupe les connexions et vide les caches par compte. Requis avant un
	 * changement de compte en cours de requête (switchAccount) : client(),
	 * folderRows, specialMap et userFolderSet sont liés à l'identité de la
	 * session au moment de leur construction.
	 */
	public function disconnect(): void
	{
		try { $this->client?->ImapClient()->Disconnect(); } catch (\Throwable) {}
		try { $this->sieveClient?->Disconnect(); } catch (\Throwable) {}
		$this->client = $this->sieveClient = null;
		$this->specialMap = $this->userFolderSet = $this->folderRows = null;
		Files::gcGlob($this->app->storageDir() . '/cache/folders-'
			. $this->app->sessionUserId() . '-*.json');
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
				// RFC 8438 - null si le serveur n'annonce pas STATUS=SIZE
				'size'         => $folder->SIZE,
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
		// 3e passe : repli sur les noms usuels pour les rôles non résolus - ces
		// dossiers sont déjà utilisés fonctionnellement (specialFolder()/
		// junkFolder()), le classement suit. Exemptés : les dossiers créés via
		// l'interface (un "Archives" fait main reste un dossier personnel)
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
						&& self::isRoleCandidate($f)
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
		$collapsed = $this->collapsedFolderSet();
		$hidden = $this->hiddenFolderSet();
		foreach ($out as &$f) {
			$f['system'] = strcasecmp((string) $f['fullName'], 'INBOX') === 0
				|| $f['special'] !== null;
			$f['collapsed'] = isset($collapsed[(string) $f['fullName']]);
			// !$f['system'] : un dossier devenu natif (assignation de rôle)
			// ne peut rester masqué même si une entrée obsolète subsiste.
			$f['hidden'] = !$f['system'] && isset($hidden[(string) $f['fullName']]);
		}
		unset($f);
		// Lignes mises en cache fichier (par compte) : cachedFolders() sert la
		// résolution des rôles (junkFolder(), trashFolder(), specialFolder(),
		// folderFlags()) sans repayer un LIST+STATUS à chaque action (spam,
		// corbeille, append Sent…). Les compteurs total/unread qu'elle
		// contient peuvent être périmés - ces consommateurs ne les lisent pas.
		// Fichier et non session : les GET ferment la session avant le handler
		// (requireReadOnly), une écriture $_SESSION serait perdue.
		// Écriture évitée si identique : le JSON est déterministe - un tick de
		// poll sans changement ne doit pas réécrire le fichier.
		if (($f = $this->folderCacheName())) {
			$json = (string) json_encode($out);
			if ($this->fileCache()->get($f) !== $json) {
				// Purge des caches d'autres comptes/sessions mortes (> 30 j).
				Files::gcGlob($this->app->storageDir() . '/cache/folders-*.json', 2592000);
				$this->fileCache()->set($f, $json);
			}
		}
		return $this->folderRows = $out;
	}

	/** Le dossier existe-t-il côté serveur (fullName exact). */
	public function folderExists(string $fullName): bool
	{
		return in_array($fullName,
			array_column($this->folders(), 'fullName'), true);
	}

	/** Cache storage/cache/ non gouverné par cache.enabled (fast-path appdata). */
	private function fileCache(): FileCache
	{
		return $this->fc ??= new FileCache($this->app->storageDir() . '/cache');
	}

	/** Nom du cache fichier par (utilisateur, compte) - survit à la session fermée. */
	private function folderCacheName(): ?string
	{
		$uid = $this->app->sessionUserId();
		$acct = $this->app->sessionAccountId();
		return $uid > 0 ? "folders-$uid-$acct.json" : null;
	}

	/**
	 * Contenu du cache fichier de dossiers (écrit à chaque folders()) -
	 * null quand absent ou illisible. Jamais de calcul : les consommateurs
	 * qui ne doivent pas payer une connexion IMAP (appdata) l'utilisent.
	 */
	private function folderCacheRead(): ?array
	{
		$f = $this->folderCacheName();
		return $f !== null ? $this->fileCache()->getJson($f) : null;
	}

	/**
	 * Lignes folders() depuis le cache fichier écrit à chaque calcul réel
	 * (GET /api/folders, donc rafraîchi par le poll list_refresh) ; absent >
	 * folders() le peuple. Pour la résolution de rôles uniquement.
	 */
	private function cachedFolders(): array
	{
		$c = $this->folderCacheRead();
		if ($c !== null) {
			return $c;
		}
		$this->folders(); // calcule et peuple le cache fichier
		return $this->folderRows ?? [];
	}

	/** Invalide la liste des dossiers et son cache fichier. */
	private function invalidateFolderCache(): void
	{
		$this->folderRows = null;
		($f = $this->folderCacheName()) !== null && $this->fileCache()->delete($f);
	}

	/**
	 * Cache fichier MailSo (UIDs de listes indexés sur l'etag du dossier,
	 * maps THREAD). Les clés embarquent déjà ImapClient::Hash()
	 * (user@host:port) - l'index '_default_' partagé est sans collision.
	 * cache.enabled=false > client sans driver (Get/Set no-op natifs).
	 */
	private function cacher(): \MailSo\Cache\CacheClient
	{
		return \MXWebMail\API::Actions()->Cacher();
	}

	/**
	 * Connect+Login d'un client MailSo (ImapClient ou SieveClient) avec
	 * mapping des erreurs : NO/BAD pendant le login (credentials refusés,
	 * compte suspendu, méthode refusée) > 401 ; réseau, TLS, EOF
	 * mid-handshake ou bannière illisible > 503 $unreachableKey (host dans
	 * le message, détail technique déjà tracé dans mail.log).
	 */
	private function connectClient(object $proto, object $settings,
		string $unreachableKey): void
	{
		try {
			$proto->Connect($settings);
			$proto->Login($settings);
		} catch (\MailSo\Imap\Exceptions\LoginException
			| \MailSo\Sieve\Exceptions\LoginException $e) {
			throw new UserFacingException(
				$this->t('errors.bad_credentials'), 401, $e);
		} catch (\MailSo\Net\Exceptions\ConnectionException
			| \MailSo\Net\Exceptions\SocketReadException
			| \MailSo\Net\Exceptions\SocketReadTimeoutException
			| \MailSo\Net\Exceptions\SocketWriteException
			| \MailSo\Imap\Exceptions\ResponseException
			| \MailSo\Sieve\Exceptions\ResponseException $e) {
			throw new UserFacingException($this->t($unreachableKey,
				['host' => $settings->host]), 503, $e);
		}
	}

	// ─── Messages ───

	/** Paramètres communs des listes IMAP (messages() et searchAllFolders()). */
	private function listParams(string $folder, int $offset, int $limit,
		string $search, string $sort): \MailSo\Mail\MessageListParams
	{
		$params = new \MailSo\Mail\MessageListParams();
		$params->sFolderName = $folder;
		$params->iOffset = $offset;
		$params->iLimit = $limit;
		$params->sSearch = $search;
		$params->sSort = $sort;
		$params->bUseSort = true;
		$params->oCacher = $this->cacher();
		return $params;
	}

	public function messages(string $folder, int $offset, int $limit, string $search = '', string $sort = 'REVERSE DATE', bool $threads = false, int $threadUid = 0): array
	{
		$params = $this->listParams($folder, $offset, $limit, $search, $sort);
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
	 * de requête que messages(), résultats fusionnés triés date desc et
	 * paginés (offset/limit sur le jeu fusionné).
	 * Plafonds : 50 dossiers scannés, 50 résultats par dossier.
	 */
	public function searchAllFolders(string $search, int $offset, int $limit): array
	{
		$folders = array_values(array_filter($this->folders(),
			fn($f) => !empty($f['isSelectable']) && !empty($f['isSubscribed'])));
		$truncated = count($folders) > 50;
		$all = [];
		foreach (array_slice($folders, 0, 50) as $f) {
			$params = $this->listParams($f['fullName'], 0, 50, $search, 'REVERSE DATE');
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
		$truncated = $truncated || count($all) > $offset + $limit;
		return [
			'total' => count($all),
			'folder' => '*',
			'truncated' => $truncated,
			'messages' => array_slice($all, $offset, $limit),
			'threadsSupported' => false,
		];
	}

	public function message(string $folder, int $uid): ?array
	{
		// FolderExamine avant Message() : la re-sélection interne saute
		// (bForceReselect=false) - l'UIDVALIDITY revient quasi gratuitement
		// pour le paramètre &uv= des URLs de PJ (clé de cache P0-3).
		$imap = $this->client()->ImapClient();
		$msg = $this->client()->Message($folder, $uid, true);
		if (!$msg) {
			return null;
		}
		$data = self::messageFull($msg);
		$data['uv'] = $imap->FolderExamine($folder)->UIDVALIDITY;
		$this->app->hooks()->runArgs('message.view', [&$data, $msg]);
		return $data;
	}

	/**
	 * Métadonnées des pièces jointes d'un message : FETCH restreint à
	 * BODYSTRUCTURE + champs minimum exigés par fromFetchResponse
	 * (INTERNALDATE non-nullable, UID). Message() enchaînait un second
	 * FETCH de toutes les parties texte (body_text_limit=0 > corps
	 * entier) pour ne lire que les PJ.
	 * La liste est cachée disque (cache/att/<cle>.list.json) indexée sur
	 * l'UIDVALIDITY lu par le FolderExamine ci-dessous : un mail à N
	 * PJ/images cid ne paie plus qu'UN FETCH BODYSTRUCTURE au lieu d'un
	 * par téléchargement/aperçu (le chemin zip sans ?uv= en profite
	 * aussi). Contenu immuable par construction ; GC 7 j du dossier
	 * att/ (attachmentCacheGc) couvre ces fichiers.
	 */
	public function attachmentList(string $folder, int $uid): ?array
	{
		$imap = $this->client()->ImapClient();
		// EXAMINE comme Message()/MessageMimeStream() : Fetch exige une
		// mailbox sélectionnée - attachmentList court-circuite MailClient
		// qui la fait en interne.
		$info = $imap->FolderExamine($folder);
		$cacheFile = null;
		if ($info->UIDVALIDITY) {
			$cacheFile = $this->app->storageDir() . '/cache/att/'
				. sha1(implode('|', [
					(string) $this->app->sessionAccountId(),
					$this->app->sessionEmail(),
					$folder, $info->UIDVALIDITY, $uid])) . '.list.json';
			if (is_file($cacheFile)) {
				$c = json_decode((string) file_get_contents($cacheFile), true);
				if (is_array($c)) {
					return $c;
				}
			}
		}
		$fetch = $imap->Fetch(
			[
				\MailSo\Imap\Enumerations\FetchType::UID,
				\MailSo\Imap\Enumerations\FetchType::INTERNALDATE,
				\MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE,
			],
			(string) $uid, true);
		$resp = $fetch[0] ?? null;
		$bs = $resp?->GetFetchBodyStructure();
		if (!$resp || !$bs) {
			return null;
		}
		$list = self::attachmentsOf(
			\MailSo\Mail\Message::fromFetchResponse($folder, $resp, $bs));
		if ($cacheFile !== null) {
			@mxwm_atomic_write($cacheFile, json_encode($list));
		}
		return $list;
	}

	/** Dernier message ARRIVÉ d'un dossier (UID le plus haut - détection
	 *  "nouveau mail" du poll, une seule enveloppe par round-trip). */
	public function latestMessage(string $folder): ?array
	{
		$info = $this->client()->ImapClient()->FolderExamine($folder);
		if (!$info->UIDNEXT || $info->UIDNEXT < 2) { return null; }
		// x:* = x jusqu'au plus haut UID : couvre le cas UIDNEXT-1 expungé.
		$col = $this->client()->MessageListByUids($folder,
			new \MailSo\Imap\SequenceSet(($info->UIDNEXT - 1) . ':*'));
		$msg = $col[0] ?? null;
		return $msg ? $this->app->hooks()->filter('message.meta',
			self::messageMeta($msg), $msg) : null;
	}

	public function flags(string $folder, int $uid, string $flag, bool $set): void
	{
		$range = new \MailSo\Imap\SequenceSet((string) $uid);
		$this->client()->MessageSetFlag($folder, $range, $flag, $set);
	}

	/** Variante multi-UIDs : un seul STORE sur la sequence "1,5,9". */
	public function flagsMany(string $folder, array $uids, string $flag, bool $set): void
	{
		$this->client()->MessageSetFlag($folder,
			new \MailSo\Imap\SequenceSet(array_map('intval', $uids)), $flag, $set);
	}

	public function move(string $from, string $to, int|array $uids): void
	{
		$this->client()->MessageMove($from, $to, new \MailSo\Imap\SequenceSet($uids));
	}

	public function copy(string $from, string $to, int|array $uids): void
	{
		$this->client()->MessageCopy($from, $to, new \MailSo\Imap\SequenceSet($uids));
	}

	/**
	 * Déplacement vers un dossier de rôle ('junk'|'trash') issu du cache
	 * session : si la cible a disparu (renommée/supprimée par un autre
	 * client), invalide le cache, re-résout et retente une fois. Retourne
	 * la cible effectivement utilisée.
	 */
	private function moveToRole(string $from, string $role, string $target, int|array $uid): string
	{
		try {
			$this->move($from, $target, $uid);
			return $target;
		} catch (\Throwable $e) {
			$this->invalidateFolderCache();
			$fresh = $role === 'junk' ? $this->junkFolder() : $this->trashFolder();
			if ($fresh !== null && $fresh !== $target) {
				$this->move($from, $fresh, $uid);
				return $fresh;
			}
			throw $e;
		}
	}

	/**
	 * "Supprimer" déplace vers la corbeille ; suppression définitive
	 * (flag \Deleted + expunge) uniquement depuis la corbeille ou sans
	 * dossier corbeille détecté.
	 */
	public function delete(string $folder, int|array $uids): array
	{
		// Trash absente > créée au premier usage, sinon repli expunge.
		$trash = $this->trashFolder() ?? $this->ensureSpecialFolder('trash');
		if ($trash !== null && $folder !== $trash) {
			return ['strategy' => 'move',
				'folder' => $this->moveToRole($folder, 'trash', $trash, $uids)];
		}
		$this->client()->MessageDelete($folder, new \MailSo\Imap\SequenceSet($uids));
		return ['strategy' => 'expunge', 'folder' => null];
	}

	/**
	 * Le dossier permet-il de persister ce flag/keyword (PERMANENTFLAGS) ?
	 */
	public function supportsKeyword(string $folder, string $keyword): bool
	{
		return $this->client()->ImapClient()->FolderSelect($folder)
			->IsFlagSupported($keyword);
	}

	/**
	 * Le serveur supporte-t-il le keyword perso $Junk (PERMANENTFLAGS \*) ?
	 */
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
		// Hook : apprentissage antispam (plugin spam-learn) - émis avant le
		// move : le nouvel UID n'est pas remonté, le FETCH doit viser la
		// source. Tire aussi si l'action échoue ensuite (409) - accepté.
		$this->app->hooks()->run('message.spam', $folder, $uid, $spam);

		// Junk absente > créée au premier marquage (un move échouerait pareil
		// et le keyword $Junk laisserait le message en place).
		$junk = $this->junkFolder() ?? ($spam ? $this->ensureSpecialFolder('junk') : null);

		if ($spam) {
			if ($junk !== null && $folder === $junk) {
				return ['spam' => true, 'strategy' => 'move', 'folder' => $junk];
			}
			if ($junk !== null) {
				return ['spam' => true, 'strategy' => 'move',
					'folder' => $this->moveToRole($folder, 'junk', $junk, $uid)];
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

		throw new \RuntimeException($this->t('errors.spam_unsupported'), 409);
	}

	// ─── Dossiers (actions) ───

	public function folderCreate(string $name, string $parent = ''): void
	{
		$this->client()->FolderCreate($name, $parent);
		$this->invalidateFolderCache();
	}

	public function folderDelete(string $name): void
	{
		$this->client()->FolderDelete($name);
		$this->invalidateFolderCache();
	}

	public function folderRename(string $from, string $to): void
	{
		$this->client()->FolderRename($from, $to);
		$this->invalidateFolderCache();
	}

	public function folderSubscribe(string $name, bool $subscribe): void
	{
		$subscribe
			? $this->client()->FolderSubscribe($name)
			: $this->client()->FolderUnsubscribe($name);
		$this->invalidateFolderCache();
	}

	public function folderClear(string $name): void
	{
		$this->client()->FolderClear($name);
		$this->invalidateFolderCache(); // compteurs total/unread périmés
	}

	/** Marque tous les messages du dossier comme lus (UID STORE 1:* +FLAGS \Seen). */
	public function folderMarkRead(string $name): void
	{
		$this->client()->MessageSetFlag($name,
			new \MailSo\Imap\SequenceSet('1:*'), '\\Seen', true);
		$this->invalidateFolderCache(); // unread du dossier remis à zéro
	}

	public function quota(): ?array
	{
		// GETQUOTAROOT INBOX : forme compatible Dovecot (GETQUOTA "" reçoit
		// souvent "NO Quota root doesn't exist"). Un NO = quota absent >
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
		'sent'    => ['Sent', 'Sent Items', 'Envoyés', 'Courrier envoyé', 'Envoyé', 'Éléments envoyés', 'Éléments envoyes'],
		'trash'   => ['Trash', 'Deleted', 'Corbeille', 'Deleted Items', 'Éléments supprimés', 'Éléments supprimes'],
		'junk'    => ['Junk', 'Spam', 'Courrier indésirable'],
		'archive' => ['Archive', 'Archives', 'All Mail'],
	];

	/**
	 * Position "racine ou enfant direct de INBOX" - seules ces places
	 * peuvent prétendre à un rôle par nom usuel (un "Foo/Trash" personnel
	 * n'est pas la corbeille ; "INBOX.Trash" des préfixes Courier/Dovecot
	 * l'est). Le préfixe inclut le délimiteur : "INBOXES.Trash" ne matche
	 * pas ; la casse du nom INBOX est ignorée (RFC 3501).
	 */
	private static function isRoleCandidate(array $f): bool
	{
		$delim = (string) ($f['delimiter'] ?? '');
		$full  = (string) $f['fullName'];
		if ($delim === '' || !str_contains($full, $delim)) {
			return true; // racine
		}
		$prefix = 'INBOX' . $delim;
		return stripos($full, $prefix) === 0
			&& !str_contains(substr($full, strlen($prefix)), $delim);
	}

	/**
	 * Assignations des dossiers système du compte actif :
	 * role => 'none'|fullName ('auto' n'est jamais stocké).
	 */
	public function specialFolderMap(): array
	{
		if ($this->specialMap === null) {
			$this->specialMap = (array) $this->accountFolderEntry('special_folders');
		}
		return $this->specialMap;
	}

	public function setSpecialFolderMap(array $map): void
	{
		// Ne stocker que les overrides : 'auto' = pas d'assignation, filtré pour
		// que isset($map[$role]) signifie "override explicite".
		$map = array_filter($map,
			fn($v) => is_string($v) && $v !== '' && $v !== 'auto');
		$this->updateAccountPref('special_folders', fn() => $map, true);
		$this->specialMap = $map;
	}

	/**
	 * Set des fullName des dossiers créés via l'interface (compte actif) :
	 * exemptés du repli par nom usuel dans folders() - un "Archives" créé
	 * à la main reste un dossier personnel, pas un dossier de base.
	 */
	public function userFolderSet(): array
	{
		return $this->userFolderSet ??= $this->accountFolderSet('user_folders');
	}

	/**
	 * Réglages dossiers de l'utilisateur en une seule requête (getMany) -
	 * mutualisé entre specialFolderMap() et userFolderSet() qui lisaient
	 * chacun leur clé (2 SELECT avant). Mémoïsé par requête, invalidé sur
	 * écriture (setSpecialFolderMap, updateUserFolders).
	 */
	private ?array $folderPrefs = null;

	private function folderPrefs(): array
	{
		return $this->folderPrefs ??= $this->app->settings()->getMany(
			$this->app->sessionUserId(),
			['special_folders', 'user_folders', 'collapsed_folders', 'hidden_folders',
				'notify_folders', 'last_folder']);
	}

	/** Réglage JSON stocké > tableau (accepte tableau natif ou chaîne). */
	private function decodeSetting(mixed $raw): array
	{
		return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
	}

	/**
	 * Entrée du compte actif dans une préférence dossier stockée en carte
	 * JSON {account_id: valeur} (null si absente).
	 */
	private function accountFolderEntry(string $setting): mixed
	{
		$all = $this->decodeSetting($this->folderPrefs()[$setting] ?? null);
		return $all[(string) $this->app->sessionAccountId()] ?? null;
	}

	/** Set fullName => true de l'entrée "liste de noms" du compte actif. */
	private function accountFolderSet(string $setting): array
	{
		$names = array_map('strval', (array) $this->accountFolderEntry($setting));
		return array_fill_keys($names, true);
	}

	/**
	 * Préférence dossier par compte (carte JSON {account_id: valeur}) :
	 * $mutate reçoit l'entrée du compte actif et retourne la nouvelle -
	 * retour vide (null/[]/'') = entrée retirée ; carte vide = réglage
	 * supprimé. folderPrefs est invalidé dans tous les cas et le cache
	 * fichier des dossiers si $invalidateFolders (rôles/flags recalculés).
	 */
	private function updateAccountPref(string $setting, callable $mutate,
		bool $invalidateFolders = false): void
	{
		$all = $this->decodeSetting($this->folderPrefs()[$setting] ?? null);
		$key = (string) $this->app->sessionAccountId();
		$new = $mutate($all[$key] ?? null);
		if ($new === null || $new === [] || $new === '') {
			unset($all[$key]);
		} else {
			$all[$key] = $new;
		}
		$this->app->settings()->set($this->app->sessionUserId(), $setting,
			array_filter($all) === [] ? null : $all);
		$this->folderPrefs = null;
		if ($invalidateFolders) {
			$this->invalidateFolderCache();
		}
	}

	/**
	 * Set des fullName des dossiers repliés dans la colonne latérale
	 * (compte actif) - état UI persisté par compte, même stockage que
	 * special_folders / notify_folders.
	 */
	private function collapsedFolderSet(): array
	{
		return $this->accountFolderSet('collapsed_folders');
	}

	/**
	 * Dossiers repliés du compte actif : intersectés avec l'existant (purge
	 * des dossiers disparus) ; vide pour le compte = clé supprimée.
	 */
	public function setCollapsedFolders(array $names): void
	{
		$existing = array_fill_keys(
			array_column($this->cachedFolders(), 'fullName'), true);
		$names = array_values(array_filter(array_map('strval', $names),
			fn($n) => isset($existing[$n])));
		$this->updateAccountPref('collapsed_folders', fn() => $names, true);
	}

	/**
	 * Set des fullName des dossiers masqués de la colonne latérale
	 * (compte actif) - état UI persisté par compte, même stockage que
	 * collapsed_folders.
	 */
	private function hiddenFolderSet(): array
	{
		return $this->accountFolderSet('hidden_folders');
	}

	/**
	 * Dossiers masqués du compte actif : intersectés avec les dossiers
	 * personnels existants (dossiers natifs exclus, purge des disparus) ;
	 * vide pour le compte = clé supprimée.
	 */
	public function setHiddenFolders(array $names): void
	{
		$allowed = array_fill_keys(array_map('strval',
			array_column(array_filter($this->cachedFolders(),
				fn($f) => empty($f['system'])), 'fullName')), true);
		$names = array_values(array_filter(array_map('strval', $names),
			fn($n) => isset($allowed[$n])));
		$this->updateAccountPref('hidden_folders', fn() => $names, true);
	}

	/**
	 * Dernier dossier consulté du compte actif - restauré à l'ouverture du
	 * webmail. Validation sur le cache fichier UNIQUEMENT (jamais folders()
	 * : appdata ne doit pas payer une connexion IMAP) ; cache absent ou
	 * périmé > nom brut, le client intersecte avec la liste reçue au boot.
	 */
	public function lastFolder(): ?string
	{
		$name = (string) ($this->accountFolderEntry('last_folder') ?? '');
		if ($name === '') {
			return null;
		}
		$cached = $this->folderCacheRead();
		if ($cached === null) {
			return $name;
		}
		foreach ($cached as $row) {
			if (($row['fullName'] ?? '') === $name) {
				return $name;
			}
		}
		return null;
	}

	/**
	 * Dernier dossier consulté du compte actif : intersecté avec l'existant
	 * (cache fichier ou LIST à froid, comme setCollapsedFolders) ; nom vide
	 * ou disparu > clé du compte retirée, carte vide > réglage supprimé.
	 */
	public function setLastFolder(string $name): void
	{
		$exists = array_fill_keys(
			array_column($this->cachedFolders(), 'fullName'), true);
		$this->updateAccountPref('last_folder',
			fn() => $name !== '' && isset($exists[$name]) ? $name : null);
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

	/** Provenance conservée au renommage : $from et ses descendants > $to. */
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
		$this->updateAccountPref('user_folders', fn($names)
			=> array_values(array_unique(array_map('strval',
				$fn(array_map('strval', (array) ($names ?? [])))))), true);
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
		$names = array_values(array_filter(array_map('strval',
			(array) $this->accountFolderEntry('notify_folders')),
			fn($n) => $n !== '' && strcasecmp($n, 'INBOX') !== 0));
		return array_values(array_unique(array_merge(['INBOX'], $names)));
	}

	public function setNotifyFolderList(array $names): void
	{
		$existing = array_column($this->folders(), 'fullName');
		$names = array_values(array_filter(array_unique(array_map('strval', $names)),
			fn($n) => in_array($n, $existing, true) && strcasecmp($n, 'INBOX') !== 0));
		$this->updateAccountPref('notify_folders', fn() => $names);
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
		$folders = $this->cachedFolders();
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
		return $this->detectedSpecialFolder($role, $folders);
	}

	/**
	 * Dossier du rôle résolu par la seule détection (special-use puis noms
	 * usuels), en ignorant toute assignation utilisateur - ce que "auto"
	 * utiliserait. Retourne un nom deviné si rien n'existe encore. $folders
	 * = lignes à scanner (défaut : folders() live).
	 */
	public function detectedSpecialFolder(string $role, ?array $folders = null): ?string
	{
		$role = strtolower(ltrim($role, '\\'));
		$folders ??= $this->folders();
		foreach ($folders as $f) {
			if (strtolower(ltrim((string) $f['role'], '\\')) === $role) {
				return $f['fullName'];
			}
		}
		foreach (self::FOLDER_FALLBACKS[$role] ?? [] as $candidate) {
			$c = mb_strtolower($candidate);
			foreach ($folders as $f) {
				if (($f['isSelectable'] ?? true) !== false
					&& self::isRoleCandidate($f)
					&& mb_strtolower((string) $f['name']) === $c) {
					return $f['fullName'];
				}
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
		return $this->existingSpecialFolder('junk');
	}

	/**
	 * Dossier Corbeille réellement existant (assignation, special-use \trash
	 * ou nom usuel), sinon null - jamais de nom deviné.
	 */
	public function trashFolder(): ?string
	{
		return $this->existingSpecialFolder('trash');
	}

	/**
	 * Résolution d'un rôle réellement existant : 'none' assigné > null,
	 * special-use en priorité puis noms usuels - jamais de nom deviné.
	 */
	private function existingSpecialFolder(string $role): ?string
	{
		if (($this->specialFolderMap()[$role] ?? null) === 'none') {
			return null;
		}
		$folders = $this->cachedFolders();
		foreach ($folders as $f) {
			if ($f['special'] === $role) {
				return $f['fullName'];
			}
		}
		$names = array_map('mb_strtolower', self::FOLDER_FALLBACKS[$role]);
		foreach ($folders as $f) {
			if (($f['isSelectable'] ?? true) !== false && self::isRoleCandidate($f)
				&& in_array(mb_strtolower((string) $f['name']), $names, true)) {
				return $f['fullName'];
			}
		}
		return null;
	}

	/**
	 * 'inbox'  > le nom désigne la boîte de réception ;
	 * 'system' > dossier "par défaut" (INBOX, rôle effectif ou assigné)
	 * sur lequel renommage, ACL et suppression sont interdits.
	 */
	public function folderFlags(string $name): array
	{
		$inbox = strcasecmp($name, 'INBOX') === 0;
		$system = $inbox;
		if (!$system) {
			foreach ($this->cachedFolders() as $f) {
				if ($f['fullName'] === $name) {
					$system = !empty($f['special']);
					break;
				}
			}
		}
		return ['inbox' => $inbox, 'system' => $system];
	}

	/**
	 * Écrit $rawMessage dans un fichier temporaire passé à $fn, supprimé
	 * ensuite quoi qu'il arrive (APPEND exige un chemin ou un stream).
	 */
	private function withTempMessage(string $rawMessage, callable $fn): mixed
	{
		$tmpDir = $this->app->storageDir() . '/tmp';
		Files::ensureDir($tmpDir);
		$tmp = tempnam($tmpDir, 'append_');
		file_put_contents($tmp, $rawMessage);
		try {
			return $fn($tmp);
		} finally {
			@unlink($tmp);
		}
	}

	/** Ajoute un message brut (EML) dans un dossier via IMAP APPEND. Retourne l'UID. */
	public function appendMessage(string $rawMessage, string $folder, array $flags = ['\\Seen'], int $internalDate = 0): int
	{
		return $this->withTempMessage($rawMessage, fn(string $tmp) =>
			$this->appendRetryCreate($folder,
				fn() => $this->client()->MessageAppendFile($tmp, $folder, $flags, $internalDate)));
	}

	/**
	 * APPEND sur un dossier potentiellement deviné (rôle sans dossier réel) :
	 * sur réponse TRYCREATE/NONEXISTENT (le SELECT préalable de certains
	 * chemins renvoie NONEXISTENT), le dossier est créé puis l'opération
	 * rejouée UNE fois. L'échec de création (ALREADYEXISTS en cas de course)
	 * est ignoré - c'est la retentative qui tranche.
	 */
	private function appendRetryCreate(string $folder, callable $append): mixed
	{
		try {
			return $append();
		} catch (\MailSo\Imap\Exceptions\ResponseException $e) {
			$m = strtoupper($e->getMessage());
			if (!str_contains($m, 'TRYCREATE') && !str_contains($m, 'NONEXISTENT')) {
				throw $e;
			}
			try {
				$this->client()->FolderCreate($folder);
			} catch (\Throwable) {}
			$this->invalidateFolderCache();
			return $append();
		}
	}

	/**
	 * Dossier d'un rôle garanti existant : détection puis création du nom
	 * deviné si absent (un move vers un dossier inexistant échoue pareil).
	 * null si le rôle est désactivé ('none') ou si la création échoue.
	 */
	private function ensureSpecialFolder(string $role): ?string
	{
		if (($this->specialFolderMap()[$role] ?? null) === 'none') {
			return null;
		}
		$name = $this->detectedSpecialFolder($role);
		foreach ($this->cachedFolders() as $f) {
			if ($f['fullName'] === $name) {
				return $name;
			}
		}
		try {
			$this->client()->FolderCreate($name);
			$this->invalidateFolderCache();
			return $name;
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * Remplace le message $uid par $rawMessage dans $folder (RFC 8508
	 * UID REPLACE ; sinon append + expunge ciblé - MessageReplaceStream
	 * gère le fallback). $uid < 1 > simple APPEND. Retourne l'UID du
	 * message résultant, ou null si le serveur ne le publie pas (pas de
	 * UIDPLUS sur le chemin append).
	 */
	public function replaceMessage(string $rawMessage, string $folder, int $uid, array $flags = ['\\Seen']): ?int
	{
		return $this->withTempMessage($rawMessage,
			function (string $tmp) use ($folder, $uid, $flags) {
			$fp = fopen($tmp, 'rb');
			try {
				$imap = $this->client()->ImapClient();
				$new = $this->appendRetryCreate($folder, function () use ($imap, $folder, $uid, $fp, $tmp, $flags) {
					rewind($fp); // stream consommé par la tentative précédente
					return $imap->MessageReplaceStream($folder, $uid, $fp, filesize($tmp), $flags);
				});
				// REPLACE conserve l'UID ; fallback append > nouvel UID (ou
				// null sans UIDPLUS > la réf client n'est juste pas mise à jour).
				return $new ?? ($uid > 0 && $imap->hasCapability('REPLACE') ? $uid : null);
			} finally {
				is_resource($fp) && fclose($fp);
			}
		});
	}

	/**
	 * Suppression définitive sans sémantique corbeille (contrairement à
	 * delete()) : flag \Deleted + UID EXPUNGE ciblé. Pour les brouillons
	 * consommés par un envoi - une copie en corbeille serait du bruit.
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
				$this->app->sessionDomain(), $this->app->isSecondaryAccount());
			if (empty($domain['sieve_host'])) {
				throw new \RuntimeException(
					$this->t('errors.sieve_not_configured', ['domain' => $domain['name'] ?? '']), 400);
			}
			$settings = AuthService::sieveSettings($domain);
			$this->applyAuth($settings);

			$client = new \MailSo\Sieve\SieveClient();
			$client->SetLogger(\MXWebMail\API::Logger());
			$this->app->hooks()->run('sieve.before-connect', $client, $settings);
			$this->connectClient($client, $settings, 'errors.sieve_unreachable');
			$this->sieveClient = $client;
		}
		return $this->sieveClient;
	}

	/**
	 * Exécute une commande ManageSieve avec mapping des erreurs : sans lui,
	 * tout NO/BAD remonte en NegativeResponseException (code 0) > 500
	 * "Erreur interne" opaque. (NONEXISTENT) > 404, autre refus > 400
	 * avec le motif serveur nettoyé, login > 401.
	 */
	private function sieveRun(callable $fn): mixed
	{
		try {
			return $fn($this->sieve());
		} catch (\MailSo\Sieve\Exceptions\LoginException $e) {
			throw new UserFacingException($this->t('errors.bad_credentials'), 401, $e);
		} catch (\MailSo\Sieve\Exceptions\NegativeResponseException $e) {
			if (str_contains($e->getMessage(), '(NONEXISTENT)')) {
				throw new UserFacingException(
					$this->t('errors.filter_not_found'), 404, $e);
			}
			throw new UserFacingException(
				$this->t('errors.sieve_rejected',
					['detail' => self::sieveErrorDetail($e->getMessage())]), 400, $e);
		}
	}

	/**
	 * Dernière ligne d'un NO/BAD ManageSieve > texte affichable : CRLF
	 * aplaties, préfixes "NO "/"BAD " et noms de fichier temporaires
	 * Dovecot ("<epoch>.M..P...<host>.tmp:" - qui fuiterait l'hostname)
	 * retirés.
	 */
	private static function sieveErrorDetail(string $message): string
	{
		$detail = (string) preg_replace('/\s*\r?\n\s*/', ' ', trim($message));
		$detail = (string) preg_replace('/^(NO|BAD)\s+/i', '', $detail);
		$detail = trim((string) preg_replace('/[0-9]+\.\S*\.tmp:\s*/', '', $detail));
		return $detail !== '' ? $detail : 'NO';
	}

	/** Scripts + modules Sieve annoncés par le serveur (l'assistant les
	 *  utilise pour masquer les actions/opérateurs non supportés). */
	public function filtersList(): array
	{
		$out = [];
		foreach ($this->sieveRun(fn($s) => $s->ListScripts()) as $name => $active) {
			$out[] = ['name' => $name, 'active' => (bool) $active];
		}
		return ['scripts' => $out, 'modules' => $this->sieve()->Modules()];
	}

	public function filterGet(string $name): string
	{
		return $this->sieveRun(fn($s) => $s->GetScript($name));
	}

	public function filterCheck(string $source): void
	{
		// Pré-contrôle local des extensions "require" : refus immédiat et
		// lisible, indépendant du support CHECKSCRIPT (sauté si le serveur
		// n'annonce pas sa capacité SIEVE).
		$modules = $this->sieve()->Modules();
		if ($modules
			&& preg_match_all('/^\s*require\s+(\[[^\]]*\]|"[^"]+")\s*;/im',
				$source, $m)) {
			$missing = [];
			foreach ($m[1] as $capList) {
				foreach ((array) (json_decode($capList, true) ?? []) as $cap) {
					if (!in_array(strtolower((string) $cap), $modules, true)) {
						$missing[] = (string) $cap;
					}
				}
			}
			if ($missing) {
				throw new UserFacingException(
					$this->t('errors.sieve_module',
						['module' => implode(', ', $missing)]), 400);
			}
		}
		try {
			$this->sieve()->CheckScript($source);
		} catch (\MailSo\Sieve\Exceptions\NegativeResponseException $e) {
			// BAD = commande inconnue du serveur (CHECKSCRIPT optionnelle,
			// RFC 5804) : PUTSCRIPT rejouera la validation - ne pas bloquer.
			if (str_starts_with($e->getMessage(), 'BAD')) {
				$this->app->log('warning',
					'CHECKSCRIPT non supporte : ' . $e->getMessage());
				return;
			}
			throw new UserFacingException(
				$this->t('errors.sieve_rejected',
					['detail' => self::sieveErrorDetail($e->getMessage())]), 400, $e);
		}
	}

	/** $oldName non null ≠ $name : renommage (PUT nouveau + DELETE ancien -
	 *  ne dépend pas de la capacité RENAMESCRIPT, optionnelle). SETACTIVE
	 *  avant DELETE si l'ancien était actif : DELETESCRIPT refuse un script
	 *  actif et l'état actif est ainsi conservé. */
	public function filterSave(string $name, string $source, ?string $oldName = null): void
	{
		$this->sieveRun(fn($s) => $s->PutScript($name, $source));
		if ($oldName !== null && $oldName !== $name
			&& array_key_exists($oldName,
				$scripts = $this->sieveRun(fn($s) => $s->ListScripts()))) {
			if ($scripts[$oldName]) {
				$this->sieveRun(fn($s) => $s->SetActiveScript($name));
			}
			try {
				$this->sieveRun(fn($s) => $s->DeleteScript($oldName));
			} catch (UserFacingException $e) {
				// Ancien nom déjà supprimé entre LISTSCRIPTS et DELETE :
				// NONEXISTENT est l'état attendu > on continue.
				if ($e->getCode() !== 404) { throw $e; }
			}
		}
	}

	public function filterActivate(string $name): void
	{
		$this->sieveRun(fn($s) => $s->SetActiveScript($name));
	}

	/** SETACTIVE "" : désactive le script actif (RFC 5804). */
	public function filterDeactivate(): void
	{
		$this->sieveRun(fn($s) => $s->SetActiveScript(''));
	}

	public function filterDelete(string $name): void
	{
		$this->sieveRun(fn($s) => $s->DeleteScript($name));
	}

	/** GetScript tolérant : script disparu entre LISTSCRIPTS et GETSCRIPT > null. */
	private function sieveGetOrNull(string $name): ?string
	{
		try {
			return $this->sieveRun(fn($s) => $s->GetScript($name));
		} catch (UserFacingException $e) {
			if ($e->getCode() !== 404) { throw $e; }
			return null;
		}
	}

	/**
	 * Scripts ManageSieve classés en un passage : [nom du script actif
	 * (null si aucun), {nom: doc marqueur parsé|null}]. Un seul GetScript
	 * par script (N+1 round-trips sinon) - le doc de l'actif est réutilisé
	 * par l'appelant au lieu d'être re-fetché.
	 */
	private function sieveScriptDocs(): array
	{
		$scripts = $this->sieveRun(fn($s) => $s->ListScripts());
		$active = null;
		$docs = [];
		foreach (array_keys($scripts) as $name) {
			$src = $this->sieveGetOrNull((string) $name);
			$docs[$name] = $src === null
				? null
				: \MXWebMail\Sieve\ScriptBuilder::parseMarker($src);
			if ($scripts[$name]) {
				$active = (string) $name;
			}
		}
		return [$active, $docs];
	}

	/**
	 * Configuration du répondeur d'absence (Sieve vacation) : lue dans le
	 * doc géré (marqueur MXWM-RULES) du script actif. 'managed' indique si
	 * le script actif est géré par l'assistant - un script externe n'est
	 * jamais réécrit silencieusement.
	 */
	public function vacationGet(): array
	{
		$sieve = $this->sieve();
		[$active, $docs] = $this->sieveScriptDocs();
		$managed = array_map('strval', array_keys(array_filter($docs)));
		$vacation = $active !== null && in_array($active, $managed, true)
			? ($docs[$active]['vacation'] ?? null) : null;
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
	 * 'mxwebmail' activé. Script actif non géré > refus (409).
	 */
	public function vacationSave(array $vacation): void
	{
		$sieve = $this->sieve();
		if (!in_array('vacation', $sieve->Modules(), true)) {
			throw new \RuntimeException(
				$this->t('errors.sieve_module', ['module' => 'vacation']), 400);
		}
		[$active, $docs] = $this->sieveScriptDocs();
		$managed = array_filter($docs);
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
		$source = \MXWebMail\Sieve\ScriptBuilder::compile($doc, $this->app->locale());
		$this->filterCheck($source);
		$this->sieveRun(fn($s) => $s->PutScript($target, $source));
		// Activation uniquement si le répondeur est actif et le script ne l'est pas
		if (!empty($vacation['enabled']) && $active !== $target) {
			$this->sieveRun(fn($s) => $s->SetActiveScript($target));
		}
	}

	// ─── ACL dossiers ───

	/** Capabilities IMAP du compte actif exposables à l'UI (connexion déjà
	 *  ouverte : simple lecture du tableau de caps actives). */
	public function capabilities(): array
	{
		return ['acl' => $this->client()->ImapClient()->hasCapability('ACL')];
	}

	/** 400 explicite si le serveur n'a pas la capacité ACL (sinon réponse vide
	 *  indistinguable de "aucun droit"). */
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
	 * $meta (sortie d'attachmentList) permet à MessageMimeStream de sauter
	 * le FETCH d'en-têtes MIME quand les champs sont déjà connus.
	 */
	/**
	 * Contenu décodé d'une pièce jointe ($mimeIndex '' = message complet),
	 * capé à $maxBytes. null si paramètres invalides (uid, CRLF injecté
	 * dans le nom de dossier) ou PJ absente/illisible - l'appelant garde
	 * sa propre erreur localisée (plugins ics/vcf/report).
	 */
	public function attachmentContent(string $folder, int $uid,
		string $mimeIndex, int $maxBytes): ?string
	{
		if ($uid <= 0 || strpbrk($folder, "\r\n") !== false) {
			return null;
		}
		$raw = null;
		$ok = $this->mimeStream($folder, $uid, $mimeIndex,
			function ($r) use (&$raw, $maxBytes) {
				$raw = stream_get_contents($r, $maxBytes);
			});
		return ($ok && is_string($raw) && $raw !== '') ? $raw : null;
	}

	public function mimeStream(string $folder, int $uid, string $mimeIndex, callable $cb, ?array $meta = null): bool
	{
		return $this->client()->MessageMimeStream(
			function ($resource) use ($cb) {
				if (is_resource($resource)) {
					$cb($resource);
				}
			},
			$folder,
			$uid,
			$mimeIndex,
			$meta
		);
	}

	// ─── SMTP ───

	public function send(\MailSo\Mime\Message $message): void
	{
		$this->app->hooks()->run('message.before-send', $message);
		// Bcc retiré du DATA : l'en-tête ne doit pas fuiter vers les destinataires.
		$stream = $message->ToStream(true);
		$from = $message->GetFrom();
		$this->smtpSendRaw(
			$from?->GetEmail(),
			array_map(static fn ($r) => $r->GetEmail(),
				iterator_to_array($message->GetRcpt())),
			$stream,
			fstat($stream)['size'] ?? 0
		);

		// Copie dans le dossier Éléments envoyés (l'échec ne doit pas faire
		// échouer l'envoi). Bcc conservé ici - ToString(false) : l'expéditeur
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

	/** "Renvoyer tel quel" (bounce) : le source brut est re-expédié préfixé
	 *  des champs Resent-* (RFC 5322 §3.6.6 - prepended comme les champs
	 *  trace). MAIL FROM = utilisateur de session, RCPT TO = destinataire
	 *  saisi. Pas de copie Sent (ce n'est pas un nouveau message) et pas de
	 *  hook message.before-send (pas d'objet Mime\Message). */
	public function bounce(string $folder, int $uid, string $to): void
	{
		$raw = null;
		if (!$this->mimeStream($folder, $uid, '', function ($r) use (&$raw) {
			$raw = fopen('php://temp', 'w+b');
			stream_copy_to_stream($r, $raw);
		}) || !is_resource($raw)) {
			throw new \RuntimeException($this->t('errors.not_found'), 404);
		}
		$out = fopen('php://temp', 'w+b');
		fwrite($out,
			'Resent-Date: ' . date(\DateTimeInterface::RFC2822) . "\r\n"
			. 'Resent-From: <' . $this->app->sessionEmail() . ">\r\n"
			. 'Resent-To: <' . $to . ">\r\n"
			. 'Resent-Message-ID: <' . bin2hex(random_bytes(16))
				. '@' . \MailSo\Smtp\SmtpClient::EhloHelper() . ">\r\n");
		rewind($raw); // le callback mimeStream l'a laissé en fin de flux
		stream_copy_to_stream($raw, $out);
		fclose($raw);
		rewind($out);
		$this->smtpSendRaw(($this->app->sessionEmail() ?: null), [$to],
			$out, fstat($out)['size'] ?? 0);
	}

	/** Hôtes SMTP exigeant des en-têtes en ordre canonique (Free : 550 Spam
	 *  Detected sinon - l'ordre technique-en-tête de MailSo est un fingerprint
	 *  de bot pour leur filtre, constaté par bissection openssl). */
	private const SMTP_CANONICAL_HEADERS = ['smtp.free.fr'];

	/** Corps SMTP partagé send/bounce : résolution domaine + auth + dialogue
	 *  (MailFrom/Rcpt/DATA). $mailFrom null > username des settings. */
	private function smtpSendRaw(?string $mailFrom, array $rcpts, $stream, int $size): void
	{
		$domain = $this->app->auth()->domainFor(
			$this->app->sessionDomain(), $this->app->isSecondaryAccount());
		$settings = AuthService::smtpSettings($domain);
		$this->applyAuth($settings);

		if (\in_array(\strtolower($settings->host), self::SMTP_CANONICAL_HEADERS, true)) {
			$stream = self::canonicalizeHeaders($stream);
			$size = fstat($stream)['size'] ?? $size;
		}

		$smtp = new \MailSo\Smtp\SmtpClient();
		$smtp->SetLogger(\MXWebMail\API::Logger());
		$smtp->Settings = $settings;
		$this->app->hooks()->run('smtp.before-connect', $smtp, $settings);
		try {
			$smtp->Connect($settings);
			// useAuth=false (option du domaine) : pas de commande AUTH -
			// relais interne ou serveur SMTP sans authentification.
			if ($settings->useAuth) {
				$smtp->Login($settings);
			}

			$smtp->MailFrom($mailFrom ?: $settings->username, $size);
			foreach ($rcpts as $rcpt) {
				$smtp->Rcpt($rcpt);
			}
			$smtp->DataWithStream($stream);
		} catch (\MailSo\Smtp\Exceptions\ResponseException $e) {
			// Réponse brute du serveur utile au diagnostic (quota, adresse
			// refusée) - n'affichée que si labs.smtp_show_server_errors l'active.
			$msg = $this->t('errors.send_failed');
			if ($settings->viewErrors) {
				$msg .= ' : ' . $e->getMessage();
			} else {
				$this->app->log('error', 'Envoi SMTP refusé : ' . $e->getMessage());
			}
			throw new UserFacingException($msg, 502, $e);
		} catch (\MailSo\Net\Exceptions\ConnectionException
			| \MailSo\Net\Exceptions\SocketReadException
			| \MailSo\Net\Exceptions\SocketReadTimeoutException
			| \MailSo\Net\Exceptions\SocketWriteException $e) {
			// Panne amont : remonter brut - le dispatcher mappe en 503 +
			// message "serveur injoignable / connexion interrompue".
			$this->app->log('error', 'Envoi SMTP impossible : ' . $e->getMessage());
			throw $e;
		} catch (\Throwable $e) {
			// Refus protocolaire inattendu ou bug : pas de détail technique.
			$this->app->log('error', 'Envoi SMTP échoué : ' . $e->getMessage());
			throw new UserFacingException($this->t('errors.send_failed'), 502, $e);
		} finally {
			// En cas d'exception mid-dialogue la socket resterait ouverte.
			try { $smtp->Disconnect(); } catch (\Throwable) {}
		}
	}

	/** En-têtes que Free rejette quelle que soit leur position (prouvé par
	 *  rejeu) : retirés du DATA. Disposition-Notification-To dégrade l'accusé
	 *  de réception en envoi simple, X-Originating-IP concerne le plugin
	 *  optionnel éponyme. */
	private const SMTP_OMIT_HEADERS = [
		'Disposition-Notification-To:', 'X-Originating-IP:'];

	/** En-têtes placés en tête du bloc canonique, dans cet ordre. */
	private const LEADING_HEADERS = [
		'Resent-', 'From:', 'Reply-To:', 'To:', 'Cc:', 'Subject:', 'Date:',
		'Message-ID:', 'In-Reply-To:', 'References:'];

	/** En-têtes MIME relégués en fin de bloc canonique, dans cet ordre. */
	private const TRAILING_HEADERS = [
		'MIME-Version:', 'Content-Type:', 'Content-Transfer-Encoding:'];

	/** Réordonne le bloc d'en-têtes du flux RFC822 : Resent-* d'abord (champs
	 *  prepended, RFC 5322 §3.6.6), puis identité From/Reply-To/To/Cc/Subject/
	 *  Date, puis Message-ID/In-Reply-To/References (threading, exigé avant le
	 *  bloc MIME par Free), puis le reste dans l'ordre d'origine, puis
	 *  MIME-Version/Content-Type/CTE en fin. Les en-têtes SMTP_OMIT_HEADERS
	 *  sont supprimés. MailSo fige l'ordre dans ToStream() (défauts ajoutés
	 *  en tête) d'où la réécriture post-sérialisation. Corps et pliures
	 *  d'en-têtes préservés. */
	private static function canonicalizeHeaders($stream)
	{
		$raw = stream_get_contents($stream);
		fclose($stream);
		$pos = strpos($raw, "\r\n\r\n");
		$head = false === $pos ? $raw : substr($raw, 0, $pos);
		$rest = false === $pos ? '' : substr($raw, $pos);
		// Rattache les lignes pliées (continuation espace/tab) à leur en-tête.
		$headers = [];
		foreach (explode("\r\n", $head) as $line) {
			if ('' !== $line && ' ' !== $line[0] && "\t" !== $line[0]) {
				$headers[] = $line;
			} elseif ($headers) {
				$headers[count($headers) - 1] .= "\r\n" . $line;
			}
		}
		// Un passage : chaque en-tête reçoit son rang canonique (LEADING
		// < reste < TRAILING) ; le tri stable préserve l'ordre d'origine
		// au sein du "reste". Les en-têtes omis sont supprimés.
		$ranked = [];
		foreach ($headers as $h) {
			$rank = self::headerOrderRank($h);
			if ($rank !== null) {
				$ranked[] = [$rank, $h];
			}
		}
		usort($ranked, fn($a, $b) => $a[0] <=> $b[0]); // stable depuis PHP 8.0
		$out = array_column($ranked, 1);
		$outStream = fopen('php://temp', 'w+b');
		fwrite($outStream, implode("\r\n", $out) . $rest);
		rewind($outStream);
		return $outStream;
	}

	/**
	 * Rang canonique d'un en-tête : LEADING_HEADERS (0..n) < reste < TRAILING
	 * (+1 décalage pour rester après le rang "reste"). null = en-tête omis.
	 */
	private static function headerOrderRank(string $h): ?int
	{
		foreach (self::SMTP_OMIT_HEADERS as $p) {
			if (0 === stripos($h, $p)) {
				return null;
			}
		}
		foreach (self::LEADING_HEADERS as $i => $p) {
			if (0 === stripos($h, $p)) {
				return $i;
			}
		}
		foreach (self::TRAILING_HEADERS as $i => $p) {
			if (0 === stripos($h, $p)) {
				return count(self::LEADING_HEADERS) + 1 + $i;
			}
		}
		return count(self::LEADING_HEADERS);
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
			'isForwarded' => in_array('$forwarded', $flags, true),
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
			} elseif (preg_match('#^https?://#i', $target) && $url === null) {
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

	/** Métadonnées PJ aplaties - partagé par messageFull et attachmentMeta. */
	private static function attachmentsOf(\MailSo\Mail\Message $msg): array
	{
		$d = $msg->jsonSerialize();
		$attachments = [];
		foreach ($d['attachments'] ?? [] as $att) {
			// Attachment est JsonSerializable : (array) exposerait les props
			// privées sous des clés mangled ("\0Class\0prop") - toutes les
			// métadonnées (mimeIndex, cId, fileName) deviendraient null.
			$attObj = $att instanceof \MailSo\Mail\Attachment ? $att : null;
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
				// CTE du BODYSTRUCTURE : permet à MessageMimeStream de
				// sauter le FETCH BODY[x.MIME] (décision BINARY/PEEK).
				'encoding'  => $attObj ? $attObj->ContentTransferEncoding() : null,
			];
		}
		return $attachments;
	}

	private static function messageFull(\MailSo\Mail\Message $msg): array
	{
		$d = $msg->jsonSerialize();
		return array_merge(self::messageMeta($msg), [
			'to'          => $d['to'],
			'cc'          => $d['cc'],
			'bcc'         => $d['bcc'],
			'replyTo'     => $d['replyTo'],
			'bodyHtml'    => $d['html'] ?? '',
			'bodyPlain'   => $d['plain'] ?? '',
			'attachments' => self::attachmentsOf($msg),
			'messageId'   => $d['messageId'] ?? '',
			'inReplyTo'   => $d['inReplyTo'] ?? '',
			'references'  => $d['references'] ?? '',
			'draftInfo'   => $d['draftInfo'] ?? null,
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

}
