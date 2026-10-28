<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Security\Csrf;
use MXWebMail\Security\TotpRequiredException;
use MXWebMail\Service\MailService;

/**
 * API JSON de l'application. Réponse uniforme {ok, data|error}.
 * Tout endpoint POST exige un token CSRF ; tout endpoint /api/* hors
 * login/ping exige une session utilisateur.
 */
final class ApiController
{
	/** Critères IMAP SORT acceptés en ?sort= sur /api/messages (RFC 5256). */
	private const SORT_CRITERIA = [
		'REVERSE DATE', 'DATE', 'ARRIVAL', 'REVERSE ARRIVAL',
		'FROM', 'REVERSE FROM', 'TO', 'REVERSE TO', 'CC',
		'SUBJECT', 'REVERSE SUBJECT', 'SIZE', 'REVERSE SIZE',
	];

	/** Types bitmap servis par /api/proxy — tout type actif (svg, xml, html) est refusé. */
	private const PROXY_IMAGE_TYPES = [
		'image/png', 'image/jpeg', 'image/gif', 'image/webp',
		'image/avif', 'image/bmp', 'image/x-icon', 'image/vnd.microsoft.icon',
	];

	/** Flags acceptés sur /api/messages/{uid}/flags (= enum IMAP complet). */
	private const ALLOWED_FLAGS = [
		\MailSo\Imap\Enumerations\MessageFlag::SEEN,
		\MailSo\Imap\Enumerations\MessageFlag::ANSWERED,
		\MailSo\Imap\Enumerations\MessageFlag::FLAGGED,
		\MailSo\Imap\Enumerations\MessageFlag::DELETED,
		\MailSo\Imap\Enumerations\MessageFlag::DRAFT,
		\MailSo\Imap\Enumerations\MessageFlag::MDNSENT,
		\MailSo\Imap\Enumerations\MessageFlag::IMPORTANT,
		\MailSo\Imap\Enumerations\MessageFlag::FORWARDED,
		\MailSo\Imap\Enumerations\MessageFlag::JUNK,
		\MailSo\Imap\Enumerations\MessageFlag::NOTJUNK,
		\MailSo\Imap\Enumerations\MessageFlag::PHISHING,
	];

	public function __construct(private App $app) {}

	// ─── Auth ───

	public function login(Request $request): JsonResponse
	{
		if (!Csrf::verifyRequest()) {
			return JsonResponse::error($this->t('errors.csrf'), 'csrf', 403);
		}
		try {
			$data = $this->app->auth()->login(
				(string) $request->input('email', ''),
				(string) $request->input('password', ''),
				$request->input('totp')
			);
			if ($request->input('remember') === true || $request->input('remember') === '1'
				|| $request->input('remember') === 1) {
				$this->app->auth()->issueRememberToken((int) $_SESSION['user_id']);
			}
			$this->app->logAuth((string) $request->input('email', ''), 'AUTH OK');
			return JsonResponse::ok($data);
		} catch (TotpRequiredException $e) {
			// Code stable 'totp_required' : le client affiche le champ TOTP
			// sans analyser le message (localisé). Un code saisi mais faux
			// compte comme tentative ratée, pas la demande initiale.
			if (trim((string) $request->input('totp', '')) !== '') {
				$this->app->auth()->recordFailedAttempt((string) $request->input('email', ''));
				$this->app->logAuth((string) $request->input('email', ''), 'AUTH FAILED (totp)');
			}
			return JsonResponse::error($this->t('errors.totp_required'), 'totp_required', 401);
		} catch (\Throwable $e) {
			// Un 429 = tentative déjà bloquée : ne pas l'enregistrer, sinon un
			// attaquant prolonge le verrou indéfiniment (lockout glissant).
			if ($e->getCode() !== 429) {
				$this->app->auth()->recordFailedAttempt((string) $request->input('email', ''));
			}
			$this->app->logAuth((string) $request->input('email', ''),
				'AUTH FAILED (' . $e->getCode() . ')');
			$this->app->log('warning', 'Login échoué: ' . $e->getMessage());
			// Les erreurs métier gardent leur message : 403 (white-list),
			// 429 (rate-limit)
			if (in_array($e->getCode(), [401, 403, 429], true)) {
				return JsonResponse::error($e->getMessage(), 'auth', $e->getCode());
			}
			return JsonResponse::error($this->t('errors.login_failed'), 'auth', 401);
		}
	}

	public function logout(Request $request): JsonResponse
	{
		// Vidage corbeille/spam à la déconnexion (réglages utilisateur) :
		// la session doit être encore ouverte pour la connexion IMAP — les
		// réglages sont lus et les dossiers purgés AVANT auth()->logout().
		// Jamais bloquant : un échec IMAP n'empêche pas la déconnexion.
		// Le jeton « se souvenir de moi » courant est révoqué à la déconnexion
		// (le cookie expire aussi côté client).
		if (!empty($_SESSION['user_id'])) {
			$sel = $this->app->auth()->currentTokenSelector();
			if ($sel !== null) {
				$this->app->db()->pdo()->prepare(
					'DELETE FROM auth_tokens WHERE selector = :s AND user_id = :u')
					->execute(['s' => $sel, 'u' => (int) $_SESSION['user_id']]);
			}
			$this->app->auth()->clearRememberCookie();
		}
		if (!empty($_SESSION['user_id'])
			&& $this->app->config()->get('dangerous_actions', true)) {
			$s = $this->userSettings((int) $_SESSION['user_id']);
			if (!empty($s['empty_trash_on_logout']) || !empty($s['empty_junk_on_logout'])) {
				try {
					$mail = $this->app->mail();
					if (!empty($s['empty_trash_on_logout'])
						&& ($t = $mail->trashFolder())) {
						$mail->folderClear($t);
					}
					if (!empty($s['empty_junk_on_logout'])
						&& ($j = $mail->junkFolder())) {
						$mail->folderClear($j);
					}
				} catch (\Throwable) {}
			}
		}
		$this->app->auth()->logout();
		return JsonResponse::ok();
	}

	public function ping(Request $request): JsonResponse
	{
		// 'maintenance' : sondé par la page maintenance (exemptée du blocage
		// /api/*) pour se recharger automatiquement à la fin de la maintenance.
		return JsonResponse::ok([
			'logged'      => $this->app->isLoggedIn(),
			// Mise à jour en cours incluse : la page maintenance sonde ce champ
			// pour se recharger — l'app revient dès que le flag tombe.
			'maintenance' => (bool) $this->app->config()->get('maintenance', false)
				|| $this->app->isUpdating(),
		]);
	}

	public function appdata(Request $request): JsonResponse
	{
		$this->requireAuth();
		$user = $this->app->currentUser();
		// Capacité Sieve du domaine courant : même ordre que
		// AuthService::resolveDomain mais sans autodetect (pas de réseau ici).
		$domainName = (string) ($_SESSION['domain'] ?? '');
		$domain = $this->app->domains()->findByName($domainName === '' ? '*' : $domainName)
			?? ($domainName !== '' && !$this->app->config()->get('restrict_domains', false)
				? $this->app->domains()->findByName('*') : null);
		$data = [
			'user'      => ['email' => $_SESSION['email'], 'display' => $user['display_name'] ?? ''],
			'appName'   => $this->app->config()->get('app_name'),
			'settings'  => $this->userSettings((int) $user['id']),
			'themes'    => $this->app->themes()->list(),
			'themeData' => $this->app->themes()->themeData(),
			'plugins'   => [],
			'csrf'      => Csrf::token(),
			'debug'     => $this->app->config()->isDebug(),
			// Feature flags lus par le frontend pour masquer les fonctions coupées
			'features'  => [
				'contacts'     => (bool) $this->app->config()->get('contacts_enable', true),
				'accounts'     => (bool) $this->app->config()->get('allow_additional_accounts', true),
				'identities'   => (bool) $this->app->config()->get('allow_additional_identities', true),
				'popup_identity' => (bool) $this->app->config()->get('popup_identity', false),
				'dangerous_actions' => (bool) $this->app->config()->get('dangerous_actions', true),
				'image_proxy' => (bool) $this->app->config()->get('image_proxy', true),
				'sieve'        => !empty($domain['sieve_host']),
				'attachments_max_total' => (int) $this->app->config()->get('attachments_max_total', 25),
				// Borne effective par fichier = min des deux limites (comme
				// composeUpload) pour le pré-contrôle client.
				'attachment_max_size' => min(
					(int) $this->app->config()->get('attachment_max_size', 25),
					(int) $this->app->config()->get('attachments_max_total', 25)),
			],
			'accounts'  => array_map(
				fn($a) => $a + ['active' => (int) $a['id'] === (int) ($_SESSION['account_id'] ?? 0)],
				$this->app->auth()->accounts((int) $user['id'])
			),
		];
		// Hook : les plugins injectent leurs données de boot
		$data = $this->app->hooks()->filter('app.data', $data, $user);
		return JsonResponse::ok($data);
	}

	// ─── Proxy images externes ───

	public function proxy(Request $request): Response
	{
		$this->requireAuth();
		if (!$this->app->config()->get('image_proxy', true)) {
			return Response::notFound();
		}
		$url = (string) $request->input('url', '');
		if (!$url || !preg_match('#^https?://#i', $url)) {
			return JsonResponse::error($this->t('errors.bad_url'), 'bad_url');
		}
		$allow = array_filter(array_map('trim',
			explode(',', (string) $this->app->config()->get('image_proxy_allowlist', ''))));
		// SSRF : l'allowlist et le blocage des IPs privées sont revérifiés sur
		// CHAQUE redirection — sinon une 302 vers une IP interne contourne le filtre.
		for ($hop = 0; $hop <= 3; $hop++) {
			$err = $this->proxyUrlError($url, $allow);
			if ($err === 'allowlist') {
				return JsonResponse::error($this->t('errors.host_denied'), 'ssrf', 403);
			}
			if ($err === 'ssrf') {
				return JsonResponse::error($this->t('errors.host_blocked'), 'ssrf', 403);
			}
			$ctx = stream_context_create(['http' => [
				'timeout' => 8, 'max_redirects' => 0, 'ignore_errors' => true,
				'user_agent' => 'MXWebMail-ImageProxy',
			]]);
			$body = @file_get_contents($url, false, $ctx, 0, 2 * 1024 * 1024);
			$status = 0;
			$location = null;
			foreach ($http_response_header ?? [] as $i => $h) {
				if ($i === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
					$status = (int) $m[1];
				} else if (stripos($h, 'Location:') === 0) {
					$location = trim(substr($h, 9));
				}
			}
			if ($status >= 300 && $status < 400 && $location && $hop < 3) {
				$url = $this->resolveRedirect($url, $location);
				if ($url && preg_match('#^https?://#i', $url)) {
					continue; // revalidation au prochain tour
				}
				return JsonResponse::error($this->t('errors.bad_url'), 'bad_url');
			}
			if ($body === false || $status >= 400) {
				$this->app->log('warning',
					"Proxy image fetch échoué url=" . self::proxyLogUrl($url) . " : "
					. (error_get_last()['message'] ?? "HTTP {$status}"));
				return Response::notFound();
			}
			break;
		}
		$mime = \MXWebMailLib\File\MimeType::fromString((string) $body) ?? 'application/octet-stream';
		// Allowlist bitmap uniquement : image/svg+xml (et tout type actif)
		// exécuterait du script same-origin en navigation directe.
		if (!in_array($mime, self::PROXY_IMAGE_TYPES, true)) {
			$this->app->log('warning',
				"Proxy image non-image url=" . self::proxyLogUrl($url) . " mime={$mime}");
			return JsonResponse::error($this->t('errors.not_image'), 'not_image', 403);
		}
		$http = (array) $this->app->config()->get('http', []);
		return new Response($body, 200, [
			'Content-Type'              => $mime,
			'Cache-Control'             => 'private, max-age='
				. max(0, (int) ($http['proxy_ttl'] ?? 86400)),
			'X-Content-Type-Options'    => 'nosniff',
			// Défense en profondeur : jamais de script si le corps est ouvert en direct.
			'Content-Security-Policy'   => 'sandbox',
		]);
	}

	/** URL tronquée pour les logs : hôte + chemin, sans query (tokens possibles). */
	private static function proxyLogUrl(string $url): string
	{
		$p = parse_url($url);
		return ($p['scheme'] ?? '?') . '://' . ($p['host'] ?? '?')
			. (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '/');
	}

	/** Allowlist optionnelle + blocage IP privée/réservée. Retourne null si OK. */
	private function proxyUrlError(string $url, array $allow): ?string
	{
		$host = parse_url($url, PHP_URL_HOST);
		if ($allow && !self::hostAllowed((string) $host, $allow)) {
			$this->app->log('warning', "Proxy image refusé (allowlist) host={$host}");
			return 'allowlist';
		}
		$ip = $host ? gethostbyname($host) : null;
		if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$this->app->log('warning',
				"Proxy image refusé (SSRF) host={$host} ip=" . ($ip ?: 'dns-ko'));
			return 'ssrf';
		}
		return null;
	}

	/**
	 * Résout un Location de redirection (RFC 7231 relative-ref) :
	 * absolu http(s), protocol-relative, chemin absolu, query, fragment
	 * ou relatif résolu contre le répertoire de la base (RFC 3986 §5.2.4).
	 * Toute autre forme est refusée. userinfo/pass de la base jamais propagés.
	 */
	private static function resolveRedirect(string $base, string $location): ?string
	{
		// Le fragment n'est jamais envoyé au serveur.
		$location = (string) preg_replace('/#.*$/', '', $location);
		if (preg_match('#^https?://#i', $location)) {
			return $location;
		}
		$p = parse_url($base);
		if (!$p || empty($p['scheme']) || empty($p['host'])) {
			return null;
		}
		$origin = $p['scheme'] . '://' . $p['host']
			. (isset($p['port']) ? ':' . $p['port'] : '');
		if (str_starts_with($location, '//')) {
			return $p['scheme'] . ':' . $location;
		}
		if (str_starts_with($location, '/')) {
			return $origin . $location;
		}
		$basePath = (string) ($p['path'] ?? '/');
		if (str_starts_with($location, '?')) {
			return $origin . ($basePath !== '' ? $basePath : '/') . $location;
		}
		if ('' === $location) {
			return $origin . ($basePath !== '' ? $basePath : '/')
				. (isset($p['query']) ? '?' . $p['query'] : '');
		}
		// Relatif : tout préfixe scheme non-http(s) est refusé (javascript:, data:…)
		if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $location)) {
			return null;
		}
		$query = '';
		if (false !== ($q = strpos($location, '?'))) {
			$query = substr($location, $q);
			$location = substr($location, 0, $q);
		}
		// Répertoire de la base sans dirname() — sous Windows dirname('/') = '\'.
		$path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $location;
		$segments = [];
		foreach (explode('/', $path) as $seg) {
			if ('..' === $seg) {
				array_pop($segments);
			} else if ('.' !== $seg && '' !== $seg) {
				$segments[] = $seg;
			}
		}
		return $origin . '/' . implode('/', $segments) . $query;
	}

	// ─── Dossiers ───

	public function folders(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->folders());
	}

	public function folderCreate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel — deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$before = array_column($mail->folders(), 'fullName');
		$mail->folderCreate(
			$this->noCrlf((string) $request->input('name'), 'name'),
			$this->noCrlf((string) $request->input('parent', ''), 'parent')
		);
		// Créé via l'interface → exempté du classement par nom usuel (diff
		// des fullNames : fiable quel que soit le délimiteur/l'encodage).
		$mail->markUserFolders(array_values(array_diff(
			array_column($mail->folders(), 'fullName'), $before)));
		return JsonResponse::ok();
	}

	public function folderDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireDangerous();
		$mail = $this->app->mail();
		$mail->folderDelete($this->requireNonSystemFolder($request));
		$mail->pruneUserFolders();
		return JsonResponse::ok();
	}

	public function folderRename(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$mail = $this->app->mail();
		$name = $this->requireNonSystemFolder($request);
		$delimiter = $this->folderDelimiter($mail, $name);
		$to = $this->noCrlf((string) $request->input('newName'), 'newName');
		$mail->folderRename($name, $to);
		$mail->renameUserFolder($name, $to, $delimiter);
		return JsonResponse::ok();
	}

	public function folderSubscribe(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderSubscribe($this->folderParam($request), (bool) $request->input('subscribe', true));
		return JsonResponse::ok();
	}

	public function folderClear(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireDangerous();
		$this->app->mail()->folderClear($this->requireNonSystemFolder($request, true));
		return JsonResponse::ok();
	}

	public function folderMarkRead(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Pas de requireNonSystemFolder : non destructif et utile sur INBOX/système.
		$this->app->mail()->folderMarkRead($this->folderParam($request));
		return JsonResponse::ok();
	}

	public function folderAcl(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->folderAcl($this->folderParam($request)));
	}

	public function folderAclSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderSetAcl(
			$this->requireNonSystemFolder($request),
			$this->noCrlf((string) $request->input('identifier'), 'identifier'),
			$this->noCrlf((string) $request->input('rights'), 'rights')
		);
		return JsonResponse::ok();
	}

	public function folderAclDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderDeleteAcl(
			$this->requireNonSystemFolder($request),
			$this->noCrlf((string) $request->input('identifier'), 'identifier')
		);
		return JsonResponse::ok();
	}

	/** Assignations des dossiers système du compte actif (auto par défaut). */
	public function foldersSpecialGet(Request $request): JsonResponse
	{
		$this->requireAuth();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel — deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$map = $mail->specialFolderMap();
		$out = [];
		foreach (MailService::SPECIAL_ROLES as $role) {
			// assigned = override stocké ('none'|fullName) ou 'auto' ;
			// detected = dossier de la seule détection — l'override
			// explicite ne doit pas se présenter comme le choix « auto ».
			$out[$role] = [
				'assigned' => $map[$role] ?? 'auto',
				'detected' => $mail->detectedSpecialFolder($role),
			];
		}
		return JsonResponse::ok($out);
	}

	public function foldersSpecialSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel — deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$existing = array_column($mail->folders(), 'fullName');
		$map = [];
		foreach ((array) $request->json() as $role => $value) {
			if (!in_array($role, MailService::SPECIAL_ROLES, true) || !is_string($value)) {
				continue;
			}
			if ($value === 'none' || in_array($value, $existing, true)) {
				$map[$role] = $value;
			}
			// 'auto' et valeurs invalides → ignorés (non stocké = auto)
		}
		$mail->setSpecialFolderMap($map);
		return JsonResponse::ok();
	}

	/** Dossiers surveillés pour les notifications (INBOX toujours incluse). */
	public function foldersNotifyGet(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->notifyFolderList());
	}

	public function foldersNotifySet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->setNotifyFolderList(
			array_map(fn($f) => $this->noCrlf((string) $f, 'folder'), (array) $request->input('folders', [])));
		return JsonResponse::ok();
	}

	// ─── Comptes additionnels ───

	public function accounts(Request $request): JsonResponse
	{
		$this->requireAuth();
		$uid = (int) $_SESSION['user_id'];
		return JsonResponse::ok(array_map(
			fn($a) => $a + ['active' => (int) $a['id'] === (int) ($_SESSION['account_id'] ?? 0)],
			$this->app->auth()->accounts($uid)
		));
	}

	public function accountAdd(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (!$this->app->config()->get('allow_additional_accounts', true)) {
			return JsonResponse::error($this->t('errors.accounts_off'), 'forbidden', 403);
		}
		$r = $this->app->auth()->addAccount(
			(int) $_SESSION['user_id'],
			(string) $request->input('email'),
			(string) $request->input('password')
		);
		return JsonResponse::ok($r);
	}

	/**
	 * Début du flow OAuth2 d'ajout de compte secondaire : l'email doit porter
	 * sur un domaine listé marqué options.oauth, sinon {url:null} — le client
	 * retombe alors sur l'ajout par mot de passe. Domaine OAuth sans app
	 * enregistrée par l'utilisateur → needs_app (le client demande les
	 * identifiants d'application puis retente).
	 */
	public function accountOauthBegin(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (!$this->app->config()->get('allow_additional_accounts', true)) {
			return JsonResponse::error($this->t('errors.accounts_off'), 'forbidden', 403);
		}
		$email = trim(mb_strtolower((string) $request->input('email', '')));
		$domainName = str_contains($email, '@')
			? substr($email, strrpos($email, '@') + 1) : '';
		$domain = $domainName !== '' ? $this->app->auth()->domainFor($domainName, true) : [];
		$provider = (string) (\MXWebMail\Service\AuthService::domainOptions($domain)['oauth'] ?? '');
		$oauth = $this->app->oauth();
		if (empty($domain['id']) || $provider === '') {
			return JsonResponse::ok(['url' => null]);
		}
		$app = $oauth->appFor((int) $_SESSION['user_id'], $provider);
		if ($app === null) {
			return JsonResponse::ok(['url' => null, 'provider' => $provider,
				'needs_app' => true, 'redirect_uri' => $oauth->redirectUri()]);
		}
		$_SESSION['oauth_pending'] = $oauth->pending($provider, $email);
		return JsonResponse::ok(
			['url' => $oauth->authorizeUrl($_SESSION['oauth_pending'], $app)]);
	}

	/**
	 * Callback du provider (GET navigateur, popup ou redirection pleine page).
	 * Pas de JSON : renvoie une mini-page qui postMessage le résultat à
	 * window.opener puis se ferme — repli meta-refresh vers /?oauth=<résultat>.
	 */
	public function accountOauthCallback(Request $request): Response
	{
		$pending = $_SESSION['oauth_pending'] ?? null;
		if (empty($_SESSION['user_id']) || !is_array($pending)) {
			unset($_SESSION['oauth_pending']);
			return Response::redirect(\MXWebMail\Http\Request::basePath() . '/');
		}
		try {
			$error = (string) $request->input('error', '');
			if ($error !== '') {
				return $this->oauthDone(false,
					$error === 'access_denied' ? 'denied' : 'failed');
			}
			$state = (string) $request->input('state', '');
			$code = (string) $request->input('code', '');
			if ($state === '' || $code === ''
				|| !hash_equals((string) $pending['state'], $state)) {
				return $this->oauthDone(false, 'state');
			}
			$app = $this->app->oauth()->appFor(
				(int) $_SESSION['user_id'], (string) $pending['provider']);
			if ($app === null) {
				return $this->oauthDone(false, 'failed');
			}
			$tokens = $this->app->oauth()->exchangeCode($pending, $code, $app);
			// Email affirmé par le provider (id_token) : s'il est présent il
			// doit égaler celui demandé — s'il est absent la connexion IMAP
			// XOAUTH2 de addAccountOauth fait la preuve d'appartenance.
			$providerEmail = (string) ($tokens['email'] ?? '');
			if ($providerEmail !== '' && $providerEmail !== $pending['email']) {
				return $this->oauthDone(false, 'email_mismatch');
			}
			$this->app->auth()->addAccountOauth(
				(int) $_SESSION['user_id'],
				(string) $pending['email'],
				(string) $tokens['access_token'],
				(string) $tokens['refresh_token'],
				(string) $pending['provider']);
			return $this->oauthDone(true);
		} catch (\Throwable $e) {
			$this->app->log('warning', 'OAuth callback : ' . $e->getMessage());
			return $this->oauthDone(false, 'failed');
		}
	}

	private function oauthDone(bool $ok, string $err = ''): Response
	{
		unset($_SESSION['oauth_pending']);
		$back = \MXWebMail\Http\Request::basePath() . '/?oauth=' . ($ok ? 'ok' : $err);
		$payload = json_encode(['type' => 'mxwm-oauth', 'ok' => $ok, 'err' => $err]);
		$msg = htmlspecialchars($ok ? $this->t('app.account_added') : $this->t('errors.oauth_failed'));
		return Response::html(
			'<!doctype html><html><head><meta charset="utf-8"><title>OAuth</title>'
			. '<meta http-equiv="refresh" content="4;url=' . htmlspecialchars($back, ENT_QUOTES) . '">'
			. '</head><body><p>' . $msg . '</p><script>try{'
			. 'if(window.opener){window.opener.postMessage(' . $payload . ',location.origin);window.close();}'
			. 'else{location.replace(' . json_encode($back) . ');}'
			. '}catch(e){location.replace(' . json_encode($back) . ');}</script></body></html>');
	}

	// ─── Applications OAuth2 (par utilisateur) ───

	/**
	 * Apps OAuth2 de l'utilisateur : liste sans secrets + providers autorisés
	 * (domaines flaggés options.oauth) + redirect URI à déclarer chez le provider.
	 */
	public function oauthApps(Request $request): JsonResponse
	{
		$this->requireAuth();
		$repo = $this->app->oauthApps();
		return JsonResponse::ok([
			'apps'         => $repo->allFor((int) $_SESSION['user_id']),
			'providers'    => $repo->allowedProviders(),
			'redirect_uri' => $this->app->oauth()->redirectUri(),
		]);
	}

	/**
	 * Enregistre/met à jour l'app d'un provider. Le provider doit être
	 * autorisé par au moins un domaine (options.oauth) — une app inutilisable
	 * n'a pas à être stockée. Secret obligatoire à la création (flow « web
	 * app » exigé par les 3 providers), vide = inchangé à l'édition.
	 */
	public function oauthAppSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$repo = $this->app->oauthApps();
		$provider = (string) $request->input('provider', '');
		if (!in_array($provider, \MXWebMail\Service\OAuthService::providers(), true)
			|| !in_array($provider, $repo->allowedProviders(), true)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'provider']), 'validation', 400);
		}
		$uid = (int) $_SESSION['user_id'];
		$clientId = $this->noCrlf(
			trim((string) $request->input('client_id', '')), 'client_id');
		$secret = $this->noCrlf(trim((string) $request->input('secret', '')), 'secret');
		$tenant = $this->noCrlf(trim((string) $request->input('tenant', '')), 'tenant');
		$exists = $repo->appFor($uid, $provider) !== null;
		if ($clientId === '' || strlen($clientId) > 255 || strlen($secret) > 255
			|| (!$exists && $secret === '')
			|| ($tenant !== '' && !preg_match('/^[A-Za-z0-9.\-]+$/', $tenant))) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'client_id']), 'validation', 400);
		}
		$repo->save($uid, $provider, $clientId,
			$secret !== '' ? $secret : null,
			$provider === 'microsoft' ? $tenant : '');
		return JsonResponse::ok();
	}

	public function oauthAppDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->oauthApps()->delete(
			(int) $_SESSION['user_id'], (string) $request->input('provider', ''));
		return JsonResponse::ok();
	}

	public function accountSwitch(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		return JsonResponse::ok(
			$this->app->auth()->switchAccount((int) $_SESSION['user_id'], (int) $request->params['id'])
		);
	}

	public function accountDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->removeAccount((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	public function accountDefault(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->setDefaultAccount(
			(int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	// ─── Filtres Sieve ───

	public function filters(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->filtersList());
	}

	public function filterGet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$name = $this->noCrlf((string) $request->input('name'), 'name');
		return JsonResponse::ok([
			'name'   => $name,
			'source' => $this->app->mail()->filterGet($name),
		]);
	}

	public function filterSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Deux formats : {name, source} (mode source) ou {name, rules}
		// (assistant — compilation serveur via ScriptBuilder ; rules =
		// doc complet ou simple tableau de règles).
		if (is_array($rules = $request->input('rules'))) {
			try {
				$doc = array_is_list($rules) ? ['rules' => $rules] : $rules;
				// Réinjecte le bloc « vacation » du script existant : l'assistant
				// n'édite que les règles — sans merge, la sauvegarde d'un filtre
				// éteindrait un répondeur actif.
				$existing = $this->noCrlf((string) ($request->input('old_name') ?: $request->input('name', '')), 'name');
				if ($existing !== '') {
					try {
						$prev = \MXWebMail\Sieve\ScriptBuilder::parseMarker(
							$this->app->mail()->filterGet($existing));
						if (isset($prev['vacation'])) {
							$doc['vacation'] = $prev['vacation'];
						}
					} catch (\Throwable) {}
				}
				$source = \MXWebMail\Sieve\ScriptBuilder::compile($doc);
			} catch (\InvalidArgumentException $e) {
				return JsonResponse::error(
					$this->t('errors.invalid_value', ['key' => 'rules'])
						. ' — ' . $e->getMessage(), 'validation', 400);
			}
		} else {
			$source = (string) $request->input('source', '');
			if (trim($source) === '') {
				return JsonResponse::error($this->t('errors.empty_script'), 'empty');
			}
		}
		$name = $this->noCrlf((string) $request->input('name'), 'name');
		if (trim($name) === '') {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$oldName = $this->noCrlf((string) $request->input('old_name', ''), 'old_name');
		// Vérif syntaxique côté serveur Sieve avant PUT
		$this->app->mail()->filterCheck($source);
		$this->app->mail()->filterSave($name, $source, $oldName !== '' ? $oldName : null);
		return JsonResponse::ok();
	}

	/** Prévisualisation de la source générée par l'assistant (rien d'écrit). */
	public function filterCompile(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		try {
			$rules = (array) $request->input('rules', []);
			$source = \MXWebMail\Sieve\ScriptBuilder::compile(
				array_is_list($rules) ? ['rules' => $rules] : $rules);
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'rules'])
					. ' — ' . $e->getMessage(), 'validation', 400);
		}
		return JsonResponse::ok(['source' => $source]);
	}

	public function filterActivate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterActivate($this->noCrlf((string) $request->input('name'), 'name'));
		return JsonResponse::ok();
	}

	public function filterDeactivate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterDeactivate();
		return JsonResponse::ok();
	}

	public function filterDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterDelete($this->noCrlf((string) $request->input('name'), 'name'));
		return JsonResponse::ok();
	}

	// ─── Répondeur d'absence (Sieve vacation) ───

	public function vacationGet(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->vacationGet());
	}

	public function vacationSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$v = (array) $request->json();
		$from = trim((string) ($v['from'] ?? ''));
		$to = trim((string) ($v['to'] ?? ''));
		$days = filter_var($v['days'] ?? 1, FILTER_VALIDATE_INT);
		$subject = $this->noCrlf(trim((string) ($v['subject'] ?? '')), 'subject');
		$message = (string) ($v['message'] ?? '');
		$enabled = (bool) ($v['enabled'] ?? false);
		$addresses = array_values(array_filter(array_map('trim',
			(array) ($v['addresses'] ?? []))));
		if ($days === false || $days < 1 || $days > 30 || strlen($subject) > 200
			|| strlen($message) > 8192) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		foreach ([$from, $to] as $d) {
			if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
			}
		}
		if ($from !== '' && $to !== '' && $from > $to) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		if ($enabled && trim($message) === '') {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		// :addresses ⊆ adresses du compte + identités déclarées (sinon le
		// répondeur partirait pour des courriers adressés à d'autres).
		$allowed = [mb_strtolower((string) $_SESSION['email'])];
		foreach ($this->app->identities()->allForUser((int) $_SESSION['user_id']) as $i) {
			$allowed[] = mb_strtolower((string) $i['email']);
		}
		$addresses = array_values(array_intersect(
			array_map('mb_strtolower', $addresses), array_unique($allowed)));
		if (!$addresses) {
			$addresses = [mb_strtolower((string) $_SESSION['email'])];
		}
		$this->app->mail()->vacationSave([
			'enabled' => $enabled, 'subject' => $subject, 'message' => $message,
			'days' => $days, 'from' => $from, 'to' => $to, 'addresses' => $addresses,
		]);
		return JsonResponse::ok();
	}

	// ─── Réponses types (stockage user_settings, JSON) ───

	private function cannedResponses(int $uid): array
	{
		$raw = $this->app->settings()->get($uid, 'canned_responses', []);
		return is_array($raw) ? array_values($raw)
			: (json_decode((string) $raw, true) ?: []);
	}

	public function responses(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->cannedResponses((int) $_SESSION['user_id']));
	}

	public function responseSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$uid = (int) $_SESSION['user_id'];
		$data = (array) $request->json();
		$id = (int) ($data['id'] ?? 0);
		$name = trim((string) ($data['name'] ?? ''));
		$body = (string) ($data['body'] ?? '');
		if ($name === '' || mb_strlen($name) > 100 || trim($body) === ''
			|| strlen($body) > 65536) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$list = $this->cannedResponses($uid);
		foreach ($list as $r) {
			if (strcasecmp((string) $r['name'], $name) === 0 && (int) $r['id'] !== $id) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'duplicate', 400);
			}
		}
		if ($id) {
			$found = false;
			foreach ($list as &$r) {
				if ((int) $r['id'] === $id) {
					$r = ['id' => $id, 'name' => $name, 'body' => $body];
					$found = true;
					break;
				}
			}
			unset($r);
			if (!$found) {
				return JsonResponse::error($this->t('errors.not_found'), 'not_found', 404);
			}
		} else {
			if (count($list) >= 50) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'limit', 400);
			}
			$id = max([0, ...array_map(fn($r) => (int) $r['id'], $list)]) + 1;
			$list[] = ['id' => $id, 'name' => $name, 'body' => $body];
		}
		$this->app->settings()->set($uid, 'canned_responses', array_values($list));
		return JsonResponse::ok(['id' => $id]);
	}

	public function responseDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$uid = (int) $_SESSION['user_id'];
		$id = (int) $request->params['id'];
		$list = array_values(array_filter($this->cannedResponses($uid),
			fn($r) => (int) $r['id'] !== $id));
		$this->app->settings()->set($uid, 'canned_responses', $list);
		return JsonResponse::ok();
	}

	// ─── Messages ───

	public function messages(Request $request): JsonResponse
	{
		$this->requireAuth();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$page = max(1, (int) $request->input('page', 1));
		$limit = min(200, max(1, (int) $request->input('limit', 50)));
		$search = $this->noCrlf((string) $request->input('search', ''), 'search');
		// Critère IMAP SORT (RFC 5256) — whitelist stricte : la valeur part
		// telle quelle dans la commande UID SORT.
		$sort = (string) $request->input('sort', '');
		if (!in_array($sort, self::SORT_CRITERIA, true)) {
			$sort = 'REVERSE DATE';
		}
		$threads = (bool) $request->input('threads', false);
		$threadUid = (int) $request->input('thread_uid', 0);
		// folder=* : recherche multi-dossiers (fusion triée date desc).
		$data = $folder === '*'
			? $this->app->mail()->searchAllFolders($search, $limit)
			: $this->app->mail()->messages($folder, ($page - 1) * $limit, $limit, $search, $sort, $threads, $threadUid);
		$data['page'] = $page;
		$data['limit'] = $limit;
		return JsonResponse::ok($data);
	}

	public function message(Request $request): JsonResponse
	{
		$this->requireAuth();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$msg = $this->app->mail()->message($folder, (int) $request->params['uid']);
		if (!$msg) {
			return JsonResponse::error($this->t('errors.not_found'), 'not_found', 404);
		}
		// Marquer comme lu : le fetch IMAP est en BODY_PEEK (flag non posé),
		// on force \Seen côté métier — sauf si un délai est configuré
		// (mark_read_delay) : le client pose alors le flag via /flags.
		$delay = (int) ($this->userSettings((int) $_SESSION['user_id'])['mark_read_delay'] ?? 0);
		if (!$msg['isSeen'] && $delay <= 0) {
			try { $this->app->mail()->flags($folder, (int) $request->params['uid'], '\Seen', true); } catch (\Throwable) {}
		}
		return JsonResponse::ok($msg);
	}

	/** Envoie l'accusé de réception (RFC 3798, action manuelle) puis pose
	 *  le mot-clé $MDNSent sur le message source. */
	public function messageMdnSend(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$folder = $this->folderParam($request);
		$uid = (int) $request->params['uid'];
		$msg = $this->app->mail()->message($folder, $uid);
		if (empty($msg['dispositionTo'])) {
			return JsonResponse::error($this->t('errors.no_mdn'), 'no_mdn', 400);
		}
		if ($msg['mdnSent']) {
			return JsonResponse::ok(); // idempotent : accusé déjà parti
		}
		$this->app->mail()->send($this->buildMdnReport($msg));
		try {
			$this->app->mail()->flags($folder, $uid, '$MDNSent', true);
		} catch (\Throwable) {} // l'accusé est parti ; le flag est best-effort
		return JsonResponse::ok();
	}

	/** multipart/report; report-type=disposition-notification (RFC 3798 §3). */
	private function buildMdnReport(array $msg): \MailSo\Mime\Message
	{
		$m = new \MailSo\Mime\Message();
		$m->DoesNotAddDefaultXMailer();
		$m->SetFrom(\MailSo\Mime\Email::Parse((string) $_SESSION['email']));
		$rcpt = new \MailSo\Mime\EmailCollection();
		foreach (preg_split('/[,;]/', (string) $msg['dispositionTo']) as $a) {
			$a = trim($a);
			if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
				$rcpt->append(new \MailSo\Mime\Email($a));
			}
		}
		if (!$rcpt->count()) {
			throw new \RuntimeException($this->t('errors.no_mdn'), 400);
		}
		$m->SetTo($rcpt);
		$m->SetSubject($this->t('app.mdn_subject', ['s' => (string) ($msg['subject'] ?? '')]));
		$m->SetInReplyTo(str_replace(["\r", "\n"], '', (string) ($msg['messageId'] ?? '')));
		$m->RegenerateMessageId($_SESSION['domain'] ?? '');
		$m->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'multipart/report; report-type=disposition-notification');

		$text = new \MailSo\Mime\Part();
		$text->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'text/plain; charset=utf-8');
		$text->Headers->AddByName(
			\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING, 'quoted-printable');
		$text->Body = quoted_printable_encode($this->t('app.mdn_body'));

		$report = new \MailSo\Mime\Part();
		$report->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'message/disposition-notification');
		$report->Body = "Reporting-UA: mxwebmail\r\n"
			. 'Final-Recipient: rfc822;' . str_replace(["\r", "\n"], '', (string) $_SESSION['email']) . "\r\n"
			. 'Original-Message-ID: ' . str_replace(["\r", "\n"], '', (string) ($msg['messageId'] ?? '')) . "\r\n"
			. "Disposition: manual-action/MDN-sent-manually; displayed\r\n";

		$m->SubParts->append($text);
		$m->SubParts->append($report);
		return $m;
	}

	/**
	 * Vérifie la signature S/MIME du message : le .eml brut complet est passé
	 * à openssl_pkcs7_verify (multipart/signed et p7m opaque traités
	 * nativement). Sans CA store (smime_ca_path) la signature crypto reste
	 * vérifiable → {valid, trusted:false}.
	 */
	public function messageSmimeVerify(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$folder = $this->folderParam($request);
		$uid = (int) $request->params['uid'];
		$msg = $this->app->mail()->message($folder, $uid);
		if (empty($msg['smimeSigned'])) {
			return JsonResponse::error($this->t('errors.not_signed'), 'not_signed', 400);
		}
		if (!function_exists('openssl_pkcs7_verify')) {
			return JsonResponse::error($this->t('errors.no_openssl'), 'no_openssl', 501);
		}
		$tmpDir = $this->app->storageDir() . '/tmp';
		is_dir($tmpDir) || mkdir($tmpDir, 0700, true);
		$eml = tempnam($tmpDir, 'smime_') . '.eml';
		$signers = tempnam($tmpDir, 'signer_') . '.pem';
		$out = null;
		try {
			$fh = fopen($eml, 'wb');
			$this->app->mail()->mimeStream($folder, $uid, '',
				function ($r) use ($fh) { stream_copy_to_stream($r, $fh); });
			fclose($fh);
			$caPath = trim((string) $this->app->config()->get('smime_ca_path', ''));
			$flags = $caPath !== '' ? 0 : PKCS7_NOVERIFY;
			$ca = $caPath !== '' ? [$caPath] : [];
			$ok = openssl_pkcs7_verify($eml, $flags, $signers, $ca);
			$out = ['valid' => $ok === true, 'trusted' => $caPath !== '', 'signer' => null];
			if ($ok === true && is_file($signers)) {
				$pem = (string) file_get_contents($signers);
				if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
					$x = openssl_x509_parse($m[0]);
					$out['signer'] = is_array($x) ? [
						'email'  => $x['subject']['emailAddress'] ?? null,
						'cn'     => $x['subject']['CN'] ?? null,
						'issuer' => $x['issuer']['O'] ?? ($x['issuer']['CN'] ?? null),
					] : null;
				}
			}
			if ($ok === false) {
				$out['error'] = trim((string) openssl_error_string()) ?: null;
			}
		} finally {
			@unlink($eml);
			@unlink($signers);
		}
		return JsonResponse::ok($out);
	}

	public function messageRaw(Request $request): Response
	{
		$this->requireAuth();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$uid = (int) $request->params['uid'];
		header('Content-Type: message/rfc822');
		header("Content-Disposition: attachment; filename=\"message-{$uid}.eml\"");
		$ok = $this->app->mail()->mimeStream($folder, $uid, '', function ($r) {
			\MailSo\Base\Utils::FpassthruWithTimeLimitReset($r);
		});
		if (!$ok) {
			return Response::notFound();
		}
		return new Response('', 200); // body déjà streamé
	}

	public function attachment(Request $request): Response
	{
		$this->requireAuth();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$uid = (int) $request->params['uid'];
		$mimeIndex = (string) $request->params['id'];
		$download = (bool) $request->input('download', true);

		$msg = $this->app->mail()->message($folder, $uid);
		$meta = null;
		foreach ($msg['attachments'] ?? [] as $att) {
			if ($att['mimeIndex'] === $mimeIndex) { $meta = $att; break; }
		}
		if (!$meta) {
			$this->app->log('warning', "Attachment 404 uid={$uid} folder={$folder} "
				. "mimeIndex={$mimeIndex} dispo=["
				. implode(',', array_column($msg['attachments'] ?? [], 'mimeIndex')) . ']');
			return Response::notFound();
		}
		$disposition = $download ? 'attachment' : 'inline';
		header('Content-Type: ' . $meta['mime']);
		$filename = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $meta['name']);
		header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
		// Contenu utilisateur servi sous notre origine (images cid inline) :
		// sandbox CSP = jamais de script même si la PJ est du HTML ouvert en direct.
		// Exception PDF : les viewers natifs (pdfium) n'exécutent pas le JS
		// embarqué mais sont neutralisés par la sandbox — sans elle, l'aperçu
		// en iframe fonctionne (le type bitmap applicatif reste inerte).
		if (strtolower((string) $meta['mime']) !== 'application/pdf') {
			header('Content-Security-Policy: sandbox');
		}
		header('X-Content-Type-Options: nosniff');
		$ok = $this->app->mail()->mimeStream($folder, $uid, $mimeIndex, function ($r) {
			\MailSo\Base\Utils::FpassthruWithTimeLimitReset($r);
		});
		if (!$ok) {
			$this->app->log('warning',
				"Attachment mimeStream échoué uid={$uid} folder={$folder} mimeIndex={$mimeIndex}");
		}
		return new Response('', $ok ? 200 : 404);
	}

	public function messageFlags(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Whitelist stricte : le flag part tel quel dans la commande
		// UID STORE — un flag libre permettrait l'injection IMAP.
		$flag = (string) $request->input('flag');
		if (!in_array($flag, self::ALLOWED_FLAGS, true)) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'flag']), 'invalid_value', 400);
		}
		$this->app->mail()->flags(
			$this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder'),
			(int) $request->params['uid'],
			$flag,
			(bool) $request->input('set', true)
		);
		return JsonResponse::ok();
	}

	public function messageSpam(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$result = $this->app->mail()->markSpam(
			$this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder'),
			(int) $request->params['uid'],
			(bool) $request->input('spam', true)
		);
		return JsonResponse::ok($result);
	}

	public function messageMove(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$mail = $this->app->mail();
		$to = $this->noCrlf((string) $request->input('to'), 'to');
		// dangerous_actions=false : déplacer vers la corbeille équivaut à
		// supprimer — bloqué comme messageDelete. La copie et le déplacement
		// hors corbeille (récupération) restent autorisés.
		if ($to === $mail->trashFolder()) {
			$this->requireDangerous();
		}
		$mail->move(
			$this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder'),
			$to,
			(int) $request->params['uid']
		);
		return JsonResponse::ok();
	}

	public function messageCopy(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->copy(
			$this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder'),
			$this->noCrlf((string) $request->input('to'), 'to'),
			(int) $request->params['uid']
		);
		return JsonResponse::ok();
	}

	public function messageDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireDangerous();
		return JsonResponse::ok($this->app->mail()->delete(
			$this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder'),
			(int) $request->params['uid']
		));
	}

	/** Import dans un dossier (IMAP APPEND) : .eml (1 message) ou .mbox
	 *  (détection par contenu : 1ʳᵉ ligne « From » — extension non fiable). */
	public function folderImport(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$mail = $this->app->mail();
		$folder = $this->folderParam($request);
		if (!in_array($folder, array_column($mail->folders(), 'fullName'), true)) {
			return JsonResponse::error($this->t('errors.not_found'), 'not_found', 404);
		}
		$f = $request->files['file'] ?? null;
		if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		$maxSize = (int) $this->app->config()->get('attachment_max_size', 25) * 1024 * 1024;
		$isMbox = str_starts_with((string) file_get_contents((string) $f['tmp_name'], false, null, 0, 5), 'From ');
		if ($isMbox) {
			$maxSize = max($maxSize,
				(int) $this->app->config()->get('import_max_size', 100) * 1024 * 1024);
		}
		if ((int) ($f['size'] ?? 0) > $maxSize) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => (int) ($maxSize / 1048576)]), 'too_big', 400);
		}
		if ($isMbox) {
			return JsonResponse::ok($this->importMbox($mail, (string) $f['tmp_name'], $folder));
		}
		$raw = file_get_contents((string) $f['tmp_name']);
		if ($raw === false || trim($raw) === '') {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		return JsonResponse::ok(['uid' => $mail->appendMessage($raw, $folder, ['\\Seen'])]);
	}

	/**
	 * Découpe un fichier mbox et APPEND chaque message (lecture en stream —
	 * pas de chargement complet d'un gros fichier en mémoire).
	 * Séparateur : ligne « From » ; « >From » est dés-échappé. La date de la
	 * ligne « From » devient l'INTERNALDATE du message quand elle est parsable.
	 * @return array{imported:int, failed:int}
	 */
	private function importMbox(\MXWebMail\Service\MailService $mail, string $path, string $folder): array
	{
		$handle = fopen($path, 'rb');
		if (!is_resource($handle)) {
			throw new \RuntimeException($this->t('errors.bad_upload'), 400);
		}
		$imported = 0; $failed = 0;
		$msg = ''; $date = 0;
		$flush = function () use (&$msg, &$date, &$imported, &$failed, $mail, $folder) {
			if (trim($msg) !== '') {
				try {
					$mail->appendMessage($msg, $folder, ['\\Seen'], $date);
					$imported++;
				} catch (\Throwable) { $failed++; }
			}
			$msg = ''; $date = 0;
		};
		while (($line = fgets($handle)) !== false) {
			if ($imported + $failed >= 2000) {
				$failed++; // plafond de sécurité
				break;
			}
			if (str_starts_with($line, 'From ')) {
				$flush();
				$ts = strtotime(substr($line, 5));
				$date = $ts !== false ? $ts : 0;
				continue;
			}
			// Un-escaping « >From » (mboxrd) : > supplémentaire retiré.
			if (preg_match('/^>+From /', $line)) {
				$line = substr($line, 1);
			}
			$msg .= $line;
		}
		$flush();
		fclose($handle);
		return ['imported' => $imported, 'failed' => $failed];
	}

	/** Toutes les PJ d'un message en un zip streamé (MXWebMailLib\Stream\ZIP). */
	/** Zip des .eml des messages cochés : GET ?uids=1,2,3 (cap 100, streamé). */
	public function emlZip(Request $request): Response
	{
		$this->requireAuth();
		$folder = $this->folderParam($request);
		$uids = array_values(array_unique(array_filter(array_map('intval',
			explode(',', (string) $request->input('uids', ''))))));
		if (!$uids) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'uids']), 'bad_uids', 400);
		}
		if (count($uids) > 100) {
			return JsonResponse::error(
				$this->t('errors.too_many', ['max' => 100]), 'too_many', 413);
		}
		$mail = $this->app->mail();
		$zip = new \MXWebMailLib\Stream\ZIP();
		$zip->pushHttpHeaders('messages');
		foreach ($uids as $uid) {
			// uid disparu entre la coche et le téléchargement : ignoré.
			$mail->mimeStream($folder, $uid, '',
				function ($r) use ($zip, $uid) { $zip->addFromStream($r, "message-{$uid}.eml"); });
		}
		$zip->close();
		return new Response('', 200);
	}

	public function attachmentsZip(Request $request): Response
	{
		$this->requireAuth();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$uid = (int) $request->params['uid'];
		$mail = $this->app->mail();
		$msg = $mail->message($folder, $uid);
		$atts = $msg['attachments'] ?? [];
		if (!$msg || !$atts) {
			return Response::notFound();
		}
		$maxTotal = 4 * (int) $this->app->config()->get('attachments_max_total', 25) * 1024 * 1024;
		$names = [];
		$total = 0;
		foreach ($atts as $att) {
			$total += (int) ($att['size'] ?? 0);
			$names[] = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', (string) $att['name']);
		}
		if ($total > $maxTotal) {
			return JsonResponse::error(
				$this->t('errors.attachments_too_big', ['mb' => (int) ($maxTotal / 1048576)]), 'too_big', 413);
		}
		// Déduplique les noms : « name (2).ext »
		$seen = [];
		$names = array_map(function ($n) use (&$seen) {
			if (!isset($seen[$n])) { $seen[$n] = 1; return $n; }
			$i = ++$seen[$n];
			$dot = strrpos($n, '.');
			return $dot === false ? "{$n} ({$i})"
				: substr($n, 0, $dot) . " ({$i})" . substr($n, $dot);
		}, $names);
		$zip = new \MXWebMailLib\Stream\ZIP();
		$zip->pushHttpHeaders("attachments-{$uid}");
		foreach ($atts as $i => $att) {
			$name = $names[$i];
			$mail->mimeStream($folder, $uid, (string) $att['mimeIndex'],
				function ($r) use ($zip, $name) { $zip->addFromStream($r, $name); });
		}
		$zip->close();
		return new Response('', 200);
	}

	/**
	 * Désabonnement (List-Unsubscribe). RFC 8058 : si le message annonce
	 * « List-Unsubscribe-Post: List-Unsubscribe=One-Click » et une cible
	 * https, le POST part côté serveur (même garde SSRF que le proxy
	 * d'images, redirects revalidés). Sinon la cible url/mailto est
	 * renvoyée pour action côté client.
	 */
	public function messageUnsubscribe(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$folder = $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
		$msg = $this->app->mail()->message($folder, (int) $request->params['uid']);
		$u = $msg['unsubscribe'] ?? null;
		if (!$u || (!$u['url'] && !$u['mailto'])) {
			return JsonResponse::error($this->t('errors.unsubscribe_none'), 'unavailable', 400);
		}
		if (!empty($u['post']) && $u['url']) {
			$url = $u['url'];
			for ($hop = 0; $hop <= 3; $hop++) {
				if ($this->proxyUrlError($url, [])) {
					return JsonResponse::error($this->t('errors.host_blocked'), 'ssrf', 403);
				}
				$ctx = stream_context_create(['http' => [
					'method' => 'POST', 'timeout' => 8, 'max_redirects' => 0,
					'ignore_errors' => true, 'user_agent' => 'MXWebMail-Unsubscribe',
					'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
					'content' => 'List-Unsubscribe=One-Click',
				]]);
				$body = @file_get_contents($url, false, $ctx);
				$status = 0; $location = null;
				foreach ($http_response_header ?? [] as $i => $h) {
					if ($i === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
						$status = (int) $m[1];
					} else if (stripos($h, 'Location:') === 0) {
						$location = trim(substr($h, 9));
					}
				}
				if ($status >= 300 && $status < 400 && $location && $hop < 3) {
					$url = $this->resolveRedirect($url, $location) ?? '';
					if ($url !== '' && preg_match('#^https://#i', $url)) {
						continue;
					}
					return JsonResponse::error($this->t('errors.bad_url'), 'bad_url');
				}
				return ($status >= 200 && $status < 300)
					? JsonResponse::ok(['done' => true])
					: JsonResponse::error($this->t('errors.unsubscribe_failed'), 'failed', 502);
			}
			return JsonResponse::error($this->t('errors.unsubscribe_failed'), 'failed', 502);
		}
		return JsonResponse::ok(['url' => $u['url'], 'mailto' => $u['mailto']]);
	}

	// ─── Compose ───

	public function composeSend(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$message = $this->buildMessage($request);
		if (!$message) {
			return JsonResponse::error($this->t('errors.empty_body'), 'empty');
		}
		// Le cumul To+Cc+Bcc est la référence — SMTP envoie à GetRcpt() :
		// exiger un To explicite refuserait un envoi Cc/Cci-seul pourtant valide.
		if (!count($message->GetRcpt())) {
			return JsonResponse::error($this->t('errors.no_rcpt'), 'no_rcpt');
		}
		$this->app->mail()->send($message);
		// Libère les streams des PJ (fopen dans buildMessage) : unlink
		// impossible sous Windows tant que $message vit.
		$rcpts = array_merge(
			(array) ($message->GetTo() ?? []),
			(array) ($message->GetCc() ?? []),
			(array) ($message->GetBcc() ?? []));
		unset($message);
		$this->cleanupTmpAttachments((array) $request->input('attachments', []));
		// Brouillon rouvert puis envoyé : il est consommé. Non bloquant —
		// l'envoi a réussi, un échec de suppression ne doit pas faire
		// échouer la réponse.
		if (($ref = $this->draftRef($request)) !== null) {
			try {
				$this->app->mail()->deletePermanent($ref[0], $ref[1]);
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Suppression brouillon consommé échouée: ' . $e->getMessage());
			}
		}

		// Fréquence des destinataires pour l'autocomplete contacts
		$autosave = $this->userSettings((int) $_SESSION['user_id'])['contacts_autosave'] ?? true;
		if (!$autosave || !$this->app->config()->get('contacts_enable', true)) {
			return JsonResponse::ok();
		}
		// Dédup par adresse : même destinataire en To+Cc → une seule écriture.
		$seen = [];
		foreach ($rcpts as $rcpt) {
			$email = $rcpt->GetEmail(true);
			$k = strtolower($email);
			if ($email === '' || isset($seen[$k])) {
				continue;
			}
			$seen[$k] = true;
			try {
				$this->app->contacts()->touchRecipient(
					(int) $_SESSION['user_id'], $email, $rcpt->GetDisplayName());
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Autosave contact échoué : ' . $e->getMessage());
			}
		}
		return JsonResponse::ok();
	}

	public function composeUpload(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (empty($request->files['file'])) {
			return JsonResponse::error($this->t('errors.no_file'), 'no_file');
		}
		$f = $request->files['file'];
		if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		// La borne effective est le min des deux limites : un fichier qui
		// dépasserait le total autorisé par message ne pourrait jamais partir.
		$maxSize = min(
			(int) $this->app->config()->get('attachment_max_size', 25),
			(int) $this->app->config()->get('attachments_max_total', 25)
		) * 1024 * 1024;
		if ($f['size'] > $maxSize) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => (int) ($maxSize / 1048576)]), 'too_big');
		}
		$dir = $this->app->storageDir() . '/tmp';
		is_dir($dir) || mkdir($dir, 0700, true);
		// Purge opportuniste des PJ abandonnées : attach_* + .meta > 24 h
		foreach (glob($dir . '/attach_*') ?: [] as $stale) {
			if (filemtime($stale) < time() - 86400) {
				@unlink($stale);
			}
		}
		// Nom assaini : basename (chemins forgés) + caractères de contrôle —
		// il part tel quel dans le paramètre filename= des en-têtes MIME.
		$name = basename(str_replace('\\', '/', (string) $f['name']));
		$name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));
		if ($name === '') {
			$name = 'attachment';
		}
		$token = bin2hex(random_bytes(16));
		$dest = $dir . '/attach_' . $token;
		if (!move_uploaded_file($f['tmp_name'], $dest)) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		// Associe au user pour le cleanup
		file_put_contents($dest . '.meta', json_encode(['name' => $name, 'user' => $_SESSION['user_id']]));
		return JsonResponse::ok(['token' => $token, 'name' => $name, 'size' => $f['size']]);
	}

	/**
	 * Supprime une PJ temporaire (icône × de la modale compose, PJ
	 * abandonnées au reset). Idempotent : token absent/inconnu → ok quand
	 * même, le client enchaîne des deletes de fichiers déjà partis.
	 */
	public function composeUploadDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$tmp = $this->tmpAttachmentPath((string) $request->params['token']);
		if ($tmp) {
			@unlink($tmp['path']);
			@unlink($tmp['path'] . '.meta');
		}
		return JsonResponse::ok();
	}

	public function composeSaveDraft(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Un brouillon n'exige qu'un corps (sujet optionnel, comme tout
		// webmail standard) — buildMessage retourne null sinon.
		$message = $this->buildMessage($request);
		if (!$message) {
			return JsonResponse::error($this->t('errors.empty_body'), 'empty');
		}
		$drafts = $this->app->mail()->specialFolder('drafts');
		if ($drafts === null) {
			return JsonResponse::error(
				$this->t('errors.folder_role_none', ['role' => 'drafts']), 'role_none');
		}
		// Ré-enregistrement d'un brouillon ouvert : REPLACE dans le dossier
		// où il vit (RFC 8508 ou append+expunge ciblé), APPEND dans Drafts
		// sinon — plus de doublon à chaque « Brouillon ».
		$ref = $this->draftRef($request);
		$uid = $this->app->mail()->replaceMessage(
			$message->ToString(), $ref ? $ref[0] : $drafts, $ref ? $ref[1] : 0,
			['\\Seen', '\\Draft']);
		unset($message); // même raison que composeSend : fclose avant unlink
		$this->cleanupTmpAttachments((array) $request->input('attachments', []));
		return JsonResponse::ok(['uid' => $uid, 'folder' => $ref ? $ref[0] : $drafts]);
	}

	/** Construit le Mime\Message depuis la requête (partagé send/save-draft). */
	private function buildMessage(Request $request): ?\MailSo\Mime\Message
	{
		$message = new \MailSo\Mime\Message();
		$message->DoesNotAddDefaultXMailer();
		try {
			$message->SetFrom(\MailSo\Mime\Email::Parse((string) $request->input('from', $_SESSION['email'])));
		} catch (\ValueError) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => 'from']), 400);
		}
		// L'expéditeur doit être l'adresse du compte ou une identité déclarée :
		// sans contrôle serveur, un client pourrait usurper n'importe quel From.
		$fromEmail = strtolower($message->GetFrom()?->GetEmail(true) ?? '');
		// GetEmail(true) décode l'IDN : les deux formes (stockée brute et
		// UTF-8) sont admises — une identité saisie en punycode doit matcher.
		$allowedFrom = [];
		$identity = null;
		foreach ($this->app->identities()->allForUser((int) $_SESSION['user_id']) as $i) {
			$ie = strtolower((string) $i['email']);
			$iu = strtolower(\MXWebMailLib\IDN::emailToUtf8((string) $i['email']));
			$allowedFrom[] = $ie;
			$allowedFrom[] = $iu;
			if ($identity === null && in_array($fromEmail, [$ie, $iu], true)) {
				$identity = $i;
			}
		}
		$allowedFrom[] = strtolower((string) $_SESSION['email']);
		$allowedFrom[] = strtolower(\MXWebMailLib\IDN::emailToUtf8((string) $_SESSION['email']));
		if ($fromEmail === '' || !in_array($fromEmail, $allowedFrom, true)) {
			throw new \RuntimeException($this->t('errors.from_denied'), 403);
		}
		// Champs de l'identité appliqués : nom affiché du From et Reply-To.
		if ($identity) {
			$idName = str_replace(["\r", "\n"], ' ', trim((string) ($identity['name'] ?? '')));
			if ($idName !== '') {
				$message->SetFrom(new \MailSo\Mime\Email(
					$message->GetFrom()->GetEmail(), $idName));
			}
			$idReplyTo = trim((string) ($identity['reply_to'] ?? ''));
			if ($idReplyTo !== '') {
				$rt = new \MailSo\Mime\EmailCollection($idReplyTo);
				if ($rt->count()) {
					$message->SetReplyTo($rt);
				}
			}
		}
		// Parse strict : le parseur de collection ignore silencieusement les
		// segments invalides — sans contrôle, « a@b.com, mauvaise » partirait à
		// a@b.com en laissant croire que « mauvaise » était destinataire.
		[$oTo, $badTo] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('to', ''));
		[$oCc, $badCc] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('cc', ''));
		[$oBcc, $badBcc] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('bcc', ''));
		$rejected = array_merge($badTo, $badCc, $badBcc);
		if ($rejected) {
			throw new \RuntimeException(
				$this->t('errors.bad_rcpt', ['list' => implode(', ', array_slice($rejected, 0, 5))]), 400);
		}
		$message->SetTo($oTo);
		$message->SetCc($oCc);
		// Le Bcc de l'identité est fusionné avec celui du formulaire, sans doublon
		// (un brouillon rouvert peut déjà contenir le Bcc d'identité).
		if ($identity && ($idBcc = trim((string) ($identity['bcc'] ?? ''))) !== '') {
			$aHave = array();
			foreach ($oBcc as $oE) { $aHave[] = strtolower($oE->GetEmail()); }
			foreach (new \MailSo\Mime\EmailCollection($idBcc) as $oE) {
				if (!\in_array(strtolower($oE->GetEmail()), $aHave, true)) {
					$oBcc->append($oE);
					$aHave[] = strtolower($oE->GetEmail());
				}
			}
		}
		$message->SetBcc($oBcc);
		// CR/LF neutralisés à la source : ces valeurs partent dans des
		// en-têtes MIME (Subject, In-Reply-To, References).
		$message->SetSubject(str_replace(["\r", "\n"], ' ', (string) $request->input('subject', '')));
		if ($request->input('priority') === true || $request->input('priority') === 'high') {
			$message->SetPriority(\MailSo\Mime\Enumerations\MessagePriority::HIGH);
		}
		// Accusé de réception (RFC 3798) : From validé, jamais l'input brut.
		if ($request->input('mdn') === true || $request->input('mdn') === '1'
			|| $request->input('mdn') === 1) {
			$message->Headers->AddByName(
				\MailSo\Mime\Enumerations\Header::DISPOSITION_NOTIFICATION_TO,
				$fromEmail);
		}
		$message->SetInReplyTo(str_replace(["\r", "\n"], '', (string) $request->input('inReplyTo', '')));
		$message->SetReferences(str_replace(["\r", "\n"], '', (string) $request->input('references', '')));
		$message->RegenerateMessageId($_SESSION['domain'] ?? '');

		$html = (string) $request->input('html', '');
		$plain = (string) $request->input('plain', '');
		// Le marqueur de position du curseur de signature ne doit jamais partir dans un mail
		$html = (string) preg_replace(
			'#<span[^>]*class="[^"]*mxwm-sig-caret[^"]*"[^>]*>.*?</span>#s', '', $html);
		// Sorties by-ref de BuildHtml : typées array, un null → TypeError.
		$foundCids = [];
		$foundData = [];
		$foundLoc = [];
		if ($html !== '') {
			// multipart/alternative : plain + html (quoted-printable)
			$alt = new \MailSo\Mime\Part();
			$alt->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE, 'multipart/alternative');
			$message->SubParts->append($alt);

			$html = \MailSo\Base\HtmlUtils::BuildHtml($html, $foundCids, $foundData, $foundLoc);
			$alt->addPlain($plain !== '' ? $plain : \MailSo\Base\HtmlUtils::ConvertHtmlToPlain($html));

			$htmlPart = new \MailSo\Mime\Part();
			$htmlPart->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE, 'text/html; charset=utf-8');
			$htmlPart->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING, 'quoted-printable');
			$htmlPart->Body = \MailSo\Base\StreamWrappers\Binary::CreateStream(
				\MailSo\Base\ResourceRegistry::CreateMemoryResourceFromString(
					preg_replace('/\\r?\\n/su', "\r\n", trim($html))
				),
				'convert.quoted-printable-encode'
			);
			$alt->SubParts->append($htmlPart);
		} elseif ($plain !== '') {
			$message->addPlain($plain);
		} else {
			return null;
		}

		// Pièces jointes : tokens tmp uploadés (string), ou références à une
		// PJ d'un message existant {folder, uid, part} — brouillon rouvert ou
		// transfert. Le nom/mime/cid sont résolus côté serveur (meta du
		// message), jamais depuis le client ; la limite totale est vérifiée
		// sur les tailles réelles.
		$attachLimit = (int) $this->app->config()->get('attachments_max_total', 25) * 1024 * 1024;
		$attachTotal = 0;
		$msgCache = [];
		foreach ((array) $request->input('attachments', []) as $att) {
			if (is_string($att)) {
				$tmp = $this->tmpAttachmentPath($att);
				// Token inconnu ou fichier purgé (24 h) : refus explicite —
				// un « continue » enverrait le message sans la PJ affichée.
				if (!$tmp || !is_file($tmp['path'])) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$size = filesize($tmp['path']);
				$attachTotal += $size;
				if ($attachTotal > $attachLimit) {
					throw new \RuntimeException(
						$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
				}
				$mime = \MXWebMailLib\File\MimeType::fromFilename($tmp['name']) ?? 'application/octet-stream';
				$message->Attachments()->append(
					new \MailSo\Mime\Attachment(fopen($tmp['path'], 'rb'), $tmp['name'], $size, false, false, '', [], '', $mime)
				);
				continue;
			}
			if (!is_array($att)) {
				continue;
			}
			$folder = $this->noCrlf((string) ($att['folder'] ?? ''), 'attachments');
			$uid = (int) ($att['uid'] ?? 0);
			if (!empty($att['eml'])) {
				// Transfert en pièce jointe : le message source complet est
				// attaché en message/rfc822 (nom = sujet côté serveur).
				if ($folder === '' || $uid <= 0) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$cacheKey = $folder . "\0" . $uid;
				if (!array_key_exists($cacheKey, $msgCache)) {
					$msgCache[$cacheKey] = $this->app->mail()->message($folder, $uid);
				}
				$src = $msgCache[$cacheKey];
				if (!$src) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$stream = null;
				$ok = $this->app->mail()->mimeStream($folder, $uid, '', function ($r) use (&$stream) {
					$stream = fopen('php://temp', 'w+b');
					stream_copy_to_stream($r, $stream);
					rewind($stream);
				});
				if (!$ok || !is_resource($stream)) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$size = fstat($stream)['size'] ?? 0;
				$attachTotal += $size;
				if ($attachTotal > $attachLimit) {
					throw new \RuntimeException(
						$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
				}
				$fname = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]/', '', (string) ($src['subject'] ?? '')));
				$message->Attachments()->append(new \MailSo\Mime\Attachment(
					$stream, ($fname !== '' ? $fname : 'message') . '.eml', $size,
					false, false, '', [], '', 'message/rfc822'));
				continue;
			}
			$partId = (string) ($att['part'] ?? '');
			// folder vide → message('') lèverait une exception non mappée (500)
			if ($folder === '' || $uid <= 0 || !preg_match('/^\d+(\.\d+)*$/', $partId)) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			$cacheKey = $folder . "\0" . $uid;
			if (!array_key_exists($cacheKey, $msgCache)) {
				$msgCache[$cacheKey] = $this->app->mail()->message($folder, $uid);
			}
			$meta = null;
			foreach ($msgCache[$cacheKey]['attachments'] ?? [] as $a) {
				if ($a['mimeIndex'] === $partId) { $meta = $a; break; }
			}
			if (!$meta) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			// Le flux IMAP ne survit pas au fetch → copie dans php://temp
			// (rewind exigé : Attachment re-stream depuis le début).
			$stream = null;
			$ok = $this->app->mail()->mimeStream($folder, $uid, $partId, function ($r) use (&$stream) {
				$stream = fopen('php://temp', 'w+b');
				stream_copy_to_stream($r, $stream);
				rewind($stream);
			});
			if (!$ok || !is_resource($stream)) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			$size = fstat($stream)['size'] ?? 0;
			$attachTotal += $size;
			if ($attachTotal > $attachLimit) {
				throw new \RuntimeException(
					$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
			}
			// PJ inline à cid → linked : le multipart/related est recréé à
			// la sérialisation et les <img src="cid:"> du corps restent
			// fonctionnels à la re-sauvegarde.
			$cid = (string) ($meta['cid'] ?? '');
			$message->Attachments()->append(
				new \MailSo\Mime\Attachment(
					$stream, $meta['name'], $size, (bool) $meta['isInline'],
					$cid !== '', $cid, [], (string) ($meta['location'] ?? ''), (string) $meta['mime'])
			);
		}
		// <img src="data:…"> réécrites en « cid:hash » par BuildHtml : chaque
		// data-URI devient une PJ inline liée (multipart/related), sinon le
		// cid resterait orphelin chez le destinataire — image cassée.
		$imgIdx = 0;
		foreach ($foundData as $hash => $dataUrl) {
			if (!preg_match('#^data:([^;,]+)((?:;[^;,]*)*),(.*)$#is', (string) $dataUrl, $m)
				|| !str_starts_with(strtolower($m[1]), 'image/')) {
				continue;
			}
			$raw = stripos($m[2], ';base64') !== false
				? base64_decode((string) preg_replace('/\s+/', '', $m[3]), true)
				: rawurldecode($m[3]);
			if (!is_string($raw) || $raw === '') {
				continue;
			}
			$attachTotal += strlen($raw);
			if ($attachTotal > $attachLimit) {
				throw new \RuntimeException(
					$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
			}
			$stream = fopen('php://temp', 'w+b');
			if (!is_resource($stream)) {
				continue;
			}
			fwrite($stream, $raw);
			rewind($stream);
			$ext = strtolower((string) preg_replace('/[^a-z0-9]/i', '',
				substr($m[1], strpos($m[1], '/') + 1))) ?: 'bin';
			$message->Attachments()->append(
				new \MailSo\Mime\Attachment(
					$stream, 'image_' . (++$imgIdx) . '.' . $ext, strlen($raw),
					true, true, '<' . $hash . '>', [], '', strtolower(trim($m[1])))
			);
		}
		return $message;
	}

	// ─── Sessions « se souvenir de moi » ───

	public function sessions(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok(
			$this->app->auth()->rememberSessions((int) $_SESSION['user_id']));
	}

	public function sessionDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->deleteRememberToken(
			(int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	public function sessionsRevokeOthers(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->revokeOtherRememberTokens((int) $_SESSION['user_id']);
		return JsonResponse::ok();
	}

	// ─── Contacts / Settings / Identities / Quota ───

	public function contacts(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireContacts();
		$q = $this->noCrlf((string) $request->input('q', ''), 'q');
		$userId = (int) $_SESSION['user_id'];
		$limit = (int) $this->app->config()->get('contacts_suggestions_limit', 20);
		$data = $q === ''
			? $this->app->contacts()->all($userId)
			: $this->app->contacts()->search($userId, $q, $limit);

		// Hook : des plugins peuvent fournir des suggestions externes (ldap, carddav…)
		foreach ($this->app->hooks()->collect('contacts.provider', $q, $userId) as $extra) {
			foreach ((array) $extra as $c) {
				$data[] = $c;
			}
		}
		return JsonResponse::ok($data);
	}

	public function contactSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$id = $this->app->contacts()->save((int) $_SESSION['user_id'], (array) $request->json());
		return JsonResponse::ok(['id' => $id]);
	}

	public function contactDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$this->app->contacts()->delete((int) $_SESSION['user_id'], (int) $request->input('id'));
		return JsonResponse::ok();
	}

	/** Import .vcf (multipart 'file') : déduplication sur les emails existants. */
	public function contactsImport(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$f = $request->files['file'] ?? null;
		if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		if ((int) ($f['size'] ?? 0) > 5 * 1024 * 1024) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => 5]), 'too_big', 400);
		}
		$raw = file_get_contents((string) $f['tmp_name']);
		if ($raw === false || trim($raw) === '') {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		// .csv → parseur CSV (mapping heuristique) ; tout le reste = vCard.
		$isCsv = str_ends_with(strtolower((string) ($f['name'] ?? '')), '.csv');
		$items = $isCsv
			? \MXWebMail\Service\CsvContacts::parse($raw)
			: \MXWebMail\Service\VCardContacts::parse($raw);
		if (!$items) {
			throw new \RuntimeException($this->t('settings.contacts_import_none'), 400);
		}
		$report = $this->app->contacts()->import((int) $_SESSION['user_id'], $items);
		return JsonResponse::ok($report);
	}

	/** Export du carnet complet en .vcf (vCard 3.0). */
	public function contactsExport(Request $request): Response
	{
		$this->requireAuth();
		$this->requireContacts();
		$vcf = \MXWebMail\Service\VCardContacts::export(
			$this->app->contacts()->exportRows((int) $_SESSION['user_id']));
		return new Response($vcf, 200, [
			'Content-Type'        => 'text/vcard; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="contacts.vcf"',
		]);
	}

	/** Changement de mot de passe — délégué à un plugin via le hook password.change. */
	public function passwordChange(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$old = (string) $request->input('old', '');
		$new = (string) $request->input('new', '');
		// Politique (longueur, force, HIBP) déléguée au driver du hook
		// « password.change » — un plancher ici plafonnerait sa config.
		if ($old === '' || $new === '') {
			return JsonResponse::error($this->t('errors.password_input'), 'input');
		}
		$handled = $this->app->hooks()->collect('password.change',
			$this->app->currentUser(), $old, $new);
		if (!$handled) {
			return JsonResponse::error($this->t('errors.no_password_driver'), 'unavailable');
		}
		// Le nouveau mot de passe devient le credential IMAP de session
		$_SESSION['imap_pass'] = CredentialCrypt::encrypt($new, $this->app->storageDir());
		return JsonResponse::ok();
	}

	public function settingsGet(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->userSettings((int) $_SESSION['user_id']));
	}

	public function settingsSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Whitelist partagée : App::allowedUserSettingKeys (user_defaults +
		// hooks « settings.allowed », jamais « plugin.* »). Valeurs bornées
		// par les mêmes règles que les défauts globaux (App::checkUserSetting)
		// — invalide = ignorée, jamais stockée.
		$allowed = $this->app->allowedUserSettingKeys();
		foreach ((array) $request->json() as $key => $value) {
			$key = (string) $key;
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			[$ok, $v] = $this->app->checkUserSetting($key, $value);
			if ($ok) {
				$this->app->settings()->set((int) $_SESSION['user_id'], $key, $v);
			}
		}
		return JsonResponse::ok();
	}

	public function identities(Request $request): JsonResponse
	{
		$this->requireAuth();
		$uid = (int) $_SESSION['user_id'];
		$data = $this->app->identities()->allForUser($uid);
		// Hook : un plugin peut fournir des identités externes (ldap-identities…)
		foreach ($this->app->hooks()->collect('identities.provider', $uid) as $extra) {
			foreach ((array) $extra as $i) {
				$data[] = $i;
			}
		}
		return JsonResponse::ok($data);
	}

	public function identitySave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$data = (array) $request->json();
		// Création d'une identité supplémentaire (pas la par défaut) :
		// bloquée quand allow_additional_identities = false.
		if (empty($data['id'])
			&& !$this->app->config()->get('allow_additional_identities', true)) {
			return JsonResponse::error($this->t('errors.identities_off'), 'forbidden', 403);
		}
		// Une nouvelle identité s'attache au compte actif, pas au compte par défaut
		$data['account_id'] = (int) ($_SESSION['account_id'] ?? 0) ?: ($data['account_id'] ?? 0);
		try {
			$id = $this->app->identities()->save((int) $_SESSION['user_id'], $data);
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error(
				$this->t('errors.invalid_identity'), 'invalid', 400);
		}
		return JsonResponse::ok(['id' => $id]);
	}

	public function identityDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->identities()->delete((int) $_SESSION['user_id'], (int) $request->input('id'));
		return JsonResponse::ok();
	}

	public function identityDefault(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->identities()->setDefault((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	// ─── PGP (keyring utilisateur ; le chiffrement se fait dans le navigateur) ───

	public function pgpKeys(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->pgp()->all((int) $_SESSION['user_id']));
	}

	public function pgpKeySave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$id = $this->app->pgp()->save(
			(int) $_SESSION['user_id'],
			(string) $request->input('email'),
			(string) $request->input('armored'),
			(bool) $request->input('private', false)
		);
		return JsonResponse::ok(['id' => $id]);
	}

	public function pgpKeyDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->pgp()->delete((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Clés publiques connues pour une liste de destinataires (?emails=a,b,c). */
	public function pgpKeysLookup(Request $request): JsonResponse
	{
		$this->requireAuth();
		$emails = preg_split('/[,;]/', (string) $request->input('emails', ''));
		return JsonResponse::ok($this->app->pgp()->publicKeysFor((int) $_SESSION['user_id'], $emails));
	}

	public function quota(Request $request): JsonResponse
	{
		$this->requireAuth();
		return JsonResponse::ok($this->app->mail()->quota() ?? []);
	}

	/** Dispatch borné : appelle Plugin::api<Action> si le plugin est activé et la méthode existe. */
	public function pluginCall(Request $request): JsonResponse
	{
		$this->requireAuth();
		// CSRF exigé pour TOUTES les méthodes (GET inclus) : le client envoie
		// X-CSRF-Token systématiquement et une action GET mutante n'est pas
		// à l'abri (navigation same-site, plugin tiers).
		$this->requireCsrf();
		$data = $this->app->plugins()->callApi(
			(string) $request->params['name'],
			(string) $request->params['action'],
			$request
		);
		return JsonResponse::ok($data);
	}

	// ─── Helpers ───

	/** Allowlist proxy : match exact ou wildcard '*.domaine'. */
	private static function hostAllowed(string $host, array $allow): bool
	{
		$host = strtolower($host);
		foreach ($allow as $entry) {
			$entry = strtolower($entry);
			if ($entry === $host
				|| (str_starts_with($entry, '*.') && str_ends_with($host, substr($entry, 1)))) {
				return true;
			}
		}
		return false;
	}

	/** Réglages utilisateur + défauts globaux (config user_defaults) pour les clés absentes. */
	private function userSettings(int $userId): array
	{
		$settings = $this->app->settings()->allFor($userId);
		foreach ((array) $this->app->config()->get('user_defaults', []) as $key => $value) {
			$settings[$key] ??= $value;
		}
		// Template stocké devenu non utilisable (désactivé/supprimé) → défaut global
		if (!is_string($settings['theme'] ?? null)
			|| !$this->app->themes()->isSelectable($settings['theme'])) {
			$settings['theme'] = $this->app->themes()->defaultTheme();
		}
		// Les clés « plugin.* » sont internes (secrets chiffrés, état) —
		// jamais exposées au frontend via settings/appdata.
		return array_filter($settings,
			fn(string $k) => !str_starts_with($k, 'plugin.'),
			ARRAY_FILTER_USE_KEY);
	}

	private function requireAuth(): void
	{
		if (!$this->app->isLoggedIn()) {
			throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
		}
	}

	private function requireCsrf(): void
	{
		if (!Csrf::verifyRequest()) {
			throw new \RuntimeException($this->t('errors.csrf'), 403);
		}
	}

	private function requireDangerous(): void
	{
		if (!$this->app->config()->get('dangerous_actions', true)) {
			throw new \RuntimeException($this->t('errors.dangerous_off'), 403);
		}
	}

	private function requireContacts(): void
	{
		if (!$this->app->config()->get('contacts_enable', true)) {
			throw new \RuntimeException($this->t('errors.contacts_off'), 403);
		}
	}

	/**
	 * Bloque les actions sensibles sur les dossiers « par défaut » :
	 * $inboxOnly = true → seule INBOX est protégée (vidage) ;
	 * sinon tous les dossiers système (renommage, ACL, suppression).
	 */
	private function requireNonSystemFolder(Request $request, bool $inboxOnly = false): string
	{
		$name = $this->folderParam($request);
		$flags = $this->app->mail()->folderFlags($name);
		if ($flags[$inboxOnly ? 'inbox' : 'system']) {
			throw new \RuntimeException($this->t('errors.folder_protected'), 403);
		}
		return $name;
	}

	/** Délimiteur de hiérarchie d'un dossier existant ('' si introuvable). */
	private function folderDelimiter(MailService $mail, string $fullName): string
	{
		foreach ($mail->folders() as $f) {
			if ($f['fullName'] === $fullName) {
				return (string) ($f['delimiter'] ?? '');
			}
		}
		return '';
	}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	/**
	 * Convention : le {name} des routes dossiers est TOUJOURS encodé en
	 * base64url par le frontend (les noms de dossiers peuvent contenir '/').
	 */
	private function folderParam(Request $request): string
	{
		$raw = (string) ($request->params['name'] ?? '');
		if ($raw !== '') {
			return $this->noCrlf(base64_decode(strtr($raw, '-_', '+/'), true) ?: $raw, 'folder');
		}
		return $this->noCrlf((string) $request->input('name'), 'folder');
	}

	/**
	 * Rejette CR/LF dans les paramètres IMAP/Sieve : ces caractères
	 * termineraient la commande en cours sur le socket (injection).
	 */
	private function noCrlf(string $value, string $key): string
	{
		if (strpbrk($value, "\r\n") !== false) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => $key]), 400);
		}
		return $value;
	}

	/** Supprime les tmp des PJ après envoi/brouillon réussi. */
	private function cleanupTmpAttachments(array $tokens): void
	{
		foreach ($tokens as $token) {
			if (!is_string($token)) {
				continue; // msgref : rien à purger côté tmp
			}
			$tmp = $this->tmpAttachmentPath($token);
			if ($tmp) {
				@unlink($tmp['path']);
				@unlink($tmp['path'] . '.meta');
			}
		}
	}

	/**
	 * Référence du brouillon à remplacer/consommer : [folder, uid] si la
	 * cible est bien un brouillon, null sinon. Entrée client — sans
	 * contrôle, une requête forgée pourrait remplacer ou purger un message
	 * arbitraire. Accepté directement dans le dossier Drafts ; ailleurs
	 * le message doit porter le flag \Draft.
	 */
	private function draftRef(Request $request): ?array
	{
		$uid = (int) $request->input('draftUid', 0);
		$folder = trim((string) $request->input('draftFolder', ''));
		if ($uid <= 0 || $folder === '') {
			return null;
		}
		$folder = $this->noCrlf($folder, 'draftFolder');
		if ($folder === $this->app->mail()->specialFolder('drafts')) {
			return [$folder, $uid];
		}
		try {
			$msg = $this->app->mail()->message($folder, $uid);
		} catch (\Throwable) {
			return null;
		}
		return ($msg && ($msg['isDraft'] ?? false)) ? [$folder, $uid] : null;
	}

	private function tmpAttachmentPath(string $token): ?array
	{
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			return null;
		}
		$path = $this->app->storageDir() . '/tmp/attach_' . $token;
		$meta = is_file($path . '.meta') ? json_decode((string) file_get_contents($path . '.meta'), true) : null;
		if (!$meta || $meta['user'] !== ($_SESSION['user_id'] ?? null)) {
			return null;
		}
		return ['path' => $path, 'name' => $meta['name'] ?? 'attachment'];
	}
}
