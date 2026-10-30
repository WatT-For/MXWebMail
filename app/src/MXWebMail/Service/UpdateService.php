<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;
use MXWebMail\Repository\Database;

/**
 * Mises à jour depuis GitHub Releases (onglet admin « À propos »).
 *
 * HTTP/release/config : délégués à GitHubClient (partagé avec
 * PluginPackageService — même dépôt verrouillé, même cache release).
 *
 * États persistés dans app/storage/update/state.json :
 *   downloaded → extracted → applied → rolled_back ; reset() pour abandonner.
 * La copie est en mode « merge » : les fichiers absents de la release ne sont
 * pas supprimés ; chaque fichier écrasé est sauvegardé dans
 * app/storage/update/backup-<Ymd-His>/ (rollback via rollback() ou manuel).
 * Le backup contient aussi manifest.json (fichiers + état DB) et, sous SQLite,
 * une copie des bases (webmail.sqlite + db/users/*.sqlite).
 *
 * Intégrité du paquet : files.sha256 à la racine (manifeste par fichier,
 * généré au packaging) est vérifié à l'extraction et sert ensuite de baseline
 * pour détecter les fichiers locaux modifiés. Les prérequis (PHP, version
 * source minimale) sont les constantes APP_REQ_* du App.php du paquet.
 *
 * Pendant apply() le flag storage/update/applying bloque l'API non-admin
 * (503 — voir App::handleRequest). Après apply, migrations_pending=true
 * attend finalize() (requête admin → nouveau code → migrations réelles ;
 * sinon la migration paresseuse d'App::db() reste le filet de sécurité).
 */
final class UpdateService
{
	private const MAX_ZIP   = 200 * 1024 * 1024;

	private GitHubClient $gh;
	private string $dir;
	private string $stateFile;

	public function __construct(private App $app)
	{
		$this->dir = $app->storageDir() . '/update';
		$this->stateFile = $this->dir . '/state.json';
		$this->gh = new GitHubClient($app);
	}

	/** État local sans appel réseau — utilisable dans bootstrap(). */
	public function status(): array
	{
		$state = $this->readState();
		if ($state) {
			unset($state['dir'], $state['file']); // chemins internes, pas renvoyés
		}
		return [
			'configured' => $this->gh->configured(),
			'current'    => APP_VERSION,
			'state'      => $state,
			'applying'   => is_file($this->applyingFlag()),
			'db'         => $this->dbStatus(),
			'rollback'   => $this->lastBackup(),
		];
	}

	/** Driver + version de schéma + dépassement éventuel (rollback fichiers). */
	private function dbStatus(): array
	{
		$out = ['driver' => null, 'schema_version' => null, 'schema_ahead' => null];
		try {
			$db = $this->app->db();
			$out['driver'] = (string) $db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
			$v = $db->meta('schema_version');
			$out['schema_version'] = $v === null ? null : (int) $v;
			$a = $db->meta('schema_ahead');
			$out['schema_ahead'] = ($a !== null && $a !== '') ? (int) $a : null;
		} catch (\Throwable) {}
		return $out;
	}

	/** Dernier backup exploitable (manifest.json présent), pour l'UI rollback. */
	private function lastBackup(): ?array
	{
		$dirs = glob($this->dir . '/backup-*', GLOB_ONLYDIR) ?: [];
		rsort($dirs);
		foreach ($dirs as $d) {
			$m = is_file("$d/manifest.json")
				? json_decode((string) file_get_contents("$d/manifest.json"), true) : null;
			if (is_array($m)) {
				return [
					'dir'            => basename($d),
					'from'           => (string) ($m['from'] ?? ''),
					'to'             => (string) ($m['to'] ?? ''),
					'at'             => (string) ($m['at'] ?? ''),
					'schema_version' => $m['db']['schema_version'] ?? null,
					'db_driver'      => $m['db']['driver'] ?? null,
				];
			}
		}
		return null;
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
		if (!$this->gh->configured()) {
			return ['configured' => false, 'current' => APP_VERSION];
		}
		$this->gh->requireExts();
		$rel = $this->gh->release($force);
		$latest = $this->normalizeTag((string) $rel['tag_name']);
		$asset = $this->gh->coreAsset($rel);
		return [
			'configured'   => true,
			'channel'      => $this->gh->channel(),
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

	private function normalizeTag(string $tag): string
	{
		return ltrim(trim($tag), "vV");
	}

	// ─── Téléchargement + extraction ───

	/** Télécharge le zip de la release, vérifie la somme, extrait (prepare). */
	public function download(): array
	{
		if (!$this->gh->configured()) {
			throw new \RuntimeException('Mise à jour non configurée', 400);
		}
		$this->gh->requireExts();
		$rel = $this->gh->release(false);
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

		$asset = $this->gh->coreAsset($rel)
			?? throw new \RuntimeException('Aucun paquet .zip dans la release', 502);
		$url = (string) ($asset['download_url'] ?? '');
		if (!$this->gh->validUrl($url)) {
			throw new \RuntimeException('URL de téléchargement refusée', 502);
		}

		$this->purgeStaging();
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$zipPath = $this->dir . '/update-' . $this->safeName($latest) . '.zip';
		$this->gh->getToFile($url, $zipPath, self::MAX_ZIP);
		if (!is_file($zipPath) || filesize($zipPath) === 0) {
			@unlink($zipPath);
			throw new \RuntimeException('Téléchargement vide', 502);
		}

		// Somme sha256 publiée à côté de l'asset — non bloquante si absente
		$verified = false;
		$sum = $this->gh->assetSha256($rel, (string) $asset['name']);
		if ($sum !== null) {
			$verified = hash_equals($sum, strtolower((string) hash_file('sha256', $zipPath)));
			if (!$verified) {
				@unlink($zipPath);
				throw new \RuntimeException('Somme sha256 invalide', 502);
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

		// Manifeste d'intégrité par fichier (files.sha256, généré au packaging).
		// Absent sur les anciennes releases → non bloquant ; présent → strict.
		$integrity = null;
		if (is_file("$root/files.sha256")) {
			$integrity = $this->verifyManifest($root);
			if ($integrity !== true) {
				$this->rmrf($dest);
				throw new \RuntimeException(
					'Intégrité du paquet invalide : ' . $integrity, 502);
			}
		}

		// Prérequis déclarés par le paquet (constantes APP_REQ_* de App.php).
		$this->checkRequirements($root, $version);

		// Fichiers locaux modifiés : baseline = manifeste de la version
		// installée (files.sha256 copié à la racine par l'update précédent).
		$modified = $this->locallyModified();

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
			'integrity' => $integrity, 'modified' => $modified,
		]);
		return ['step' => 'extracted', 'version' => $version,
			'verified' => $verified, 'integrity' => $integrity,
			'modified' => $modified];
	}

	/**
	 * Vérifie files.sha256 à la racine du paquet : chaque fichier listé doit
	 * exister avec la somme annoncée, et tout fichier du paquet (hors le
	 * manifeste lui-même) doit y figurer. true ou message d'erreur.
	 */
	private function verifyManifest(string $root): bool|string
	{
		$expected = [];
		foreach (file("$root/files.sha256", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
			if (!preg_match('/^([0-9a-f]{64})\s+(.+)$/', trim($line), $m)) {
				continue;
			}
			$expected[str_replace('\\', '/', $m[2])] = strtolower($m[1]);
		}
		if (!$expected) {
			return 'files.sha256 illisible';
		}
		foreach ($expected as $rel => $sha) {
			if (!is_file("$root/$rel")) {
				return "fichier du manifeste absent : $rel";
			}
			if (hash_file('sha256', "$root/$rel") !== $sha) {
				return "somme invalide : $rel";
			}
		}
		foreach ($this->packageFiles($root) as $rel) {
			if ($rel !== 'files.sha256' && !isset($expected[$rel])) {
				return "fichier absent du manifeste : $rel";
			}
		}
		return true;
	}

	/**
	 * Prérequis du paquet : constantes APP_REQ_PHP / APP_REQ_FROM du App.php
	 * embarqué, lues par regex (jamais exécuté) — optionnelles :
	 *   define('APP_REQ_PHP', '>=8.4')     — contraintes version_compare
	 *                                        séparées par virgule/espace
	 *   define('APP_REQ_FROM', '>=0.40.0') — version source minimale
	 *                                        autorisée à appliquer ce paquet
	 */
	private function checkRequirements(string $root, string $target): void
	{
		$appPhp = "$root/app/src/MXWebMail/Application/App.php";
		$src = is_file($appPhp) ? (string) file_get_contents($appPhp) : '';
		if ($src === '') {
			return;
		}
		preg_match("/define\\('APP_REQ_PHP',\\s*'([^']*)'/", $src, $m);
		foreach (preg_split('/[\s,]+/', trim($m[1] ?? '')) ?: [] as $c) {
			if ($c === '') {
				continue;
			}
			if (!preg_match('/^(>=|<=|>|<|!=|==|=)?\s*([0-9][0-9a-zA-Z.\-]*)$/', $c, $cm)) {
				throw new \RuntimeException("APP_REQ_PHP : contrainte invalide « $c »", 502);
			}
			$op = ($cm[1] === '' || $cm[1] === '=') ? '==' : $cm[1];
			if (!version_compare(PHP_VERSION, $cm[2], $op)) {
				throw new \RuntimeException(
					"PHP $c requis par v$target (installé : " . PHP_VERSION . ')', 400);
			}
		}
		preg_match("/define\\('APP_REQ_FROM',\\s*'([^']*)'/", $src, $m);
		$from = trim($m[1] ?? '');
		if ($from !== '' && !version_compare(APP_VERSION, ltrim($from, 'vV'), '>=')) {
			throw new \RuntimeException(
				"v$target exige une source ≥ $from (installée : v" . APP_VERSION . ')', 400);
		}
	}

	/**
	 * Fichiers installés dont le contenu diffère du manifeste de la version
	 * courante (files.sha256 à la racine d'installation — baseline posée par
	 * l'update précédent). Liste bornée, affichée comme warning avant apply.
	 */
	private function locallyModified(): array
	{
		$baseline = $this->app->installDir() . '/files.sha256';
		if (!is_file($baseline)) {
			return [];
		}
		$out = [];
		foreach (file($baseline, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
			if (!preg_match('/^([0-9a-f]{64})\s+(.+)$/', trim($line), $m)) {
				continue;
			}
			$rel = str_replace('\\', '/', $m[2]);
			$target = $this->targetPath($rel);
			if (is_file($target) && hash_file('sha256', $target) !== strtolower($m[1])) {
				$out[] = $rel;
				if (count($out) >= 100) {
					break;
				}
			}
		}
		return $out;
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
		$lock = $this->lock();
		try {
			// Flag lu par App::handleRequest : l'API non-admin répond 503 tant
			// que la copie est en cours (jamais de mix ancien/nouveau code).
			@file_put_contents($this->applyingFlag(), (string) time());
			set_time_limit(300);
			ignore_user_abort(true);
			$version = (string) $state['version'];
			$backup = $this->dir . '/backup-' . date('Ymd-His');
			// Sauvegarde DB AVANT l'écrasement des fichiers : SQLite → copie
			// checkpointée ; MariaDB → manifeste seul (dump externe requis).
			$dbInfo = $this->backupDatabase("$backup/db");
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
			is_dir($backup) || mkdir($backup, 0755, true);
			file_put_contents("$backup/manifest.json", json_encode([
				'from'        => APP_VERSION,
				'to'          => $version,
				'at'          => date('c'),
				'overwritten' => $overwritten,
				'created'     => $created,
				'db'          => $dbInfo,
			], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
			// Classmap périmée (MailSo/Lib rescan au prochain hit) + opcache
			@unlink($this->app->storageDir() . '/cache/classmap.php');
			if (function_exists('opcache_reset')) {
				@opcache_reset();
			}
			// update.json racine : reliquat des paquets ≤ 0.43.x — les
			// prérequis sont désormais les constantes APP_REQ_* de App.php.
			@unlink($this->app->installDir() . '/update.json');
			$this->rmrf($this->dir . '/extract-' . $this->safeName($version));
			@unlink((string) ($state['file'] ?? ''));
			$this->pruneBackups(3);
			$this->writeState([
				'step' => 'applied', 'version' => $version, 'at' => date('c'),
				'migrations_pending' => true, 'backup' => basename($backup),
			]);
			$this->app->log('info', "update $version : " . count($files) . ' fichiers copiés');
			return [
				'applied' => true,
				'version' => $version,
				'files'   => count($files),
				'migrations_pending' => true,
				'backup'  => 'app/storage/update/' . basename($backup),
			];
		} finally {
			@unlink($this->applyingFlag());
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Migrations du code fraîchement installé : base globale + bases contacts
	 * par utilisateur. Appelé par /api/admin/update/finalize juste après apply
	 * — requête nouvelle → nouveau code chargé → les migrations de la version
	 * cible s'exécutent réellement. Le fallback paresseux (App::db → migrate)
	 * couvre le cas où finalize n'est jamais appelé.
	 */
	public function finalize(): array
	{
		$state = $this->readState();
		if (($state['step'] ?? '') !== 'applied') {
			throw new \RuntimeException('Aucune mise à jour à finaliser', 400);
		}
		$this->runMigrations();
		// Reliquat des installs ≤ 0.44 : l'assistant /setup est intégré au
		// cœur — le fichier standalone ne doit pas rester dans le webroot.
		try {
			$setupDir = $this->app->installDir() . '/public/setup';
			if (is_dir($setupDir)) {
				$this->rmrf($setupDir);
				$this->app->log('info', 'update : reliquat public/setup supprimé');
			}
		} catch (\Throwable $e) {
			$this->app->log('error',
				'update : suppression de public/setup échouée — ' . $e->getMessage());
		}
		$state['migrations_pending'] = false;
		$this->writeState($state);
		$schema = null;
		try {
			$v = $this->app->db()->meta('schema_version');
			$schema = $v === null ? null : (int) $v;
		} catch (\Throwable) {}
		$this->app->log('info', 'update ' . $state['version']
			. ' : migrations finalisées (schéma v' . ($schema ?? '?') . ')');
		return ['finalized' => true, 'version' => (string) $state['version'],
			'schema_version' => $schema];
	}

	/**
	 * Rollback du dernier backup : fichiers restaurés/créés supprimés, bases
	 * SQLite recopiées. Sous MariaDB les migrations ne sont pas réversibles :
	 * si le schéma a avancé depuis le backup, refus sauf force (l'admin a
	 * restauré son dump au préalable).
	 */
	public function rollback(bool $force = false): array
	{
		$dirs = glob($this->dir . '/backup-*', GLOB_ONLYDIR) ?: [];
		rsort($dirs);
		$backup = null;
		$manifest = null;
		foreach ($dirs as $d) {
			$m = is_file("$d/manifest.json")
				? json_decode((string) file_get_contents("$d/manifest.json"), true) : null;
			if (is_array($m)) {
				$backup = $d;
				$manifest = $m;
				break;
			}
		}
		if ($backup === null) {
			throw new \RuntimeException('Aucun backup de mise à jour disponible', 400);
		}

		$driver = (string) ($manifest['db']['driver'] ?? '');
		$schemaBefore = (int) ($manifest['db']['schema_version'] ?? 0);
		if ($driver === 'mysql' && !$force) {
			$cur = 0;
			try {
				$cur = (int) ($this->app->db()->meta('schema_version') ?? 0);
			} catch (\Throwable) {}
			if ($cur > $schemaBefore) {
				throw new \RuntimeException(
					"Schéma MariaDB en v$cur (backup v$schemaBefore) : restaurez la base "
					. 'depuis votre dump puis relancez le rollback avec force=1', 409);
			}
		}

		$lock = $this->lock();
		try {
			@file_put_contents($this->applyingFlag(), (string) time());
			set_time_limit(300);
			ignore_user_abort(true);

			$failed = [];
			foreach ((array) ($manifest['created'] ?? []) as $rel) {
				is_string($rel) && @unlink($this->targetPath($rel));
			}
			$restored = 0;
			foreach ((array) ($manifest['overwritten'] ?? []) as $rel) {
				$src = "$backup/$rel";
				if (!is_string($rel) || !is_file($src)) {
					continue;
				}
				@copy($src, $this->targetPath($rel)) ? $restored++ : $failed[] = $rel;
			}

			$dbRestored = false;
			if ($driver === 'sqlite' && is_dir("$backup/db")) {
				$dbRestored = $this->restoreSqlite("$backup/db");
			}

			@unlink($this->app->storageDir() . '/cache/classmap.php');
			if (function_exists('opcache_reset')) {
				@opcache_reset();
			}
			$this->writeState([
				'step' => 'rolled_back', 'version' => (string) ($manifest['from'] ?? ''),
				'at' => date('c'), 'from_backup' => basename($backup),
			]);
			$this->app->log('info', 'update rollback → v' . ($manifest['from'] ?? '?')
				. " : $restored fichiers restaurés"
				. ($failed ? ' — échecs : ' . implode(', ', $failed) : ''));
			return [
				'rolled_back' => true,
				'version'     => (string) ($manifest['from'] ?? ''),
				'files'       => $restored,
				'db_restored' => $dbRestored,
				'failed'      => $failed,
			];
		} finally {
			@unlink($this->applyingFlag());
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Migrations post-update : base globale puis chaque base contacts
	 * (storage/db/users/*.sqlite — SQLite aussi sous MariaDB).
	 */
	private function runMigrations(): void
	{
		$this->app->db()->migrate();
		foreach (glob($this->app->storageDir() . '/db/users/*.sqlite') ?: [] as $f) {
			try {
				Database::sqliteFile($f)->migrate();
			} catch (\Throwable $e) {
				// Une base utilisateur en échec n'invalide pas l'update : elle
				// sera retentée à son prochain accès (migration paresseuse).
				$this->app->log('error', 'update : migration ' . basename($f)
					. ' échouée — ' . $e->getMessage());
			}
		}
	}

	// ─── Sauvegarde / restauration DB ───

	/**
	 * Sauvegarde pré-update dans $dest. SQLite : wal_checkpoint puis copie des
	 * fichiers (webmail.sqlite + users/*.sqlite, sidecars -wal/-shm inclus).
	 * MariaDB : manifeste uniquement — le dump relève de l'admin (mysqldump).
	 * Non bloquant : l'échec est consigné dans le manifeste, pas fatal.
	 */
	private function backupDatabase(string $dest): array
	{
		$info = ['driver' => null, 'schema_version' => null, 'saved' => false];
		try {
			$db = $this->app->db();
			$pdo = $db->pdo();
			$driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
			$info['driver'] = $driver;
			try {
				$v = $db->meta('schema_version');
				$info['schema_version'] = $v === null ? null : (int) $v;
			} catch (\Throwable) {}

			if ($driver === 'sqlite') {
				// Checkpoint : le fichier copié doit être auto-suffisant
				// (les données en -wal ne sont pas encore dans le .sqlite).
				@$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
				is_dir($dest) || mkdir($dest, 0755, true);
				$dbDir = $this->app->storageDir() . '/db';
				$saved = 0;
				foreach (array_merge([$dbDir . '/webmail.sqlite'],
					glob("$dbDir/users/*.sqlite") ?: []) as $f) {
					if (!is_file($f)) {
						continue;
					}
					$rel = substr($f, strlen($dbDir) + 1);
					$t = "$dest/$rel";
					is_dir(dirname($t)) || mkdir(dirname($t), 0755, true);
					@copy($f, $t) && $saved++;
					foreach (['-wal', '-shm'] as $s) {
						is_file("$f$s") && @copy("$f$s", "$t$s");
					}
				}
				$info['saved'] = $saved > 0;
				$info['files'] = $saved;
			} else {
				// MariaDB : référence pour un rollback ultérieur — liste des
				// tables + version de schéma. Pas de dump depuis PHP.
				try {
					$info['tables'] = $pdo->query('SHOW TABLES')
						->fetchAll(\PDO::FETCH_COLUMN);
				} catch (\Throwable) {}
				$info['external_dump_required'] = true;
			}
		} catch (\Throwable $e) {
			$info['error'] = $e->getMessage();
			$this->app->log('error', 'update : sauvegarde DB échouée — ' . $e->getMessage());
		}
		return $info;
	}

	/** Restaure les bases SQLite copiées par backupDatabase(). */
	private function restoreSqlite(string $srcDir): bool
	{
		$ok = true;
		$dbDir = $this->app->storageDir() . '/db';
		try {
			// Vide le WAL courant pour repartir sur les fichiers restaurés.
			@$this->app->db()->pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
		} catch (\Throwable) {}
		// Ferme le PDO : sous Windows le fichier ouvert est verrouillé et la
		// copie échouerait. db() reconnecte paresseusement ensuite.
		$this->app->closeDb();
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) {
			if (!$f->isFile()) {
				continue;
			}
			$rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($srcDir) + 1);
			$t = "$dbDir/$rel";
			is_dir(dirname($t)) || mkdir(dirname($t), 0755, true);
			@copy($f->getPathname(), $t) || $ok = false;
		}
		return $ok;
	}

	/** Ne conserve que les $keep backups les plus récents. */
	private function pruneBackups(int $keep): void
	{
		$dirs = glob($this->dir . '/backup-*', GLOB_ONLYDIR) ?: [];
		rsort($dirs);
		foreach (array_slice($dirs, $keep) as $d) {
			$this->rmrf($d);
		}
	}

	private function applyingFlag(): string
	{
		return $this->dir . '/applying';
	}

	/** Abandonne une mise à jour téléchargée/extraite (refusé pendant un apply). */
	public function reset(): array
	{
		is_dir($this->dir) || mkdir($this->dir, 0700, true);
		$lock = $this->lock();
		try {
			$this->purgeStaging();
			@unlink($this->stateFile);
			@unlink($this->applyingFlag()); // reliquat d'un apply mort
			$this->gh->invalidateCache(); // prochain check frais
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return ['reset' => true];
	}

	// ─── Internes ───

	/**
	 * Verrou partagé des opérations update : UpdateService (apply/reset) et
	 * PluginPackageService (install/remove) se mutuellement exclusifs — le code
	 * sous les pieds ne doit jamais être remplacé pendant une opération.
	 */
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
	 * app/plugins/ est exclu : les plugins se gèrent via le catalogue
	 * (PluginPackageService), jamais par une mise à jour cœur.
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
		foreach (['app/storage/', 'app/plugins/', '.git/', '.github/', 'dist/',
			// L'assistant /setup est intégré au cœur (SetupController) depuis
			// 0.45 : un paquet ancien ou bricolé ne doit pas redéposer le
			// fichier standalone public/setup/index.php.
			'public/setup/'] as $p) {
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
