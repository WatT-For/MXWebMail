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
	 * "Utilisable par les utilisateurs" : plugin activé chargé uniquement en
	 * contexte admin quand le flag est faux (miroir de themes.allowed).
	 * Défaut : refusé - l'admin autorise explicitement dans l'onglet Extensions.
	 */
	public function isUserAllowed(string $name): bool
	{
		return $this->states()[$name]['user_allowed'] ?? false;
	}

	/** États enabled/user_allowed de tous les plugins - une requête par instance. */
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
		$this->upsert($name, ['enabled' => $enabled ? 1 : 0]);
		if ($this->stateMap !== null) {
			$this->stateMap[$name]['enabled'] = $enabled;
		}
	}

	public function setUserAllowed(string $name, bool $allowed): void
	{
		$this->upsert($name, ['user_allowed' => $allowed ? 1 : 0]);
		if ($this->stateMap !== null) {
			$this->stateMap[$name]['user_allowed'] = $allowed;
		}
	}

	public function has(string $name): bool
	{
		$stmt = $this->db->pdo()->prepare('SELECT 1 FROM plugins WHERE name = :n');
		$stmt->execute(['n' => $name]);
		return (bool) $stmt->fetchColumn();
	}

	/** Purge complète de la ligne (enabled + user_allowed + config). */
	public function delete(string $name): void
	{
		$this->db->pdo()->prepare('DELETE FROM plugins WHERE name = :n')
			->execute(['n' => $name]);
		if ($this->stateMap !== null) {
			unset($this->stateMap[$name]);
		}
	}

	/**
	 * INSERT complet (enabled/user_allowed à 0 par défaut - le DEFAULT du
	 * schéma, historiquement 1, devient inerte) + UPDATE ciblé sur les seules
	 * clés de $data.
	 * @param array<string, int|string> $data colonnes à écrire
	 */
	private function upsert(string $name, array $data): void
	{
		if ($data === []) {
			return;
		}
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$row = array_merge(['enabled' => 0, 'user_allowed' => 0], $data);
		$cols = 'name, ' . implode(', ', array_keys($row));
		$marks = ':n, :' . implode(', :', array_keys($row));
		$sets = implode(', ', array_map(
			fn(string $c) => $driver === 'sqlite' ? "$c = excluded.$c" : "$c = VALUES($c)",
			array_keys($data)));
		$sql = $driver === 'sqlite'
			? "INSERT INTO plugins ($cols) VALUES ($marks)
			   ON CONFLICT(name) DO UPDATE SET $sets"
			: "INSERT INTO plugins ($cols) VALUES ($marks)
			   ON DUPLICATE KEY UPDATE $sets";
		$this->db->pdo()->prepare($sql)->execute(['n' => $name] + $row);
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
		$this->upsert($name, ['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
	}

	public function all(): array
	{
		return $this->db->pdo()->query('SELECT * FROM plugins ORDER BY name')->fetchAll();
	}
}
