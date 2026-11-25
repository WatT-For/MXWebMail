<?php
declare(strict_types=1);

namespace MXWebMail;

/**
 * Helpers système de fichiers partagés : suppression récursive, verrou flock,
 * taille de dossier, GC par pattern. Les services paquets (update, plugins)
 * et les GC opportunistes des contrôleurs passent tous par ici.
 */
final class Files
{
	/** Suppression récursive d'un dossier - erreur fatale si un fichier résiste. */
	public static function rmrf(string $dir): void
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

	/** Taille cumulée des fichiers d'un dossier (octets, récursif). */
	public static function dirSize(string $dir): int
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

	/**
	 * Verrou flock exclusif non-bloquant (update.lock partagé par
	 * UpdateService et PluginPackageService - le code sous les pieds ne doit
	 * jamais être remplacé pendant une opération). Retourne la ressource :
	 * le verrou vit tant qu'elle n'est pas perdue/fermée.
	 */
	public static function lock(string $file, string $error): mixed
	{
		self::ensureDir(dirname($file));
		$lock = fopen($file, 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			if ($lock) {
				fclose($lock);
			}
			throw new \RuntimeException($error, 409);
		}
		return $lock;
	}

	/** Libère un verrou pris par lock() (flock UN + fclose, null-safe). */
	public static function unlock(mixed $lock): void
	{
		if (is_resource($lock)) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Lecture JSON sous verrou partagé : [] si absent/illisible. Le
	 * LOCK_SH évite de lire un fichier en cours de ftruncate+fwrite
	 * (withJsonLock concurrent).
	 */
	public static function readJsonLocked(string $file): array
	{
		if (!is_file($file)) {
			return [];
		}
		$fp = fopen($file, 'rb');
		if (!$fp) {
			return [];
		}
		flock($fp, LOCK_SH);
		$raw = (string) stream_get_contents($fp);
		flock($fp, LOCK_UN);
		fclose($fp);
		return json_decode($raw, true) ?: [];
	}

	/** Crée le dossier si absent (récursif) - convention is_dir||mkdir. */
	public static function ensureDir(string $dir, int $mode = 0700): void
	{
		is_dir($dir) || mkdir($dir, $mode, true);
	}

	/**
	 * Chemin relatif sûr pour extraction d'archive : refuse '..', '\'
	 * n'importe où (séparateur Windows), '/' en tête, lettre de lecteur.
	 * Règle la plus stricte des deux implémentations historiques
	 * (le paquet cœur acceptait un '\' médian - traversal Windows).
	 */
	public static function safeRelPath(string $rel): bool
	{
		return $rel !== ''
			&& !str_contains($rel, '..')
			&& !str_contains($rel, '\\')
			&& $rel[0] !== '/'
			&& !preg_match('/^[a-zA-Z]:/', $rel);
	}

	/**
	 * Cache "fichier PHP retournant un array" revalidé par signature :
	 * hit si la signature stockée === $signature et la donnée est un
	 * array ; sinon $build() produit la donnée, écrite atomiquement
	 * (tmp+rename + opcache_invalidate). Signature de tout type
	 * (comparaison stricte) ; les caches d'un ancien format sont
	 * simplement régénérés, pas de compat à conserver.
	 */
	public static function phpCache(string $file, mixed $signature, callable $build): array
	{
		if (is_file($file)) {
			$c = require $file;
			if (is_array($c) && ($c['sig'] ?? null) === $signature
				&& is_array($c['data'] ?? null)) {
				return $c['data'];
			}
		}
		$data = $build();
		@mxwm_atomic_write($file, '<?php return '
			. var_export(['sig' => $signature, 'data' => $data], true) . ";\n");
		return $data;
	}

	/**
	 * Lecture-modification-écriture d'un JSON tableau associatif sous
	 * verrou exclusif : sans flock autour du read+write, des écritures
	 * concurrentes se perdent. $mutate(array &$data) modifie le contenu
	 * en place. Non bloquant à l'échec d'ouverture (retour silencieux).
	 */
	public static function withJsonLock(string $file, callable $mutate): void
	{
		$fp = fopen($file, 'c+');
		if (!$fp) {
			return;
		}
		try {
			flock($fp, LOCK_EX);
			$all = json_decode((string) stream_get_contents($fp), true) ?: [];
			$mutate($all);
			rewind($fp);
			ftruncate($fp, 0);
			fwrite($fp, json_encode($all));
			fflush($fp);
		} finally {
			flock($fp, LOCK_UN);
			fclose($fp);
		}
	}

	/**
	 * GC par pattern : supprime les fichiers matchés plus vieux que
	 * $maxAge secondes (0 = tous), sauf $except (chemin ou basename du
	 * fichier courant à conserver). Retourne le nombre supprimé.
	 */
	public static function gcGlob(string $pattern, int $maxAge = 0, string $except = ''): int
	{
		$n = 0;
		foreach (glob($pattern) ?: [] as $f) {
			if ($except !== '' && ($f === $except || basename($f) === $except)) {
				continue;
			}
			if ($maxAge <= 0 || (int) @filemtime($f) < time() - $maxAge) {
				@unlink($f);
				$n++;
			}
		}
		return $n;
	}
}
