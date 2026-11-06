<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

final class DomainRepository
{
	/** Mémoïsation par requête name => row|null (findByName est appelé en boucle). */
	private array $byName = [];

	public function __construct(private Database $db) {}

	public function findByName(string $name): ?array
	{
		$n = self::normalizeName($name);
		if (!array_key_exists($n, $this->byName)) {
			$stmt = $this->db->pdo()->prepare('SELECT * FROM domains WHERE name = :n AND active = 1');
			$stmt->execute(['n' => $n]);
			$this->byName[$n] = $stmt->fetch() ?: null;
		}
		return $this->byName[$n];
	}

	/** Existence sans filtre active (garde anti-doublon - name est UNIQUE). */
	public function exists(string $name): bool
	{
		$stmt = $this->db->pdo()->prepare('SELECT 1 FROM domains WHERE name = :n');
		$stmt->execute(['n' => self::normalizeName($name)]);
		return $stmt->fetchColumn() !== false;
	}

	/**
	 * Noms de domaine toujours en minuscules : la comparaison SQLite est
	 * binaire (Example.COM ≠ example.com) alors que utf8mb4_unicode_ci est
	 * insensible à la casse - sans normalisation le comportement diverge
	 * selon le driver.
	 */
	private static function normalizeName(string $name): string
	{
		return $name === '*' ? '*' : mb_strtolower(trim($name));
	}

	public function all(): array
	{
		return $this->db->pdo()->query('SELECT * FROM domains ORDER BY name')->fetchAll();
	}

	public function save(array $domain): int
	{
		$pdo = $this->db->pdo();
		$domain['name'] = self::normalizeName((string) $domain['name']);
		$options = self::sanitizeOptions($domain['options'] ?? null);
		$this->byName = []; // écriture (update/rename possible) > mémo vidée
		if (!empty($domain['id'])) {
			$pdo->prepare(
				'UPDATE domains SET name=:name, imap_host=:ih, imap_port=:ip, imap_encryption=:ie,
				 smtp_host=:sh, smtp_port=:sp, smtp_encryption=:se, sieve_host=:svh, sieve_port=:svp,
				 active=:a, options=:o
				 WHERE id=:id'
			)->execute([
				'id' => $domain['id'], 'name' => $domain['name'],
				'ih' => $domain['imap_host'], 'ip' => $domain['imap_port'], 'ie' => $domain['imap_encryption'],
				'sh' => $domain['smtp_host'], 'sp' => $domain['smtp_port'], 'se' => $domain['smtp_encryption'],
				'svh' => $domain['sieve_host'] ?? null, 'svp' => $domain['sieve_port'] ?? 4190,
				'a' => $domain['active'] ?? 1, 'o' => $options,
			]);
			return (int) $domain['id'];
		}
		$pdo->prepare(
			'INSERT INTO domains (name, imap_host, imap_port, imap_encryption, smtp_host, smtp_port,
			 smtp_encryption, sieve_host, sieve_port, active, options)
			 VALUES (:name, :ih, :ip, :ie, :sh, :sp, :se, :svh, :svp, :a, :o)'
		)->execute([
			'name' => $domain['name'],
			'ih' => $domain['imap_host'], 'ip' => $domain['imap_port'], 'ie' => $domain['imap_encryption'],
			'sh' => $domain['smtp_host'], 'sp' => $domain['smtp_port'], 'se' => $domain['smtp_encryption'],
			'svh' => $domain['sieve_host'] ?? null, 'svp' => $domain['sieve_port'] ?? 4190,
			'a' => $domain['active'] ?? 1, 'o' => $options,
		]);
		return (int) $pdo->lastInsertId();
	}

	/**
	 * Ne conserve que les clés d'options connues, avec types vérifiés.
	 * Structure : {imap:{…}, smtp:{…}, sieve:{…}, whitelist:"…",
	 * secondary_only:bool, oauth:"google|microsoft|yahoo", detected:bool} -
	 * les sous-objets sont passés à Settings::fromArray (MailSo).
	 */
	private static function sanitizeOptions(mixed $options): ?string
	{
		if (is_string($options)) {
			$options = json_decode($options, true);
		}
		if (!is_array($options)) {
			return null;
		}
		$out = [];

		$whiteList = trim((string) ($options['whitelist'] ?? ''));
		if ($whiteList !== '') {
			$out['whitelist'] = mb_substr($whiteList, 0, 2000);
		}
		// Marqueur "domaine auto-détecté" (badge admin ; perdu à la
		// prochaine sauvegarde manuelle - la ligne devient administrée).
		if (!empty($options['detected'])) {
			$out['detected'] = true;
		}
		// Domaine réservé aux comptes secondaires (login primaire refusé).
		if (!empty($options['secondary_only'])) {
			$out['secondary_only'] = true;
		}
		// Provider OAuth2 de ce domaine (comptes secondaires ajoutés par
		// consentement, jamais par mot de passe).
		if (in_array($options['oauth'] ?? '', ['google', 'microsoft', 'yahoo'], true)) {
			$out['oauth'] = $options['oauth'];
		}

		$bool = fn(array $a, string $k): array => array_key_exists($k, $a) ? [$k => (bool) $a[$k]] : [];
		$int = fn(array $a, string $k, int $max = 3600): array =>
			array_key_exists($k, $a) && is_numeric($a[$k]) && $a[$k] >= 0 && $a[$k] <= $max
				? [$k => (int) $a[$k]] : [];
		$str = fn(array $a, string $k, int $max = 255): array =>
			isset($a[$k]) && is_string($a[$k]) && strlen($a[$k]) <= $max && !preg_match('/[\r\n\x00]/', $a[$k])
				? [$k => $a[$k]] : [];
		$csv = fn(array $a, string $k): array =>
			isset($a[$k]) && is_string($a[$k])
				? [$k => array_values(array_filter(array_map('trim', explode(',', $a[$k]))))]
				: (isset($a[$k]) && is_array($a[$k])
					? [$k => array_values(array_filter(array_map(fn($v) => is_string($v) ? trim($v) : '', $a[$k])))]
					: []);

		foreach (['imap', 'smtp', 'sieve'] as $section) {
			$in = (array) ($options[$section] ?? []);
			$s = $bool($in, 'shortLogin') + $bool($in, 'lowerLogin')
				+ $bool($in, 'useAuth') + $str($in, 'stripLogin', 32)
				+ $int($in, 'timeout') + $csv($in, 'sasl') + self::ssl($in);
			if ($section === 'imap') {
				$s += $bool($in, 'fast_simple_search') + $bool($in, 'force_select')
					+ $bool($in, 'expunge_all_on_delete') + $bool($in, 'message_all_headers')
					+ $bool($in, 'fetch_new_messages')
					+ $int($in, 'message_list_limit', 100000)
					+ $str($in, 'search_filter') + $str($in, 'spam_headers') + $str($in, 'virus_headers')
					+ $csv($in, 'disabled_capabilities');
			} elseif ($section === 'smtp') {
				$s += $bool($in, 'setSender');
			} else { // sieve
				$s += $bool($in, 'authLiteral');
				$enc = (string) ($in['encryption'] ?? '');
				if (in_array($enc, ['ssl', 'tls', 'none'], true)) {
					$s['encryption'] = $enc;
				}
			}
			$s && $out[$section] = $s;
		}
		return $out ? json_encode($out) : null;
	}

	/** Sous-objet ssl : noms de propriétés de MailSo\Net\SSLContext uniquement. */
	private static function ssl(array $in): array
	{
		$in = (array) ($in['ssl'] ?? []);
		if (!$in) {
			return [];
		}
		$out = [];
		foreach (['verify_peer', 'verify_peer_name', 'allow_self_signed', 'SNI_enabled',
			'disable_compression'] as $k) {
			if (array_key_exists($k, $in)) {
				$out[$k] = (bool) $in[$k];
			}
		}
		foreach (['cafile', 'capath', 'local_cert'] as $k) {
			if (isset($in[$k]) && is_string($in[$k]) && strlen($in[$k]) <= 500) {
				$out[$k] = $in[$k];
			}
		}
		if (isset($in['security_level']) && is_numeric($in['security_level'])) {
			$out['security_level'] = max(0, min(5, (int) $in['security_level']));
		}
		return $out ? ['ssl' => $out] : [];
	}

	public function delete(int $id): void
	{
		$this->db->pdo()->prepare('DELETE FROM domains WHERE id = :id')->execute(['id' => $id]);
		$this->byName = [];
	}
}
