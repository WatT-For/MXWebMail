<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class PluginRepository
{
	private ?array $stateMap = null;

	public function __construct(private Database $db) {}

	public function isEnabled(string $name): bool
	{
		return $this->states()[$name]['enabled'] ?? false;
	}

	/**
	 * « Utilisable par les utilisateurs » : plugin activé chargé uniquement en
	 * contexte admin quand le flag est faux (miroir de themes.allowed).
	 */
	public function isUserAllowed(string $name): bool
	{
		return $this->states()[$name]['user_allowed'] ?? true;
	}

	/** États enabled/user_allowed de tous les plugins — une requête par instance. */
	private function states(): array
	{
		if ($this->stateMap === null) {
			$this->stateMap = [];
			foreach ($this->db->pdo()->query('SELECT name, enabled, user_allowed FROM plugins') as $row) {
				$this->stateMap[$row['name']] = [
					'enabled'      => (bool) $row['enabled'],
					'user_allowed' => (bool) $row['user_allowed'],
				];
			}
		}
		return $this->stateMap;
	}

	public function setEnabled(string $name, bool $enabled): void
	{
		$this->upsert($name, 'enabled', $enabled ? 1 : 0);
		if ($this->stateMap !== null) {
			$this->stateMap[$name]['enabled'] = $enabled;
		}
	}

	public function setUserAllowed(string $name, bool $allowed): void
	{
		$this->upsert($name, 'user_allowed', $allowed ? 1 : 0);
		if ($this->stateMap !== null) {
			$this->stateMap[$name]['user_allowed'] = $allowed;
		}
	}

	private function upsert(string $name, string $column, int $value): void
	{
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? "INSERT INTO plugins (name, $column) VALUES (:n, :v)
			   ON CONFLICT(name) DO UPDATE SET $column = excluded.$column"
			: "INSERT INTO plugins (name, $column) VALUES (:n, :v)
			   ON DUPLICATE KEY UPDATE $column = VALUES($column)";
		$this->db->pdo()->prepare($sql)->execute(['n' => $name, 'v' => $value]);
	}

	public function getConfig(string $name): array
	{
		$stmt = $this->db->pdo()->prepare('SELECT config FROM plugins WHERE name = :n');
		$stmt->execute(['n' => $name]);
		$v = $stmt->fetchColumn();
		return $v ? (json_decode((string) $v, true) ?: []) : [];
	}

	public function setConfig(string $name, array $config): void
	{
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? 'INSERT INTO plugins (name, config) VALUES (:n, :c)
			   ON CONFLICT(name) DO UPDATE SET config = excluded.config'
			: 'INSERT INTO plugins (name, config) VALUES (:n, :c)
			   ON DUPLICATE KEY UPDATE config = VALUES(config)';
		$this->db->pdo()->prepare($sql)->execute(['n' => $name, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
	}

	public function all(): array
	{
		return $this->db->pdo()->query('SELECT * FROM plugins ORDER BY name')->fetchAll();
	}
}
