<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

/**
 * Table auth_tokens : jetons "Rester connecté" (selector:validator en
 * cookie, seul le hash sha256 du validator est stocké). Rotation du
 * validator à chaque usage ; une ligne = une session persistante.
 */
final class AuthTokenRepository
{
	public function __construct(private Database $db) {}

	/** $token = hash sha256 du validator ; $agent tronqué par l'appelant. */
	public function insert(int $userId, string $selector, string $token,
		string $agent, ?string $ip, int $createdAt, int $expiresAt): void
	{
		$this->purgeExpired();
		$this->db->pdo()->prepare(
			'INSERT INTO auth_tokens (user_id, selector, token, agent, ip, created_at, expires_at)
			 VALUES (:u,:s,:t,:a,:i,:c,:e)')
			->execute([
				'u' => $userId, 's' => $selector, 't' => $token,
				'a' => $agent, 'i' => $ip, 'c' => $createdAt, 'e' => $expiresAt,
			]);
	}

	/** Jeton + état du compte utilisateur (disabled) pour la validation. */
	public function findBySelector(string $selector): ?array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT t.id, t.user_id, t.token, t.expires_at, u.disabled
			 FROM auth_tokens t JOIN users u ON u.id = t.user_id
			 WHERE t.selector = :s');
		$stmt->execute(['s' => $selector]);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	/** Rotation après usage : nouveau hash + trace d'utilisation. */
	public function rotate(int $id, string $token, int $lastUsedAt, ?string $lastIp): void
	{
		$this->db->pdo()->prepare(
			'UPDATE auth_tokens SET token = :t, last_used_at = :n, last_ip = :i WHERE id = :id')
			->execute(['t' => $token, 'n' => $lastUsedAt, 'i' => $lastIp, 'id' => $id]);
	}

	/** Sessions persistantes de l'utilisateur (hash jamais renvoyé). */
	public function sessionsFor(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id, selector, agent, ip, last_ip, created_at, last_used_at, expires_at
			 FROM auth_tokens WHERE user_id = :u ORDER BY last_used_at DESC, created_at DESC');
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/** Purge interne (validator invalide/volé, compte désactivé). */
	public function deleteById(int $id): void
	{
		$this->db->pdo()->prepare('DELETE FROM auth_tokens WHERE id = :id')
			->execute(['id' => $id]);
	}

	/** Révocation d'une session par l'utilisateur ou l'admin. */
	public function deleteForUser(int $id, int $userId): void
	{
		$this->db->pdo()->prepare(
			'DELETE FROM auth_tokens WHERE id = :id AND user_id = :u')
			->execute(['id' => $id, 'u' => $userId]);
	}

	/** Révocation du jeton courant (logout / changement d'état). */
	public function deleteBySelector(int $userId, string $selector): void
	{
		$this->db->pdo()->prepare(
			'DELETE FROM auth_tokens WHERE selector = :s AND user_id = :u')
			->execute(['s' => $selector, 'u' => $userId]);
	}

	public function deleteAllFor(int $userId): void
	{
		$this->db->pdo()->prepare('DELETE FROM auth_tokens WHERE user_id = :u')
			->execute(['u' => $userId]);
	}

	/** "Me déconnecter des autres appareils" : garde le jeton courant. */
	public function deleteOthersFor(int $userId, string $exceptSelector): void
	{
		$this->db->pdo()->prepare(
			'DELETE FROM auth_tokens WHERE user_id = :u AND selector != :s')
			->execute(['u' => $userId, 's' => $exceptSelector]);
	}

	/**
	 * GC opportuniste au nouvel insert : un jeton expiré dont le compte ne
	 * se reconnecte jamais n'est supprimé par aucun autre chemin - la table
	 * grossirait sans borne.
	 */
	private function purgeExpired(): void
	{
		$this->db->pdo()->prepare('DELETE FROM auth_tokens WHERE expires_at < :t')
			->execute(['t' => time()]);
	}
}
