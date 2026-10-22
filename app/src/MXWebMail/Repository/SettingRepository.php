<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class SettingRepository
{
	public function __construct(private Database $db) {}

	public function get(int $userId, string $key, mixed $default = null): mixed
	{
		$stmt = $this->db->pdo()->prepare('SELECT value FROM user_settings WHERE user_id = :u AND key = :k');
		$stmt->execute(['u' => $userId, 'k' => $key]);
		$v = $stmt->fetchColumn();
		if ($v === false) {
			return $default;
		}
		$decoded = json_decode((string) $v, true);
		// Échec du décodage → chaîne brute historique ; 'null' stocké (ancien bug
		// ou set(null) avant suppression) se comporte comme une clé absente.
		if (json_last_error() !== JSON_ERROR_NONE) {
			return $v;
		}
		return $decoded ?? $default;
	}

	public function set(int $userId, string $key, mixed $value): void
	{
		if ($value === null) {
			$this->db->pdo()->prepare('DELETE FROM user_settings WHERE user_id = :u AND key = :k')
				->execute(['u' => $userId, 'k' => $key]);
			return;
		}
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? 'INSERT INTO user_settings (user_id, key, value) VALUES (:u, :k, :v)
			   ON CONFLICT(user_id, key) DO UPDATE SET value = excluded.value'
			: 'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (:u, :k, :v)
			   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';
		$this->db->pdo()->prepare($sql)->execute([
			'u' => $userId,
			'k' => $key,
			'v' => json_encode($value, JSON_UNESCAPED_UNICODE),
		]);
	}

	/**
	 * Réinitialisation admin : les préférences repassent aux défauts
	 * user_defaults (incl. special_folders / notify_folders — retour à
	 * l'auto-détection et INBOX). Sont préservées les clés internes :
	 * « plugin.* » (secrets 2FA, état de sync…) et « user_folders »
	 * (provenance des dossiers créés via l'UI — sa perte reclasserait
	 * un dossier manuel nommé « Archives » en dossier système).
	 */
	public function deleteAllFor(int $userId): void
	{
		$key = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
			? 'key' : '`key`';
		$this->db->pdo()->prepare(
			"DELETE FROM user_settings WHERE user_id = :u
				AND $key NOT LIKE 'plugin.%' AND $key != 'user_folders'"
		)->execute(['u' => $userId]);
	}

	public function allFor(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare('SELECT key, value FROM user_settings WHERE user_id = :u');
		$stmt->execute(['u' => $userId]);
		$out = [];
		foreach ($stmt->fetchAll() as $row) {
			$decoded = json_decode((string) $row['value'], true);
			$out[$row['key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['value'];
		}
		return $out;
	}
}
