<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;
use MXWebMail\Security\CredentialCrypt;

/**
 * OAuth2 « authorization code + PKCE » pour les comptes secondaires des
 * domaines marqués options.oauth (google, microsoft, yahoo).
 *
 * Les identifiants d'application (client_id/secret/tenant) sont propres à
 * chaque utilisateur : table oauth_apps, saisis au premier ajout ou dans
 * Réglages → Applications OAuth2.
 *
 * Le flow ne produit jamais de mot de passe IMAP : accounts.imap_pass stocke
 * le refresh token chiffré (CredentialCrypt) ; chaque connexion échange un
 * access token éphémère injecté en passphrase XOAUTH2/OAUTHBEARER (MailSo).
 */
final class OAuthService
{
	/** Endpoints/scopes provider — '{tenant}' = tenant de l'app utilisateur. */
	private const PROVIDERS = [
		'google' => [
			'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token_url' => 'https://oauth2.googleapis.com/token',
			'scope'     => 'https://mail.google.com/',
			// offline + consent : garantit un refresh_token à chaque consentement
			'extra'     => ['access_type' => 'offline', 'prompt' => 'consent'],
		],
		'microsoft' => [
			'auth_url'  => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
			'token_url' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
			'scope'     => 'offline_access https://outlook.office.com/IMAP.AccessAsUser.All '
				. 'https://outlook.office.com/SMTP.Send',
		],
		'yahoo' => [
			'auth_url'  => 'https://api.login.yahoo.com/oauth2/request_auth',
			'token_url' => 'https://api.login.yahoo.com/oauth2/get_token',
			'scope'     => 'openid mail-r mail-w',
		],
	];

	/**
	 * Domaines publics pré-autorisés à l'install (comptes secondaires OAuth
	 * uniquement) : name => [provider, imap_host, smtp_host, smtp_port,
	 * smtp_encryption]. IMAP = 993/ssl pour les trois. Hôtes synchrones avec
	 * DomainDetector::MX_PROVIDERS ; pas de ManageSieve chez ces providers.
	 */
	public const PRESET_DOMAINS = [
		'gmail.com'   => ['google',    'imap.gmail.com',        'smtp.gmail.com',       465, 'ssl'],
		'outlook.com' => ['microsoft', 'outlook.office365.com', 'smtp.office365.com',   587, 'tls'],
		'yahoo.com'   => ['yahoo',     'imap.mail.yahoo.com',   'smtp.mail.yahoo.com',  465, 'ssl'],
	];

	public function __construct(private App $app) {}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	public static function providers(): array
	{
		return array_keys(self::PROVIDERS);
	}

	/**
	 * Endpoints du provider ; $tenant = tenant Microsoft de l'app utilisateur.
	 * Slug/domaine/GUID uniquement — jamais de caractères d'URL.
	 */
	private function provider(string $name, string $tenant = 'common'): array
	{
		$p = self::PROVIDERS[$name]
			?? throw new \RuntimeException($this->t('errors.oauth_not_configured'), 400);
		$tenant = trim($tenant);
		$tenant = $tenant !== '' && preg_match('/^[A-Za-z0-9.\-]+$/', $tenant)
			? $tenant : 'common';
		foreach (['auth_url', 'token_url'] as $k) {
			$p[$k] = str_replace('{tenant}', $tenant, $p[$k]);
		}
		return $p;
	}

	/** App OAuth2 de l'utilisateur pour le provider (null si non enregistrée). */
	public function appFor(int $userId, string $provider): ?array
	{
		return isset(self::PROVIDERS[$provider])
			? $this->app->oauthApps()->appFor($userId, $provider)
			: null;
	}

	/**
	 * URI de redirection à déclarer chez le provider. Même filtrage du Host
	 * que la redirection force_https (en-tête client-contrôlé).
	 */
	public function redirectUri(): string
	{
		return $this->app->publicUrl('/api/oauth/callback');
	}

	/** État de flow : state anti-CSRF + challenge PKCE S256. */
	public function pending(string $provider, string $email): array
	{
		$verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
		return [
			'provider'  => $provider,
			'email'     => $email,
			'state'     => bin2hex(random_bytes(16)),
			'verifier'  => $verifier,
			'challenge' => rtrim(strtr(
				base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
		];
	}

	/** $app : identifiants de l'utilisateur (OAuthAppRepository::appFor). */
	public function authorizeUrl(array $pending, array $app): string
	{
		$p = $this->provider($pending['provider'], (string) ($app['tenant'] ?? ''));
		$q = [
			'client_id'             => (string) $app['client_id'],
			'redirect_uri'          => $this->redirectUri(),
			'response_type'         => 'code',
			'scope'                 => $p['scope'],
			'state'                 => $pending['state'],
			'code_challenge'        => $pending['challenge'],
			'code_challenge_method' => 'S256',
			'login_hint'            => $pending['email'],
		] + ($p['extra'] ?? []);
		return $p['auth_url'] . '?' . http_build_query($q);
	}

	/**
	 * Échange code → tokens. Retourne {access_token, refresh_token, email}.
	 * email = claim id_token si présent (sinon '' — la preuve d'appartenance
	 * est alors la connexion IMAP XOAUTH2 elle-même).
	 */
	public function exchangeCode(array $pending, string $code, array $app): array
	{
		$provider = (string) $pending['provider'];
		$data = $this->tokenRequest($provider, $app, [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $this->redirectUri(),
			'code_verifier' => $pending['verifier'],
		]);
		if (empty($data['refresh_token'])) {
			throw new \RuntimeException($this->t('errors.oauth_failed'), 400);
		}
		return [
			'access_token'  => (string) ($data['access_token'] ?? ''),
			'refresh_token' => (string) $data['refresh_token'],
			'email'         => $this->emailFromIdToken((string) ($data['id_token'] ?? '')),
		];
	}

	/**
	 * Access token frais pour le compte (grant refresh_token). Rotation :
	 * Microsoft et Yahoo réémettent un refresh_token à chaque usage — l'ancien
	 * est alors révoqué, il faut le remplacer en base et en session.
	 */
	public function accessToken(int $accountId, int $userId): string
	{
		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('SELECT id, email, imap_pass, auth FROM accounts WHERE id = :a AND user_id = :u');
		$stmt->execute(['a' => $accountId, 'u' => $userId]);
		$account = $stmt->fetch();
		if (!$account || !isset(self::PROVIDERS[(string) $account['auth']])) {
			throw new \RuntimeException($this->t('errors.account_not_found'), 404);
		}
		$app = $this->appFor($userId, (string) $account['auth']);
		if ($app === null) {
			throw new \RuntimeException($this->t('errors.oauth_app_missing'), 401);
		}
		$refresh = CredentialCrypt::decrypt((string) $account['imap_pass'], $this->app->storageDir());
		if ($refresh === null || $refresh === '') {
			throw new \RuntimeException($this->t('errors.oauth_failed'), 401);
		}
		$data = $this->tokenRequest((string) $account['auth'], $app, [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh,
		]);
		$token = (string) ($data['access_token'] ?? '');
		if ($token === '') {
			throw new \RuntimeException($this->t('errors.oauth_failed'), 401);
		}
		if (!empty($data['refresh_token']) && $data['refresh_token'] !== $refresh) {
			$enc = CredentialCrypt::encrypt((string) $data['refresh_token'], $this->app->storageDir());
			$pdo->prepare('UPDATE accounts SET imap_pass = :ip WHERE id = :a')
				->execute(['ip' => $enc, 'a' => $accountId]);
			if ((int) ($_SESSION['account_id'] ?? 0) === $accountId) {
				$_SESSION['imap_pass'] = $enc;
			}
		}
		return $token;
	}

	// ─── internes ───

	/** POST form-urlencoded sur l'endpoint token du provider → JSON décodé. */
	private function tokenRequest(string $provider, array $app, array $params): array
	{
		$p = $this->provider($provider, (string) ($app['tenant'] ?? ''));
		$clientId = (string) $app['client_id'];
		$params['client_id'] = $clientId;
		$req = \MXWebMailLib\HTTP\Request::factory();
		$req->timeout = 15;
		$headers = ['Content-Type: application/x-www-form-urlencoded'];
		// Credentials en Basic (RFC 6749 §2.3.1) : exigé par Yahoo, accepté
		// par Google et le endpoint v2.0 Microsoft. En-tête direct — le
		// transport « socket » n'envoie setAuth() qu'après un challenge 401.
		$secret = (string) ($app['secret'] ?? '');
		if ($secret !== '') {
			$headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $secret);
		}
		$req->verify_peer = true;
		$ca = (string) (((array) $this->app->config()->get('ssl'))['cafile'] ?? '');
		if ($ca !== '') {
			$req->setCABundleFile($ca);
		}
		try {
			$res = $req->doRequest('POST', $p['token_url'], $params, $headers);
		} catch (\Throwable $e) {
			throw new \RuntimeException($this->t('errors.oauth_failed'), 0, $e);
		}
		$data = $res && $res->status === 200 && is_string($res->body)
			? (json_decode($res->body, true) ?: [])
			: [];
		if (!$data) {
			$detail = is_string($res?->body)
				? (string) (json_decode($res->body, true)['error_description']
					?? json_decode($res->body, true)['error'] ?? '')
				: '';
			throw new \RuntimeException(
				$this->t('errors.oauth_failed') . ($detail !== '' ? " ({$detail})" : ''), 400);
		}
		return $data;
	}

	/** Claim 'email' (ou preferred_username Microsoft) d'un id_token JWT, '' sinon. */
	private function emailFromIdToken(string $jwt): string
	{
		$parts = explode('.', $jwt);
		if (count($parts) !== 3) {
			return '';
		}
		$claims = json_decode(
			(string) base64_decode(strtr($parts[1], '-_', '+/'), true) ?: '', true);
		$email = (string) ($claims['email'] ?? $claims['preferred_username'] ?? '');
		return str_contains($email, '@') ? mb_strtolower($email) : '';
	}
}
