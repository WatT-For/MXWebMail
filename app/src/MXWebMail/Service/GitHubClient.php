<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;

/**
 * Client GitHub Releases partagé : UpdateService (paquet cœur
 * mxwebmail-*.zip) et PluginPackageService (assets plugin-<name>-*.zip +
 * plugins.index.json) l'utilisent.
 *
 * Config : section 'update' de config.php —
 *   repo         'owner/mxwebmail' ; vide = vérification désactivée
 *   channel      stable | beta (beta = pré-releases incluses)
 *   token        PAT GitHub optionnel (rate-limit / dépôt privé)
 *   manifest_url dev uniquement : URL d'un manifeste JSON au format « release »,
 *                bypass l'API GitHub (jamais modifiable via l'API admin).
 *
 * Cache release : app/storage/cache/update-check.json (TTL cache.update_ttl,
 * clé repo+channel+manifest) — partagé : un check cœur suivi d'un catalogue
 * plugins ne coûte qu'un seul appel GitHub.
 */
final class GitHubClient
{
	// Dépôt officiel des releases — à renseigner ici à la publication (sécurité :
	// un repo éditable via l'admin permettrait de détourner l'updater → RCE).
	// Tant qu'il est vide, update.repo dans config.php fait foi (fallback dev,
	// non exposé à l'API admin).
	private const REPO = 'WatT-For/MXWebMail';

	private string $cacheFile;

	public function __construct(private App $app)
	{
		$this->cacheFile = $app->storageDir() . '/cache/update-check.json';
	}

	private function cfg(): array
	{
		$upd = $this->app->config()->get('update', []);
		return is_array($upd) ? $upd : [];
	}

	private function repo(): ?string
	{
		// La constante prime ; update.repo n'est qu'un fallback dev (hors whitelist
		// d'écriture admin — modifiable uniquement via config.php).
		$repo = self::REPO !== '' ? self::REPO : trim((string) ($this->cfg()['repo'] ?? ''));
		return preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $repo) ? $repo : null;
	}

	private function manifest(): string
	{
		return trim((string) ($this->cfg()['manifest_url'] ?? ''));
	}

	private function manifestMode(): bool
	{
		return $this->manifest() !== '';
	}

	public function channel(): string
	{
		return (string) ($this->cfg()['channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';
	}

	public function configured(): bool
	{
		return $this->repo() !== null || $this->manifestMode();
	}

	public function requireExts(): void
	{
		if (!extension_loaded('curl') || !extension_loaded('zip')) {
			throw new \RuntimeException('Extensions PHP requises manquantes : curl et/ou zip', 500);
		}
	}

	// ─── Release ───

	/**
	 * Dernière release disponible, tronquée + plugins_index embarqué.
	 * Cache fichier, invalidé si repo/channel/manifest changent.
	 */
	public function release(bool $force = false): array
	{
		$manifest = $this->manifest();
		$repo = $this->repo();
		$channel = $this->channel();

		if (!$force && ($c = $this->readCache()) !== null
			&& $c['repo'] === $repo && $c['channel'] === $channel && $c['manifest'] === $manifest) {
			return $c['release'];
		}

		if ($manifest !== '') {
			// Mode dev : le manifeste est la réponse (canal ignoré)
			if (!$this->validUrl($manifest)) {
				throw new \RuntimeException('update.manifest_url invalide', 500);
			}
			$rel = json_decode($this->get($manifest), true);
		} else {
			if ($repo === null) {
				throw new \RuntimeException('update.repo non configuré', 400);
			}
			if ($channel === 'beta') {
				$list = json_decode(
					$this->get("https://api.github.com/repos/$repo/releases?per_page=10"), true);
				$rel = null;
				foreach ((array) $list as $r) {
					if (empty($r['draft'])) { $rel = $r; break; }
				}
			} else {
				// /releases/latest exclut déjà drafts et pré-releases
				$rel = json_decode(
					$this->get("https://api.github.com/repos/$repo/releases/latest"), true);
			}
		}
		if (!is_array($rel) || empty($rel['tag_name'])) {
			throw new \RuntimeException('Réponse de release invalide', 502);
		}

		$trim = [
			'tag_name'     => (string) $rel['tag_name'],
			'name'         => (string) ($rel['name'] ?? ''),
			'body'         => (string) ($rel['body'] ?? ''),
			'html_url'     => (string) ($rel['html_url'] ?? ''),
			'published_at' => (string) ($rel['published_at'] ?? ''),
			'assets'       => array_map(fn($a) => [
				'name'                  => (string) ($a['name'] ?? ''),
				'browser_download_url'  => (string) ($a['browser_download_url'] ?? ''),
				// Endpoint API releases/assets/<id> — requis sur dépôt privé
				// (browser_download_url y renvoie 404 même authentifié).
				'api_url'               => (string) ($a['url'] ?? ''),
				'download_url'          => $this->pickUrl($a),
				'size'                  => (int) ($a['size'] ?? 0),
			], (array) ($rel['assets'] ?? [])),
			// Index du catalogue plugins — embarqué pour profiter du même cache
			'plugins_index' => $this->fetchPluginIndex($rel),
		];
		$this->writeCache($repo, $channel, $manifest, $trim);
		return $trim;
	}

	public function invalidateCache(): void
	{
		@unlink($this->cacheFile);
	}

	/**
	 * URL de téléchargement d'un asset : endpoint API releases/assets/<id> si
	 * un token est configuré (dépôt privé — browser_download_url y renvoie 404
	 * même authentifié), sinon l'URL publique. Accepte un asset brut de
	 * l'API ('url') ou tronqué ('api_url').
	 */
	private function pickUrl(array $a): string
	{
		$api = (string) ($a['api_url'] ?? $a['url'] ?? '');
		$web = (string) ($a['browser_download_url'] ?? '');
		return ($api !== '' && trim((string) ($this->cfg()['token'] ?? '')) !== '')
			? $api : $web;
	}

	/** plugins.index.json : inline (manifeste dev) ou asset de la release. */
	private function fetchPluginIndex(array $rel): ?array
	{
		if (is_array($rel['plugins_index'] ?? null)) {
			return $rel['plugins_index'];
		}
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			if (($a['name'] ?? '') !== 'plugins.index.json') {
				continue;
			}
			$url = $this->pickUrl($a);
			if (!$this->validUrl($url)) {
				return null;
			}
			try {
				$idx = json_decode($this->get($url), true);
			} catch (\RuntimeException $e) {
				$this->app->log('error', 'plugins.index.json : ' . $e->getMessage());
				return null;
			}
			return is_array($idx) ? $idx : null;
		}
		return null;
	}

	// ─── Assets ───

	/**
	 * Paquet cœur : « mxwebmail-*.zip » — jamais un asset plugin-*.zip.
	 * Fallback (manifeste dev) : premier .zip non préfixé plugin-.
	 */
	public function coreAsset(array $rel): ?array
	{
		$fallback = null;
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			$name = strtolower((string) ($a['name'] ?? ''));
			if (!str_ends_with($name, '.zip') || str_starts_with($name, 'plugin-')) {
				continue;
			}
			if (str_starts_with($name, 'mxwebmail-')) {
				return $a;
			}
			$fallback ??= $a;
		}
		return $fallback;
	}

	/**
	 * Assets « plugin-<name>-<ver>.zip » → map name => {name, version, url, size}.
	 * Fallback du catalogue pour les releases sans plugins.index.json.
	 */
	public function pluginAssets(array $rel): array
	{
		$out = [];
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			$name = (string) ($a['name'] ?? '');
			if (preg_match('/^plugin-([a-z0-9-]+)-(\d+\.\d+\.\d+)\.zip$/', $name, $m)) {
				$out[$m[1]] = [
					'name'    => $name,
					'version' => $m[2],
					'url'     => (string) ($a['download_url'] ?? ''),
					'size'    => (int) ($a['size'] ?? 0),
				];
			}
		}
		return $out;
	}

	/** Asset « <zip>.sha256 » ou fichier de sommes (SHA256SUMS, checksums.txt…). */
	public function sha256Url(array $rel, string $zipName): ?string
	{
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			$name = strtolower((string) ($a['name'] ?? ''));
			if ($name === strtolower($zipName) . '.sha256'
				|| $name === 'sha256sums' || $name === 'sha256sums.txt'
				|| $name === 'checksums.txt') {
				return (string) ($a['download_url'] ?? '') ?: null;
			}
		}
		return null;
	}

	/** Somme sha256 publiée pour $zipName, ou null si absente/illisible. */
	public function assetSha256(array $rel, string $zipName): ?string
	{
		$sumUrl = $this->sha256Url($rel, $zipName);
		if ($sumUrl === null || !$this->validUrl($sumUrl)) {
			return null;
		}
		$txt = trim($this->get($sumUrl));
		return preg_match('/\b([0-9a-f]{64})\b/i', $txt, $m) ? strtolower($m[1]) : null;
	}

	// ─── HTTP ───

	/** GitHub uniquement ; en mode manifeste : https partout, http seulement en local. */
	public function validUrl(string $url): bool
	{
		if ($this->manifestMode()) {
			if (preg_match('#^https://#i', $url)) {
				return true;
			}
			return (bool) preg_match('#^http://(localhost|127\.0\.0\.1)([:/]|$)#i', $url);
		}
		return (bool) preg_match(
			'#^https://(api\.github\.com|github\.com|objects\.githubusercontent\.com)/#i', $url);
	}

	/**
	 * GET via cURL. Le token GitHub n'est envoyé que vers des domaines github.com.
	 */
	public function get(string $url): string
	{
		return $this->request($url, null, 0);
	}

	/** GET streamé vers un fichier (plafond $maxBytes). */
	public function getToFile(string $url, string $dest, int $maxBytes): void
	{
		$this->request($url, $dest, $maxBytes);
	}

	private function request(string $url, ?string $toFile, int $maxBytes): string
	{
		$ch = curl_init($url);
		// L'endpoint releases/assets/<id> exige octet-stream pour servir le
		// binaire (sinon il renvoie le JSON de métadonnées de l'asset).
		$headers = ['Accept: ' . (str_contains($url, '/releases/assets/')
			? 'application/octet-stream' : 'application/vnd.github+json')];
		$token = trim((string) ($this->cfg()['token'] ?? ''));
		if ($token !== '' && preg_match(
			'#^https://(api\.github\.com|github\.com|objects\.githubusercontent\.com)/#i', $url)) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS    => 3,
			CURLOPT_TIMEOUT      => $toFile !== null ? 120 : 15,
			CURLOPT_USERAGENT    => 'MXWebMail/' . APP_VERSION,
			CURLOPT_HTTPHEADER   => $headers,
		];
		$fp = null;
		if ($toFile !== null) {
			$fp = fopen($toFile, 'wb');
			if (!$fp) {
				throw new \RuntimeException("Impossible d'écrire $toFile", 500);
			}
			$opts[CURLOPT_RETURNTRANSFER] = false;
			$downloaded = 0;
			$opts[CURLOPT_WRITEFUNCTION] = static function ($ch, string $chunk) use ($fp, &$downloaded, $maxBytes) {
				$downloaded += strlen($chunk);
				if ($downloaded > $maxBytes) {
					return -1; // taille différente de strlen → CURLE_WRITE_ERROR
				}
				return fwrite($fp, $chunk);
			};
		}
		curl_setopt_array($ch, $opts);
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($fp) {
			fclose($fp);
		}
		if ($body === false) {
			if ($toFile !== null) {
				@unlink($toFile);
			}
			throw new \RuntimeException("Requête échouée : $err", 502);
		}
		if ($code >= 400) {
			if ($toFile !== null) {
				@unlink($toFile);
			}
			throw new \RuntimeException("HTTP $code — $url", 502);
		}
		return is_string($body) ? $body : '';
	}

	// ─── Cache ───

	/** Section « cache » de la config — enabled=false → cache ignoré. */
	private function cacheCfg(): array
	{
		$c = $this->app->config()->get('cache', []);
		return is_array($c) ? $c : [];
	}

	private function cacheEnabled(): bool
	{
		return (bool) ($this->cacheCfg()['enabled'] ?? true);
	}

	private function readCache(): ?array
	{
		if (!$this->cacheEnabled()) {
			return null;
		}
		$ttl = (int) ($this->cacheCfg()['update_ttl'] ?? 3600);
		$raw = is_file($this->cacheFile) ? file_get_contents($this->cacheFile) : false;
		$c = is_string($raw) ? json_decode($raw, true) : null;
		return (is_array($c) && ($c['at'] ?? 0) > time() - $ttl
			&& ($c['v'] ?? 0) >= 2
			&& is_array($c['release'] ?? null)) ? $c : null;
	}

	private function writeCache(?string $repo, string $channel, string $manifest, array $release): void
	{
		if (!$this->cacheEnabled()) {
			return;
		}
		is_dir(dirname($this->cacheFile)) || mkdir(dirname($this->cacheFile), 0700, true);
		$this->writeJson($this->cacheFile, [
			'v' => 2, 'at' => time(), 'repo' => $repo, 'channel' => $channel,
			'manifest' => $manifest, 'release' => $release,
		]);
	}

	/** Écriture JSON atomique : tmp + rename, jamais de fichier tronqué lu. */
	private function writeJson(string $file, mixed $data): void
	{
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES)) !== false) {
			rename($tmp, $file) || @unlink($tmp); // pas de reliquat .tmp
		}
	}
}
