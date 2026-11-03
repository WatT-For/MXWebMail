<?php
declare(strict_types=1);

namespace MXWebMail\Service;

/**
 * Lecture des carnets d'adresses RainLoop/SnappyMail (schéma PDO :
 * rainloop_users + rainloop_ab_contacts + rainloop_ab_properties) -
 * partagée entre l'import utilisateur (/api/contacts/import, upload
 * d'un AddressBook.sqlite) et le plugin webmail-migration.
 *
 * Item : {name, emails:[{email, type}], properties:{…}, source_uid,
 * frequency} - même format que VCardContacts/CsvContacts, consommé tel
 * quel par ContactRepository::import().
 */
final class RainLoopContacts
{
	/** prop_type > clé de properties (hors EMAIL/UID/FN traités à part). */
	private const PROP_MAP = [
		15 => 'first', 16 => 'last', 17 => 'middle', 18 => 'nick',
		20 => 'prefix', 21 => 'suffix', 31 => 'tel', 32 => 'url',
		40 => 'bday', 110 => 'note',
	];

	/** Signature d'en-tête d'un fichier SQLite ("SQLite format 3\0"). */
	public static function isSqlite(string $header): bool
	{
		return str_starts_with($header, "SQLite format 3\0");
	}

	/** Contacts visibles (non supprimés) - comptage rapide pour les scans. */
	public static function contactCount(\PDO $pdo): int
	{
		return (int) $pdo->query(
			'SELECT COUNT(*) FROM rainloop_ab_contacts WHERE deleted = 0'
		)->fetchColumn();
	}

	/**
	 * Nombre de propriétaires réels des contacts (id_user résolus dans
	 * rainloop_users, contacts non supprimés). Les id_user orphelins
	 * (ligne rainloop_users purgée) ne comptent pas - un carnet
	 * mono-propriétaire + orphelins reste mono-propriétaire.
	 */
	public static function ownerCount(\PDO $pdo): int
	{
		return (int) $pdo->query(
			'SELECT COUNT(DISTINCT c.id_user) FROM rainloop_ab_contacts c
			 JOIN rainloop_users u ON u.id_user = c.id_user
			 WHERE c.deleted = 0')->fetchColumn();
	}

	/**
	 * Lit un carnet et groupe les items par email utilisateur
	 * (rainloop_users.rl_email). $onlyEmail filtre un seul compte.
	 * $includeUnmapped regroupe les contacts sans id_user résolu sous la
	 * clé '' (un carnet par-compte peut ne pas référencer rainloop_users) ;
	 * false les ignore.
	 * @return array<string, array<int, array>>
	 */
	public static function readAddressBook(\PDO $pdo, ?string $onlyEmail = null,
		bool $includeUnmapped = false): array
	{
		// rainloop_users : id_user > rl_email (association au compte)
		$emails = [];
		foreach ($pdo->query('SELECT id_user, rl_email FROM rainloop_users') ?: [] as $r) {
			$emails[(int) $r['id_user']] = mb_strtolower((string) $r['rl_email']);
		}
		$contacts = $pdo->query(
			'SELECT id_contact, id_user, id_contact_str, display, deleted
			 FROM rainloop_ab_contacts')->fetchAll(\PDO::FETCH_ASSOC);
		$props = $pdo->query(
			'SELECT id_contact, id_user, prop_type, prop_type_str, prop_value,
				prop_value_custom, prop_frec
			 FROM rainloop_ab_properties')->fetchAll(\PDO::FETCH_ASSOC);

		$byContact = [];
		foreach ($props as $p) {
			$byContact[(int) $p['id_user']][(int) $p['id_contact']][] = $p;
		}

		$out = [];
		foreach ($contacts as $c) {
			if ((int) ($c['deleted'] ?? 0)) {
				continue;
			}
			$email = $emails[(int) $c['id_user']] ?? null;
			if ($email === null && !$includeUnmapped) {
				continue;
			}
			$key = (string) $email;
			if ($onlyEmail !== null && $key !== mb_strtolower($onlyEmail)) {
				continue;
			}
			$item = ['name' => '', 'emails' => [], 'properties' => [],
				'source_uid' => (string) ($c['id_contact_str'] ?? ''), 'frequency' => 0];
			foreach ((array) ($byContact[(int) $c['id_user']][(int) $c['id_contact']] ?? []) as $p) {
				$type = (int) $p['prop_type'];
				$val  = (string) ($p['prop_value'] ?? '');
				if ($type === 30) { // EMAIL
					if (filter_var($val, FILTER_VALIDATE_EMAIL)) {
						$item['emails'][] = ['email' => $val,
							'type' => self::propTypeStr($p)];
						$item['frequency'] = max($item['frequency'], (int) $p['prop_frec']);
					}
				} elseif ($type === 10) { // FULLNAME
					$item['name'] = $item['name'] ?: $val;
				} elseif ($type === 9) { // UID
					$item['source_uid'] = $item['source_uid'] ?: $val;
				} elseif (isset(self::PROP_MAP[$type]) && $val !== '') {
					$k = self::PROP_MAP[$type];
					$item['properties'][$k] = $item['properties'][$k] ?? $val;
				} elseif ($type === 250 && $val !== '') { // CUSTOM
					$k = trim((string) ($p['prop_value_custom'] ?: $p['prop_type_str']));
					$item['properties']['x-' . substr(preg_replace('/[^a-z0-9\-]/i', '-',
						strtolower($k ?: 'custom')), 0, 40)] = $val;
				}
				// 251 JCARD : doublon structuré des props - ignoré
			}
			$item['name'] = trim((string) ($c['display'] ?? '')) !== ''
				? trim((string) $c['display']) : $item['name'];
			$out[$key][] = $item;
		}
		return $out;
	}

	/**
	 * Items plats pour l'import d'un carnet par un utilisateur : filtré sur
	 * son email quand il est référencé, sinon tout si le carnet est
	 * mono-propriétaire (tous les contacts appartiennent à un seul compte
	 * résolu - évite d'aspirer les contacts d'autres comptes dans un
	 * carnet global réellement partagé).
	 * @return array<int, array>
	 */
	public static function itemsForUser(\PDO $pdo, string $email): array
	{
		$groups = self::readAddressBook($pdo, null, true);
		$key = mb_strtolower($email);
		if (isset($groups[$key])) {
			return $groups[$key];
		}
		if (self::ownerCount($pdo) > 1) {
			return [];
		}
		return array_merge(...array_values($groups));
	}

	/** prop_type_str "work,home" > premier type significatif. */
	private static function propTypeStr(array $p): ?string
	{
		foreach (preg_split('/[\s,]+/', (string) ($p['prop_type_str'] ?? '')) ?: [] as $t) {
			$t = strtolower(trim($t));
			if ($t !== '' && !in_array($t, ['internet', 'pref'], true)) {
				return substr($t, 0, 32);
			}
		}
		return null;
	}
}
