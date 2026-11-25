<?php
declare(strict_types=1);

namespace MXWebMail\Cache;

/**
 * Cache fichiers (storage/cache/) : lectures/écritures atomiques via
 * mxwm_atomic_write, décodage JSON, test de fraîcheur par mtime.
 * Le flag cache.enabled est porté par l'instance (défaut true : les caches
 * non gouvernés par la config - dossiers, proxy - l'instancient sans).
 * Les clés (noms de fichiers), TTL et schémas de payload restent propres
 * à chaque service ; seule la plomberie est mutualisée.
 */
final class FileCache
{
	public function __construct(
		private string $dir,
		private bool $enabled = true,
	) {}

	public function path(string $file): string
	{
		return $this->dir . '/' . $file;
	}

	/** JSON décodé ou null (désactivé, absent, illisible). */
	public function getJson(string $file): ?array
	{
		if (!$this->enabled) {
			return null;
		}
		$c = is_file($p = $this->path($file))
			? json_decode((string) @file_get_contents($p), true) : null;
		return is_array($c) ? $c : null;
	}

	public function setJson(string $file, array $data): void
	{
		if ($this->enabled) {
			mxwm_atomic_write($this->path($file),
				(string) json_encode($data, JSON_UNESCAPED_SLASHES));
		}
	}

	/** Contenu brut ou null (désactivé, absent). */
	public function get(string $file): ?string
	{
		if (!$this->enabled) {
			return null;
		}
		$raw = is_file($p = $this->path($file)) ? @file_get_contents($p) : false;
		return is_string($raw) ? $raw : null;
	}

	public function set(string $file, string $content): void
	{
		if ($this->enabled) {
			mxwm_atomic_write($this->path($file), $content);
		}
	}

	/** Fichier présent et modifié depuis moins de $ttl secondes. */
	public function fresh(string $file, int $ttl): bool
	{
		return $this->enabled && $ttl > 0
			&& is_file($p = $this->path($file))
			&& (int) @filemtime($p) > time() - $ttl;
	}

	public function delete(string $file): void
	{
		is_file($p = $this->path($file)) && @unlink($p);
	}
}
