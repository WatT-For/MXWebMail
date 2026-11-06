<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

use PDO;

final class UserRepository
{
	public function __construct(private Database $db) {}

	public function findByUsername(string $username): ?array
	{
		$stmt = $this->db->pdo()->prepare('SELECT * FROM users WHERE username = :u');
		$stmt->execute(['u' => $username]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public function findById(int $id): ?array
	{
		$stmt = $this->db->pdo()->prepare('SELECT * FROM users WHERE id = :id');
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	/** Crée l'utilisateur applicatif au premier login IMAP réussi. */
	public function create(string $username): array
	{
		try {
			$this->db->pdo()->prepare('INSERT INTO users (username, created_at) VALUES (:u, :t)')
				->execute(['u' => $username, 't' => time()]);
		} catch (\PDOException) {
			// Deux premiers logins concurrents : l'autre requête a créé la
			// ligne entre notre SELECT et notre INSERT - on la relit.
		}
		return $this->findByUsername($username);
	}

	public function findOrCreate(string $username): array
	{
		return $this->findByUsername($username) ?? $this->create($username);
	}

	public function touchLastLogin(int $id): void
	{
		$this->db->pdo()->prepare('UPDATE users SET last_login_at = :t WHERE id = :id')
			->execute(['t' => time(), 'id' => $id]);
	}

	public function isAdmin(int $id): bool
	{
		$stmt = $this->db->pdo()->prepare('SELECT is_admin FROM users WHERE id = :id');
		$stmt->execute(['id' => $id]);
		return (bool) $stmt->fetchColumn();
	}

	/** Liste admin : utilisateurs + compteurs (comptes liés, identités). */
	public function all(): array
	{
		// JOIN + GROUP BY plutôt que 2 sous-requêtes corrélées par ligne.
		return $this->db->pdo()->query(
			'SELECT u.*,
				COUNT(DISTINCT a.id) AS accounts_count,
				COUNT(DISTINCT i.id) AS identities_count
			 FROM users u
			 LEFT JOIN accounts  a ON a.user_id     = u.id
			 LEFT JOIN identities i ON i.account_id = a.id
			 GROUP BY u.id ORDER BY u.username'
		)->fetchAll();
	}

	/** Mise à jour admin : display_name (vide > NULL), is_admin, disabled. */
	public function save(int $id, array $data): void
	{
		$name = trim((string) ($data['display_name'] ?? ''));
		$this->db->pdo()->prepare(
			'UPDATE users SET display_name = :n, is_admin = :a, disabled = :d WHERE id = :id'
		)->execute([
			'n'  => $name !== '' ? $name : null,
			'a'  => !empty($data['is_admin']) ? 1 : 0,
			'd'  => !empty($data['disabled']) ? 1 : 0,
			'id' => $id,
		]);
	}

	/** Suppression : les FK ON DELETE CASCADE purgent comptes, identités, réglages, contacts, clés. */
	public function delete(int $id): void
	{
		$this->db->pdo()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
	}

	/** Incrémente l'epoch de session : toute session PHP de l'utilisateur
	 *  est rejetée à la requête suivante (comparée dans App::isLoggedIn). */
	public function bumpSessionEpoch(int $id): void
	{
		$this->db->pdo()->prepare(
			'UPDATE users SET session_epoch = session_epoch + 1 WHERE id = :id'
		)->execute(['id' => $id]);
	}
}
