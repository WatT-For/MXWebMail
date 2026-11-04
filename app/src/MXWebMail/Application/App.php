<?php
declare(strict_types=1);

namespace MXWebMail\Application;

use MXWebMail\Config\Config;
use MXWebMail\Controller\AdminController;
use MXWebMail\Controller\Api;
use MXWebMail\Controller\Admin;
use MXWebMail\Controller\ApiController;
use MXWebMail\Controller\PageController;
use MXWebMail\Controller\SetupController;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Http\Router;
use MXWebMail\Locale\Translator;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginManager;
use MXWebMail\Repository\Database;
use MXWebMail\Repository\DomainRepository;
use MXWebMail\Repository\PluginRepository;
use MXWebMail\Repository\SettingRepository;
use MXWebMail\Repository\UserRepository;
use MXWebMail\Security\Headers;
use MXWebMail\Service\AuthService;
use MXWebMail\Service\MailService;
use MXWebMail\Theme\ThemeManager;

/**
 * Point d'entrée applicatif : config > session > stockage > routes.
 * Conteneur de services minimal, instanciés à la demande.
 */
final class App
{
	private string $rootDir;
	private string $installDir;
	private Config $config;
	private ?Database $db = null;
	private ?\MXWebMail\Repository\UserDatabase $userDb = null;
	private ?PluginManager $plugins = null;
	private ?HookRegistry $hooks = null;
	private ?Translator $locale = null;
	private ?ThemeManager $themes = null;
	private ?\MXWebMail\Service\UpdateService $updater = null;
	private ?\MXWebMail\Service\GitHubClient $github = null;
	private ?\MXWebMail\Service\PluginPackageService $pluginPackages = null;
	private ?\MXWebMail\Service\DomainDetector $domainDetector = null;
	private \DateTimeZone|false|null $logTimeZone = null;

	public function __construct(?string $rootDir = null, ?string $installDir = null)
	{
		defined('APP_VERSION') || define('APP_VERSION', '0.51.0'); // X-Mailer MailSo
		// Prérequis du paquet de mise à jour - lus par regex dans le App.php du
		// paquet extrait (UpdateService::checkRequirements) avant application.
		defined('APP_REQ_PHP') || define('APP_REQ_PHP', '>=8.4');
		defined('APP_REQ_FROM') || define('APP_REQ_FROM', ''); // source minimale, '' = aucune
		$this->rootDir = $rootDir ?? dirname(__DIR__, 3);
		// Racine d'installation (parent de public/) - passée explicitement par
		// index.php car indéductible de rootDir quand app/ est hors racine web.
		$this->installDir = $installDir ?? dirname($this->rootDir);
		$this->config = new Config($this->rootDir);
		\MXWebMail\API::init($this); // façade de compatibilité du MailSo vendored
	}

	/** Racine privée = dossier app/ (src, config, storage, plugins, templates). */
	public function rootDir(): string
	{
		return $this->rootDir;
	}

	/** Racine d'installation = parent de public/. */
	public function installDir(): string
	{
		return $this->installDir;
	}

	public function storageDir(): string
	{
		return $this->rootDir . '/storage';
	}

	public function config(): Config
	{
		return $this->config;
	}

	public function db(): Database
	{
		if ($this->db === null) {
			$this->db = Database::connect($this->config, $this->storageDir());
			$this->db->migrate();
		}
		return $this->db;
	}

	/**
	 * Libère la connexion PDO globale. Requis avant de remplacer le fichier
	 * sqlite (rollback update : sous Windows un fichier ouvert est verrouillé).
	 * La prochaine sollicitation de db() reconnecte et migre normalement.
	 */
	public function closeDb(): void
	{
		$this->db = null;
		// Repositories mémoïsés : ils référencent l'ancien PDO - à recréer.
		$this->users = $this->domains = $this->settings = $this->pluginRepo
			= $this->themeRepo = $this->pgp = $this->oauthApps = $this->identities = null;
	}

	/**
	 * Bases par utilisateur (storage/db/users/<id>.sqlite) - contacts.
	 * L'import legacy est déclenché ici et non dans db() : /api/contacts sur
	 * session valide n'ouvre jamais la base globale, l'import doit donc être
	 * garanti au premier accès contacts.
	 */
	public function userDb(): \MXWebMail\Repository\UserDatabase
	{
		if ($this->userDb === null) {
			$this->userDb = new \MXWebMail\Repository\UserDatabase($this->storageDir());
			$this->importLegacyContacts();
		}
		return $this->userDb;
	}

	/**
	 * Bascule unique : contacts de la base globale > fichiers par utilisateur.
	 * Le flag meta est posé après succès - un échec est retenté à la requête
	 * suivante (auto-réparant). Fonctionne sous SQLite et MariaDB.
	 */
	private function importLegacyContacts(): void
	{
		$db = $this->db();
		if ($db->meta('contacts_moved') === '1') {
			return;
		}

		$isSqlite = str_starts_with($db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME), 'sqlite');
		$has = $isSqlite
			? (bool) $db->pdo()->query(
				"SELECT 1 FROM sqlite_master WHERE type='table' AND name='contacts'"
			)->fetchColumn()
			: (bool) $db->pdo()->query("SHOW TABLES LIKE 'contacts'")->fetchColumn();

		if ($has) {
			// Seuls les utilisateurs existants sont copiés - les orphelins
			// éventuels sont purgés par le DROP final.
			$uids = $db->pdo()->query(
				'SELECT DISTINCT c.user_id FROM contacts c JOIN users u ON u.id = c.user_id'
			)->fetchAll(\PDO::FETCH_COLUMN);

			$sel = $db->pdo()->prepare('SELECT * FROM contacts WHERE user_id = :u');
			foreach ($uids as $uid) {
				$sel->execute(['u' => $uid]);
				$rows = $sel->fetchAll();
				if (!$rows) {
					continue;
				}
				$target = $this->userDb->for((int) $uid)->pdo();
				$target->beginTransaction();
				try {
					// INSERT OR IGNORE : un retry après échec partiel ne doit pas
					// violer les PK ni écraser des données plus récentes.
					$insC = $target->prepare(
						'INSERT OR IGNORE INTO contacts
						 (id, user_id, name, frequency, properties, source, source_uid)
						 VALUES (:id, :u, :n, :f, :p, :s, :su)'
					);
					$ids = [];
					foreach ($rows as $r) {
						$insC->execute([
							'id' => $r['id'], 'u' => $r['user_id'], 'n' => $r['name'],
							'f'  => $r['frequency'], 'p' => $r['properties'],
							's'  => $r['source'] ?? '', 'su' => $r['source_uid'] ?? null,
						]);
						$ids[] = (int) $r['id'];
					}
					$in = implode(',', $ids);
					$src = $db->pdo()->query(
						"SELECT * FROM contact_emails WHERE contact_id IN ($in)"
					);
					$insE = $target->prepare(
						'INSERT OR IGNORE INTO contact_emails (id, contact_id, email, type)
						 VALUES (:id, :c, :e, :t)'
					);
					foreach ($src->fetchAll() as $r) {
						$insE->execute([
							'id' => $r['id'], 'c' => $r['contact_id'],
							'e'  => $r['email'], 't' => $r['type'],
						]);
					}
					$target->commit();
				} catch (\Throwable $e) {
					$target->rollBack();
					throw $e;
				}
			}
			// Enfant avant parent (FK enforcement)
			$db->pdo()->exec('DROP TABLE IF EXISTS contact_emails');
			$db->pdo()->exec('DROP TABLE IF EXISTS contacts');
		}
		$db->setMeta('contacts_moved', '1');
	}

	private ?UserRepository $users = null;
	private ?DomainRepository $domains = null;
	private ?SettingRepository $settings = null;
	private ?PluginRepository $pluginRepo = null;
	private ?\MXWebMail\Repository\ThemeRepository $themeRepo = null;
	private ?\MXWebMail\Repository\PgpRepository $pgp = null;
	private ?\MXWebMail\Repository\OAuthAppRepository $oauthApps = null;
	private ?\MXWebMail\Repository\IdentityRepository $identities = null;

	public function users(): UserRepository
	{
		return $this->users ??= new UserRepository($this->db());
	}

	public function domains(): DomainRepository
	{
		return $this->domains ??= new DomainRepository($this->db());
	}

	public function settings(): SettingRepository
	{
		return $this->settings ??= new SettingRepository($this->db());
	}

	public function pluginRepo(): PluginRepository
	{
		return $this->pluginRepo ??= new PluginRepository($this->db());
	}

	public function themeRepo(): \MXWebMail\Repository\ThemeRepository
	{
		return $this->themeRepo ??= new \MXWebMail\Repository\ThemeRepository($this->db());
	}

	public function contacts(): \MXWebMail\Repository\ContactRepository
	{
		return new \MXWebMail\Repository\ContactRepository($this->userDb());
	}

	public function pgp(): \MXWebMail\Repository\PgpRepository
	{
		return $this->pgp ??= new \MXWebMail\Repository\PgpRepository($this->db());
	}

	public function oauthApps(): \MXWebMail\Repository\OAuthAppRepository
	{
		return $this->oauthApps ??= new \MXWebMail\Repository\OAuthAppRepository($this->db(), $this->storageDir());
	}

	public function identities(): \MXWebMail\Repository\IdentityRepository
	{
		return $this->identities ??= new \MXWebMail\Repository\IdentityRepository($this->db());
	}

	public function hooks(): HookRegistry
	{
		return $this->hooks ??= new HookRegistry();
	}

	// ─── Traduction ───

	private function localeDir(): string
	{
		return $this->rootDir . '/src/MXWebMail/Locale';
	}

	/** Langues installées = fichiers Locale/<lang>.php présents. */
	public function languages(): array
	{
		static $langs = null;
		return $langs ??= Translator::languages($this->localeDir());
	}

	/**
	 * Traducteur de la requête courante. Langue = config 'language' si ≠
	 * 'auto' et présente sur disque, sinon Accept-Language, sinon 'en'.
	 * Les dictionnaires sous app/plugins/<name>/langs et app/templates/<name>/langs
	 * sont fusionnés (activés ou non : l'admin affiche aussi les extensions
	 * désactivées et la config de chaque template).
	 */
	public function locale(): Translator
	{
		if ($this->locale === null) {
			$langs = $this->languages();
			$forced = strtolower(trim((string) $this->config->get('language', 'auto')));
			$lang = ($forced !== 'auto' && in_array($forced, $langs, true))
				? $forced
				: Translator::detect($langs);
			$this->locale = $this->translatorFor($lang);
		}
		return $this->locale;
	}

	/** Dossiers de dictionnaires : cœur + plugins + templates (mêmes globs que locale()). */
	private function localeDirs(): array
	{
		return array_merge(
			[$this->localeDir()],
			glob($this->rootDir . '/plugins/*/langs', GLOB_ONLYDIR) ?: [],
			glob($this->rootDir . '/templates/*/langs', GLOB_ONLYDIR) ?: []
		);
	}

	/**
	 * Traducteur d'une langue donnée (sert le dict via /assets/i18n/{lang}.js).
	 * Le cache i18n_{lang}.php est mutualisé avec locale() - même signature de sources.
	 */
	public function translatorFor(string $lang): Translator
	{
		return new Translator($lang, $this->localeDirs(),
			$this->storageDir() . '/cache/i18n_' . $lang . '.php');
	}

	/**
	 * $adminSurface = requête sur la surface admin (page + /api/admin/*) : les
	 * plugins "non utilisables par les utilisateurs" s'y chargent quand même,
	 * y compris avant login admin (hooks admin.post-login / admin.totp-required).
	 */
	public function plugins(bool $adminSurface = false): PluginManager
	{
		if ($this->plugins === null) {
			$this->plugins = new PluginManager($this, $this->hooks(), $this->pluginRepo());
			$this->plugins->loadEnabled($adminSurface);
		}
		return $this->plugins;
	}

	public function themes(): ThemeManager
	{
		return $this->themes ??= new ThemeManager($this);
	}

	public function auth(): AuthService
	{
		return new AuthService($this);
	}

	public function oauth(): \MXWebMail\Service\OAuthService
	{
		return new \MXWebMail\Service\OAuthService($this);
	}

	public function domainDetector(): \MXWebMail\Service\DomainDetector
	{
		return $this->domainDetector ??= new \MXWebMail\Service\DomainDetector($this);
	}

	private ?MailService $mail = null;

	/**
	 * MailService mémoïsé : une seule connexion IMAP/Sieve par requête.
	 * Avant : chaque appel instanciait un service neuf > Connect+Login par
	 * appel (jusqu'à 3-4 connexions par endpoint, ×2 par image cid:).
	 * Mutateurs de session mail (AuthService::switchAccount) doivent appeler
	 * mail()->disconnect() pour forcer la reconnexion au prochain accès.
	 */
	public function mail(): MailService
	{
		return $this->mail ??= new MailService($this);
	}

	public function updater(): \MXWebMail\Service\UpdateService
	{
		return $this->updater ??= new \MXWebMail\Service\UpdateService($this);
	}

	/**
	 * Copie de fichiers d'une mise à jour en cours (flag UpdateService).
	 * Expire après 15 min : un apply mort ne laisse pas l'app bloquée.
	 */
	public function isUpdating(): bool
	{
		$f = $this->storageDir() . '/update/applying';
		// filemtime suffit : le contenu posé par UpdateService == l'instant
		// d'écriture - évite une lecture de fichier par requête API.
		return is_file($f) && (int) @filemtime($f) > time() - 900;
	}

	public function github(): \MXWebMail\Service\GitHubClient
	{
		return $this->github ??= new \MXWebMail\Service\GitHubClient($this);
	}

	public function pluginPackages(): \MXWebMail\Service\PluginPackageService
	{
		return $this->pluginPackages ??= new \MXWebMail\Service\PluginPackageService($this);
	}

	// ─── Session ───

	public function startSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}
		session_name('mxwm_session');
		$lifetime = (int) $this->config->get('session_lifetime', 0) * 3600;
		// Plancher 8 h même à 0 ("cookie de session") : sinon le GC PHP
		// (défaut 1440 s) collecte une session idle > 24 min et l'utilisateur
		// est déconnecté alors que l'onglet est encore ouvert.
		ini_set('session.gc_maxlifetime', (string) max($lifetime, 28800));
		// Lax par défaut : le retour OAuth2 (navigation GET cross-site) doit
		// transporter le cookie de session. Strict le bloquerait ; None est
		// réservé aux intégrations cross-site explicites (exige HTTPS).
		$samesite = (string) $this->config->get('cookie_samesite', 'Lax');
		if (!in_array($samesite, ['Strict', 'Lax', 'None'], true)) {
			$samesite = 'Lax';
		}
		session_set_cookie_params([
			'lifetime' => $lifetime,
			'httponly' => true,
			'samesite' => $samesite,
			// None exige Secure (sinon le cookie est rejeté par le navigateur)
			'secure'   => $samesite === 'None' || $this->isHttps(),
			'path'     => '/',
		]);
		session_start();
	}

	public function isHttps(): bool
	{
		if (($_SERVER['HTTPS'] ?? '') === 'on'
			|| (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
			return true;
		}
		// X-Forwarded-Proto n'est fiable que depuis un proxy de confiance
		return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
			&& $this->isTrustedProxy();
	}

	/**
	 * URL absolue publique de l'instance : scheme + Host + basePath + $path.
	 * HTTP_HOST est client-contrôlé : même filtrage que la redirection
	 * force_https (nom/punycode, IPv6 littéral, port ≤ 65535).
	 */
	public function publicUrl(string $path): string
	{
		return ($this->isHttps() ? 'https' : 'http') . '://'
			. self::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''))
			. Request::basePath() . $path;
	}

	/**
	 * HTTP_HOST est client-contrôlé : validation partagée entre publicUrl et
	 * la redirection force_https (nom/punycode, IPv6 littéral […], port ≤
	 * 65535) - tout autre forme retombe sur 'localhost'.
	 */
	private static function normalizeHost(string $host): string
	{
		if (!preg_match('/^(\[[0-9a-fA-F:]+\]|[A-Za-z0-9.\-]+)(?::(\d{1,5}))?$/', $host, $m)) {
			return 'localhost';
		}
		if (('[' === $m[1][0]
				&& !filter_var(substr($m[1], 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
			|| (isset($m[2]) && (int) $m[2] > 65535)) {
			return 'localhost';
		}
		return $host;
	}

	/**
	 * IP cliente réelle : X-Forwarded-For accepté uniquement si REMOTE_ADDR
	 * figure dans trusted_proxies (on remonte la chaîne de droite à gauche
	 * en sautant les proxies de confiance).
	 */
	public function clientIp(): string
	{
		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		if ($this->isTrustedProxy()) {
			$xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
			foreach (array_reverse(array_map('trim', explode(',', $xff))) as $candidate) {
				if (filter_var($candidate, FILTER_VALIDATE_IP)
					&& !$this->inTrustedProxies($candidate)) {
					return $candidate;
				}
			}
		}
		return $ip;
	}

	private function isTrustedProxy(): bool
	{
		return $this->inTrustedProxies((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	}

	private function inTrustedProxies(string $ip): bool
	{
		$proxies = trim((string) $this->config->get('trusted_proxies', ''));
		return $proxies !== ''
			&& $ip !== ''
			&& \MXWebMail\Security\IpMatch::inList($ip, $proxies);
	}

	/**
	 * L'hôte résout-il vers une/des IP publiques ? Voir publicHostBlockReason().
	 */
	public function isPublicHost(string $host): bool
	{
		return $this->publicHostBlockReason($host) === null;
	}

	/**
	 * Pourquoi l'hôte n'est-il pas public ? null = OK. Bloque les littéraux
	 * IP ([10.0.0.5], [::1]) et les DNS privés/réservés - anti-SSRF pour
	 * toute requête sortante sur un hôte issu d'une saisie (plugins
	 * avatars, carddav, wkd…). Fail-closed : échec DNS ou une seule IP
	 * privée/réservée > refus. Raisons : 'literal' = littéral IP non
	 * public, 'dns' = rien à résoudre (hôte vide, NXDOMAIN, échec DNS),
	 * 'private' = résolution vers une IP privée/réservée.
	 */
	public function publicHostBlockReason(string $host): ?string
	{
		$h = trim($host, '[]');
		if ($h === '') {
			return 'dns';
		}
		if (filter_var($h, FILTER_VALIDATE_IP)) {
			return filter_var($h, FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
				? null : 'literal';
		}
		// A + AAAA : gethostbyname (IPv4 seul) refuserait un hôte IPv6-only.
		// @ : un SERVFAIL émettrait sinon "DNS Query failed" par requête.
		$ips = [];
		foreach (@dns_get_record($h, DNS_A + DNS_AAAA) ?: [] as $r) {
			if (isset($r['ip'])) {
				$ips[] = $r['ip'];
			}
			if (isset($r['ipv6'])) {
				$ips[] = $r['ipv6'];
			}
		}
		if (!$ips) {
			return 'dns';
		}
		foreach ($ips as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return 'private';
			}
		}
		return null;
	}

	private bool $sessionUserLoaded = false;
	private ?array $sessionUser = null;

	/** Ligne users de la session, mémoïsée (1 requête max par requête HTTP). */
	private function sessionUser(): ?array
	{
		if (!$this->sessionUserLoaded) {
			$this->sessionUserLoaded = true;
			$this->sessionUser = empty($_SESSION['user_id'])
				? null : $this->users()->findById((int) $_SESSION['user_id']);
		}
		return $this->sessionUser;
	}

	public function isLoggedIn(): bool
	{
		// Compte supprimé ou bloqué par l'admin : la session est rejetée
		$u = $this->sessionUser();
		return $u !== null && !(int) ($u['disabled'] ?? 0);
	}

	public function currentUser(): ?array
	{
		return $this->isLoggedIn() ? $this->sessionUser() : null;
	}

	/**
	 * Whitelist des clés de réglages utilisateur (user_settings) : clés de
	 * user_defaults + celles déclarées par les plugins via "settings.allowed".
	 * Les clés "plugin.*" restent gérées par l'API dédiée de chaque plugin.
	 */
	public function allowedUserSettingKeys(): array
	{
		$allowed = array_keys((array) $this->config->get('user_defaults', []));
		foreach ($this->hooks()->collect('settings.allowed') as $keys) {
			$allowed = array_merge($allowed, (array) $keys);
		}
		return array_filter($allowed,
			fn($k) => is_string($k) && !str_starts_with($k, 'plugin.'));
	}

	/**
	 * Règles de validation des clés user_defaults - partagées entre
	 * l'édition admin des défauts globaux (AdminController::settingsSet)
	 * et les écritures de réglages par utilisateur (settingsSet /
	 * userSettingsSet) pour qu'une valeur hors bornes ne soit jamais
	 * stockée, d'où qu'elle vienne.
	 */
	public function userSettingRules(): array
	{
		return [
			'messages_per_page' => 'int:5:200',
			'list_sort' => 'enum:REVERSE DATE,DATE,ARRIVAL,REVERSE ARRIVAL,FROM,REVERSE FROM,TO,REVERSE TO,CC,SUBJECT,REVERSE SUBJECT,SIZE,REVERSE SIZE',
			'view_images' => 'enum:ask,match,always',
			'msg_default_action' => 'enum:reply,reply_all',
			'mark_read_delay' => 'int:0:300',
			'compose_autosave' => 'int:0:300',
			'mdn_mode' => 'enum:ask,never',
			'autologout' => 'int:0:1440',
			'list_refresh' => 'int:0:60',
			'notify_new_mail' => 'bool',
			'contacts_autosave' => 'bool',
			'folder_pane_width' => 'int:140:600',
			'message_pane_width' => 'int:200:800',
			'layout' => 'enum:horizontal,vertical',
			'message_pane_height' => 'int:120:1200',
			'compose_modal_width' => 'int:0:3000',
			'compose_modal_height' => 'int:0:3000',
			'identity_modal_width' => 'int:0:3000',
			'identity_modal_height' => 'int:0:3000',
			'theme' => 'key',
			'empty_trash_on_logout' => 'bool',
			'empty_junk_on_logout'  => 'bool',
			'list_threads'          => 'bool',
			'after_delete'          => 'enum:next,prev,close',
			'keyboard_shortcuts'    => 'bool',
		];
	}

	/**
	 * Validation non fatale d'une valeur de réglage utilisateur.
	 * Retourne [accepté, valeur normalisée] :
	 * - null en entrée = suppression de la clé (toujours accepté) ;
	 * - theme : existence + autorisation (isSelectable, plus strict que 'key') ;
	 * - clé sans règle (plugins via "settings.allowed") : scalaire accepté ;
	 * - valeur non scalaire ou hors bornes : rejetée, jamais stockée.
	 */
	public function checkUserSetting(string $key, mixed $value): array
	{
		if ($value === null) {
			return [true, null];
		}
		if ($key === 'theme') {
			return $this->themes()->isSelectable((string) $value)
				? [true, (string) $value] : [false, null];
		}
		$rule = $this->userSettingRules()[$key] ?? null;
		if ($rule === null) {
			return [is_scalar($value), $value];
		}
		if ($rule === 'bool') {
			return [is_scalar($value), (bool) $value];
		}
		if (str_starts_with($rule, 'enum:')) {
			return is_string($value)
				&& in_array($value, explode(',', substr($rule, 5)), true)
				? [true, $value] : [false, null];
		}
		if (str_starts_with($rule, 'int:')) {
			[$min, $max] = array_map('intval', array_slice(explode(':', $rule), 1));
			$i = filter_var($value, FILTER_VALIDATE_INT);
			return ($i !== false && $i >= $min && $i <= $max) ? [true, $i] : [false, null];
		}
		return [false, null];
	}

	// ─── Dispatch ───

	/**
	 * Envoi unique d'une Response : applique la compression gzip configurable
	 * (section "http") puis émet. Vary est posé dès que le type est
	 * compressible - même sans compression effective. zlib.output_compression
	 * actif côté ini ou en-tête Content-Encoding déjà posé > rien à faire.
	 */
	private function sendResponse(Response $response, Request $request): void
	{
		$http = (array) $this->config->get('http', []);
		if (($http['gzip'] ?? true) && function_exists('gzencode')
			&& !filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)) {
			$accepted = (bool) preg_match('/\bgzip\b/i',
				(string) $request->header('Accept-Encoding'));
			$response->gzip(
				(int) ($http['gzip_level'] ?? 6),
				(int) ($http['gzip_min_size'] ?? 1024),
				$accepted
			);
		}
		$response->send();
	}

	public function run(): void
	{
		$request = Request::fromGlobals();
		$isApi = str_starts_with($request->path, '/api/');

		// Verrou d'installation absent > assistant d'installation (l'API répond
		// 503). /setup est servi par le SetupController intégré ; une fois le
		// verrou posé, /setup suit le routing normal et tombe sur un 404.
		$isSetup = $request->path === '/setup'
			|| str_starts_with($request->path, '/setup/');
		if (!is_file($this->storageDir() . '/INSTALLED')) {
			$response = match (true) {
				$isSetup && class_exists(SetupController::class)
					=> (new SetupController($this))->handle($request),
				// Classe absente (upload incomplet) : 503 - le bras "default"
				// redirigerait vers /setup/ en boucle.
				$isSetup => Response::html('<h1>503 - '
					. htmlspecialchars($this->locale()->t('errors.not_installed'), ENT_QUOTES, 'UTF-8')
					. '</h1>', 503),
				$isApi => \MXWebMail\Http\JsonResponse::error(
					$this->locale()->t('errors.not_installed'), 'not_installed', 503),
				default => Response::redirect(
					Request::basePath() . '/setup/'),
			};
			$this->sendResponse($response, $request);
			return;
		}

		// Redirection HTTPS permanente avant tout traitement
		if ($this->config->get('force_https', false) && !$this->isHttps()) {
			// HTTP_HOST est client-contrôlé : sans validation (normalizeHost),
			// un Host forgé se retrouve dans le Location du 301 (host header
			// injection).
			header('Location: https://'
				. self::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''))
				. ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
			return;
		}

		Headers::send(
			$isApi,
			(string) $this->config->get('csp_extra', ''),
			(bool) $this->config->get('hsts', false) && $this->isHttps()
		);

		// Anti-CSRF via en-têtes Sec-Fetch-* : rejette le cross-site, sauf
		// navigation GET vers un document (isEntering - y compris sans
		// Sec-Fetch-User : le navigateur le retire après chaque redirection,
		// cf. entrées via portail/liens réécrits). Les règles additionnelles
		// de 'secfetch_allow' étendent les combinaisons acceptées.
		// Exception : le retour OAuth2 est une navigation cross-site par
		// redirection provider (pas de Sec-Fetch-User) - sa protection réelle
		// est le state signé + PKCE, vérifiés dans accountOauthCallback.
		$oauthCallback = $request->method === 'GET'
			&& $request->path === '/api/oauth/callback';
		if (!$oauthCallback
			&& !\MXWebMailLib\HTTP\SecFetch::isEntering()
			&& !\MXWebMailLib\HTTP\SecFetch::matchAnyRule(
				(string) $this->config->get('secfetch_allow', ''))) {
			$this->sendResponse($isApi
				? \MXWebMail\Http\JsonResponse::error(
					$this->locale()->t('errors.cross_site'), 'secfetch', 403)
				: Response::html('<h1>403 - '
					. htmlspecialchars($this->locale()->t('errors.forbidden'), ENT_QUOTES, 'UTF-8')
					. '</h1>', 403), $request);
			return;
		}

		// Mise à jour en cours (flag posé par UpdateService::apply/rollback) :
		// bloque l'API non-admin - les fichiers sont remplacés pendant la copie.
		if ($isApi && $this->isUpdating()
			&& !str_starts_with($request->path, '/api/admin/')
			&& $request->path !== '/api/ping') {
			$this->sendResponse(\MXWebMail\Http\JsonResponse::error(
				$this->locale()->t('errors.updating'), 'updating', 503), $request);
			return;
		}

		// Mode maintenance : bloque l'API sauf admin et ping
		if ($isApi && $this->config->get('maintenance', false)
			&& !str_starts_with($request->path, '/api/admin/')
			&& $request->path !== '/api/ping') {
			$this->sendResponse(\MXWebMail\Http\JsonResponse::error(
				$this->locale()->t('errors.maintenance'), 'maintenance', 503), $request);
			return;
		}

		$this->startSession();
		// Jeton "se souvenir de moi" : restaure une session webmail
		// absente/expirée avant le dispatch (rotation du validator à l'usage).
		if (empty($_SESSION['user_id']) && !empty($_COOKIE['mxwm_remember'])) {
			try {
				$this->auth()->resumeFromRemember();
			} catch (\Throwable $e) {
				$this->log('warning', 'Remember-token: ' . $e->getMessage());
			}
		}
		// Sonde maintenance/update : réponse sans charger les plugins ni le
		// routeur - la page maintenance la frappe en boucle pendant la copie.
		if ($request->path === '/api/ping') {
			$this->sendResponse((new ApiController($this))->ping($request), $request);
			return;
		}

		// Charge les plugins activés : enregistre leurs hooks dans HookRegistry.
		// Surface admin = page /{admin_path} + /api/admin/* (cf. doc de plugins()).
		$this->plugins(
			str_starts_with($request->path, '/api/admin/')
			|| $request->path === '/' . $this->config->get('admin_path', 'admin')
		);

		$router = new Router();
		$this->registerRoutes($router);

		try {
			$response = $router->dispatch($request) ?? Response::notFound();
		} catch (\Throwable $e) {
			// Panne du serveur mail amont (socket coupé, hôte down, timeout) :
			// la chaîne est parcourue - les services enveloppent l'erreur
			// socket d'origine. > 503 + message dédié, pas "erreur interne".
			$net = null;
			for ($x = $e; $x !== null && $net === null; $x = $x->getPrevious()) {
				$net = match (true) {
					$x instanceof \MailSo\Net\Exceptions\ConnectionException,
					$x instanceof \MailSo\Net\Exceptions\SocketReadTimeoutException => 'errors.mail_unreachable',
					$x instanceof \MailSo\Net\Exceptions\SocketReadException,
					$x instanceof \MailSo\Net\Exceptions\SocketWriteException => 'errors.mail_conn_lost',
					default => null,
				};
			}
			$code = $e->getCode();
			$status = $net !== null ? 503
				: (($code >= 400 && $code < 600) ? $code : 500);
			if ($status >= 500) {
				$this->log('error', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
			}
			$message = match (true) {
				// Message déjà traduit et sûr (avec host éventuel) : prioritaire.
				$e instanceof \MXWebMail\UserFacingException => $e->getMessage(),
				$net !== null => $this->locale()->t($net),
				$status >= 500 && !$this->config->isDebug() => $this->locale()->t('errors.internal'),
				default => $e->getMessage(),
			};
			$response = $isApi
				? \MXWebMail\Http\JsonResponse::error($message, 'error', $status)
				: Response::html('<h1>' . $status . ' - '
					. htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>', $status);
		}

		$this->sendResponse($response, $request);
	}

	private function registerRoutes(Router $router): void
	{
		$pages = new PageController($this);
		$api = new ApiController($this);
		$admin = new AdminController($this);
		$apiFolders = new Api\FoldersController($this);
		$apiMessages = new Api\MessagesController($this);
		$apiCompose = new Api\ComposeController($this);
		$apiContacts = new Api\ContactsController($this);
		$apiSettings = new Api\SettingsController($this);
		$apiAccounts = new Api\AccountsController($this);
		$apiFilters = new Api\FiltersController($this);
		$apiPgp = new Api\PgpController($this);
		$admUsers = new Admin\UsersController($this);
		$admDomains = new Admin\DomainsController($this);
		$admPlugins = new Admin\PluginsController($this);
		$admThemes = new Admin\ThemesController($this);
		$admSystem = new Admin\SystemController($this);

		// ─── Pages ───
		$router->get('/', [$pages, 'home']);
		$router->get('/' . $this->config->get('admin_path', 'admin'), [$pages, 'admin']);
		$router->get('/favicon.ico', [$pages, 'favicon']);
		$router->get('/assets/plugin/{name}/{file}', [$pages, 'pluginAsset']);
		$router->get('/assets/theme/{name}/{file}', [$pages, 'themeAsset']);
		$router->get('/assets/i18n/{lang}.js', [$pages, 'i18nAsset']);

		// ─── API publique ───
		$router->post('/api/login', [$api, 'login']);
		$router->post('/api/logout', [$api, 'logout']);
		$router->get('/api/ping', [$api, 'ping']);
		$router->get('/api/appdata', [$api, 'appdata']);
		$router->get('/api/proxy', [$api, 'proxy']);

		// ─── API authentifiée ───
		$router->get('/api/folders', [$apiFolders, 'folders']);
		$router->post('/api/folders/create', [$apiFolders, 'folderCreate']);
		$router->post('/api/folders/{name}/delete', [$apiFolders, 'folderDelete']);
		$router->post('/api/folders/{name}/rename', [$apiFolders, 'folderRename']);
		$router->post('/api/folders/{name}/subscribe', [$apiFolders, 'folderSubscribe']);
		$router->post('/api/folders/{name}/clear', [$apiFolders, 'folderClear']);
		$router->post('/api/folders/{name}/mark-read', [$apiFolders, 'folderMarkRead']);
		$router->post('/api/folders/{name}/import', [$apiFolders, 'folderImport']);
		$router->get('/api/folders/{name}/eml.zip', [$apiFolders, 'emlZip']);
		$router->get('/api/folders/{name}/acl', [$apiFolders, 'folderAcl']);
		$router->post('/api/folders/{name}/acl', [$apiFolders, 'folderAclSet']);
		$router->post('/api/folders/{name}/acl/delete', [$apiFolders, 'folderAclDelete']);
		$router->get('/api/folders/special', [$apiFolders, 'foldersSpecialGet']);
		$router->post('/api/folders/special', [$apiFolders, 'foldersSpecialSet']);
		$router->get('/api/folders/notify', [$apiFolders, 'foldersNotifyGet']);
		$router->post('/api/folders/notify', [$apiFolders, 'foldersNotifySet']);
		$router->post('/api/folders/collapsed', [$apiFolders, 'foldersCollapsedSet']);
		$router->post('/api/folders/hidden', [$apiFolders, 'foldersHiddenSet']);

		$router->get('/api/messages', [$apiMessages, 'messages']);
		$router->get('/api/messages/{uid}', [$apiMessages, 'message']);
		$router->get('/api/messages/{uid}/raw', [$apiMessages, 'messageRaw']);
		$router->get('/api/messages/{uid}/attachment/{id}', [$apiMessages, 'attachment']);
		$router->get('/api/messages/{uid}/attachment/{id}/eml', [$apiMessages, 'attachmentEml']);
		$router->get('/api/messages/{uid}/attachment/{id}/eml/{part}', [$apiMessages, 'attachmentEmlPart']);
		// Batch multi-dossiers (sélection cochée) - avant les routes {uid}.
		$router->post('/api/messages/delete', [$apiMessages, 'messagesDelete']);
		$router->post('/api/messages/move', [$apiMessages, 'messagesMove']);
		$router->post('/api/messages/{uid}/flags', [$apiMessages, 'messageFlags']);
		$router->post('/api/messages/{uid}/move', [$apiMessages, 'messageMove']);
		$router->post('/api/messages/{uid}/copy', [$apiMessages, 'messageCopy']);
		$router->post('/api/messages/{uid}/delete', [$apiMessages, 'messageDelete']);
		$router->post('/api/messages/{uid}/spam', [$apiMessages, 'messageSpam']);
		$router->post('/api/messages/{uid}/unsubscribe', [$apiMessages, 'messageUnsubscribe']);
		$router->post('/api/messages/{uid}/mdn', [$apiMessages, 'messageMdnSend']);
		$router->post('/api/messages/{uid}/smime-verify', [$apiMessages, 'messageSmimeVerify']);
		$router->get('/api/messages/{uid}/attachments.zip', [$apiMessages, 'attachmentsZip']);
		$router->get('/api/responses', [$apiCompose, 'responses']);
		$router->post('/api/responses', [$apiCompose, 'responseSave']);
		$router->post('/api/responses/{id}/delete', [$apiCompose, 'responseDelete']);

		$router->post('/api/compose/send', [$apiCompose, 'composeSend']);
		$router->post('/api/compose/upload', [$apiCompose, 'composeUpload']);
		$router->post('/api/compose/upload/{token}/delete', [$apiCompose, 'composeUploadDelete']);
		$router->post('/api/compose/save-draft', [$apiCompose, 'composeSaveDraft']);

		$router->get('/api/contacts/group', [$apiContacts, 'contactsGroup']);
		$router->get('/api/contacts', [$apiContacts, 'contacts']);
		$router->post('/api/contacts', [$apiContacts, 'contactSave']);
		$router->post('/api/contacts/delete', [$apiContacts, 'contactDelete']);
		$router->post('/api/contacts/import', [$apiContacts, 'contactsImport']);
		$router->get('/api/contacts/export', [$apiContacts, 'contactsExport']);
		$router->post('/api/password/change', [$apiSettings, 'passwordChange']);
		$router->get('/api/sessions', [$apiSettings, 'sessions']);
		$router->post('/api/sessions/{id}/delete', [$apiSettings, 'sessionDelete']);
		$router->post('/api/sessions/revoke-others', [$apiSettings, 'sessionsRevokeOthers']);
		$router->get('/api/settings', [$apiSettings, 'settingsGet']);
		$router->post('/api/settings', [$apiSettings, 'settingsSet']);
		$router->get('/api/identities', [$apiSettings, 'identities']);
		$router->post('/api/identities', [$apiSettings, 'identitySave']);
		$router->post('/api/identities/delete', [$apiSettings, 'identityDelete']);
		$router->post('/api/identities/{id}/default', [$apiSettings, 'identityDefault']);

		// Comptes additionnels (+ flow OAuth2 : begin JSON, callback page ;
		// identifiants d'app par utilisateur : /api/oauth/apps)
		$router->get('/api/accounts', [$apiAccounts, 'accounts']);
		$router->post('/api/accounts', [$apiAccounts, 'accountAdd']);
		$router->post('/api/accounts/oauth', [$apiAccounts, 'accountOauthBegin']);
		$router->get('/api/oauth/callback', [$apiAccounts, 'accountOauthCallback']);
		$router->get('/api/oauth/apps', [$apiAccounts, 'oauthApps']);
		$router->post('/api/oauth/apps', [$apiAccounts, 'oauthAppSave']);
		$router->post('/api/oauth/apps/delete', [$apiAccounts, 'oauthAppDelete']);
		$router->post('/api/accounts/{id}/switch', [$apiAccounts, 'accountSwitch']);
		$router->post('/api/accounts/{id}/delete', [$apiAccounts, 'accountDelete']);
		$router->post('/api/accounts/{id}/default', [$apiAccounts, 'accountDefault']);
		$router->post('/api/accounts/order', [$apiAccounts, 'accountOrder']);

		// Filtres Sieve
		$router->get('/api/filters', [$apiFilters, 'filters']);
		$router->get('/api/filters/script', [$apiFilters, 'filterGet']);
		$router->post('/api/filters/script', [$apiFilters, 'filterSave']);
		$router->post('/api/filters/activate', [$apiFilters, 'filterActivate']);
		$router->post('/api/filters/deactivate', [$apiFilters, 'filterDeactivate']);
		$router->post('/api/filters/compile', [$apiFilters, 'filterCompile']);
		$router->get('/api/filters/vacation', [$apiFilters, 'vacationGet']);
		$router->post('/api/filters/vacation', [$apiFilters, 'vacationSave']);
		$router->post('/api/filters/delete', [$apiFilters, 'filterDelete']);
		// PGP - gestion du keyring (crypto côté navigateur, OpenPGP.js)
		$router->get('/api/pgp/keys', [$apiPgp, 'pgpKeys']);
		$router->post('/api/pgp/keys', [$apiPgp, 'pgpKeySave']);
		$router->post('/api/pgp/keys/{id}/delete', [$apiPgp, 'pgpKeyDelete']);
		$router->get('/api/pgp/keys/lookup', [$apiPgp, 'pgpKeysLookup']);

		$router->get('/api/quota', [$apiSettings, 'quota']);

		// API générique des plugins : /api/plugin/{name}/{action} > Plugin::api<Action>
		$router->get('/api/plugin/{name}/{action}', [$api, 'pluginCall']);
		$router->post('/api/plugin/{name}/{action}', [$api, 'pluginCall']);

		// ─── API admin ───
		$router->post('/api/admin/login', [$admin, 'login']);
		$router->post('/api/admin/logout', [$admin, 'logout']);
		$router->get('/api/admin/bootstrap', [$admSystem, 'bootstrap']);
		$router->get('/api/admin/users', [$admUsers, 'users']);
		$router->get('/api/admin/users/{id}', [$admUsers, 'userGet']);
		$router->post('/api/admin/users/{id}', [$admUsers, 'userSave']);
		$router->post('/api/admin/users/{id}/delete', [$admUsers, 'userDelete']);
		$router->post('/api/admin/users/{id}/settings', [$admUsers, 'userSettingsSet']);
		$router->post('/api/admin/users/{id}/settings/reset', [$admUsers, 'userSettingsReset']);
		$router->post('/api/admin/users/{id}/accounts/delete', [$admUsers, 'userAccountDelete']);
		$router->post('/api/admin/users/{id}/identities', [$admUsers, 'userIdentitySave']);
		$router->post('/api/admin/users/{id}/identities/delete', [$admUsers, 'userIdentityDelete']);
		$router->post('/api/admin/users/{id}/identities/default', [$admUsers, 'userIdentityDefault']);
		$router->get('/api/admin/domains', [$admDomains, 'domains']);
		$router->post('/api/admin/domains', [$admDomains, 'domainSave']);
		$router->post('/api/admin/domains/delete', [$admDomains, 'domainDelete']);
		$router->post('/api/admin/domains/detect', [$admDomains, 'domainDetect']);
		$router->get('/api/admin/plugins', [$admPlugins, 'plugins']);
		$router->get('/api/admin/plugins/catalog', [$admPlugins, 'pluginsCatalog']);
		$router->post('/api/admin/plugins/{name}/install', [$admPlugins, 'pluginInstall']);
		$router->post('/api/admin/plugins/{name}/remove', [$admPlugins, 'pluginRemove']);
		$router->post('/api/admin/plugins/{name}/toggle', [$admPlugins, 'pluginToggle']);
		$router->post('/api/admin/plugins/{name}/allowed', [$admPlugins, 'pluginAllowedSet']);
		$router->get('/api/admin/plugins/{name}/config', [$admPlugins, 'pluginConfigGet']);
		$router->post('/api/admin/plugins/{name}/config', [$admPlugins, 'pluginConfigSet']);
		// API générique des plugins côté admin : > Plugin::apiAdmin<Action>
		$router->get('/api/admin/plugin/{name}/{action}', [$admin, 'pluginCall']);
		$router->post('/api/admin/plugin/{name}/{action}', [$admin, 'pluginCall']);
		$router->get('/api/admin/themes', [$admThemes, 'themes']);
		$router->post('/api/admin/themes/{name}/default', [$admThemes, 'themeDefaultSet']);
		$router->post('/api/admin/themes/{name}/allowed', [$admThemes, 'themeAllowedSet']);
		$router->get('/api/admin/themes/{name}/config', [$admThemes, 'themeConfigGet']);
		$router->post('/api/admin/themes/{name}/config', [$admThemes, 'themeConfigSet']);
		$router->post('/api/admin/test-connexion', [$admSystem, 'testConnexion']);
		$router->post('/api/admin/test-db', [$admSystem, 'testDb']);
		$router->get('/api/admin/logs', [$admSystem, 'logs']);
		$router->post('/api/admin/logs/clear', [$admSystem, 'logsClear']);
		$router->get('/api/admin/settings', [$admSystem, 'settingsGet']);
		$router->post('/api/admin/settings', [$admSystem, 'settingsSet']);
		$router->post('/api/admin/password', [$admSystem, 'passwordSet']);
		$router->post('/api/admin/favicon', [$admSystem, 'faviconUpload']);
		$router->post('/api/admin/favicon/delete', [$admSystem, 'faviconDelete']);
		$router->get('/api/admin/about', [$admSystem, 'about']);
		$router->get('/api/admin/update/check', [$admSystem, 'updateCheck']);
		$router->get('/api/admin/update/status', [$admSystem, 'updateStatus']);
		$router->post('/api/admin/update/download', [$admSystem, 'updateDownload']);
		$router->post('/api/admin/update/apply', [$admSystem, 'updateApply']);
		$router->post('/api/admin/update/finalize', [$admSystem, 'updateFinalize']);
		$router->post('/api/admin/update/rollback', [$admSystem, 'updateRollback']);
		$router->post('/api/admin/update/reset', [$admSystem, 'updateReset']);

		// Les plugins activés enregistrent leurs routes publiques/dédiées
		// (manifest PWA, service worker…). Émis en dernier : leurs routes
		// passent après les routes cœur dans l'ordre de dispatch.
		$this->hooks()->run('app.routes', $router);
	}

	// ─── Logging ───

	public function log(string $level, string $message): void
	{
		$dir = $this->storageDir() . '/logs';
		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}
		self::rotateLog($dir . '/app.log', $this->logMaxSize(), $this->logKeep());
		$line = sprintf("[%s] [%s] %s\n",
			(new \DateTime('now', $this->logTimeZone()))->format('Y-m-d H:i:s'),
			strtoupper($level), $message);
		file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Journal d'authentification dédié (ex. pour fail2ban).
	 * Placeholders : {date:FORMAT} {request:ip} {imap:login} {result}
	 */
	public function logAuth(string $login, string $result): void
	{
		if (!$this->config->get('auth_logging', false)) {
			return;
		}
		$dir = $this->storageDir() . '/logs';
		is_dir($dir) || mkdir($dir, 0700, true);
		$format = (string) $this->config->get('auth_logging_format',
			'{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}');
		$now = new \DateTime('now', $this->logTimeZone());
		$line = (string) preg_replace_callback(
			'/\{date:([^}]*)\}/',
			fn(array $m) => $now->format($m[1] !== '' ? $m[1] : 'c'),
			$format
		);
		$line = strtr($line, [
			'{request:ip}' => $this->clientIp(),
			// CR/LF neutralisés : un login forgé pourrait injecter de fausses
			// lignes AUTH dans le fichier lu par fail2ban.
			'{imap:login}' => str_replace(["\r", "\n"], '', $login),
			'{result}'     => str_replace(["\r", "\n"], '', $result),
		]);
		$filename = basename((string) $this->config->get('auth_logging_filename', 'auth.log')) ?: 'auth.log';
		self::rotateLog($dir . '/' . $filename, $this->logMaxSize(), $this->logKeep());
		file_put_contents($dir . '/' . $filename, rtrim($line) . "\n", FILE_APPEND | LOCK_EX);
	}

	/**
	 * Rotation par taille : file.log > file.1.log > … > file.{keep}.log.
	 * Publique/statique : aussi appelée par MailSo\Log\Drivers\File (mail.log).
	 */
	public static function rotateLog(string $file, int $maxBytes, int $keep): void
	{
		if ($keep < 1 || !is_file($file) || (int) filesize($file) < $maxBytes) {
			return;
		}
		$rotated = static fn(int $i): string => str_ends_with($file, '.log')
			? substr($file, 0, -4) . '.' . $i . '.log' : $file . '.' . $i;
		@unlink($rotated($keep));
		for ($i = $keep - 1; $i >= 1; --$i) {
			is_file($rotated($i)) && @rename($rotated($i), $rotated($i + 1));
		}
		@rename($file, $rotated(1));
	}

	/** logs.max_size (Mio) > octets ; défaut 5 Mio, minimum 1. */
	private function logMaxSize(): int
	{
		$logs = $this->config->get('logs');
		return max(1, (int) (is_array($logs) ? ($logs['max_size'] ?? 5) : 5)) * 1048576;
	}

	/** logs.keep : rotations conservées ; défaut 3, plafond 20. */
	private function logKeep(): int
	{
		$logs = $this->config->get('logs');
		return min(20, max(0, (int) (is_array($logs) ? ($logs['keep'] ?? 3) : 3)));
	}

	/** Fuseau des horodatages de log (logs.time_zone) ; null = défaut serveur. */
	private function logTimeZone(): ?\DateTimeZone
	{
		if ($this->logTimeZone === null) {
			$logs = $this->config->get('logs');
			try {
				$this->logTimeZone = is_array($logs) && ($logs['time_zone'] ?? '') !== ''
					? new \DateTimeZone((string) $logs['time_zone']) : false;
			} catch (\Throwable) {
				$this->logTimeZone = false;
			}
		}
		return $this->logTimeZone ?: null;
	}
}
