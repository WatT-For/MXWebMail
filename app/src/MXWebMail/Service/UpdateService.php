<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;

/**
 * Mises à jour depuis GitHub Releases (onglet admin « À propos »).
 *
 * Config : section 'update' de config.php —
 *   repo         'owner/mxwebmail' ; vide = vérification désactivée
 *   channel      stable | beta (beta = pré-releases incluses)
 *   token        PAT GitHub optionnel (rate-limit / dépôt privé)
 *   manifest_url dev uniquement : URL d'un manifeste JSON au format « release »,
 *                bypass l'API GitHub (jamais modifiable via l'API admin).
 *
 * États persistés dans app/storage/update/state.json :
 *   downloaded → extracted → applied ; reset() pour abandonner.
 * La copie est en mode « merge » : les fichiers absents de la release ne sont
 * pas supprimés ; chaque fichier écrasé est sauvegardé dans
 * app/storage/update/backup-<Ymd-His>/ (rollback manuel).
 */
final class UpdateService
{
	private const MAX_ZIP   = 200 * 1024 * 1024;
	// Dépôt officiel des releases — à renseigner ici à la publication (sécurité :
	// un repo éditable via l'admin permettrait de détourner l'updater → RCE).
	// Tant qu'il est vide, update.repo dans config.php fait foi (fallback dev,
	// non exposé à l'API admin).
	private const REPO = 'WatT-For/MXWebMail';

	private string $dir;
	private string $stateFile;
	private string $cacheFile;

	public function __construct(private App $app)
	{
		$this->dir = $app->storageDir() . '/update';
		$this->stateFile = $this->dir . '/state.json';
		$this->cacheFile = $app->storageDir() . '/cache/update-check.json';
	}

	// ─── Config & état ───

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

	private function channel(): string
	{
		return (string) ($this->cfg()['channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';
	}

	private function configured(): bool
	{
		return $this->repo() !== null || $this->manifestMode();
	}

	/** État local sans appel réseau — utilisable dans bootstrap(). */
	public function status(): array
	{
		$state = $this->readState();
		if ($state) {
			unset($state['dir'], $state['file']); // chemins internes, pas renvoyés
		}
		return [
			'configured' => $this->configured(),
			'current'    => APP_VERSION,
			'state'      => $state,
		];
	}

	private function readState(): ?array
	{
		$raw = is_file($this->stateFile) ? file_get_contents($this->stateFile) : false;
		$state = is_string($raw) ? json_decode($raw, true) : null;
		return is_array($state) ? $state : null;
	}

	private function writeState(array $state): void
	{
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$this->writeJson($this->stateFile, $state);
	}

	/** Écriture JSON atomique : tmp + rename, jamais de fichier tronqué lu. */
	private function writeJson(string $file, mixed $data): void
	{
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES)) !== false) {
			rename($tmp, $file) || @unlink($tmp); // pas de reliquat .tmp
		}
	}

	// ─── Vérification ───

	/**
	 * Dernière release disponible + comparaison avec APP_VERSION.
	 * Réponse publique — l'URL de l'asset n'y figure pas (recalculée serveur).
	 */
	public function check(bool $force = false): array
	{
		if (!$this->configured()) {
			return ['configured' => false, 'current' => APP_VERSION];
		}
		$this->requireExts();
		$rel = $this->fetchRelease($force);
		$latest = $this->normalizeTag((string) $rel['tag_name']);
		$asset = $this->zipAsset($rel);
		return [
			'configured'   => true,
			'channel'      => $this->channel(),
			'current'      => APP_VERSION,
			'latest'       => $latest,
			'up_to_date'   => !version_compare($latest, APP_VERSION, '>'),
			'tag'          => $rel['tag_name'],
			'name'         => $rel['name'],
			'notes'        => $rel['body'],
			'html_url'     => $rel['html_url'],
			'published_at' => $rel['published_at'],
			'has_asset'    => $asset !== null,
			'asset_name'   => $asset['name'] ?? null,
			'size'         => $asset['size'] ?? null,
		];
	}

	private function requireExts(): void
	{
		if (!extension_loaded('curl') || !extension_loaded('zip')) {
			throw new \RuntimeException('Extensions PHP requises manquantes : curl et/ou zip', 500);
		}
	}

	private function normalizeTag(string $tag): string
	{
		return ltrim(trim($tag), "vV");
	}

	private function zipAsset(array $rel): ?array
	{
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			if (str_ends_with(strtolower((string) ($a['name'] ?? '')), '.zip')) {
				return $a;
			}
		}
		return null;
	}

	/** Asset « <zip>.sha256 » ou fichier de sommes (SHA256SUMS, checksums.txt…). */
	private function sha256Url(array $rel, string $zipName): ?string
	{
		foreach ((array) ($rel['assets'] ?? []) as $a) {
			$name = strtolower((string) ($a['name'] ?? ''));
			if ($name === strtolower($zipName) . '.sha256'
				|| $name === 'sha256sums' || $name === 'sha256sums.txt'
				|| $name === 'checksums.txt') {
				return (string) ($a['browser_download_url'] ?? '') ?: null;
			}
		}
		return null;
	}

	/** Release brute (tronquée) — cache 1 h, invalidée si repo/channel/manifest changent. */
	private function fetchRelease(bool $force): array
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
			if (!$this->validUrl($manifest, true)) {
				throw new \RuntimeException('update.manifest_url invalide', 500);
			}
			$rel = json_decode($this->curlGet($manifest), true);
		} else {
			if ($repo === null) {
				throw new \RuntimeException('update.repo non configuré', 400);
			}
			if ($channel === 'beta') {
				$list = json_decode(
					$this->curlGet("https://api.github.com/repos/$repo/releases?per_page=10"), true);
				$rel = null;
				foreach ((array) $list as $r) {
					if (empty($r['draft'])) { $rel = $r; break; }
				}
			} else {
				// /releases/latest exclut déjà drafts et pré-releases
				$rel = json_decode(
					$this->curlGet("https://api.github.com/repos/$repo/releases/latest"), true);
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
			'assets'       => array_map(static fn($a) => [
				'name'                  => (string) ($a['name'] ?? ''),
				'browser_download_url'  => (string) ($a['browser_download_url'] ?? ''),
				'size'                  => (int) ($a['size'] ?? 0),
			], (array) ($rel['assets'] ?? [])),
		];
		$this->writeCache($repo, $channel, $manifest, $trim);
		return $trim;
	}

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
			&& is_array($c['release'] ?? null)) ? $c : null;
	}

	private function writeCache(?string $repo, string $channel, string $manifest, array $release): void
	{
		if (!$this->cacheEnabled()) {
			return;
		}
		is_dir(dirname($this->cacheFile)) || mkdir(dirname($this->cacheFile), 0700, true);
		$this->writeJson($this->cacheFile, [
			'at' => time(), 'repo' => $repo, 'channel' => $channel,
			'manifest' => $manifest, 'release' => $release,
		]);
	}

	/** GitHub uniquement ; en mode manifeste : https partout, http seulement en local. */
	private function validUrl(string $url, bool $manifestMode): bool
	{
		if ($manifestMode) {
			if (preg_match('#^https://#i', $url)) {
				return true;
			}
			return (bool) preg_match('#^http://(localhost|127\.0\.0\.1)([:/]|$)#i', $url);
		}
		return (bool) preg_match(
			'#^https://(api\.github\.com|github\.com|objects\.githubusercontent\.com)/#i', $url);
	}

	/**
	 * GET via cURL. $toFile : stream vers un fichier (plafond MAX_ZIP).
	 * Le token GitHub n'est envoyé que vers des domaines github.com.
	 */
	private function curlGet(string $url, ?string $toFile = null): string
	{
		$ch = curl_init($url);
		$headers = ['Accept: application/vnd.github+json'];
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
			$opts[CURLOPT_WRITEFUNCTION] = static function ($ch, string $chunk) use ($fp, &$downloaded) {
				$downloaded += strlen($chunk);
				if ($downloaded > self::MAX_ZIP) {
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

	// ─── Téléchargement + extraction ───

	/** Télécharge le zip de la release, vérifie la somme, extrait (prepare). */
	public function download(): array
	{
		if (!$this->configured()) {
			throw new \RuntimeException('Mise à jour non configurée', 400);
		}
		$this->requireExts();
		$rel = $this->fetchRelease(false);
		$latest = $this->normalizeTag((string) $rel['tag_name']);
		if (!version_compare($latest, APP_VERSION, '>')) {
			throw new \RuntimeException('Déjà à jour', 400);
		}

		// Reprise : même version déjà téléchargée/extraite
		$state = $this->readState();
		if (($state['version'] ?? '') === $latest) {
			if (($state['step'] ?? '') === 'extracted' && is_dir((string) ($state['dir'] ?? ''))) {
				return ['step' => 'extracted', 'version' => $latest,
					'verified' => (bool) ($state['verified'] ?? false)];
			}
			if (($state['step'] ?? '') === 'downloaded' && is_file((string) ($state['file'] ?? ''))) {
				return $this->prepare((string) $state['file'], $latest,
					(bool) ($state['verified'] ?? false));
			}
		}

		$asset = $this->zipAsset($rel)
			?? throw new \RuntimeException('Aucun paquet .zip dans la release', 502);
		$url = (string) $asset['browser_download_url'];
		if (!$this->validUrl($url, $this->manifestMode())) {
			throw new \RuntimeException('URL de téléchargement refusée', 502);
		}

		$this->purgeStaging();
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$zipPath = $this->dir . '/update-' . $this->safeName($latest) . '.zip';
		$this->curlGet($url, $zipPath);
		if (!is_file($zipPath) || filesize($zipPath) === 0) {
			@unlink($zipPath);
			throw new \RuntimeException('Téléchargement vide', 502);
		}

		// Somme sha256 publiée à côté de l'asset — non bloquante si absente
		$verified = false;
		$sumUrl = $this->sha256Url($rel, (string) $asset['name']);
		if ($sumUrl !== null && $this->validUrl($sumUrl, $this->manifestMode())) {
			$txt = trim($this->curlGet($sumUrl));
			if (preg_match('/\b([0-9a-f]{64})\b/i', $txt, $m)) {
				$verified = hash_equals(
					strtolower($m[1]), strtolower((string) hash_file('sha256', $zipPath)));
				if (!$verified) {
					@unlink($zipPath);
					throw new \RuntimeException('Somme sha256 invalide', 502);
				}
			}
		}

		$this->writeState([
			'step' => 'downloaded', 'version' => $latest,
			'file' => $zipPath, 'verified' => $verified,
		]);
		return $this->prepare($zipPath, $latest, $verified);
	}

	/** Extraction + validation du paquet + preflight d'écriture. */
	private function prepare(string $zipPath, string $version, bool $verified): array
	{
		$dest = $this->dir . '/extract-' . $this->safeName($version);
		$this->rmrf($dest);

		$zip = new \ZipArchive();
		if ($zip->open($zipPath) !== true) {
			throw new \RuntimeException('Paquet zip illisible', 502);
		}
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string) $zip->getNameIndex($i);
			// Anti path-traversal : '..', chemin absolu, lettre de lecteur
			if ($name !== '' && (str_contains($name, '..')
				|| $name[0] === '/' || $name[0] === '\\'
				|| preg_match('/^[a-zA-Z]:/', $name))) {
				$zip->close();
				throw new \RuntimeException("Entrée zip dangereuse : $name", 400);
			}
		}
		if (!$zip->extractTo($dest)) {
			$zip->close();
			throw new \RuntimeException('Extraction du paquet impossible', 500);
		}
		$zip->close();

		// Racine du paquet : unique sous-dossier (mxwebmail-<ver>/, owner-repo-<sha>/)
		$root = $dest;
		$items = array_values(array_diff(scandir($dest) ?: [], ['.', '..']));
		if (count($items) === 1 && is_dir("$dest/{$items[0]}")) {
			$root = "$dest/{$items[0]}";
		}
		foreach (['app/src/bootstrap.php', 'public/index.php',
			'app/config/config.example.php'] as $f) {
			if (!is_file("$root/$f")) {
				$this->rmrf($dest);
				throw new \RuntimeException("Paquet invalide ($f absent)", 502);
			}
		}

		// Preflight : tout ce qui sera écrit doit être inscriptible avant de commencer
		$blocked = [];
		foreach ($this->packageFiles($root) as $rel) {
			$target = $this->targetPath($rel);
			if (is_file($target)) {
				if (!is_writable($target)) {
					$blocked[] = $rel;
				}
			} else {
				$d = dirname($target);
				while (!is_dir($d) && dirname($d) !== $d) {
					$d = dirname($d);
				}
				if (!is_writable($d)) {
					$blocked[] = $rel;
				}
			}
		}
		if ($blocked) {
			$list = implode(', ', array_slice($blocked, 0, 10));
			throw new \RuntimeException(
				'Chemins non inscriptibles : ' . $list . (count($blocked) > 10 ? '…' : ''), 500);
		}

		$this->writeState([
			'step' => 'extracted', 'version' => $version,
			'dir' => $root, 'verified' => $verified,
		]);
		return ['step' => 'extracted', 'version' => $version, 'verified' => $verified];
	}

	// ─── Application ───

	/** Copie le paquet par-dessus l'installation (merge, avec backup). */
	public function apply(): array
	{
		$state = $this->readState();
		$root = (string) ($state['dir'] ?? '');
		if (($state['step'] ?? '') !== 'extracted' || !is_dir($root)
			|| !str_starts_with(str_replace('\\', '/', $root),
				str_replace('\\', '/', $this->dir) . '/extract-')) {
			throw new \RuntimeException('Aucune mise à jour prête à appliquer', 400);
		}
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$lock = fopen($this->dir . '/update.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			throw new \RuntimeException('Une mise à jour est déjà en cours', 409);
		}
		try {
			set_time_limit(300);
			ignore_user_abort(true);
			$version = (string) $state['version'];
			$backup = $this->dir . '/backup-' . date('Ymd-His');
			$files = $this->packageFiles($root);
			$overwritten = [];
			$created = [];
			try {
				foreach ($files as $rel) {
					$src = "$root/$rel";
					$dst = $this->targetPath($rel);
					if (is_file($dst)) {
						// Le backup doit réussir AVANT l'écrasement : sans lui le
						// rollback est impossible. On échoue donc ici, avant tout.
						$bdir = dirname("$backup/$rel");
						if (!is_dir($bdir) && !mkdir($bdir, 0755, true)) {
							throw new \RuntimeException("Backup impossible (mkdir) : $rel", 500);
						}
						if (!copy($dst, "$backup/$rel")) {
							throw new \RuntimeException("Backup échoué : $rel", 500);
						}
						$overwritten[] = $rel;
					} else {
						$created[] = $rel;
					}
					is_dir(dirname($dst)) || mkdir(dirname($dst), 0755, true);
					if (!copy($src, $dst)) {
						throw new \RuntimeException("Copie échouée : $rel", 500);
					}
				}
			} catch (\Throwable $e) {
				// Rollback automatique : restaurer les écrasés depuis le backup,
				// supprimer les créés — jamais d'installation à moitié appliquée.
				$failed = [];
				foreach ($overwritten as $rel) {
					if (!@copy("$backup/$rel", $this->targetPath($rel))) {
						$failed[] = $rel;
					}
				}
				foreach ($created as $rel) {
					@unlink($this->targetPath($rel));
				}
				$this->app->log('error', 'update rollback : ' . $e->getMessage()
					. ($failed ? ' — restaurations échouées : ' . implode(', ', $failed) : ''));
				if ($failed) {
					throw new \RuntimeException(
						$e->getMessage() . ' — rollback incomplet : ' . implode(', ', $failed), 500);
				}
				throw $e;
			}
			if ($overwritten) {
				file_put_contents("$backup/files.list", implode("\n", $overwritten) . "\n");
			}
			// Classmap périmée (MailSo/Lib rescan au prochain hit) + opcache
			@unlink($this->app->storageDir() . '/cache/classmap.php');
			if (function_exists('opcache_reset')) {
				@opcache_reset();
			}
			$this->rmrf($this->dir . '/extract-' . $this->safeName($version));
			@unlink((string) ($state['file'] ?? ''));
			$this->writeState(['step' => 'applied', 'version' => $version, 'at' => date('c')]);
			$this->app->log('info', "update $version : " . count($files) . ' fichiers copiés');
			return [
				'applied' => true,
				'version' => $version,
				'files'   => count($files),
				'backup'  => $overwritten ? 'app/storage/update/' . basename($backup) : null,
			];
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/** Abandonne une mise à jour téléchargée/extraite (refusé pendant un apply). */
	public function reset(): array
	{
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$lock = fopen($this->dir . '/update.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			if ($lock) {
				fclose($lock);
			}
			throw new \RuntimeException('Une mise à jour est en cours', 409);
		}
		try {
			$this->purgeStaging();
			@unlink($this->stateFile);
			@unlink($this->cacheFile); // prochain check frais
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return ['reset' => true];
	}

	// ─── Internes ───

	private function safeName(string $version): string
	{
		return (string) preg_replace('/[^a-zA-Z0-9.\-]/', '-', $version);
	}

	/**
	 * Chemin cible d'un fichier du paquet : rel « app/… » → dossier privé
	 * (rootDir = MXWM_APP_DIR, possiblement déplacé hors racine web) ;
	 * tout le reste (public/, .htaccess, README.md…) → racine d'installation.
	 */
	private function targetPath(string $rel): string
	{
		return str_starts_with($rel, 'app/')
			? $this->app->rootDir() . '/' . substr($rel, 4)
			: $this->app->installDir() . '/' . $rel;
	}

	/**
	 * Chemins relatifs exclus de la copie. app/storage/ est préservé sauf le
	 * .htaccess de protection, que la release doit pouvoir restaurer.
	 * public/define.php n'est écrasé que s'il manque : il peut contenir le
	 * chemin relocalisé saisi par l'admin.
	 */
	private function excluded(string $rel): bool
	{
		if ($rel === 'app/config/config.php' || $rel === '.gitignore'
			|| $rel === '.git') {
			return true;
		}
		if ($rel === 'public/define.php') {
			return is_file($this->targetPath($rel));
		}
		if ($rel === 'app/storage/.htaccess') {
			return false;
		}
		foreach (['app/storage/', '.git/', '.github/', 'dist/'] as $p) {
			if (str_starts_with($rel, $p)) {
				return true;
			}
		}
		return false;
	}

	private function packageFiles(string $root): array
	{
		$out = [];
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) {
			if (!$f->isFile()) {
				continue;
			}
			$rel = str_replace('\\', '/', substr((string) $f->getPathname(), strlen($root) + 1));
			if (!$this->excluded($rel)) {
				$out[] = $rel;
			}
		}
		return $out;
	}

	private function purgeStaging(): void
	{
		foreach (glob($this->dir . '/extract-*', GLOB_ONLYDIR) ?: [] as $d) {
			$this->rmrf($d);
		}
		foreach (glob($this->dir . '/update-*.zip') ?: [] as $f) {
			@unlink($f);
		}
	}

	private function rmrf(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		) as $f) {
			$f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
		}
		rmdir($dir);
	}
}
