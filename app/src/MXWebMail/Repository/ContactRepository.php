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
			 WHERE c.user_id = :u AND (c.name LIKE :q OR ce.email LIKE :q
			 	OR c.properties LIKE :q)
			 ORDER BY c.frequency DESC, c.name LIMIT :l'
		);
		$stmt->bindValue('u', $userId, \PDO::PARAM_INT);
		$stmt->bindValue('q', '%' . $q . '%');
		$stmt->bindValue('l', $limit, \PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		// name = email : nom de substitution autosave, masqué pour éviter le
		// doublon "x@y - x@y" dans les suggestions et ""x@y" <x@y>" à l'envoi.
		foreach ($rows as &$r) {
			if (strcasecmp((string) $r['name'], (string) $r['email']) === 0) {
				$r['name'] = '';
			}
		}
		return $rows;
	}

	public function all(int $userId): array
	{
		$stmt = $this->udb->for($userId)->pdo()->prepare(
			'SELECT c.*, GROUP_CONCAT(ce.email, ", ") AS emails
			 FROM contacts c LEFT JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u GROUP BY c.id ORDER BY c.name'
		);
		$stmt->execute(['u' => $userId]);
		$rows = $stmt->fetchAll();
		foreach ($rows as &$r) {
			$r['properties'] = json_decode((string) ($r['properties'] ?? ''), true) ?: [];
		}
		return $rows;
	}

	/**
	 * Lignes complètes pour l'export : emails typés, properties décodées.
	 * @return array<int, array>
	 */
	public function exportRows(int $userId): array
	{
		$pdo = $this->udb->for($userId)->pdo();
		// Un seul JOIN : l'ancien code interrogeait contact_emails par contact (N+1).
		$stmt = $pdo->prepare(
			'SELECT c.id, c.name, c.properties, c.source_uid, ce.email, ce.type
			 FROM contacts c LEFT JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u ORDER BY c.name, ce.id'
		);
		$stmt->execute(['u' => $userId]);
		$out = [];
		foreach ($stmt->fetchAll() as $r) {
			$id = (int) $r['id'];
			if (!isset($out[$id])) {
				$out[$id] = [
					'id'         => $id,
					'name'       => (string) $r['name'],
					'emails'     => [],
					'properties' => json_decode((string) ($r['properties'] ?? ''), true) ?: [],
					'source_uid' => $r['source_uid'],
				];
			}
			if ($r['email'] !== null) {
				$out[$id]['emails'][] = ['email' => $r['email'], 'type' => $r['type']];
			}
		}
		return array_values($out);
	}

	public function save(int $userId, array $data): int
	{
		$pdo = $this->udb->for($userId)->pdo();
		$emails = array_values(array_filter(array_map('trim',
			preg_split('/[,;]/', (string) ($data['emails'] ?? ''))),
			static fn(string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false));

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
		$insMail = $pdo->prepare('INSERT INTO contact_emails (contact_id, email) VALUES (:c, :e)');
		foreach ($emails as $email) {
			$insMail->execute(['c' => $id, 'e' => $email]);
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
	 * Remplace intégralement les contacts de cette source - les locaux sont préservés.
	 * @param array<array{name:string,emails:array,source_uid:string}> $items
	 *   emails : chaînes ou {email, type} (format VCardContacts).
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
			$insMail = $pdo->prepare('INSERT INTO contact_emails (contact_id, email, type) VALUES (:c,:e,:t)');
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
				foreach (\MXWebMail\Service\VCardContacts::normalizeEmails(
						(array) ($item['emails'] ?? [])) as $e) {
					$insMail->execute(['c' => $id, 'e' => $e['email'], 't' => $e['type']]);
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
	 * Import additif (vCard, SnappyMail…) : déduplique sur les emails
	 * existants. Un contact déjà présent (un de ses emails) reçoit les
	 * adresses manquantes + son nom s'il était vide - jamais de remplacement.
	 * @param array<int, array> $items items VCardContacts ({name, emails,
	 *   properties, source_uid, frequency}) - emails chaînes ou {email,type}.
	 * @return array{imported:int, merged:int, skipped:int}
	 */
	public function import(int $userId, array $items, string $source = ''): array
	{
		$pdo = $this->udb->for($userId)->pdo();
		$find = $pdo->prepare(
			'SELECT c.id, c.name, c.properties FROM contacts c
			 JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u AND ce.email = :e COLLATE NOCASE
			 ORDER BY c.id LIMIT 1'
		);
		$report = ['imported' => 0, 'merged' => 0, 'skipped' => 0];
		// Statements préparés une fois hors boucle (ATTR_EMULATE_PREPARES=false :
		// chaque prepare est un aller-retour serveur sous MariaDB).
		$insMail = $pdo->prepare(
			'INSERT INTO contact_emails (contact_id, email, type) VALUES (:c,:e,:t)');
		$insMailIfNew = $pdo->prepare(
			'INSERT INTO contact_emails (contact_id, email, type)
			 SELECT :c, :e, :t WHERE NOT EXISTS (
				SELECT 1 FROM contact_emails WHERE contact_id = :c AND email = :e COLLATE NOCASE)');
		$pdo->beginTransaction();
		try {
			foreach ($items as $item) {
				$raw = $item['emails'] ?? [];
				if (is_string($raw)) {
					$raw = preg_split('/[,;]/', $raw) ?: [];
				}
				$emails = \MXWebMail\Service\VCardContacts::normalizeEmails((array) $raw);
				if (!$emails) {
					$report['skipped']++;
					continue;
				}

				$existing = null;
				foreach ($emails as $e) {
					$find->execute(['u' => $userId, 'e' => $e['email']]);
					if ($row = $find->fetch()) {
						$existing = $row;
						break;
					}
				}

				if ($existing) {
					$id = (int) $existing['id'];
					$changed = false;
					foreach ($emails as $e) {
						$insMailIfNew->execute(['c' => $id, 'e' => $e['email'], 't' => $e['type']]);
						$changed = $changed || $insMailIfNew->rowCount() > 0;
					}
					if (trim((string) $existing['name']) === ''
						&& trim((string) ($item['name'] ?? '')) !== '') {
						$pdo->prepare('UPDATE contacts SET name = :n WHERE id = :id')
							->execute(['n' => trim((string) $item['name']), 'id' => $id]);
						$changed = true;
					}
					// Properties : fusion additive, l'existant prévaut.
					$newProps = (array) ($item['properties'] ?? []);
					if ($newProps) {
						$oldProps = json_decode((string) ($existing['properties'] ?? ''), true) ?: [];
						$merged = $oldProps + $newProps;
						if ($merged !== $oldProps) {
							$pdo->prepare('UPDATE contacts SET properties = :p WHERE id = :id')
								->execute(['p' => json_encode($merged), 'id' => $id]);
							$changed = true;
						}
					}
					$report[$changed ? 'merged' : 'skipped']++;
					continue;
				}

				$pdo->prepare(
					'INSERT INTO contacts (user_id, name, frequency, properties, source, source_uid)
					 VALUES (:u, :n, :f, :p, :s, :su)'
				)->execute([
					'u'  => $userId,
					'n'  => trim((string) ($item['name'] ?? '')) ?: $emails[0]['email'],
					'f'  => max(0, (int) ($item['frequency'] ?? 0)),
					'p'  => json_encode($item['properties'] ?? []),
					's'  => $source,
					'su' => (string) ($item['source_uid'] ?? '') !== ''
						? (string) $item['source_uid'] : null,
				]);
				$id = (int) $pdo->lastInsertId();
				foreach ($emails as $e) {
					$insMail->execute(['c' => $id, 'e' => $e['email'], 't' => $e['type']]);
				}
				$report['imported']++;
			}
			$pdo->commit();
			return $report;
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}

	/**
	 * Destinataire d'un envoi : déjà connu > frequency++, sinon le contact
	 * est créé (name = display-name de l'adresse, l'adresse elle-même sinon).
	 * Un nom de substitution (vide ou = adresse) est remplacé par un vrai
	 * display-name ultérieur ; un nom renseigné manuellement est préservé.
	 */
	public function touchRecipient(int $userId, string $email, string $name = ''): void
	{
		$pdo = $this->udb->for($userId)->pdo();
		$stmt = $pdo->prepare(
			'SELECT c.id, c.name FROM contacts c
			 JOIN contact_emails ce ON ce.contact_id = c.id
			 WHERE c.user_id = :u AND ce.email = :e COLLATE NOCASE LIMIT 1');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		$name = trim($name);
		if ($row = $stmt->fetch()) {
			$id = (int) $row['id'];
			$pdo->prepare('UPDATE contacts SET frequency = frequency + 1 WHERE id = :id')
				->execute(['id' => $id]);
			$stored = (string) $row['name'];
			if ($name !== ''
				&& ($stored === '' || strcasecmp($stored, $email) === 0)
				&& strcasecmp($stored, $name) !== 0) {
				$pdo->prepare('UPDATE contacts SET name = :n WHERE id = :id')
					->execute(['n' => $name, 'id' => $id]);
			}
			return;
		}
		$pdo->prepare('INSERT INTO contacts (user_id, name, properties) VALUES (:u,:n,:p)')
			->execute(['u' => $userId, 'n' => $name !== '' ? $name : $email, 'p' => '[]']);
		$pdo->prepare('INSERT INTO contact_emails (contact_id, email) VALUES (:c,:e)')
			->execute(['c' => $pdo->lastInsertId(), 'e' => $email]);
	}
}
