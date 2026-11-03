<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Service\AuthService;

/**
 * Applications OAuth2 enregistrées par utilisateur (table oauth_apps) :
 * les identifiants d'app des providers (google, microsoft, yahoo) sont
 * propres à chaque utilisateur - l'admin n'autorise que le provider sur
 * le domaine (options.oauth). secret = CredentialCrypt, jamais renvoyé
 * au frontend (allFor ne le sélectionne pas).
 */
final class OAuthAppRepository
{
	public function __construct(private Database $db, private string $storageDir) {}

	/** App d'un provider pour l'utilisateur ; secret déchiffré. null si absente. */
	public function appFor(int $userId, string $provider): ?array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT client_id, secret, tenant FROM oauth_apps WHERE user_id = :u AND provider = :p');
		$stmt->execute(['u' => $userId, 'p' => $provider]);
		$row = $stmt->fetch();
		if (!$row) {
			return null;
		}
		return [
			'client_id' => (string) $row['client_id'],
			'secret'    => (string) (CredentialCrypt::decrypt(
				(string) $row['secret'], $this->storageDir) ?? ''),
			'tenant'    => (string) $row['tenant'],
		];
	}

	/**
	 * Liste pour l'UI : jamais le secret. 'accounts' = nb de comptes du
	 * provider - l'app sert au refresh de leurs tokens (suppression cassante).
	 */
	public function allFor(int $userId): array
	{
		$stmt = $this->db->pdo()->prepare(
			'SELECT o.provider, o.client_id, o.tenant,
				(SELECT COUNT(*) FROM accounts a
					WHERE a.user_id = o.user_id AND a.auth = o.provider) AS accounts
				FROM oauth_apps o WHERE o.user_id = :u ORDER BY o.provider');
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	/** Upsert (user_id, provider) ; $secret vide/null = inchangé. */
	public function save(int $userId, string $provider, string $clientId,
		?string $secret, string $tenant): void
	{
		$pdo = $this->db->pdo();
		$existing = $pdo->prepare(
			'SELECT id FROM oauth_apps WHERE user_id = :u AND provider = :p');
		$existing->execute(['u' => $userId, 'p' => $provider]);
		$id = $existing->fetchColumn();
		if ($id !== false) {
			$pdo->prepare('UPDATE oauth_apps SET client_id = :c, tenant = :t WHERE id = :id')
				->execute(['c' => $clientId, 't' => $tenant, 'id' => $id]);
			if ($secret !== null && $secret !== '') {
				$pdo->prepare('UPDATE oauth_apps SET secret = :s WHERE id = :id')
					->execute([
						's' => CredentialCrypt::encrypt($secret, $this->storageDir),
						'id' => $id]);
			}
			return;
		}
		$pdo->prepare(
			'INSERT INTO oauth_apps (user_id, provider, client_id, secret, tenant)
				VALUES (:u, :p, :c, :s, :t)')
			->execute([
				'u' => $userId, 'p' => $provider, 'c' => $clientId,
				's' => CredentialCrypt::encrypt((string) $secret, $this->storageDir),
				't' => $tenant]);
	}

	public function delete(int $userId, string $provider): void
	{
		$this->db->pdo()->prepare(
			'DELETE FROM oauth_apps WHERE user_id = :u AND provider = :p')
			->execute(['u' => $userId, 'p' => $provider]);
	}

	/**
	 * Providers autorisés : ceux référencés par options.oauth des domaines
	 * actifs (y compris '*' - fallback de resolveDomain). Borne
	 * l'enregistrement : une app sans domaine qui l'autorise est inutilisable.
	 */
	public function allowedProviders(): array
	{
		$out = [];
		foreach ($this->db->pdo()
			->query('SELECT options FROM domains WHERE active = 1')->fetchAll() as $d) {
			$p = AuthService::domainOptions($d)['oauth'] ?? '';
			if ($p !== '') {
				$out[$p] = true;
			}
		}
		return array_keys($out);
	}
}
