<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

/**
 * Config des templates d'interface : table `themes(name, config, allowed)`.
 * Le schéma des champs est sur disque (app/templates/<name>/config.schema.json) ;
 * les valeurs saisies par l'admin sont en base. `allowed` = proposable dans
 * les réglages utilisateur (le template par défaut l'est toujours).
 */
final class ThemeRepository
{
	private ?array $allowedMap = null;
	private array $configs = [];

	public function __construct(private Database $db) {}

	/**
	 * Map name => allowed en une requête par instance (comme
	 * PluginRepository::states) — isAllowed était sinon une requête par
	 * template, multipliée par chaque appel de ThemeManager::isSelectable.
	 */
	private function allowed(): array
	{
		if ($this->allowedMap === null) {
			$this->allowedMap = [];
			foreach ($this->db->pdo()->query('SELECT name, allowed FROM themes') as $row) {
				$this->allowedMap[$row['name']] = (bool) $row['allowed'];
			}
		}
		return $this->allowedMap;
	}

	/** Absence de ligne = autorisé (défaut de la colonne). */
	public function isAllowed(string $name): bool
	{
		return $this->allowed()[$name] ?? true;
	}

	public function setAllowed(string $name, bool $allowed): void
	{
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? 'INSERT INTO themes (name, allowed) VALUES (:n, :a)
			   ON CONFLICT(name) DO UPDATE SET allowed = excluded.allowed'
			: 'INSERT INTO themes (name, allowed) VALUES (:n, :a)
			   ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)';
		$this->db->pdo()->prepare($sql)->execute(['n' => $name, 'a' => $allowed ? 1 : 0]);
		if ($this->allowedMap !== null) {
			$this->allowedMap[$name] = $allowed;
		}
	}

	/** Config JSON par template — mémoïsée : ThemeManager::config la lit en boucle. */
	public function getConfig(string $name): array
	{
		if (!array_key_exists($name, $this->configs)) {
			$stmt = $this->db->pdo()->prepare('SELECT config FROM themes WHERE name = :n');
			$stmt->execute(['n' => $name]);
			$v = $stmt->fetchColumn();
			$this->configs[$name] = $v ? (json_decode((string) $v, true) ?: []) : [];
		}
		return $this->configs[$name];
	}

	public function setConfig(string $name, array $config): void
	{
		$driver = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		$sql = $driver === 'sqlite'
			? 'INSERT INTO themes (name, config) VALUES (:n, :c)
			   ON CONFLICT(name) DO UPDATE SET config = excluded.config'
			: 'INSERT INTO themes (name, config) VALUES (:n, :c)
			   ON DUPLICATE KEY UPDATE config = VALUES(config)';
		$this->db->pdo()->prepare($sql)->execute(['n' => $name, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
		$this->configs[$name] = $config;
	}
}