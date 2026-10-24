<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;
use MXWebMail\Security\CredentialCrypt;

/**
 * Authentification : vérifie les credentials contre IMAP, puis
 * provisionne l'utilisateur applicatif (user + account + identity).
 * Le mot de passe IMAP est stocké chiffré en session (sodium + app.key).
 */
final class AuthService
{
	public function __construct(private App $app) {}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	/**
	 * Tente un login IMAP. Retourne ['ok'=>true] ou lève une exception métier.
	 * Applique le rate-limit avant même de toucher au réseau.
	 */
	public function login(string $email, string $password, ?string $totpCode = null): array
	{
		$email = trim(mb_strtolower($email));
		if ($email === '' || $password === '') {
			throw new \InvalidArgumentException($this->t('errors.credentials_required'));
		}

		$this->checkRateLimit($email);

		// Résolution du domaine : table domains → wildcard '*' → détection.
		// Login sans @domaine : 'auto' déduit le suffixe de HTTP_HOST
		// (sans www. ni :port), sinon suffixe fixe default_domain.
		// Un domaine vide reste possible : un '*' peut servir les logins courts.
		if (str_contains($email, '@')) {
			$domainName = substr($email, strrpos($email, '@') + 1);
		} else {
			$domainName = strtolower(trim(
				(string) $this->app->config()->get('default_domain', '')));
			if ($domainName === 'auto') {
				$domainName = preg_replace('/:\d+$/', '',
					strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')));
				$domainName = preg_replace('/^www\./', '', $domainName);
				// Le Host header est client-contrôlé : un domaine dérivé
				// invalide retombe sur '' (wildcard) plutôt que d'être
				// injecté dans le username provisionné.
				if (!preg_match('/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/', $domainName)) {
					$domainName = '';
				}
			}
			if ($domainName !== '') {
				$email .= '@' . $domainName;
			}
		}
		$domain = $this->resolveDomain($domainName) ?? ['name' => $domainName];

		// Hook : un plugin peut fournir/mapper les credentials (ldap-auth, login-*)
		// ou refuser le login en levant une exception (white-list…).
		$mapped = $this->app->hooks()->first('auth.login.mapping', $email, $domain);
		if (is_array($mapped)) {
			[$email, $domain] = [$mapped['email'] ?? $email, $mapped['domain'] ?? $domain];
		}

		// Compte bloqué par l'admin : refus avant toute requête IMAP.
		// mb_strtolower : le hook peut retourner une casse non normalisée.
		$existing = $this->app->users()->findByUsername(mb_strtolower($email));
		if ($existing && (int) ($existing['disabled'] ?? 0)) {
			throw new \RuntimeException($this->t('errors.account_disabled'), 403);
		}

		// Option de domaine « whitelist » : emails autorisés (wildcards)
		$whiteList = trim((string) (self::domainOptions($domain)['whitelist'] ?? ''));
		if ($whiteList !== '' && !\MXWebMail\Security\Wildcard::match($email, $whiteList)) {
			throw new \RuntimeException($this->t('errors.account_not_allowed'), 403);
		}

		// Option « secondary_only » : le domaine ne sert qu'aux comptes
		// secondaires — jamais de login primaire dessus (ex. gmail.com).
		if (!empty(self::domainOptions($domain)['secondary_only'])) {
			throw new \RuntimeException($this->t('errors.domain_secondary_only'), 403);
		}

		// Restriction : le domaine final doit être listé en table (ni '*'
		// ni domaine auto-détecté). Test par nom : un plugin qui mappe vers
		// un domaine listé reste accepté.
		if ($this->app->config()->get('restrict_domains', false)) {
			$dn = (string) ($domain['name'] ?? '');
			$listed = $dn !== '' && $dn !== '*'
				&& (!empty($domain['id'])
					|| $this->app->domains()->findByName($dn) !== null);
			if (!$listed) {
				throw new \RuntimeException($this->t('errors.domain_not_allowed'), 403);
			}
		}

		// Hook : un plugin peut prendre en charge l'authentification complète
		// (retour ['email'=>…,'password'=>…] = credentials IMAP validés par le plugin,
		//  la vérification IMAP habituelle est alors sauté ; null = vérification IMAP).
		$provided = $this->app->hooks()->first('auth.provider', $email, $password, $domain);
		if (is_array($provided)) {
			$email = $provided['email'] ?? $email;
			$password = $provided['password'] ?? $password;
		} else {
			$this->verifyImapCredentials($email, $password, $domain);
		}

		// Provisioning applicatif
		$user = $this->app->users()->findOrCreate($email);
		$accountId = $this->provisionAccount((int) $user['id'], $email, $password);
		$this->app->users()->touchLastLogin((int) $user['id']);

		// Hook post-login AVANT la session : un plugin (2FA…) peut refuser
		// en levant une exception → aucune session n'est alors établie.
		$this->app->hooks()->run('auth.post-login', $user, $totpCode);

		// Session
		session_regenerate_id(true);
		$_SESSION['user_id']    = (int) $user['id'];
		$_SESSION['account_id'] = $accountId;
		$_SESSION['email']      = $email;
		$_SESSION['imap_pass']  = CredentialCrypt::encrypt($password, $this->app->storageDir());
		$_SESSION['domain']     = $domainName;
		$_SESSION['acct_auth']      = 'password';
		$_SESSION['acct_secondary'] = false;
		unset($_SESSION['oauth_pending']);

		$this->resetRateLimit($email);
		return ['email' => $email];
	}

	public function logout(): void
	{
		if (!empty($_SESSION['admin'])) {
			// Session admin active : on ne purge que les clés webmail.
			// 'csrf_token' (Csrf::SESSION_KEY, const privée) est conservé :
			// l'onglet admin partage ce jeton — le purger casserait ses POST.
			$_SESSION = array_intersect_key($_SESSION,
				array_flip(['admin', 'csrf_token']));
			session_regenerate_id(true);
			return;
		}
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
	}

	// ─── IMAP ───

	private function verifyImapCredentials(string $email, string $password, array $domain,
		bool $oauth = false): void
	{
		if (empty($domain['imap_host']) || (int) ($domain['imap_port'] ?? 0) <= 0) {
			throw new \RuntimeException(
				$this->t('errors.imap_not_configured', ['domain' => $domain['name'] ?? '']), 401);
		}
		$settings = self::imapSettings($domain);
		$settings->username = $settings->fixUsername($email);
		$settings->passphrase = $password;
		if ($oauth) {
			// Passphrase = access token ; les mécanismes par défaut (PLAIN,
			// LOGIN) l'échoueraient — OAuth2 explicite.
			$settings->SASLMechanisms = ['OAUTHBEARER', 'XOAUTH2'];
		}

		$client = new \MailSo\Imap\ImapClient();
		$client->SetLogger(\MXWebMail\API::Logger());
		try {
			$client->Settings = $settings;
			$client->Connect($settings);
			$client->Login($settings);
		} finally {
			try { $client->Disconnect(); } catch (\Throwable) {}
		}
	}

	/**
	 * Options avancées du domaine (colonne JSON domains.options) :
	 * {imap:{…Settings::fromArray…}, smtp:{…}, sieve:{…}, whitelist:"…"}.
	 * Les colonnes host/port/encryption restent la source de vérité.
	 */
	public static function domainOptions(array $domain): array
	{
		$opts = $domain['options'] ?? [];
		if (is_string($opts)) {
			$opts = json_decode($opts, true) ?: [];
		}
		return is_array($opts) ? $opts : [];
	}

	public static function imapSettings(array $domain): \MailSo\Imap\Settings
	{
		$options = (array) (self::domainOptions($domain)['imap'] ?? []);
		$s = \MailSo\Imap\Settings::fromArray(array_merge($options, [
			'host' => (string) ($domain['imap_host'] ?? ''),
			'port' => (int) ($domain['imap_port'] ?? 993),
			'type' => self::securityType($domain['imap_encryption'] ?? 'ssl'),
		]));
		isset($options['timeout']) || $s->timeout = 30;
		self::relaxLoopbackSsl($s);
		return $s;
	}

	public static function smtpSettings(array $domain): \MailSo\Smtp\Settings
	{
		$options = (array) (self::domainOptions($domain)['smtp'] ?? []);
		// fromArray() met useAuth à false par défaut — le mail sortant
		// s'authentifie sauf si l'option le désactive explicitement.
		$s = \MailSo\Smtp\Settings::fromArray(array_merge(['useAuth' => true], $options, [
			'host' => (string) ($domain['smtp_host'] ?? ''),
			'port' => (int) ($domain['smtp_port'] ?? 465),
			'type' => self::securityType($domain['smtp_encryption'] ?? 'ssl'),
		]));
		$s->Ehlo = \MailSo\Smtp\SmtpClient::EhloHelper();
		self::relaxLoopbackSsl($s);
		return $s;
	}

	/** Réglages Sieve (host/port + options.sieve : encryption, timeout, sasl…). */
	public static function sieveSettings(array $domain): \MailSo\Sieve\Settings
	{
		$options = (array) (self::domainOptions($domain)['sieve'] ?? []);
		$enc = (string) ($options['encryption'] ?? '');
		// fromArray exige 'type' (ou 'secure') — AUTO_DETECT résout via le port.
		$options['type'] = $enc !== ''
			? self::securityType($enc)
			: \MailSo\Net\Enumerations\ConnectionSecurityType::AUTO_DETECT;
		$options['enabled'] = true;
		return \MailSo\Sieve\Settings::fromArray(array_merge($options, [
			'host' => (string) ($domain['sieve_host'] ?? ''),
			'port' => (int) ($domain['sieve_port'] ?? 4190),
		]));
	}

	/**
	 * Hôte loopback : pas de vérification de certificat — les certs locaux
	 * n'ont généralement pas « localhost » en SAN (comportement SnappyMail).
	 */
	private static function relaxLoopbackSsl(\MailSo\Net\ConnectSettings $s): void
	{
		if (\in_array(\strtolower($s->host), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
			$s->ssl->verify_peer = false;
			$s->ssl->verify_peer_name = false;
		}
	}

	private static function securityType(string $enc): int
	{
		return match ($enc) {
			'ssl'    => \MailSo\Net\Enumerations\ConnectionSecurityType::SSL,
			'tls'    => \MailSo\Net\Enumerations\ConnectionSecurityType::STARTTLS,
			default  => \MailSo\Net\Enumerations\ConnectionSecurityType::NONE,
		};
	}

	/**
	 * Résolution d'un domaine : table domains → wildcard '*' → détection
	 * automatique (autodetect.enabled ; le résultat est persisté en table
	 * pour que MailService le retrouve via domainFor()). null si rien.
	 */
	public function resolveDomain(string $name, bool $secondary = false): ?array
	{
		// Login sans @domaine : seul le wildcard '*' peut fournir la config.
		if ($name === '') {
			return $this->app->domains()->findByName('*');
		}
		$domain = $this->app->domains()->findByName($name);
		if ($domain) {
			return $domain;
		}
		if ($this->app->config()->get('restrict_domains', false)) {
			return null;
		}
		if ($name !== '*'
			&& ($domain = $this->app->domains()->findByName('*'))) {
			return $domain;
		}
		if (!empty(((array) $this->app->config()->get('autodetect', []))['enabled'])) {
			$detected = $this->app->domainDetector()->detect($name);
			if ($detected) {
				// Auto-détecté en contexte secondaire : sinon le domaine
				// nouvellement listé deviendrait loggable en primaire.
				if ($secondary) {
					$opts = is_array($detected['options'] ?? null) ? $detected['options'] : [];
					$opts['secondary_only'] = true;
					$detected['options'] = $opts;
				}
				$detected['id'] = $this->app->domains()->save($detected);
				$this->app->log('info', "domaine « {$name} » auto-détecté et créé");
				return $detected;
			}
		}
		return null;
	}

	public function domainFor(string $domainName, bool $secondary = false): array
	{
		return $this->resolveDomain($domainName, $secondary) ?? ['name' => $domainName];
	}

	// ─── Comptes additionnels ───

	public function accounts(int $userId): array
	{
		$stmt = $this->app->db()->pdo()->prepare(
			'SELECT id, email, is_default, auth FROM accounts WHERE user_id = :u ORDER BY is_default DESC, email'
		);
		$stmt->execute(['u' => $userId]);
		return $stmt->fetchAll();
	}

	/** Ajoute un compte IMAP secondaire après vérification des credentials. */
	public function addAccount(int $userId, string $email, string $password): array
	{
		$email = trim(mb_strtolower($email));
		if (!str_contains($email, '@') || $password === '') {
			throw new \InvalidArgumentException($this->t('errors.email_pass_required'));
		}
		$domainName = substr($email, strrpos($email, '@') + 1);
		$domain = $this->domainFor($domainName, true);
		// Domaine OAuth2 : l'ajout passe par le consentement provider
		// (/api/accounts/oauth), jamais par un mot de passe.
		if (!empty(self::domainOptions($domain)['oauth'])) {
			throw new \RuntimeException($this->t('errors.oauth_required'), 400);
		}
		$this->verifyImapCredentials($email, $password, $domain);

		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('SELECT id FROM accounts WHERE user_id = :u AND email = :e');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		if ($row = $stmt->fetch()) {
			// Compte déjà connu → on met à jour le mot de passe stocké
			// (auth='password' : couvre un domaine repassé d'OAuth à mot de passe)
			$enc = CredentialCrypt::encrypt($password, $this->app->storageDir());
			$pdo->prepare("UPDATE accounts SET imap_pass = :ip, auth = 'password' WHERE id = :id")
				->execute(['ip' => $enc, 'id' => $row['id']]);
			// Compte actif : la session suit les nouveaux identifiants.
			if ((int) $row['id'] === (int) ($_SESSION['account_id'] ?? 0)) {
				$_SESSION['imap_pass'] = $enc;
				$_SESSION['acct_auth'] = 'password';
			}
			return ['id' => (int) $row['id'], 'email' => $email];
		}
		return ['id' => $this->provisionAccount($userId, $email, $password, false), 'email' => $email];
	}

	/**
	 * Provisionne un compte secondaire OAuth2 (callback du flow provider) :
	 * la preuve d'appartenance est la connexion IMAP XOAUTH2 avec l'access
	 * token frais — l'email saisi est ainsi validé par le provider lui-même.
	 */
	public function addAccountOauth(int $userId, string $email, string $accessToken,
		string $refreshToken, string $provider): array
	{
		$email = trim(mb_strtolower($email));
		if (!str_contains($email, '@')) {
			throw new \InvalidArgumentException($this->t('errors.email_pass_required'));
		}
		$domainName = substr($email, strrpos($email, '@') + 1);
		$domain = $this->domainFor($domainName, true);
		if ((self::domainOptions($domain)['oauth'] ?? '') !== $provider) {
			throw new \RuntimeException($this->t('errors.oauth_required'), 400);
		}
		$this->verifyImapCredentials($email, $accessToken, $domain, true);

		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('SELECT id FROM accounts WHERE user_id = :u AND email = :e');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		$enc = CredentialCrypt::encrypt($refreshToken, $this->app->storageDir());
		if ($row = $stmt->fetch()) {
			// Compte déjà connu → nouveau refresh token + provider à jour
			$pdo->prepare('UPDATE accounts SET imap_pass = :ip, auth = :a WHERE id = :id')
				->execute(['ip' => $enc, 'a' => $provider, 'id' => $row['id']]);
			// Compte actif : la session suit les nouveaux identifiants.
			if ((int) $row['id'] === (int) ($_SESSION['account_id'] ?? 0)) {
				$_SESSION['imap_pass'] = $enc;
				$_SESSION['acct_auth'] = $provider;
			}
			return ['id' => (int) $row['id'], 'email' => $email];
		}
		return ['id' => $this->provisionAccount($userId, $email, $refreshToken, false, $provider),
			'email' => $email];
	}

	/** Bascule la session sur un autre compte du même utilisateur. */
	public function switchAccount(int $userId, int $accountId): array
	{
		$stmt = $this->app->db()->pdo()->prepare(
			'SELECT * FROM accounts WHERE id = :id AND user_id = :u'
		);
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		$account = $stmt->fetch();
		if (!$account) {
			throw new \RuntimeException($this->t('errors.account_not_found'), 404);
		}
		$_SESSION['account_id'] = (int) $account['id'];
		$_SESSION['email']      = $account['email'];
		// Le mot de passe est déjà chiffré avec la même clé applicative
		$_SESSION['imap_pass']  = $account['imap_pass'];
		$_SESSION['domain']     = substr($account['email'], strrpos($account['email'], '@') + 1);
		$_SESSION['acct_auth']      = (string) ($account['auth'] ?? 'password');
		$_SESSION['acct_secondary'] = !(int) $account['is_default'];
		return ['email' => $account['email']];
	}

	public function removeAccount(int $userId, int $accountId): void
	{
		if ($accountId === (int) ($_SESSION['account_id'] ?? 0)) {
			throw new \RuntimeException($this->t('errors.account_active'), 400);
		}
		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('DELETE FROM accounts WHERE id = :id AND user_id = :u AND is_default = 0');
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		if (!$stmt->rowCount()) {
			throw new \RuntimeException($this->t('errors.account_not_found_or_main'), 404);
		}
	}

	/**
	 * Définit le compte principal de l'utilisateur : rattache les nouvelles
	 * identités sans compte explicite et protège de la suppression.
	 */
	public function setDefaultAccount(int $userId, int $accountId): void
	{
		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('SELECT id FROM accounts WHERE id = :id AND user_id = :u');
		$stmt->execute(['id' => $accountId, 'u' => $userId]);
		if (!$stmt->fetch()) {
			throw new \RuntimeException($this->t('errors.account_not_found'), 404);
		}
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
		// Le compte actif devient secondaire s'il n'est pas le nouveau principal.
		$_SESSION['acct_secondary'] =
			(int) ($_SESSION['account_id'] ?? 0) !== $accountId;
	}

	// ─── Provisioning ───

	private function provisionAccount(int $userId, string $email, string $password,
		bool $isDefault = true, string $auth = 'password'): int
	{
		$pdo = $this->app->db()->pdo();
		$stmt = $pdo->prepare('SELECT id FROM accounts WHERE user_id = :u AND email = :e');
		$stmt->execute(['u' => $userId, 'e' => $email]);
		if ($row = $stmt->fetch()) {
			return (int) $row['id'];
		}
		// Un seul principal : un compte recréé au login après suppression de
		// l'ancien ne doit pas produire deux is_default.
		if ($isDefault) {
			$d = $pdo->prepare('SELECT 1 FROM accounts WHERE user_id = :u AND is_default = 1');
			$d->execute(['u' => $userId]);
			$isDefault = !$d->fetchColumn();
		}
		$pdo->prepare(
			'INSERT INTO accounts (user_id, email, imap_user, imap_pass, is_default, auth)
			 VALUES (:u, :e, :iu, :ip, :d, :a)'
		)->execute([
			'u'  => $userId,
			'e'  => $email,
			'iu' => $email,
			'ip' => CredentialCrypt::encrypt($password, $this->app->storageDir()),
			'd'  => $isDefault ? 1 : 0,
			'a'  => $auth,
		]);
		$accountId = (int) $pdo->lastInsertId();
		$pdo->prepare(
			'INSERT INTO identities (account_id, name, email, is_default) VALUES (:a, :n, :e, 1)'
		)->execute(['a' => $accountId, 'n' => '', 'e' => $email]);
		return $accountId;
	}

	// ─── Rate limit (fichier, par IP+login) ───

	private function attemptsFile(): string
	{
		$dir = $this->app->storageDir() . '/cache';
		is_dir($dir) || mkdir($dir, 0700, true);
		return $dir . '/login_attempts.json';
	}

	public function checkRateLimit(string $email): void
	{
		$attempts = $this->loadAttempts();
		$key = $this->attemptKey($email);
		$entry = $attempts[$key] ?? null;
		if ($entry && $entry['count'] >= (int) $this->app->config()->get('login_max_attempts', 5)) {
			$wait = $entry['until'] - time();
			if ($wait > 0) {
				throw new \RuntimeException(
					$this->t('errors.rate_limit', ['wait' => $wait]), 429);
			}
		}
	}

	public function recordFailedAttempt(string $email): void
	{
		$this->withAttemptsLock(function (array &$attempts) use ($email): void {
			$key = $this->attemptKey($email);
			$entry = $attempts[$key] ?? ['count' => 0, 'until' => 0];
			// Verrou expiré → nouveau cycle : l'utilisateur légitime récupère
			// login_max_attempts essais au lieu d'être reverrouillé au 1er échec.
			if (($entry['until'] ?? 0) > 0 && $entry['until'] <= time()) {
				$entry['count'] = 0;
				$entry['until'] = 0;
			}
			$entry['count']++;
			$entry['last'] = time();
			if ($entry['count'] >= (int) $this->app->config()->get('login_max_attempts', 5)) {
				$entry['until'] = time() + (int) $this->app->config()->get('login_lock_seconds', 300);
			}
			$attempts[$key] = $entry;
		});
	}

	public function resetRateLimit(string $email): void
	{
		$this->withAttemptsLock(function (array &$attempts) use ($email): void {
			unset($attempts[$this->attemptKey($email)]);
		});
	}

	private function attemptKey(string $email): string
	{
		return hash('sha256', ($this->app->clientIp() ?: 'cli') . '|' . $email);
	}

	private function loadAttempts(): array
	{
		$file = $this->attemptsFile();
		if (!is_file($file)) {
			return [];
		}
		// Lecture sous verrou partagé : un withAttemptsLock() concurrent fait
		// ftruncate+fwrite — sans LOCK_SH on pourrait lire un fichier vide.
		$fp = fopen($file, 'rb');
		if (!$fp) {
			return [];
		}
		flock($fp, LOCK_SH);
		$raw = (string) stream_get_contents($fp);
		flock($fp, LOCK_UN);
		fclose($fp);
		$all = json_decode($raw, true) ?: [];
		// Purge : un verrou expiré depuis > 1 h ou une tentative inactive
		// depuis > 1 h disparaît — sans quoi le fichier grossit sans borne.
		return array_filter($all, fn($e) => ($e['until'] ?? 0) > time() - 3600
			|| ($e['last'] ?? 0) > time() - 3600);
	}

	/**
	 * Lecture-modification-écriture de login_attempts.json sous verrou
	 * exclusif : sans flock autour du read+write, des compteurs sont
	 * perdus en concurrence (deux requêtes lisent puis écrasent).
	 */
	private function withAttemptsLock(callable $mutate): void
	{
		$file = $this->attemptsFile();
		$fp = fopen($file, 'c+');
		if (!$fp) {
			return;
		}
		try {
			flock($fp, LOCK_EX);
			$all = json_decode((string) stream_get_contents($fp), true) ?: [];
			$mutate($all);
			rewind($fp);
			ftruncate($fp, 0);
			fwrite($fp, json_encode($all));
			fflush($fp);
		} finally {
			flock($fp, LOCK_UN);
			fclose($fp);
		}
	}
}
