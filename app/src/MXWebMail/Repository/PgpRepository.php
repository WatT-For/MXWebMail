<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

/**
 * Keyring PGP de l'utilisateur : clés publiques des correspondants
 * + clés privées armored (protégées par passphrase, jamais déchiffrées côté serveur).
 */
final class PgpRepository
{
	public function __construct(private Database $db, private \MXWebMail\Locale\Translator $tr) {}

	public function all(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id, email, key_data, is_private FROM pgp_keys WHERE user_id = :u ORDER BY is_private DESC, email'
		);
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	public function save(int $userId, string $email, string $armored, bool $isPrivate): int
	{
		$email = trim(mb_strtolower($email));
		if ($email === '' || !str_contains($armored, '-----BEGIN PGP')) {
			throw new \InvalidArgumentException($this->tr->t('errors.pgp_key_required'), 400);
		}
		// Une clé par (utilisateur, email, type) : remplace l'existante
		$pdo = $this->db->pdo();
		$pdo->prepare('DELETE FROM pgp_keys WHERE user_id = :u AND email = :e AND is_private = :p')
			->execute(['u' => $userId, 'e' => $email, 'p' => $isPrivate ? 1 : 0]);
		$pdo->prepare('INSERT INTO pgp_keys (user_id, email, key_data, is_private) VALUES (:u,:e,:k,:p)')
			->execute(['u' => $userId, 'e' => $email, 'k' => $armored, 'p' => $isPrivate ? 1 : 0]);
		return (int) $pdo->lastInsertId();
	}

	public function delete(int $userId, int $id): void
	{
		$this->db->pdo()->prepare('DELETE FROM pgp_keys WHERE id = :id AND user_id = :u')
			->execute(['id' => $id, 'u' => $userId]);
	}

	/** Clés publiques stockées correspondant aux emails donnés (chiffrement sortant). */
	public function publicKeysFor(int $userId, array $emails): array
	{
		$emails = array_values(array_unique(array_filter(
			array_map(fn($e) => trim(mb_strtolower($e)), $emails))));
		if (!$emails) {
			return [];
		}
		$in = implode(',', array_fill(0, count($emails), '?'));
		$stmt = $this->db->pdo()->prepare(
			"SELECT email, key_data FROM pgp_keys WHERE user_id = ? AND is_private = 0 AND email IN ($in)"
		);
		$stmt->execute(array_merge([$userId], $emails));
		return $stmt->fetchAll();
	}
}
