<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;


/**
 * Helpers du proxy HTTP sortant (images distantes, avatars) : résolution
 * des redirections, allowlist d'hôtes et blocage SSRF. Consommé par
 * ApiController (proxy d'images) et MessagesController (cid: externes) -
 * la classe consommatrice doit exposer $this->app.
 */
trait ProxyGuard
{
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
			} elseif ('.' !== $seg && '' !== $seg) {
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
		// Garde SSRF commune : resolution A + AAAA, fail-closed sur DNS-KO.
		$reason = $this->app->publicHostBlockReason((string) $host);
		if ($reason !== null) {
			$this->app->log('warning',
				"Proxy image refusé (SSRF) host={$host} reason={$reason}");
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
