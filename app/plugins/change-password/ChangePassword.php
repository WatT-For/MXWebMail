<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Validation\Rule;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Security\Wildcard;
use MXWebMail\Service\AuthService;
use MXWebMail\Service\LdapConnect;

/**
 * Changement de mot de passe - drivers : poppassd, pdo, ldap, mailcow.
 * Vérifie l'ancien mot de passe contre le credential IMAP de session,
 * applique pass_min_length / pass_min_strength / check_hibp,
 * puis délègue au driver configuré. Hook password.change + onglet Paramètres.
 * API : /api/plugin/change-password/config (contraintes pour l'UI).
 */
final class ChangePassword extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('password.change', function (array $user, string $old, string $new) {
			$this->changePassword($user, $old, $new);
			return true;
		});

		$hooks->on('app.data', function (array $data) {
			// Pas de mot de passe à changer sur un compte OAuth2 (session basculée)
			if (($this->app->sessionAuthProvider()) !== 'password') {
				$data['features'] += $this->feature('password', false);
				return $data;
			}
			$data['features'] += $this->feature('password');
			$data['pluginTabs'][] = $this->userTab('change-password.js', 'fa-lock');
			return $data;
		});
	}

	/** Contraintes exposées au JS de l'onglet (non sensibles). */
	public function apiConfig(Request $request, ?array $user): array
	{
		return [
			'minLength'   => $this->configInt('pass_min_length', 10),
			'minStrength' => $this->configInt('pass_min_strength', 70),
		];
	}

	private function changePassword(array $user, string $old, string $new): void
	{
		// Compte OAuth2 actif : pas de mot de passe IMAP à changer
		if (($this->app->sessionAuthProvider()) !== 'password') {
			throw new \RuntimeException($this->t('plugin.change-password.not_allowed'), 403);
		}
		$email = $this->app->sessionEmail() ?: (string) ($user['username'] ?? '');
		if ($email === '') {
			throw new \RuntimeException($this->t('plugin.change-password.email_not_found'), 400);
		}
		if (!Wildcard::match($email, $this->configString('allowed_emails', '*'))) {
			throw new \RuntimeException($this->t('plugin.change-password.not_allowed'), 403);
		}

		// L'ancien mot de passe doit correspondre au credential de session
		$sessionPass = CredentialCrypt::decrypt(
			(string) ($this->app->sessionImapPass()), $this->app->storageDir());
		if ($sessionPass === null || !\hash_equals($sessionPass, $old)) {
			throw new \RuntimeException($this->t('plugin.change-password.wrong_current'), 400);
		}
		unset($sessionPass);

		if (\mb_strlen($new) < $this->configInt('pass_min_length', 10)) {
			throw new \RuntimeException($this->t('plugin.change-password.too_short'), 400);
		}
		if ($this->configInt('pass_min_strength', 70) > self::strength($new)) {
			throw new \RuntimeException($this->t('plugin.change-password.too_weak'), 400);
		}
		if ($this->configBool('check_hibp') && \MXWebMailLib\Hibp::password($new) > 0) {
			throw new \RuntimeException($this->t('plugin.change-password.pwned'), 400);
		}

		$domain = $this->app->auth()->domainFor($this->app->sessionDomain());
		$login = AuthService::imapSettings($domain)->fixUsername($email);

		$driver = $this->configString('driver', 'poppassd');
		$ok = match ($driver) {
			'poppassd' => $this->poppassd($login, $old, $new),
			'pdo'      => $this->pdo($email, $login, $old, $new),
			'ldap'     => $this->ldap($email, $login, $domain, $old, $new),
			'mailcow'  => $this->mailcow($email, $new),
			default    => throw new \RuntimeException(
				$this->t('plugin.change-password.driver_unknown', ['driver' => $driver]), 500),
		};
		if ($ok) {
			$this->app->log('info', "change-password: mot de passe modifié pour {$email} ({$driver})");
		}
	}

	// ─── Drivers ───

	/**
	 * poppassd (port 106) :
	 *   200 hello > "user <login>" > 200 > "pass <old>" > 200 > "newpass <new>" > 200
	 */
	private function poppassd(string $login, string $old, string $new): bool
	{
		// CR/LF injecteraient des lignes arbitraires dans le protocole poppassd.
		foreach (['login' => $login, 'old' => $old, 'new' => $new] as $f => $v) {
			if (strpbrk($v, "\r\n") !== false) {
				throw new \RuntimeException(
					$this->t('errors.invalid_value', ['key' => $f]), 400);
			}
		}
		$host = $this->configString('poppassd_host', 'localhost');
		$port = $this->configInt('poppassd_port', 106);
		$fp = @fsockopen($host, $port, $errno, $errstr, 10);
		if (!$fp) {
			throw new \RuntimeException(
				$this->t('plugin.change-password.poppassd_down', ['host' => $host, 'port' => $port]), 502);
		}
		try {
			$this->expect($fp, '200'); // bannière
			fwrite($fp, "user {$login}\r\n");
			$this->expect($fp, '200');
			fwrite($fp, "pass {$old}\r\n");
			$this->expect($fp, '200');
			fwrite($fp, "newpass {$new}\r\n");
			$this->expect($fp, '200');
			fwrite($fp, "quit\r\n");
			return true;
		} finally {
			fclose($fp);
		}
	}

	private function expect($fp, string $code): void
	{
		$line = fgets($fp, 512);
		if ($line === false || !str_starts_with(trim($line), $code)) {
			throw new \RuntimeException(
				$this->t('plugin.change-password.poppassd_failed', ['msg' => trim((string) $line)]), 400);
		}
	}

	/** PDO : requête SQL libre, wildcards :email :oldpass :newpass :domain :username :login_name. */
	private function pdo(string $email, string $login, string $old, string $new): bool
	{
		$attrs = [
			\PDO::ATTR_EMULATE_PREPARES => true,
			\PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
		];
		if ($this->configBool('pdo_mysql_ssl')) {
			$ca = $this->configString('pdo_mysql_ssl_ca');
			if ($ca !== '') {
				$attrs[\PDO::MYSQL_ATTR_SSL_CA] = $ca;
			}
			$attrs[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] =
				$this->configBool('pdo_mysql_ssl_verify');
		}

		$conn = new \PDO(
			$this->configString('pdo_dsn'),
			$this->configString('pdo_user'),
			$this->configString('pdo_password'),
			$attrs
		);

		$algo   = $this->configString('pdo_encrypt', 'none');
		$prefix = $this->configString('pdo_encryptprefix');
		$at = strrpos($email, '@');
		$placeholders = [
			':email'      => $email,
			':oldpass'    => $prefix . self::encrypt($algo, $old),
			':newpass'    => $prefix . self::encrypt($algo, $new),
			':domain'     => Rule::emailDomain($email),
			':username'   => $at !== false ? substr($email, 0, $at) : $email,
			':login_name' => $login,
		];

		$sql = $this->configString('pdo_sql');
		if ($sql === '') {
			throw new \RuntimeException($this->t('plugin.change-password.sql_missing'), 500);
		}
		$stmt = $conn->prepare($sql);
		// Seuls les placeholders réellement présents dans la requête sont liés
		foreach ($placeholders as $ph => $value) {
			if (preg_match('/' . preg_quote($ph, '/') . '(?![a-zA-Z0-9\-])/', $sql)) {
				$stmt->bindValue($ph, $value);
			}
		}
		return (bool) $stmt->execute();
	}

	/** LDAP : bind avec le DN utilisateur + ancien mot de passe, puis mod_replace. */
	private function ldap(string $email, string $login, array $domain, string $old, string $new): bool
	{
		if (!\function_exists('ldap_connect')) {
			throw new \RuntimeException($this->t('plugin.change-password.ldap_ext_missing'), 500);
		}
		$at = strrpos($email, '@');
		$emailDomain = Rule::emailDomain($email);
		$emailUser   = $at !== false ? substr($email, 0, $at) : $email;

		// Valeurs substituées échappées pour le contexte DN (LDAP_ESCAPE_DN) :
		// une valeur contenant , + " \ ; etc. casserait la structure du DN.
		$escDn = fn(string $v) => ldap_escape($v, '', LDAP_ESCAPE_DN);
		$userDn = strtr($this->configString('ldap_user_dn_format'), [
			'{email}'        => $escDn($email),
			'{email:user}'   => $escDn($emailUser),
			'{email:domain}' => $escDn($emailDomain),
			'{login}'        => $escDn($login),
			'{domain}'       => $escDn($emailDomain),
			'{domain:dc}'    => 'dc=' . implode(',dc=', array_map($escDn, explode('.', $emailDomain))),
			'{imap:login}'   => $escDn($login),
			'{imap:host}'    => $escDn((string) ($domain['imap_host'] ?? '')),
			'{imap:port}'    => $escDn((string) ($domain['imap_port'] ?? '')),
		]);

		$con = LdapConnect::open($this->configString('ldap_uri',
			'ldap://localhost:389'));
		if (!$con) {
			throw new \RuntimeException($this->t('plugin.change-password.ldap_connect'), 502);
		}
		try {
			if ($this->configBool('ldap_use_start_tls') && !LdapConnect::startTls($con)) {
				throw new \RuntimeException(
					$this->t('plugin.change-password.ldap_starttls', ['msg' => ldap_error($con)]), 502);
			}
			if (!LdapConnect::bind($con, $userDn, $old)) {
				throw new \RuntimeException(
					$this->t('plugin.change-password.ldap_bind', ['msg' => ldap_error($con)]), 400);
			}

			$field = $this->configString('ldap_password_field', 'userPassword');
			$enc = strtoupper($this->configString('ldap_password_enc_type', 'SSHA'));
			$encoded = match ($enc) {
				'SSHA'  => '{SSHA}' . base64_encode(hash('sha1', $new . ($salt = self::salt(4)), true) . $salt),
				'SHA'   => '{SHA}' . base64_encode(hash('sha1', $new, true)),
				'MD5'   => '{MD5}' . base64_encode(md5($new, true)),
				'CRYPT' => '{CRYPT}' . crypt($new, self::salt(2)),
				default => $new,
			};

			if (!ldap_mod_replace($con, $userDn, [$field => $encoded])) {
				throw new \RuntimeException(
					$this->t('plugin.change-password.ldap_replace', ['msg' => ldap_error($con)]), 502);
			}
			return true;
		} finally {
			ldap_close($con);
		}
	}

	/** Mailcow : POST /api/v1/edit/mailbox avec token Read/Write. */
	private function mailcow(string $email, string $new): bool
	{
		$host = $this->configTrim('mailcow_api_hostname');
		$token = $this->configString('mailcow_api_token');
		if ($host === '' || $token === '') {
			throw new \RuntimeException($this->t('plugin.change-password.mailcow_unconfigured'), 500);
		}
		// newHttpRequest : verify_peer + cafile config (gagne le bundle
		// personnalisé au passage - l'appel direct factory l'ignorait).
		$request = $this->app->newHttpRequest();
		$response = $request->doRequest('POST',
			'https://' . $host . '/api/v1/edit/mailbox',
			json_encode([
				'items' => [$email],
				'attr'  => ['password' => $new, 'password2' => $new],
			]),
			[
				'Content-Type: application/json',
				'Accept: application/json',
				'X-API-Key: ' . $token,
			]);
		if ($response && $response->status === 200
			&& ($result = json_decode((string) $response->body, true))
			&& ($result[0]['type'] ?? '') === 'success') {
			return true;
		}
		throw new \RuntimeException(
			$this->t('plugin.change-password.mailcow_failed',
				['status' => $response->status ?? 'timeout']), 502);
	}

	// ─── Helpers ───

	public static function encrypt(string $algo, string $password): string
	{
		return match (strtolower($algo)) {
			'argon2i'      => password_hash($password, PASSWORD_ARGON2I),
			'argon2id'     => password_hash($password, PASSWORD_ARGON2ID),
			'bcrypt'       => password_hash($password, PASSWORD_BCRYPT),
			'sha256-crypt' => crypt($password, '$5$' . substr(base64_encode(random_bytes(32)), 0, 16)),
			'sha512-crypt' => crypt($password, '$6$' . substr(base64_encode(random_bytes(32)), 0, 16)),
			default        => $password,
		};
	}

	private static function salt(int $length): string
	{
		return substr(preg_replace('#+/=#', '', base64_encode(random_bytes($length))), 0, $length);
	}

	/** Score de robustesse 0-100 (portage de la fonction legacy). */
	public static function strength(string $password): int
	{
		$i = strlen($password);
		$max = min(100, $i * 8);
		$s = 0;
		while (--$i) {
			$s += ($password[$i] !== $password[$i - 1] ? 1 : -0.5);
		}
		$c = 0;
		foreach (['/[^0-9A-Za-z]+/', '/[0-9]+/', '/[A-Z]+/', '/[a-z]+/'] as $regex) {
			if (preg_match_all($regex, $password, $m)) {
				++$c;
				foreach ($m[0] as $str) {
					if (strlen($str) < 5) {
						++$s;
					}
				}
			}
		}
		return (int) max(0, min($max, $s * $c * 1.5));
	}
}
