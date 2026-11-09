<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;

/**
 * Installation / mise à jour / suppression des plugins lus directement dans
 * le dépôt GitHub au ref update.plugins_ref ('release' = tag de la dernière
 * release, 'main', tout ref - voir GitHubClient) : tree git récursif pour la
 * liste des fichiers, raw.githubusercontent.com pinné sur le sha du commit
 * pour les contenus, chaque fichier vérifié par sha1 de blob git + taille.
 *
 * Source alternative dev/tests : update.plugins_local (dossier disque -
 * catalogue + install hors réseau, sans ancre de vérification externe).
 *
 * Staging : app/storage/update/plugins/. Verrou partagé update.lock avec
 * UpdateService - jamais d'opération cœur et plugin simultanées (le code
 * sous les pieds ne doit pas être remplacé pendant une opération).
 */
final class PluginPackageService
{
	private GitHubClient $gh;
	private string $dir;      // storage/update
	private string $work;     // storage/update/plugins
	private string $plugins;  // app/plugins
	private ?array $remote = null; // {ref, sha, tree} mémoïsé par requête

	public function __construct(private App $app)
	{
		$this->dir = $app->storageDir() . '/update';
		$this->work = $this->dir . '/plugins';
		$this->plugins = $app->rootDir() . '/plugins';
		$this->gh = new GitHubClient($app);
	}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	// ─── Catalogue ───

	/**
	 * Plugins installés (disque) ∪ source distante (dépôt au ref ou
	 * plugins_local). Réponse publique : {configured, plugins:[…]}.
	 */
	public function catalog(bool $force = false): array
	{
		$installed = [];
		foreach ($this->app->plugins()->available() as $p) {
			$installed[$p['name']] = $p;
		}

		$local = $this->gh->pluginLocalDir();
		if ($local !== null) {
			$remote = $this->localCatalog($local);
		} elseif ($this->gh->configured()) {
			$remote = $this->gitCatalog($force);
		} else {
			return ['configured' => false, 'plugins' => []];
		}

		$names = array_unique(array_merge(array_keys($installed), array_keys($remote)));
		sort($names);

		$out = [];
		foreach ($names as $name) {
			$p = $installed[$name] ?? null;
			$r = $remote[$name] ?? null;
			$cur = (string) ($p['version'] ?? '');
			$latest = (string) ($r['version'] ?? '');
			// Compat : bornes du manifeste distant sinon du manifeste installé
			if ($r !== null) {
				$compat = $this->compatible(
					(string) ($r['min_app'] ?? ''), (string) ($r['max_app'] ?? ''));
			} else {
				$m = $p !== null ? $this->localManifest($name) : [];
				$compat = ($m !== [])
					? $this->compatible(
						(string) ($m['min_app'] ?? ''), (string) ($m['max_app'] ?? ''))
					: null;
			}
			$out[] = [
				'name'             => $name,
				'installed'        => $p !== null,
				'version'          => $cur,
				'latest'           => $latest,
				'update_available' => $latest !== '' && $cur !== ''
					&& version_compare($latest, $cur, '>'),
				'label'            => (string) (($p['label'] ?? '') !== ''
					? $p['label'] : ($r['label'] ?? '')),
				'description'      => (string) (($p['description'] ?? '') !== ''
					? $p['description'] : ($r['description'] ?? '')),
				'has_schema'       => (bool) ($p['has_schema'] ?? false),
				'enabled'          => (bool) ($p['enabled'] ?? false),
				'user_allowed'     => (bool) ($p['user_allowed'] ?? false),
				'in_catalog'       => $r !== null,
				'compatible'       => $compat,
				'size'             => (int) ($r['size'] ?? 0),
			];
		}
		return ['configured' => true, 'plugins' => $out];
	}

	// ─── Sources du catalogue ───

	/** Dossier local (update.plugins_local) > name => métadonnées manifeste. */
	private function localCatalog(string $dir): array
	{
		$out = [];
		foreach (glob(rtrim($dir, '/\\') . '/*/plugin.json') ?: [] as $f) {
			$name = basename(dirname($f));
			if (!preg_match('/^[a-z0-9-]+$/', $name)) {
				continue;
			}
			$m = json_decode((string) file_get_contents($f), true);
			if (!is_array($m)) {
				continue;
			}
			$out[$name] = [
				'version'     => (string) ($m['version'] ?? ''),
				'label'       => (string) ($m['label'] ?? ''),
				'description' => (string) ($m['description'] ?? ''),
				'min_app'     => (string) ($m['min_app'] ?? ''),
				'max_app'     => (string) ($m['max_app'] ?? ''),
				'size'        => $this->dirSize(dirname($f)),
			];
		}
		return $out;
	}

	/**
	 * Dépôt au ref résolu > name => métadonnées manifeste.
	 * plugin.json de chaque plugin via raw (cache par blob sha - seuls les
	 * manifestes modifiés sont re-téléchargés).
	 */
	private function gitCatalog(bool $force): array
	{
		$src = $this->gitSource($force);
		$out = [];
		foreach ($src['tree'] as $path => $meta) {
			if (!preg_match('#^app/plugins/([a-z0-9-]+)/plugin\.json$#', $path, $m)) {
				continue;
			}
			$mf = $this->remoteManifest($src['sha'], $m[1], $path, $meta);
			if ($mf === null) {
				continue;
			}
			$out[$m[1]] = [
				'version'     => (string) ($mf['version'] ?? ''),
				'label'       => (string) ($mf['label'] ?? ''),
				'description' => (string) ($mf['description'] ?? ''),
				'min_app'     => (string) ($mf['min_app'] ?? ''),
				'max_app'     => (string) ($mf['max_app'] ?? ''),
				'size'        => 0,
			];
		}
		foreach ($src['tree'] as $path => $meta) {
			if (preg_match('#^app/plugins/([a-z0-9-]+)/#', $path, $m) && isset($out[$m[1]])) {
				$out[$m[1]]['size'] += $meta['size'];
			}
		}
		return $out;
	}

	/** {ref, sha, tree} - ref>commit à TTL, tree immutable (mémoïsé). */
	private function gitSource(bool $force): array
	{
		if (!$force && $this->remote !== null) {
			return $this->remote;
		}
		$ref = $this->gh->pluginsRef($force);
		$c = $this->gh->resolveCommit($ref, $force);
		return $this->remote = [
			'ref'  => $ref,
			'sha'  => $c['sha'],
			'tree' => $this->gh->tree($c['tree']),
		];
	}

	/** plugin.json distant - cache par blob sha, contenu vérifié (sha1+size). */
	private function remoteManifest(string $commit, string $name, string $path, array $blob): ?array
	{
		$cacheDir = $this->app->storageDir() . '/cache';
		$file = "$cacheDir/plugin-manifest-$name-{$blob['sha']}.json";
		$mf = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		if (is_array($mf)) {
			return $mf;
		}
		try {
			$raw = $this->gh->get($this->gh->rawUrl($commit, $path));
		} catch (\RuntimeException $e) {
			$this->app->log('error', "plugin.json $name : " . $e->getMessage());
			return null;
		}
		if (strlen($raw) !== $blob['size'] || GitHubClient::blobId($raw) !== $blob['sha']) {
			$this->app->log('error', "plugin.json $name : contenu non concordant avec le tree");
			return null;
		}
		$mf = json_decode($raw, true);
		if (!is_array($mf)) {
			$this->app->log('error', "plugin.json $name : JSON illisible");
			return null;
		}
		// GC : manifestes obsolètes du même plugin (blob sha différents)
		foreach (glob("$cacheDir/plugin-manifest-$name-*.json") ?: [] as $old) {
			if ($old !== $file) {
				@unlink($old);
			}
		}
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (file_put_contents($tmp, json_encode($mf, JSON_UNESCAPED_SLASHES)) !== false) {
			rename($tmp, $file) || @unlink($tmp);
		}
		return $mf;
	}

	// ─── Installation / mise à jour ───

	/**
	 * Installe ou met à jour un plugin (opération synchrone, idempotente :
	 * réinstaller la même version = réparation). Retourne
	 * {name, version, previous, verified}.
	 */
	public function install(string $name): array
	{
		if (!preg_match('/^[a-z0-9-]+$/', $name)) {
			throw new \RuntimeException($this->t('errors.pkg_invalid_name'), 400);
		}
		$local = $this->gh->pluginLocalDir();
		if ($local === null) {
			if (!$this->gh->configured()) {
				throw new \RuntimeException($this->t('errors.pkg_catalog_unconfigured'), 400);
			}
			$this->gh->requireExts();
		}
		$lock = $this->lock();
		try {
			$extract = $this->work . '/extract-' . $name;
			try {
				if ($local !== null) {
					$root = $this->stageLocal($local, $extract, $name);
					$verified = false; // pas d'ancre de vérification externe
				} else {
					$root = $this->stageRemote($this->gitSource(false), $extract, $name);
					$verified = true; // chaque fichier : taille + sha1 de blob git
				}
				$m = $this->validatePluginDir($root, $name);
				$this->preflight($name);
				$previous = $this->swap($root, $name);
				$this->invalidateCaches();
				$version = (string) $m['version'];
				$this->app->log('info', "plugin $name "
					. ($previous !== null ? "$previous > $version" : "installé en $version"));
				return [
					'name'     => $name,
					'version'  => $version,
					'previous' => $previous,
					'verified' => $verified,
				];
			} finally {
				$this->rmrf($extract);
			}
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	// ─── Désinstallation ───

	/**
	 * Suppression définitive : ligne DB purgée + dossier supprimé.
	 * Aucun backup - désinstaller = supprimer.
	 */
	public function remove(string $name): array
	{
		if (!preg_match('/^[a-z0-9-]+$/', $name)) {
			throw new \RuntimeException($this->t('errors.pkg_invalid_name'), 400);
		}
		$target = "{$this->plugins}/$name";
		// Garde anti-symlink/traversal : la cible réelle doit rester sous app/plugins/
		$base = realpath($this->plugins);
		$real = realpath($target);
		if ($base !== false && $real !== false
			&& !str_starts_with(str_replace('\\', '/', $real),
				str_replace('\\', '/', $base) . '/')) {
			throw new \RuntimeException($this->t('errors.pkg_path_denied'), 400);
		}
		$exists = $real !== false && is_dir($target);
		if (!$exists && !$this->app->pluginRepo()->has($name)) {
			throw new \RuntimeException($this->t('errors.plugin_not_found', ['name' => $name]), 404);
		}
		$lock = $this->lock();
		try {
			$this->preflight($name);
			if ($exists) {
				$this->rmrf($target);
			}
			$this->app->pluginRepo()->delete($name);
			$this->invalidateCaches();
			$this->app->log('info', "plugin $name désinstallé");
			return ['removed' => $name];
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	// ─── Internes ───

	/** Copie récursive <dir>/<name> > staging $dest/<name> (source locale). */
	private function stageLocal(string $dir, string $dest, string $name): string
	{
		$srcDir = rtrim($dir, '/\\') . '/' . $name;
		if (!is_dir($srcDir) || !is_file("$srcDir/plugin.json")) {
			throw new \RuntimeException($this->t('errors.plugin_missing_local', ['name' => $name]), 404);
		}
		$this->rmrf($dest);
		$root = "$dest/$name";
		$base = str_replace('\\', '/', $srcDir);
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
				$srcDir, \FilesystemIterator::SKIP_DOTS)) as $f) {
			if (!$f->isFile()) {
				continue;
			}
			$rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($base) + 1);
			@mkdir(dirname("$root/$rel"), 0700, true);
			copy($f->getPathname(), "$root/$rel");
		}
		return $root;
	}

	/**
	 * Télécharge chaque blob app/plugins/<name>/** du tree via raw (pinné au
	 * sha du commit) > staging $dest/<name>. Chaque fichier est vérifié :
	 * taille + sha1 de blob git du tree (anti corruption/CDN raw obsolète).
	 */
	private function stageRemote(array $src, string $dest, string $name): string
	{
		$prefix = "app/plugins/$name/";
		$files = [];
		foreach ($src['tree'] as $path => $meta) {
			if (str_starts_with($path, $prefix)) {
				$files[substr($path, strlen($prefix))] = $meta;
			}
		}
		if ($files === [] || !isset($files['plugin.json'])) {
			throw new \RuntimeException(
				"Plugin \"$name\" absent du dépôt (ref {$src['ref']})", 404);
		}
		$this->rmrf($dest);
		$root = "$dest/$name";
		foreach ($files as $rel => $meta) {
			// Anti-traversal (défense en profondeur - la source est l'API GitHub)
			if ($rel === '' || str_contains($rel, '..') || str_contains($rel, '\\')
				|| $rel[0] === '/' || preg_match('/^[a-zA-Z]:/', $rel)) {
				throw new \RuntimeException("Chemin refusé : $rel", 502);
			}
			$raw = $this->gh->get($this->gh->rawUrl($src['sha'], $prefix . $rel));
			if (strlen($raw) !== $meta['size'] || GitHubClient::blobId($raw) !== $meta['sha']) {
				throw new \RuntimeException("Contenu non concordant : $rel", 502);
			}
			$target = "$root/$rel";
			@mkdir(dirname($target), 0700, true);
			file_put_contents($target, $raw);
		}
		return $root;
	}

	/** Validation du dossier stagé > manifeste (plugin.json concordant, semver, main sain, compat). */
	private function validatePluginDir(string $root, string $name): array
	{
		$m = json_decode((string) @file_get_contents("$root/plugin.json"), true);
		if (!is_array($m) || ($m['name'] ?? '') !== $name) {
			throw new \RuntimeException("Paquet invalide (plugin.json absent ou name ≠ $name)", 502);
		}
		if (!preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? ''))) {
			throw new \RuntimeException('Paquet invalide (version non semver)', 502);
		}
		// main : fichier existant résolu sous la racine (anti-traversal manifeste)
		$main = (string) ($m['main'] ?? '');
		$rootReal = realpath($root);
		$mainReal = realpath("$root/$main");
		if ($main === '' || $rootReal === false || $mainReal === false
			|| !str_starts_with(str_replace('\\', '/', $mainReal),
				str_replace('\\', '/', $rootReal) . '/')) {
			throw new \RuntimeException("Paquet invalide (main \"$main\" illisible)", 502);
		}
		// Compatibilité : min_app/max_app du manifeste distant vs APP_VERSION
		if ($this->compatible(
				(string) ($m['min_app'] ?? ''), (string) ($m['max_app'] ?? '')) === false) {
			throw new \RuntimeException(
				"Plugin incompatible avec MXWebMail " . APP_VERSION
				. (($m['min_app'] ?? '') !== '' ? " (min {$m['min_app']}" : '')
				. (($m['max_app'] ?? '') !== '' ? ", max {$m['max_app']}" : '') . ')', 409);
		}
		return $m;
	}

	/** Preflight : app/plugins/ et la cible éventuelle doivent être inscriptibles. */
	private function preflight(string $name): void
	{
		$blocked = [];
		$d = is_dir($this->plugins) ? $this->plugins : dirname($this->plugins);
		if (!is_dir($d) || !is_writable($d)) {
			$blocked[] = 'app/plugins';
		}
		$target = "{$this->plugins}/$name";
		if (is_dir($target) && !is_writable($target)) {
			$blocked[] = "app/plugins/$name";
		}
		if ($blocked) {
			throw new \RuntimeException(
				'Chemins non inscriptibles : ' . implode(', ', $blocked), 500);
		}
	}

	/**
	 * Remplace app/plugins/<name> par $src (renommages - même volume) ;
	 * l'ancien est déplacé dans un backup unique par plugin (rollback +
	 * restauration manuelle). Retourne la version précédente ou null.
	 */
	private function swap(string $src, string $name): ?string
	{
		set_time_limit(120);
		ignore_user_abort(true);
		$target = "{$this->plugins}/$name";
		$previous = null;
		$backup = null;
		if (is_dir($target)) {
			$v = (string) ($this->localManifest($name)['version'] ?? '');
			$previous = $v !== '' ? $v : null;
			foreach (glob($this->work . "/backup-$name-*", GLOB_ONLYDIR) ?: [] as $old) {
				$this->rmrf($old); // un seul backup conservé par plugin
			}
			$backup = $this->work . '/backup-' . $name . '-' . date('YmdHis');
			if (!rename($target, $backup)) {
				throw new \RuntimeException("Backup impossible : $name", 500);
			}
		}
		if (!rename($src, $target)) {
			if ($backup !== null) {
				@rename($backup, $target); // rollback
			}
			throw new \RuntimeException("Installation impossible : $name", 500);
		}
		return $previous;
	}

	/** min_app/max_app vs APP_VERSION ; les deux vides = aucune borne. */
	private function compatible(string $min, string $max): ?bool
	{
		if ($min !== '' && !preg_match('/^\d+\.\d+\.\d+$/', $min)) {
			$min = '';
		}
		if ($max !== '' && !preg_match('/^\d+\.\d+\.\d+$/', $max)) {
			$max = '';
		}
		if ($min !== '' && !version_compare(APP_VERSION, $min, '>=')) {
			return false;
		}
		if ($max !== '' && !version_compare(APP_VERSION, $max, '<=')) {
			return false;
		}
		return ($min !== '' || $max !== '') ? true : null;
	}

	private function localManifest(string $name): array
	{
		$file = "{$this->plugins}/$name/plugin.json";
		return is_file($file)
			? (json_decode((string) file_get_contents($file), true) ?: [])
			: [];
	}

	/** Verrou partagé avec UpdateService (storage/update/update.lock). */
	private function lock()
	{
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$lock = fopen($this->dir . '/update.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			if ($lock) {
				fclose($lock);
			}
			throw new \RuntimeException($this->t('errors.updating'), 409);
		}
		return $lock;
	}

	private function invalidateCaches(): void
	{
		// Classmap (MailSo/Lib rescan au prochain hit) + opcache
		@unlink($this->app->storageDir() . '/cache/classmap.php');
		if (function_exists('opcache_reset')) {
			@opcache_reset();
		}
	}

	private function dirSize(string $dir): int
	{
		$n = 0;
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
				$dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
			if ($f->isFile()) {
				$n += $f->getSize();
			}
		}
		return $n;
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
			$ok = $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
			if (!$ok) {
				throw new \RuntimeException(
					'Suppression impossible : ' . $f->getPathname(), 500);
			}
		}
		if (!@rmdir($dir)) {
			throw new \RuntimeException("Suppression impossible : $dir", 500);
		}
	}
}
