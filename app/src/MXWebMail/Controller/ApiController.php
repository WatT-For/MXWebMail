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
final class ApiController extends Api\BaseController
{
	/** Types bitmap servis par /api/proxy - tout type actif (svg, xml, html) est refusé. */
	private const PROXY_IMAGE_TYPES = [
		'image/png', 'image/jpeg', 'image/gif', 'image/webp',
		'image/avif', 'image/bmp', 'image/x-icon', 'image/vnd.microsoft.icon',
	];

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
		// la session doit être encore ouverte pour la connexion IMAP - les
		// réglages sont lus et les dossiers purgés AVANT auth()->logout().
		// Jamais bloquant : un échec IMAP n'empêche pas la déconnexion.
		// Le jeton "se souvenir de moi" courant est révoqué à la déconnexion
		// manuelle (le cookie expire aussi côté client), mais pas sur une
		// déconnexion d'inactivité (idle=true, timer autologout) - la session
		// se restaure alors silencieusement à la prochaine requête.
		$idle = $request->input('idle') === true || $request->input('idle') === '1'
			|| $request->input('idle') === 1;
		if (!empty($_SESSION['user_id']) && !$idle) {
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
			// pour se recharger - l'app revient dès que le flag tombe.
			'maintenance' => (bool) $this->app->config()->get('maintenance', false)
				|| $this->app->isUpdating(),
		]);
	}

	public function appdata(Request $request): JsonResponse
	{
		$this->requireReadOnly();
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
			// Dossiers surveillés pour la détection de nouveaux mails -
			// lecture de réglage sans IMAP (évite un RTT /folders/notify au boot).
			'notify_folders' => $this->app->mail()->notifyFolderList(),
		];
		// Hook : les plugins injectent leurs données de boot
		$data = $this->app->hooks()->filter('app.data', $data, $user);
		return JsonResponse::ok($data);
	}

	public function proxy(Request $request): Response
	{
		$this->requireReadOnly();
		if (!$this->app->config()->get('image_proxy', true)) {
			return Response::notFound();
		}
		$url = (string) $request->input('url', '');
		if (!$url || !preg_match('#^https?://#i', $url)) {
			return JsonResponse::error($this->t('errors.bad_url'), 'bad_url');
		}
		$http = (array) $this->app->config()->get('http', []);
		$ttl = max(0, (int) ($http['proxy_ttl'] ?? 86400));
		// Cache disque : l'image distante était refetchée (+ DNS anti-SSRF) à
		// chaque affichage d'un même mail. Clé = URL complète (les URLs
		// tokenisées restent distinctes ; le contenu est adressé par URL).
		$cacheBase = $this->app->storageDir() . '/cache/proxy/' . hash('sha256', $url);
		if ($ttl > 0 && is_file($cacheBase) && is_file($cacheBase . '.mime')
			&& (int) filemtime($cacheBase) > time() - $ttl) {
			$mime = trim((string) file_get_contents($cacheBase . '.mime'));
			$body = file_get_contents($cacheBase);
			if (in_array($mime, self::PROXY_IMAGE_TYPES, true) && is_string($body)) {
				return $this->proxyResponse($mime, $body, $ttl);
			}
		}
		$allow = array_filter(array_map('trim',
			explode(',', (string) $this->app->config()->get('image_proxy_allowlist', ''))));
		// SSRF : l'allowlist et le blocage des IPs privées sont revérifiés sur
		// CHAQUE redirection - sinon une 302 vers une IP interne contourne le filtre.
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
		if ($ttl > 0) {
			mxwm_atomic_write($cacheBase, $body);
			mxwm_atomic_write($cacheBase . '.mime', $mime);
		}
		return $this->proxyResponse($mime, $body, $ttl);
	}

	private function proxyResponse(string $mime, string $body, int $ttl): Response
	{
		return new Response($body, 200, [
			'Content-Type'              => $mime,
			'Cache-Control'             => 'private, max-age=' . $ttl,
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

}

