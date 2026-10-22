<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class ContactRepository
{
	public function __construct(private UserDatabase $udb) {}

	public function search(int $userId, string $q, int $limit = 20): array
	{
		$stmt = $this->udb->for($userId)->pdo()->prepare(
			'SELECT c.id, c.name, ce.email FROM contacts c
			 JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u AND (c.name LIKE :q OR ce.email LIKE :q)
			 ORDER BY c.frequency DESC, c.name LIMIT :l'
		);
		$stmt->bindValue('u', $userId, \PDO::PARAM_INT);
		$stmt->bindValue('q', '%' . $q . '%');
		$stmt->bindValue('l', $limit, \PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function all(int $userId): array
	{
		$stmt = $this->udb->for($userId)->pdo()->prepare(
			'SELECT c.*, GROUP_CONCAT(ce.email, ", ") AS emails
			 FROM contacts c LEFT JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u GROUP BY c.id ORDER BY c.name'
		);
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	public function save(int $userId, array $data): int
	{
		$pdo = $this->udb->for($userId)->pdo();
		$emails = array_values(array_filter(array_map('trim',
			preg_split('/[,;]/', (string) ($data['emails'] ?? '')))));

		if (!empty($data['id'])) {
			$id = (int) $data['id'];
			$pdo->prepare('UPDATE contacts SET name=:n, properties=:p WHERE id=:id AND user_id=:u')
				->execute(['n' => $data['name'] ?? '', 'p' => json_encode($data['properties'] ?? []), 'id' => $id, 'u' => $userId]);
			$pdo->prepare('DELETE FROM contact_emails WHERE contact_id = :id')->execute(['id' => $id]);
		} else {
			$pdo->prepare('INSERT INTO contacts (user_id, name, properties) VALUES (:u, :n, :p)')
				->execute(['u' => $userId, 'n' => $data['name'] ?? '', 'p' => json_encode($data['properties'] ?? [])]);
			$id = (int) $pdo->lastInsertId();
		}
		foreach ($emails as $email) {
			$pdo->prepare('INSERT INTO contact_emails (contact_id, email) VALUES (:c, :e)')
				->execute(['c' => $id, 'e' => $email]);
		}
		return $id;
	}

	public function delete(int $userId, int $id): void
	{
		$this->udb->for($userId)->pdo()->prepare('DELETE FROM contacts WHERE id = :id AND user_id = :u')
			->execute(['id' => $id, 'u' => $userId]);
	}

	/**
	 * Resynchronise les contacts d'une source externe (carddav, ldap…).
	 * Remplace intégralement les contacts de cette source — les locaux sont préservés.
	 * @param array<array{name:string,emails:string[],source_uid:string}> $items
	 */
	public function syncFrom(int $userId, string $source, array $items): int
	{
		$pdo = $this->udb->for($userId)->pdo();
		$pdo->beginTransaction();
		try {
			$pdo->prepare('DELETE FROM contacts WHERE user_id = :u AND source = :s')
				->execute(['u' => $userId, 's' => $source]);
			$ins = $pdo->prepare(
				'INSERT INTO contacts (user_id, name, properties, source, source_uid) VALUES (:u,:n,:p,:s,:su)'
			);
			$insMail = $pdo->prepare('INSERT INTO contact_emails (contact_id, email) VALUES (:c,:e)');
			$n = 0;
			foreach ($items as $item) {
				$ins->execute([
					'u'  => $userId,
					'n'  => (string) ($item['name'] ?? ''),
					'p'  => json_encode($item['properties'] ?? []),
					's'  => $source,
					'su' => (string) ($item['source_uid'] ?? ''),
				]);
				$id = (int) $pdo->lastInsertId();
				foreach ((array) ($item['emails'] ?? []) as $email) {
					$insMail->execute(['c' => $id, 'e' => $email]);
				}
				$n++;
			}
			$pdo->commit();
			return $n;
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}

	/**
	 * Destinataire d'un envoi : déjà connu → frequency++, sinon le contact
	 * est créé (name = display-name de l'adresse, vide sinon).
	 */
	public function touchRecipient(int $userId, string $email, string $name = ''): void
	{
		$pdo = $this->udb->for($userId)->pdo();
		$stmt = $pdo->prepare(
			'SELECT c.id FROM contacts c
			 JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u AND ce.email = :e COLLATE NOCASE LIMIT 1');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		if ($id = (int) $stmt->fetchColumn()) {
			$pdo->prepare('UPDATE contacts SET frequency = frequency + 1 WHERE id = :id')
				->execute(['id' => $id]);
			return;
		}
		$pdo->prepare('INSERT INTO contacts (user_id, name, properties) VALUES (:u,:n,:p)')
			->execute(['u' => $userId, 'n' => $name, 'p' => '[]']);
		$pdo->prepare('INSERT INTO contact_emails (contact_id, email) VALUES (:c,:e)')
			->execute(['c' => $pdo->lastInsertId(), 'e' => $email]);
	}
}
