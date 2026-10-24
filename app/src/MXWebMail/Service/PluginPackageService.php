<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;

/**
 * Installation / mise à jour / suppression des plugins depuis les assets
 * « plugin-<name>-<ver>.zip » de la dernière release GitHub (même dépôt
 * verrouillé que l'updater cœur — voir GitHubClient).
 *
 * Catalogue : plugins_index embarqué dans la release (généré au packaging :
 * métadonnées + sha256 + compatibilité + description) ; fallback regex sur
 * les noms d'assets pour les releases antérieures sans index.
 *
 * Staging : app/storage/update/plugins/. Verrou partagé update.lock avec
 * UpdateService — jamais d'opération cœur et plugin simultanées (le code
 * sous les pieds ne doit pas être remplacé pendant une opération).
 */
final class PluginPackageService
{
	private const MAX_ZIP = 20 * 1024 * 1024;

	private GitHubClient $gh;
	private string $dir;      // storage/update
	private string $work;     // storage/update/plugins
	private string $plugins;  // app/plugins

	public function __construct(private App $app)
	{
		$this->dir = $app->storageDir() . '/update';
		$this->work = $this->dir . '/plugins';
		$this->plugins = $app->rootDir() . '/plugins';
		$this->gh = new GitHubClient($app);
	}

	// ─── Catalogue ───

	/**
	 * Plugins installés (disque) ∪ paquets de la dernière release.
	 * Réponse publique : {configured, plugins:[…]}.
	 */
	public function catalog(bool $force = false): array
	{
		if (!$this->gh->configured()) {
			return ['configured' => false, 'plugins' => []];
		}
		$rel = $this->gh->release($force);
		$assets = $this->gh->pluginAssets($rel);

		$indexMap = [];
		$idx = $rel['plugins_index'] ?? null;
		foreach ((array) (is_array($idx) ? ($idx['plugins'] ?? []) : []) as $e) {
			if (is_array($e) && isset($e['name'])) {
				$indexMap[(string) $e['name']] = $e;
			}
		}

		$installed = [];
		foreach ($this->app->plugins()->available() as $p) {
			$installed[$p['name']] = $p;
		}

		$names = array_unique(array_merge(
			array_keys($installed), array_keys($indexMap), array_keys($assets)));
		sort($names);

		$out = [];
		foreach ($names as $name) {
			$local = $installed[$name] ?? null;
			$entry = $indexMap[$name] ?? null;
			$asset = $assets[$name] ?? null;
			$latest = (string) ($entry['version'] ?? $asset['version'] ?? '');
			$cur = (string) ($local['version'] ?? '');
			// Compat : bornes de l'index sinon du manifeste local ; inconnu = null
			if ($entry !== null) {
				$compat = $this->compatible(
					(string) ($entry['min_app'] ?? ''), (string) ($entry['max_app'] ?? ''));
			} else {
				$m = $local !== null ? $this->localManifest($name) : [];
				$compat = ($m !== [])
					? $this->compatible(
						(string) ($m['min_app'] ?? ''), (string) ($m['max_app'] ?? ''))
					: null;
			}
			$out[] = [
				'name'             => $name,
				'installed'        => $local !== null,
				'version'          => $cur,
				'latest'           => $latest,
				'update_available' => $latest !== '' && $cur !== ''
					&& version_compare($latest, $cur, '>'),
				'description'      => (string) (($local['description'] ?? '') !== ''
					? $local['description'] : ($entry['description'] ?? '')),
				'has_schema'       => (bool) ($local['has_schema'] ?? false),
				'enabled'          => (bool) ($local['enabled'] ?? false),
				'user_allowed'     => (bool) ($local['user_allowed'] ?? true),
				'in_catalog'       => $entry !== null || $asset !== null,
				'compatible'       => $compat,
				'has_sha256'       => isset($entry['sha256'])
					|| ($asset !== null
						&& $this->gh->sha256Url($rel, (string) $asset['name']) !== null),
				'size'             => (int) ($entry['size'] ?? $asset['size'] ?? 0),
			];
		}
		return ['configured' => true, 'plugins' => $out];
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
			throw new \RuntimeException('Nom de plugin invalide', 400);
		}
		if (!$this->gh->configured()) {
			throw new \RuntimeException('Catalogue non configuré', 400);
		}
		$this->gh->requireExts();
		$lock = $this->lock();
		try {
			$rel = $this->gh->release(false);
			$assets = $this->gh->pluginAssets($rel);
			$index = [];
			$idx = $rel['plugins_index'] ?? null;
			foreach ((array) (is_array($idx) ? ($idx['plugins'] ?? []) : []) as $e) {
				if (is_array($e) && isset($e['name'])) {
					$index[(string) $e['name']] = $e;
				}
			}
			$asset = $assets[$name] ?? null;
			if ($asset === null) {
				throw new \RuntimeException("Aucun paquet distant pour le plugin « $name »", 404);
			}
			$version = (string) ($index[$name]['version'] ?? $asset['version']);
			$url = (string) ($asset['url'] ?? '');
			if (!$this->gh->validUrl($url)) {
				throw new \RuntimeException('URL de téléchargement refusée', 502);
			}

			is_dir($this->work) || mkdir($this->work, 0700, true);
			$zipPath = $this->work . '/' . $asset['name']; // nom d'asset déjà validé par regex
			$extract = $this->work . '/extract-' . $name;
			try {
				$this->gh->getToFile($url, $zipPath, self::MAX_ZIP);
				if (!is_file($zipPath) || filesize($zipPath) === 0) {
					@unlink($zipPath);
					throw new \RuntimeException('Téléchargement vide', 502);
				}

				// sha256 : plugins_index en priorité, sinon asset <zip>.sha256
				// à côté — non bloquant si les deux absents
				$verified = false;
				$sum = strtolower((string) ($index[$name]['sha256'] ?? ''));
				if (!preg_match('/^[0-9a-f]{64}$/', $sum)) {
					$sum = (string) ($this->gh->assetSha256($rel, (string) $asset['name']) ?? '');
				}
				if ($sum !== '') {
					$verified = hash_equals(
						$sum, strtolower((string) hash_file('sha256', $zipPath)));
					if (!$verified) {
						throw new \RuntimeException('Somme sha256 invalide', 502);
					}
				}

				$root = $this->extractAndValidate($zipPath, $extract, $name);
				$this->preflight($name);
				$previous = $this->swap($root, $name);
				$this->invalidateCaches();
				$this->app->log('info', "plugin $name "
					. ($previous !== null ? "$previous → $version" : "installé en $version"));
				return [
					'name'     => $name,
					'version'  => $version,
					'previous' => $previous,
					'verified' => $verified,
				];
			} finally {
				@unlink($zipPath);
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
	 * Aucun backup — désinstaller = supprimer.
	 */
	public function remove(string $name): array
	{
		if (!preg_match('/^[a-z0-9-]+$/', $name)) {
			throw new \RuntimeException('Nom de plugin invalide', 400);
		}
		$target = "{$this->plugins}/$name";
		// Garde anti-symlink/traversal : la cible réelle doit rester sous app/plugins/
		$base = realpath($this->plugins);
		$real = realpath($target);
		if ($base !== false && $real !== false
			&& !str_starts_with(str_replace('\\', '/', $real),
				str_replace('\\', '/', $base) . '/')) {
			throw new \RuntimeException('Chemin de plugin refusé', 400);
		}
		$exists = $real !== false && is_dir($target);
		if (!$exists && !$this->app->pluginRepo()->has($name)) {
			throw new \RuntimeException("Plugin « $name » inconnu", 404);
		}
		$lock = $this->lock();
		try {
			$this->app->pluginRepo()->delete($name);
			if ($exists) {
				$this->rmrf($target);
			}
			$this->invalidateCaches();
			$this->app->log('info', "plugin $name désinstallé");
			return ['removed' => $name];
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	// ─── Internes ───

	/** Extraction sécurisée + validation du paquet → racine $dest/<name>. */
	private function extractAndValidate(string $zipPath, string $dest, string $name): string
	{
		$this->rmrf($dest);
		$zip = new \ZipArchive();
		if ($zip->open($zipPath) !== true) {
			throw new \RuntimeException('Paquet zip illisible', 502);
		}
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = (string) $zip->getNameIndex($i);
			// Anti path-traversal : '..', chemin absolu, lettre de lecteur
			if ($entry !== '' && (str_contains($entry, '..')
				|| $entry[0] === '/' || $entry[0] === '\\'
				|| preg_match('/^[a-zA-Z]:/', $entry))) {
				$zip->close();
				throw new \RuntimeException("Entrée zip dangereuse : $entry", 400);
			}
		}
		if (!$zip->extractTo($dest)) {
			$zip->close();
			throw new \RuntimeException('Extraction du paquet impossible', 500);
		}
		$zip->close();

		// Racine unique nommée exactement comme le plugin
		$items = array_values(array_diff(scandir($dest) ?: [], ['.', '..']));
		if (count($items) !== 1 || $items[0] !== $name || !is_dir("$dest/$name")) {
			throw new \RuntimeException("Paquet invalide (racine $name attendue)", 502);
		}
		$root = "$dest/$name";

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
			throw new \RuntimeException("Paquet invalide (main « $main » illisible)", 502);
		}
		// Compatibilité : min_app/max_app du manifeste distant vs APP_VERSION
		if ($this->compatible(
				(string) ($m['min_app'] ?? ''), (string) ($m['max_app'] ?? '')) === false) {
			throw new \RuntimeException(
				"Plugin incompatible avec MXWebMail " . APP_VERSION
				. (($m['min_app'] ?? '') !== '' ? " (min {$m['min_app']}" : '')
				. (($m['max_app'] ?? '') !== '' ? ", max {$m['max_app']}" : '') . ')', 409);
		}
		return $root;
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
	 * Remplace app/plugins/<name> par $src (renommages — même volume) ;
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
			throw new \RuntimeException('Une mise à jour est en cours', 409);
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
