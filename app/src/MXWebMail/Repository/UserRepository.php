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
		$this->db->pdo()->prepare('INSERT INTO users (username, created_at) VALUES (:u, :t)')
			->execute(['u' => $username, 't' => time()]);
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
		return $this->db->pdo()->query(
			'SELECT u.*,
				(SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id) AS accounts_count,
				(SELECT COUNT(*) FROM identities i
					JOIN accounts a2 ON a2.id = i.account_id
					WHERE a2.user_id = u.id) AS identities_count
			 FROM users u ORDER BY username'
		)->fetchAll();
	}

	/** Mise à jour admin : display_name (vide → NULL), is_admin, disabled. */
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
}
