<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Security\Csrf;
use MXWebMail\Security\TotpRequiredException;
use MXWebMail\Service\MailService;




abstract class BaseController
{
	/** Critères IMAP SORT acceptés en ?sort= sur /api/messages (RFC 5256). */
	protected const SORT_CRITERIA = [
		'REVERSE DATE', 'DATE', 'ARRIVAL', 'REVERSE ARRIVAL',
		'FROM', 'REVERSE FROM', 'TO', 'REVERSE TO', 'CC',
		'SUBJECT', 'REVERSE SUBJECT', 'SIZE', 'REVERSE SIZE',
	];

	public function __construct(protected App $app) {}

	/**
	 * requireAuth + session_write_close : pour les endpoints en lecture
	 * seule (GET). Le fichier de session resterait sinon verrouillé durant
	 * les appels IMAP/Sieve/HTTP - poll, images cid:, quota et actions de
	 * l'onglet se sérialiseraient les uns derrière les autres.
	 * Csrf::token() avant la fermeture : créé normalement au rendu de page,
	 * mais garanti ici - sinon il régénèrerait à chaque requête et tous les
	 * POST échoueraient en 403. $_SESSION reste lisible après fermeture ;
	 * seule la persistance s'arrête.
	 */
	protected function requireReadOnly(): void
	{
		$this->requireAuth();
		if (session_status() === PHP_SESSION_ACTIVE) {
			Csrf::token();
			session_write_close();
		}
	}

	protected function requireAuth(): void
	{
		if (!$this->app->isLoggedIn()) {
			throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
		}
	}

	protected function requireCsrf(): void
	{
		if (!Csrf::verifyRequest()) {
			throw new \RuntimeException($this->t('errors.csrf'), 403);
		}
	}

	protected function requireDangerous(): void
	{
		if (!$this->app->config()->get('dangerous_actions', true)) {
			throw new \RuntimeException($this->t('errors.dangerous_off'), 403);
		}
	}

	protected function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	/**
	 * Rejette CR/LF dans les paramètres IMAP/Sieve : ces caractères
	 * termineraient la commande en cours sur le socket (injection).
	 */
	protected function noCrlf(string $value, string $key): string
	{
		if (strpbrk($value, "\r\n") !== false) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => $key]), 400);
		}
		return $value;
	}

	/**
	 * Convention : le {name} des routes dossiers est TOUJOURS encodé en
	 * base64url par le frontend (les noms de dossiers peuvent contenir '/').
	 */
	protected function folderParam(Request $request): string
	{
		$raw = (string) ($request->params['name'] ?? '');
		if ($raw !== '') {
			// Round-trip strict : un nom composé de caractères base64 ("INBOX")
			// envoyé brut ne doit pas être décodé en binaire - il reste tel quel.
			$dec = base64_decode(strtr($raw, '-_', '+/'), true);
			if (is_string($dec) && rtrim(base64_encode($dec), '=') === strtr($raw, '-_', '+/')) {
				return $this->noCrlf($dec, 'folder');
			}
			return $this->noCrlf($raw, 'folder');
		}
		return $this->noCrlf((string) $request->input('name'), 'folder');
	}

	/** Réglages utilisateur + défauts globaux (config user_defaults) pour les clés absentes. */
	protected function userSettings(int $userId): array
	{
		$settings = $this->app->settings()->allFor($userId);
		foreach ((array) $this->app->config()->get('user_defaults', []) as $key => $value) {
			$settings[$key] ??= $value;
		}
		// Template stocké devenu non utilisable (désactivé/supprimé) > défaut global
		if (!is_string($settings['theme'] ?? null)
			|| !$this->app->themes()->isSelectable($settings['theme'])) {
			$settings['theme'] = $this->app->themes()->defaultTheme();
		}
		// Les clés "plugin.*" sont internes (secrets chiffrés, état) -
		// jamais exposées au frontend via settings/appdata.
		return array_filter($settings,
			fn(string $k) => !str_starts_with($k, 'plugin.'),
			ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Page de messages normalisée - partagée par GET /api/messages et la
	 * section "list" de GET /api/poll. $p = paramètres bruts (query ou JSON
	 * décodé) : folder, page, limit, search, sort, threads, thread_uid.
	 */
	protected function listData(array $p): array
	{
		$mail = $this->app->mail();
		$folder = $this->noCrlf((string) ($p['folder'] ?? 'INBOX'), 'folder');
		$page = max(1, (int) ($p['page'] ?? 1));
		$limit = min(200, max(1, (int) ($p['limit'] ?? 50)));
		$search = $this->noCrlf((string) ($p['search'] ?? ''), 'search');
		$sort = (string) ($p['sort'] ?? '');
		if (!in_array($sort, self::SORT_CRITERIA, true)) {
			$sort = 'REVERSE DATE';
		}
		$threads = !empty($p['threads']);
		$threadUid = (int) ($p['thread_uid'] ?? 0);
		// folder=* : recherche multi-dossiers (fusion triée date desc).
		$data = $folder === '*'
			? $mail->searchAllFolders($search, ($page - 1) * $limit, $limit)
			: $mail->messages($folder, ($page - 1) * $limit, $limit,
				$search, $sort, $threads, $threadUid);
		$data['page'] = $page;
		$data['limit'] = $limit;
		return $data;
	}

	/**
	 * Résout un Location de redirection (RFC 7231 relative-ref) :
	 * absolu http(s), protocol-relative, chemin absolu, query, fragment
	 * ou relatif résolu contre le répertoire de la base (RFC 3986 §5.2.4).
	 * Toute autre forme est refusée. userinfo/pass de la base jamais propagés.
	 */
	protected static function resolveRedirect(string $base, string $location): ?string
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
		// Répertoire de la base sans dirname() - sous Windows dirname('/') = '\'.
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
	/** Allowlist optionnelle + blocage IP privée/réservée. Retourne null si OK. */
	protected function proxyUrlError(string $url, array $allow): ?string
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
	/** Allowlist proxy : match exact ou wildcard '*.domaine'. */
	protected static function hostAllowed(string $host, array $allow): bool
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
}
