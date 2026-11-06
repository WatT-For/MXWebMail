<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class SettingRepository
{
	/** Mémo par requête : userId => map key=>valeur décodée (cf. allFor). */
	private array $cache = [];

	public function __construct(private Database $db) {}

	public function get(int $userId, string $key, mixed $default = null): mixed
	{
		$all = $this->allFor($userId);
		// Clé présente avec 'null' stocké (ancien bug ou set(null) avant
		// suppression) se comporte comme une clé absente.
		return array_key_exists($key, $all) ? ($all[$key] ?? $default) : $default;
	}

	/**
	 * Lecture groupée de plusieurs clés en une seule requête (évite N SELECT
	 * successifs). Décodage JSON identique à get() ; seules les clés
	 * présentes sont retournées (pas de valeur par défaut injectée).
	 */
	public function getMany(int $userId, array $keys): array
	{
		if (!$keys) {
			return [];
		}
		return array_intersect_key($this->allFor($userId), array_flip($keys));
	}

	public function set(int $userId, string $key, mixed $value): void
	{
		if ($value === null) {
			$this->db->pdo()->prepare('DELETE FROM user_settings WHERE user_id = :u AND `key` = :k')
				->execute(['u' => $userId, 'k' => $key]);
			unset($this->cache[$userId][$key]);
			return;
		}
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? 'INSERT INTO user_settings (user_id, `key`, value) VALUES (:u, :k, :v)
			   ON CONFLICT(user_id, `key`) DO UPDATE SET value = excluded.value'
			: 'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (:u, :k, :v)
			   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';
		$this->db->pdo()->prepare($sql)->execute([
			'u' => $userId,
			'k' => $key,
			'v' => json_encode($value, JSON_UNESCAPED_UNICODE),
		]);
		// Mémo synchro (read-after-write : setSpecialFolderMap lit puis écrit
		// la même clé dans la même requête).
		if (isset($this->cache[$userId])) {
			$this->cache[$userId][$key] = $value;
		}
	}

	/**
	 * Réinitialisation admin : les préférences repassent aux défauts
	 * user_defaults (incl. special_folders / notify_folders - retour à
	 * l'auto-détection et INBOX). Sont préservées les clés internes :
	 * "plugin.*" (secrets 2FA, état de sync…) et "user_folders"
	 * (provenance des dossiers créés via l'UI - sa perte reclasserait
	 * un dossier manuel nommé "Archives" en dossier système).
	 */
	public function deleteAllFor(int $userId): void
	{
		$key = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
			? 'key' : '`key`';
		$this->db->pdo()->prepare(
			"DELETE FROM user_settings WHERE user_id = :u
				AND $key NOT LIKE 'plugin.%' AND $key != 'user_folders'"
		)->execute(['u' => $userId]);
		if (isset($this->cache[$userId])) {
			$this->cache[$userId] = array_filter($this->cache[$userId],
				fn(string $k) => str_starts_with($k, 'plugin.') || $k === 'user_folders',
				ARRAY_FILTER_USE_KEY);
		}
	}

	/**
	 * Tous les réglages d'un utilisateur - mémoïsé par requête (get/getMany
	 * lisent la même map : N SELECT > 1). Invalidée sur set/deleteAllFor.
	 */
	public function allFor(int $userId): array
	{
		if (isset($this->cache[$userId])) {
			return $this->cache[$userId];
		}
		$stmt = $this->db->pdo()->prepare('SELECT `key`, value FROM user_settings WHERE user_id = :u');
		$stmt->execute(['u' => $userId]);
		$out = [];
		foreach ($stmt->fetchAll() as $row) {
			$decoded = json_decode((string) $row['value'], true);
			$out[$row['key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['value'];
		}
		return $this->cache[$userId] = $out;
	}
}
