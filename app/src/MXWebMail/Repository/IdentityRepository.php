<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class IdentityRepository
{
	public function __construct(private Database $db) {}

	public function allForUser(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT i.* FROM identities i
			 JOIN accounts a ON a.id = i.account_id
			 WHERE a.user_id = :u ORDER BY i.is_default DESC, i.email'
		);
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	/** Compte par défaut de l'utilisateur (1er compte is_default). */
	private function defaultAccountId(int $userId): ?int
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT id FROM accounts WHERE user_id = :u ORDER BY is_default DESC, id LIMIT 1'
		);
		$stmt->execute(['u' => $userId]);
		$id = $stmt->fetchColumn();
		return $id === false ? null : (int) $id;
	}

	public function save(int $userId, array $data): int
	{
		$pdo = $this->db->pdo();
		$fields = [
			'n' => (string) ($data['name'] ?? ''),
			'e' => (string) ($data['email'] ?? ''),
			'r' => $data['reply_to'] ?? null,
			'b' => $data['bcc'] ?? null,
			's' => $data['signature'] ?? null,
		];
		// Adresses validées : un champ non conforme (ou avec CR/LF, qui
		// injecterait des en-têtes/commandes SMTP à l'envoi) est rejeté.
		foreach (['e', 'r', 'b'] as $k) {
			$v = $fields[$k];
			if ($v === null || $v === '') {
				continue;
			}
			$v = (string) $v;
			if (strpbrk($v, "\r\n") !== false || !$this->validAddresses($v, $k === 'e')) {
				throw new \InvalidArgumentException('invalid identity address', 400);
			}
		}
		if ($fields['e'] === '') {
			throw new \InvalidArgumentException('identity email required');
		}
		if (!empty($data['id'])) {
			$id = (int) $data['id'];
			$this->assertOwner($userId, $id);
			$pdo->prepare(
				'UPDATE identities SET name=:n, email=:e, reply_to=:r, bcc=:b, signature=:s WHERE id=:id'
			)->execute($fields + ['id' => $id]);
		} else {
			$accountId = (int) ($data['account_id'] ?? 0) ?: $this->defaultAccountId($userId);
			if (!$accountId || !$this->accountBelongsTo($accountId, $userId)) {
				throw new \RuntimeException('Aucun compte', 400);
			}
			$pdo->prepare(
				'INSERT INTO identities (account_id, name, email, reply_to, bcc, signature)
				 VALUES (:a, :n, :e, :r, :b, :s)'
			)->execute($fields + ['a' => $accountId]);
			$id = (int) $pdo->lastInsertId();
		}
		if (!empty($data['is_default'])) {
			$this->setDefault($userId, $id);
		}
		return $id;
	}

	public function setDefault(int $userId, int $id): void
	{
		$this->assertOwner($userId, $id);
		$pdo = $this->db->pdo();
		$pdo->prepare(
			'UPDATE identities SET is_default = 0
			 WHERE account_id = (SELECT account_id FROM identities WHERE id = :id)'
		)->execute(['id' => $id]);
		$pdo->prepare('UPDATE identities SET is_default = 1 WHERE id = :id')->execute(['id' => $id]);
	}

	public function delete(int $userId, int $id): void
	{
		$this->assertOwner($userId, $id);
		$this->db->pdo()->prepare('DELETE FROM identities WHERE id = :id')->execute(['id' => $id]);
	}

	/**
	 * email : adresse unique (IDN accepté via sa forme ASCII) ;
	 * reply_to/bcc : liste séparée par , ou ; consommée par EmailCollection.
	 */
	private function validAddresses(string $v, bool $single): bool
	{
		if ($single) {
			return false !== filter_var(
				\MXWebMailLib\IDN::emailToAscii($v), FILTER_VALIDATE_EMAIL);
		}
		$aParts = preg_split('/[,;]/', $v, -1, PREG_SPLIT_NO_EMPTY);
		if (!$aParts) {
			return false;
		}
		foreach ($aParts as $sPart) {
			try {
				$oEmail = \MailSo\Mime\Email::Parse(trim($sPart));
				// Parse accepte un nom seul > exiger une vraie adresse.
				if (!filter_var($oEmail->GetEmail(), FILTER_VALIDATE_EMAIL)) {
					return false;
				}
			} catch (\Throwable $e) {
				return false;
			}
		}
		return true;
	}

	private function accountBelongsTo(int $accountId, int $userId): bool
	{
		$stmt = $this->db->pdo()->prepare('SELECT 1 FROM accounts WHERE id = :a AND user_id = :u');
		$stmt->execute(['a' => $accountId, 'u' => $userId]);
		return (bool) $stmt->fetchColumn();
	}

	private function assertOwner(int $userId, int $identityId): void
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT 1 FROM identities i JOIN accounts a ON a.id = i.account_id
			 WHERE i.id = :id AND a.user_id = :u'
		);
		$stmt->execute(['id' => $identityId, 'u' => $userId]);
		if (!$stmt->fetchColumn()) {
			throw new \RuntimeException('Identité introuvable', 404);
		}
	}
}
