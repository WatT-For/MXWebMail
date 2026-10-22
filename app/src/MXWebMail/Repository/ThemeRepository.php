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
	public function __construct(private Database $db) {}

	/** Absence de ligne = autorisé (défaut de la colonne). */
	public function isAllowed(string $name): bool
	{
		$stmt = $this->db->pdo()->prepare('SELECT allowed FROM themes WHERE name = :n');
		$stmt->execute(['n' => $name]);
		$v = $stmt->fetchColumn();
		return $v === false ? true : (bool) $v;
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
	}

	public function getConfig(string $name): array
	{
		$stmt = $this->db->pdo()->prepare('SELECT config FROM themes WHERE name = :n');
		$stmt->execute(['n' => $name]);
		$v = $stmt->fetchColumn();
		return $v ? (json_decode((string) $v, true) ?: []) : [];
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
	}
}