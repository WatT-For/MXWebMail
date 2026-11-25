<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

use PDO;

/**
 * Table accounts : comptes IMAP/SMTP rattachés à un utilisateur.
 * is_default = compte principal (provisioning login, indeletable) ;
 * position = ordre d'affichage des secondaires ; auth = 'password' ou
 * le provider OAuth2 (imap_pass stocke alors le refresh token chiffré).
 */
final class AccountRepository
{
	public function __construct(private Database $db) {}

	/**
	 * Comptes de l'utilisateur : principal d'abord, puis position/email -
	 * même ordre partout (liste, repli, migration).
	 */
	public function allForUser(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id, email, is_default, auth FROM accounts
			 WHERE user_id = :u ORDER BY is_default DESC, position, email'
		);
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	/** Id du compte par défaut (is_default, sinon le 1er secondaire). */
	public function defaultId(int $userId): ?int
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id FROM accounts WHERE user_id = :u
			 ORDER BY is_default DESC, position, email LIMIT 1'
		);
		$stmt->execute(['u' => $userId]);
		$id = $stmt->fetchColumn();
		return $id === false ? null : (int) $id;
	}

	/** Ligne complète du compte principal (is_default = 1). */
	public function defaultForUser(int $userId): ?array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT * FROM accounts WHERE user_id = :u AND is_default = 1');
		$stmt->execute(['u' => $userId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	/** Ligne complète si le compte appartient à l'utilisateur. */
	public function findForUser(int $userId, int $accountId): ?array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT * FROM accounts WHERE id = :id AND user_id = :u');
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public function findIdByEmail(int $userId, string $email): ?int
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id FROM accounts WHERE user_id = :u AND email = :e');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		$id = $stmt->fetchColumn();
		return $id === false ? null : (int) $id;
	}

	public function owns(int $userId, int $accountId): bool
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT 1 FROM accounts WHERE id = :id AND user_id = :u');
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		return (bool) $stmt->fetchColumn();
	}

	/**
	 * Nouveau credential chiffré. $auth != null met aussi à jour la
	 * méthode (repassage mot de passe > OAuth ou l'inverse).
	 */
	public function updateCredential(int $accountId, string $encPass, ?string $auth = null): void
	{
		$pdo = $this->db->pdo();
		if ($auth === null) {
			$pdo->prepare('UPDATE accounts SET imap_pass = :ip WHERE id = :id')
				->execute(['ip' => $encPass, 'id' => $accountId]);
			return;
		}
		$pdo->prepare('UPDATE accounts SET imap_pass = :ip, auth = :a WHERE id = :id')
			->execute(['ip' => $encPass, 'a' => $auth, 'id' => $accountId]);
	}

	/** Credential du compte principal (changement de mot de passe). */
	public function updateDefaultCredential(int $userId, string $encPass): void
	{
		$this->db->pdo()->prepare(
			'UPDATE accounts SET imap_pass = :ip WHERE user_id = :u AND is_default = 1')
			->execute(['ip' => $encPass, 'u' => $userId]);
	}

	/** Supprime un secondaire (le principal est protégé). */
	public function deleteSecondary(int $userId, int $accountId): bool
	{
		$stmt = $this->db->pdo()->prepare(
			'DELETE FROM accounts WHERE id = :id AND user_id = :u AND is_default = 0');
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		return $stmt->rowCount() > 0;
	}

	/** Bascule is_default en transaction (un seul principal). */
	public function setDefault(int $userId, int $accountId): void
	{
		$pdo = $this->db->pdo();
		$pdo->beginTransaction();
		try {
			$pdo->prepare('UPDATE accounts SET is_default = 0 WHERE user_id = :u')
				->execute(['u' => $userId]);
			$pdo->prepare('UPDATE accounts SET is_default = 1 WHERE id = :id')
				->execute(['id' => $accountId]);
			$pdo->commit();
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}

	/** Ids des comptes secondaires, pour validation du réordonnancement. */
	public function secondaryIds(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id FROM accounts WHERE user_id = :u AND is_default = 0');
		$stmt->execute(['u' => $userId]);
		return array_map('intval', array_column($stmt->fetchAll(), 'id'));
	}

	/** position = 1..n dans l'ordre du tableau (transaction). */
	public function setPositions(array $orderedIds): void
	{
		$pdo = $this->db->pdo();
		$pdo->beginTransaction();
		try {
			$up = $pdo->prepare('UPDATE accounts SET position = :p WHERE id = :id');
			foreach ($orderedIds as $i => $id) {
				$up->execute(['p' => $i + 1, 'id' => $id]);
			}
			$pdo->commit();
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}

	public function hasDefault(int $userId): bool
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT 1 FROM accounts WHERE user_id = :u AND is_default = 1');
		$stmt->execute(['u' => $userId]);
		return (bool) $stmt->fetchColumn();
	}

	/** Prochaine position libre (fin de liste des secondaires). */
	public function nextPosition(int $userId): int
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT COALESCE(MAX(position), 0) + 1 FROM accounts WHERE user_id = :u');
		$stmt->execute(['u' => $userId]);
		return (int) $stmt->fetchColumn();
	}

	/** Insère un compte ; imap_pass doit être déjà chiffré. */
	public function insert(int $userId, string $email, string $encPass,
		bool $isDefault, string $auth, int $position): int
	{
		$pdo = $this->db->pdo();
		$pdo->prepare(
			'INSERT INTO accounts (user_id, email, imap_user, imap_pass, is_default, auth, position)
			 VALUES (:u, :e, :iu, :ip, :d, :a, :pos)'
		)->execute([
			'u'  => $userId, 'e' => $email, 'iu' => $email,
			'ip' => $encPass, 'd' => $isDefault ? 1 : 0,
			'a'  => $auth, 'pos' => $position,
		]);
		return (int) $pdo->lastInsertId();
	}
}
