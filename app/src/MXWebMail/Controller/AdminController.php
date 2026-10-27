<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;

/**
 * API admin : une seule administration globale (multi-domaine inclus).
 * Session admin distincte ($_SESSION['admin']).
 */
final class AdminController
{
	public function __construct(private App $app) {}

	public function login(Request $request): JsonResponse
	{
		if (!Csrf::verifyRequest()) {
			return JsonResponse::error($this->t('errors.csrf'), 'csrf', 403);
		}
		$this->requireAdminIp();
		$user = (string) $request->input('user', $request->input('email', ''));
		$password = (string) $request->input('password', '');
		$rateKey = 'admin:' . strtolower($user);
		$auth = $this->app->auth();
		try {
			$auth->checkRateLimit($rateKey);

			$cfgUser = (string) $this->app->config()->get('admin_user', '');
			$cfgHash = (string) $this->app->config()->get('admin_password_hash', '');
			if ($cfgUser !== '' && $cfgHash !== '') {
				// Compte admin local — indépendant d'IMAP
				if (!hash_equals($cfgUser, $user) || !password_verify($password, $cfgHash)) {
					throw new \RuntimeException($this->t('errors.bad_credentials'), 401);
				}
				// TOTP du compte local : délégué au plugin two-factor-auth
				// (admin.post-login — plugin désactivé = non exigé).
				$this->app->hooks()->run('admin.post-login',
					(string) $request->input('totp', ''));
				$_SESSION['admin'] = true;
				$auth->resetRateLimit($rateKey);
				$this->app->logAuth('admin:' . $user, 'AUTH OK');
				return JsonResponse::ok();
			}

			// Fallback : utilisateur is_admin vérifié via IMAP
			$auth->login($user, $password, (string) $request->input('totp', '') ?: null);
			$u = $this->app->users()->findByUsername(strtolower($user));
			if (!$u || !$this->app->users()->isAdmin((int) $u['id'])) {
				// login() a créé une session webmail complète : la détruire,
				// sinon un non-admin repart avec une session valide.
				$auth->logout();
				$this->app->logAuth('admin:' . $user, 'AUTH FAILED (403)');
				return JsonResponse::error($this->t('errors.admin_required'), 'forbidden', 403);
			}
			$_SESSION['admin'] = (int) $u['id'];
			$auth->resetRateLimit($rateKey);
			$this->app->logAuth('admin:' . $user, 'AUTH OK');
			return JsonResponse::ok();
		} catch (\MXWebMail\Security\TotpRequiredException $e) {
			if (trim((string) $request->input('totp', '')) !== '') {
				$auth->recordFailedAttempt($rateKey);
				$this->app->logAuth('admin:' . $user, 'AUTH FAILED (totp)');
			}
			return JsonResponse::error($e->getMessage(), 'totp_required', 401);
		} catch (\Throwable $e) {
			// Un 429 = tentative déjà bloquée : ne pas prolonger le verrou.
			if ($e->getCode() !== 429) {
				$auth->recordFailedAttempt($rateKey);
			}
			$this->app->logAuth('admin:' . $user, 'AUTH FAILED (' . $e->getCode() . ')');
			$this->app->log('warning', 'Login admin échoué: ' . $e->getMessage());
			if (in_array($e->getCode(), [401, 403, 429], true)) {
				return JsonResponse::error($e->getMessage(), 'auth', $e->getCode());
			}
			return JsonResponse::error($this->t('errors.bad_credentials'), 'auth', 401);
		}
	}

	public function logout(Request $request): JsonResponse
	{
		unset($_SESSION['admin']);
		return JsonResponse::ok();
	}

	/**
	 * Données initiales du panneau en une seule requête : évite la rafale
	 * d'appels sérialisés (verrou de session FPM, serveur dev mono-thread).
	 */
	public function bootstrap(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok([
			'settings' => $this->settingsData(),
			'domains'  => $this->app->domains()->all(),
			'users'    => $this->app->users()->all(),
			'plugins'  => $this->app->plugins()->available(),
			'themes'   => [
				'default' => $this->app->themes()->defaultTheme(),
				'items'   => $this->app->themes()->details(),
			],
			'about'    => $this->aboutData(),
			'update'   => $this->app->updater()->status(),
			// Blocs UI déclarés par les plugins activés (hook admin.mounts)
			'adminMounts' => $this->app->hooks()->collect('admin.mounts'),
		]);
	}

	/** Dispatch borné : /api/admin/plugin/{name}/{action} → Plugin::apiAdmin<Action>. */
	public function pluginCall(Request $request): JsonResponse
	{
		$this->requireAdmin();
		// CSRF exigé pour TOUTES les méthodes (GET inclus), comme pluginCall user.
		$this->requireCsrf();
		return JsonResponse::ok($this->app->plugins()->callAdminApi(
			(string) $request->params['name'],
			(string) $request->params['action'],
			$request
		));
	}

	public function users(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->users()->all());
	}

	public function domains(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->domains()->all());
	}

	public function domainSave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$data = (array) $request->json();
		if (empty($data['name']) || empty($data['imap_host']) || empty($data['smtp_host'])) {
			return JsonResponse::error($this->t('errors.domain_required'), 'validation');
		}
		$bad = fn(string $key) => JsonResponse::error(
			$this->t('errors.invalid_value', ['key' => $key]), 'validation', 400);
		$name = (string) $data['name'];
		// '*' = domaine catch-all (paramètres pour tout domaine non listé)
		if ($name !== '*'
			&& !preg_match('/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/i', $name)) {
			return $bad('name');
		}
		foreach (['imap_host', 'smtp_host', 'sieve_host'] as $key) {
			$data[$key] = self::str($data[$key] ?? '', 255);
			if ($data[$key] === null || ($key !== 'sieve_host' && $data[$key] === '')) {
				return $bad($key);
			}
		}
		foreach (['imap_port', 'smtp_port'] as $key) {
			$data[$key] = self::intRange($data[$key] ?? 0, 1, 65535);
			if ($data[$key] === null) {
				return $bad($key);
			}
		}
		$data['sieve_port'] = self::intRange($data['sieve_port'] ?? 4190, 1, 65535);
		if ($data['sieve_port'] === null) {
			return $bad('sieve_port');
		}
		foreach (['imap_encryption', 'smtp_encryption'] as $key) {
			if (!in_array($data[$key] ?? '', ['ssl', 'tls', 'none'], true)) {
				return $bad($key);
			}
		}
		$data['active'] = (int) filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOL);
		$id = $this->app->domains()->save($data);
		return JsonResponse::ok(['id' => $id]);
	}

	public function domainDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$this->app->domains()->delete((int) $request->input('id'));
		return JsonResponse::ok();
	}

	/**
	 * Détection explicite de la config d'un domaine (bouton « Détecter » du
	 * formulaire domaine). Indépendant de autodetect.enabled — action admin.
	 */
	public function domainDetect(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = strtolower(trim((string) $request->input('name', '')));
		if ($name === '' || $name === '*') {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$detected = $this->app->domainDetector()->detect($name, true);
		if (!$detected) {
			return JsonResponse::error($this->t('errors.domain_detect_failed'), 'detect_failed');
		}
		return JsonResponse::ok($detected);
	}

	public function plugins(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->plugins()->available());
	}

	public function pluginToggle(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->pluginRepo()->setEnabled($name, (bool) $request->input('enabled', false));
		return JsonResponse::ok();
	}

	/** « Utilisable par les utilisateurs » : sinon chargé uniquement en contexte admin. */
	public function pluginAllowedSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->pluginRepo()->setUserAllowed($name, (bool) $request->input('allowed', true));
		return JsonResponse::ok();
	}

	public function pluginConfigGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		// Le schéma sur disque décrit les champs ; les valeurs sont en base
		return JsonResponse::ok([
			'schema' => $this->pluginSchema($name),
			'values' => $this->app->pluginRepo()->getConfig($name),
		]);
	}

	public function pluginConfigSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		// Validation par le schéma : postée > stockée > default ; clés hors
		// schéma ignorées ; sans schéma, rien à stocker.
		$posted = (array) $request->json();
		$stored = $this->app->pluginRepo()->getConfig($name);
		$out = [];
		foreach ($this->pluginSchema($name) as $key => $def) {
			$raw = array_key_exists($key, $posted)
				? $posted[$key]
				: ($stored[$key] ?? (is_array($def) ? ($def['default'] ?? '') : ''));
			try {
				$out[$key] = self::coercePluginValue($key, $raw, is_array($def) ? $def : []);
			} catch (\RuntimeException) {
				return JsonResponse::error(
					$this->t('errors.invalid_value', ['key' => $key]), 'validation', 400);
			}
		}
		$this->app->pluginRepo()->setConfig($name, $out);
		return JsonResponse::ok();
	}

	private static function pluginName(mixed $name): ?string
	{
		$name = (string) $name;
		return preg_match('/^[a-z0-9\-]+$/i', $name) ? $name : null;
	}

	/** Schéma de config d'un plugin : config.schema.json, plat {clé: def}. */
	private function pluginSchema(string $name): array
	{
		$file = $this->app->rootDir() . "/plugins/{$name}/config.schema.json";
		return is_file($file)
			? (json_decode((string) file_get_contents($file), true) ?: [])
			: [];
	}

	private static function coercePluginValue(string $key, mixed $v, array $def): mixed
	{
		$fail = fn() => throw new \RuntimeException("Valeur invalide pour $key", 400);
		$type = $def['type'] ?? 'string';
		if ($type === 'enum') {
			$opts = (array) ($def['options'] ?? []);
			return array_key_exists((string) $v, $opts) || in_array((string) $v, $opts, true)
				? (string) $v : $fail();
		}
		return match ($type) {
			'bool' => (int) filter_var($v, FILTER_VALIDATE_BOOL),
			'integer' => self::intRange($v, PHP_INT_MIN, PHP_INT_MAX) ?? $fail(),
			'number' => is_numeric($v) ? (float) $v : $fail(),
			'list' => is_array($v)
				? implode(', ', array_filter(array_map('trim', array_map('strval', $v))))
				: self::str($v, 8192) ?? $fail(),
			default => self::str($v, 4096) ?? $fail(),
		};
	}

	// ─── Templates d'interface ───

	public function themes(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok([
			'default' => $this->app->themes()->defaultTheme(),
			'items'   => $this->app->themes()->details(),
		]);
	}

	public function themeDefaultSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		try {
			(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
				->write(['user_defaults' => ['theme' => $name]], $this->app->config());
		} catch (\Throwable) {
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		return JsonResponse::ok();
	}

	public function themeAllowedSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)
			|| $name === $this->app->themes()->defaultTheme()) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->themeRepo()->setAllowed($name, (bool) $request->input('allowed', true));
		return JsonResponse::ok();
	}

	public function themeConfigGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return JsonResponse::ok([
			'schema' => $this->app->themes()->schema($name),
			'values' => $this->app->themes()->config($name),
		]);
	}

	public function themeConfigSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		try {
			$values = $this->app->themes()->validate($name, (array) $request->json());
		} catch (\RuntimeException $e) {
			return JsonResponse::error($e->getMessage(), 'validation', 400);
		}
		$this->app->themeRepo()->setConfig($name, $values);
		return JsonResponse::ok();
	}

	public function testConnexion(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$type = (string) $request->input('type', 'imap');
		$data = (array) $request->json();
		try {
			if ($type === 'imap') {
				$settings = \MXWebMail\Service\AuthService::imapSettings($data);
				$client = new \MailSo\Imap\ImapClient();
				$client->Connect($settings);
				$client->Disconnect();
			} elseif ($type === 'smtp') {
				$settings = \MXWebMail\Service\AuthService::smtpSettings($data);
				$client = new \MailSo\Smtp\SmtpClient();
				$client->Connect($settings);
				$client->Disconnect();
			} elseif ($type === 'sieve') {
				$settings = \MXWebMail\Service\AuthService::sieveSettings([
					'sieve_host' => (string) ($data['sieve_host'] ?? $data['host'] ?? ''),
					'sieve_port' => (int) ($data['sieve_port'] ?? $data['port'] ?? 4190),
					'options'    => $data['options'] ?? [],
				]);
				$client = new \MailSo\Sieve\SieveClient();
				$client->Connect($settings);
				$client->Disconnect();
			} else {
				return JsonResponse::error($this->t('errors.bad_type'), 'bad_type');
			}
			return JsonResponse::ok(['message' => $this->t('admin.conn_success')]);
		} catch (\Throwable $e) {
			return JsonResponse::error(
				$this->t('errors.failed', ['msg' => $e->getMessage()]), 'connect_failed');
		}
	}

	public function logs(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$map = ['app' => 'app.log', 'mail' => 'mail.log',
			'auth' => basename((string) $this->app->config()->get('auth_logging_filename', 'auth.log'))];
		$name = $map[$request->input('file', 'app')] ?? 'app.log';
		$file = $this->app->storageDir() . '/logs/' . $name;
		$lines = is_file($file) ? $this->tail($file, 200) : [];
		return JsonResponse::ok($lines);
	}

	/** Lit les $max dernières lignes sans charger tout le fichier en mémoire. */
	private function tail(string $file, int $max): array
	{
		$fh = fopen($file, 'rb');
		if (!$fh) {
			return [];
		}
		$size = fstat($fh)['size'] ?? 0;
		$offset = max(0, $size - 512 * 1024);
		fseek($fh, $offset);
		$lines = explode("\n", (string) stream_get_contents($fh));
		fclose($fh);
		if ($offset > 0) {
			array_shift($lines); // première ligne potentiellement tronquée
		}
		$lines = array_values(array_filter($lines, static fn($l) => $l !== ''));
		return array_slice($lines, -$max);
	}

	// ─── Réglages globaux (écriture app/config/config.php) ───

	public function settingsGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->settingsData());
	}

	private function settingsData(): array
	{
		$v = $this->app->config()->all();
		unset($v['admin_password_hash']);
		$v['db_pass'] = '';
		$v['admin_totp_secret'] = ''; // secret non renvoyé ; vide = inchangé à l'enregistrement
		if (isset($v['update']) && is_array($v['update'])) {
			// config()->all() est renvoyé tel quel : jamais le token dans le bootstrap
			$v['update']['token'] = '';
		}
		// Apps OAuth2 = par utilisateur (table oauth_apps) : la section oauth
		// d'un éventuel config.php hérité n'est plus ni lue ni exposée.
		unset($v['oauth']);
		// Champs dérivés du DSN pour le formulaire Base
		$dsn = (string) ($v['db_dsn'] ?? 'sqlite');
		$v['db_driver'] = str_starts_with($dsn, 'mysql') ? 'mysql' : 'sqlite';
		$v['db_host'] = 'localhost'; $v['db_port'] = 3306; $v['db_name'] = 'mxwebmail';
		if ($v['db_driver'] === 'mysql') {
			preg_match('/host=([^;]+)/', $dsn, $m) && $v['db_host'] = $m[1];
			preg_match('/port=(\d+)/', $dsn, $m) && $v['db_port'] = (int) $m[1];
			preg_match('/dbname=([^;]+)/', $dsn, $m) && $v['db_name'] = $m[1];
		}
		return [
			'values' => $v,
			'schema' => $this->settingsSchema(),
			'writable' => (new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))->writable(),
			'ip' => $this->app->clientIp(),
		];
	}

	public function settingsSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$input = (array) $request->json();
		$out = [];

		foreach (self::EDITABLE as $key => $rule) {
			if (!array_key_exists($key, $input)) {
				continue;
			}
			try {
				// 'language' : 'auto' ou un fichier Locale/<lang>.php existant ;
				// 'default_domain' : '', 'auto' ou un nom de domaine valide.
				$out[$key] = match ($key) {
					'language' => $this->validLanguage($input[$key]),
					'default_domain' => self::validDefaultDomain($input[$key]),
					default => self::validate($key, $input[$key], $rule),
				};
			} catch (\RuntimeException) {
				return JsonResponse::error(
					$this->t('errors.invalid_value', ['key' => $key]), 'validation', 400);
			}
		}
		// admin_path : les chemins publics/API réservés sont interdits
		if (isset($out['admin_path'])
			&& in_array($out['admin_path'], ['api', 'assets', 'setup'], true)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'admin_path']), 'validation', 400);
		}

		// Cohérence PJ : la limite par fichier ne peut excéder le total par
		// message — au-delà elle serait sans effet (l'upload borne déjà à
		// min() des deux). Le repli sur la valeur stockée couvre les
		// soumissions partielles (hors UI, qui envoie toute la section).
		if (isset($out['attachment_max_size']) || isset($out['attachments_max_total'])) {
			$perFile = $out['attachment_max_size']
				?? (int) $this->app->config()->get('attachment_max_size', 25);
			$total = $out['attachments_max_total']
				?? (int) $this->app->config()->get('attachments_max_total', 25);
			if ($perFile > $total) {
				return JsonResponse::error(
					$this->t('errors.attach_max_gt_total'), 'validation', 400);
			}
		}

		foreach (self::SECTIONS + ['user_defaults' => $this->app->userSettingRules()]
			as $section => $rules) {
			$sub = [];
			foreach ($rules as $key => $rule) {
				$full = "$section.$key";
				if (array_key_exists($full, $input)) {
					// Défaut global du template : existence + autorisation,
					// pas seulement le format de la règle 'key'.
					if ($section === 'user_defaults' && $key === 'theme'
						&& !$this->app->themes()->isSelectable((string) $input[$full])) {
						return JsonResponse::error(
							$this->t('errors.invalid_value', ['key' => $full]), 'validation', 400);
					}
					try {
						$sub[$key] = self::validate($full, $input[$full], $rule);
					} catch (\RuntimeException) {
						return JsonResponse::error(
							$this->t('errors.invalid_value', ['key' => $full]), 'validation', 400);
					}
				}
			}
			if ($sub) {
				$out[$section] = $sub;
			}
		}

		// Base de données : re-test PDO serveur obligatoire avant écriture
		if (isset($input['db_driver'])) {
			try {
				$out += $this->validatedDbConfig($input);
			} catch (\Throwable $e) {
				return JsonResponse::error(
					$this->t('errors.db_connect', ['msg' => $e->getMessage()]), 'db_failed');
			}
		}

		$writer = new \MXWebMail\Config\ConfigWriter($this->app->rootDir());
		try {
			$writer->write($out, $this->app->config());
		} catch (\Throwable $e) {
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		return JsonResponse::ok(['writable' => $writer->writable()]);
	}

	public function passwordSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$pass = (string) $request->input('password', '');
		if (strlen($pass) < 8) {
			return JsonResponse::error($this->t('errors.password_short'), 'validation');
		}
		try {
			(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
				->write(['admin_password_hash' => password_hash($pass, PASSWORD_DEFAULT)],
					$this->app->config());
		} catch (\Throwable) {
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		return JsonResponse::ok();
	}

	// ─── Favicon (upload → app/storage/branding, servi publiquement via /favicon.ico) ───

	private const FAVICON_MAX = 524288; // 512 Ko
	private const FAVICON_TYPES = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'webp'];

	public function faviconUpload(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$f = $request->files['file'] ?? null;
		if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		if ((int) $f['size'] > self::FAVICON_MAX) {
			return JsonResponse::error($this->t('errors.favicon_too_big'), 'too_big', 400);
		}
		$ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, self::FAVICON_TYPES, true)
			|| !self::faviconContentOk((string) $f['tmp_name'], $ext)) {
			return JsonResponse::error($this->t('errors.favicon_invalid'), 'validation', 400);
		}
		$dir = $this->app->storageDir() . '/branding';
		is_dir($dir) || mkdir($dir, 0700, true);
		$name = 'favicon.' . $ext;
		if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		try {
			(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
				->write(['favicon' => $name], $this->app->config());
		} catch (\Throwable) {
			@unlink($dir . '/' . $name);
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		// Purge les anciennes versions d'une extension différente
		foreach (glob($dir . '/favicon.*') ?: [] as $old) {
			if (basename($old) !== $name) {
				@unlink($old);
			}
		}
		return JsonResponse::ok(['favicon' => $name]);
	}

	public function faviconDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		foreach (glob($this->app->storageDir() . '/branding/favicon.*') ?: [] as $old) {
			@unlink($old);
		}
		try {
			(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
				->write(['favicon' => ''], $this->app->config());
		} catch (\Throwable) {
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		return JsonResponse::ok();
	}

	/** Contenu attendu : raster via getimagesize, .ico via magic bytes, svg sans script. */
	private static function faviconContentOk(string $tmp, string $ext): bool
	{
		if ($ext === 'ico') {
			return (string) file_get_contents($tmp, false, null, 0, 4) === "\x00\x00\x01\x00";
		}
		if ($ext === 'svg') {
			$xml = (string) file_get_contents($tmp);
			return str_contains($xml, '<svg')
				&& !preg_match('/<script|<foreignObject|on\w+\s*=/i', $xml);
		}
		return @getimagesize($tmp) !== false;
	}

	public function testDb(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		try {
			// Test = vérification de connexion uniquement : pas de
			// CREATE DATABASE — un bouton « tester » ne doit rien écrire.
			$this->validatedDbConfig((array) $request->json(), false);
			return JsonResponse::ok(['message' => $this->t('admin.conn_success')]);
		} catch (\Throwable $e) {
			return JsonResponse::error(
				$this->t('errors.failed', ['msg' => $e->getMessage()]), 'db_failed');
		}
	}

	/** Valide + teste la config DB du POST ; retourne les clés db_* à écrire.
	 *  $create : réservé au save — la base est créée puis sélectionnée. */
	private function validatedDbConfig(array $input, bool $create = true): array
	{
		$driver = (string) ($input['db_driver'] ?? 'sqlite');
		if ($driver !== 'mysql') {
			return ['db_dsn' => 'sqlite', 'db_user' => '', 'db_pass' => ''];
		}
		$host = trim((string) ($input['db_host'] ?? 'localhost'));
		if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => 'db_host']), 400);
		}
		$port = (int) ($input['db_port'] ?? 3306);
		$name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($input['db_name'] ?? 'mxwebmail'));
		$user = (string) ($input['db_user'] ?? '');
		$pass = (string) ($input['db_pass'] ?? '');
		if ($pass === '') {
			$pass = (string) $this->app->config()->get('db_pass', ''); // vide = inchangé
		}
		$pdo = new \PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass,
			[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		if ($create) {
			$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
			$pdo->exec("USE `$name`");
		} else {
			// Test sans écriture : si la base existe déjà, USE vérifie que
			// l'utilisateur a bien le droit d'y accéder ; sinon la connexion
			// suffit (le save la créera).
			$exists = $pdo->prepare(
				'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
			$exists->execute([$name]);
			if ($exists->fetchColumn()) {
				$pdo->exec("USE `$name`");
			}
		}
		return [
			'db_dsn' => "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
			'db_user' => $user,
			'db_pass' => $pass,
		];
	}

	// ─── Gestion des utilisateurs ───

	/** Utilisateur cible de la route {id} ; 404 si inconnu. */
	private function userOr404(Request $request): array
	{
		$u = $this->app->users()->findById((int) ($request->params['id'] ?? 0));
		if (!$u) {
			throw new \RuntimeException($this->t('errors.account_not_found'), 404);
		}
		return $u;
	}

	/**
	 * Un admin IMAP (session = user_id) ne peut ni se rétrograder, ni se
	 * bloquer, ni se supprimer — sinon il se coupe l'accès au panel.
	 */
	private function guardSelf(array $target, bool $demote, bool $disable, bool $delete): void
	{
		if (is_int($_SESSION['admin'] ?? null)
			&& (int) $target['id'] === $_SESSION['admin']
			&& ($demote || $disable || $delete)) {
			throw new \RuntimeException($this->t('errors.admin_self'), 400);
		}
	}

	/** Détail d'un compte : profil, réglages effectifs, comptes IMAP, identités. */
	public function userGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$u = $this->userOr404($request);
		$id = (int) $u['id'];
		// Valeurs effectives : réglages stockés + défauts user_defaults,
		// jamais les clés internes « plugin.* » (même filtre que /api/settings).
		$values = $this->app->settings()->allFor($id);
		foreach ((array) $this->app->config()->get('user_defaults', []) as $key => $v) {
			$values[$key] ??= $v;
		}
		// Template stocké devenu non utilisable (désactivé/supprimé) → défaut global
		if (!is_string($values['theme'] ?? null)
			|| !$this->app->themes()->isSelectable($values['theme'])) {
			$values['theme'] = $this->app->themes()->defaultTheme();
		}
		$values = array_filter($values,
			fn(string $k) => !str_starts_with($k, 'plugin.'), ARRAY_FILTER_USE_KEY);
		return JsonResponse::ok([
			'user'       => $u,
			'settings'   => ['schema' => $this->userSettingsSchema(), 'values' => $values],
			'accounts'   => $this->app->auth()->accounts($id), // jamais SELECT * (imap_pass)
			'identities' => $this->app->identities()->allForUser($id),
		]);
	}

	public function userSave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$data = (array) $request->json();
		// Clé absente = inchangée (évite qu'un POST partiel rétrograde/débloque)
		$isAdmin = (bool) ($data['is_admin'] ?? $u['is_admin']);
		$disabled = (bool) ($data['disabled'] ?? $u['disabled'] ?? 0);
		$this->guardSelf($u, !$isAdmin, $disabled, false);
		$name = self::str($data['display_name'] ?? $u['display_name'] ?? '', 255);
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'display_name']), 'validation', 400);
		}
		$this->app->users()->save((int) $u['id'], [
			'display_name' => $name,
			'is_admin'     => $isAdmin,
			'disabled'     => $disabled,
		]);
		return JsonResponse::ok();
	}

	public function userDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->guardSelf($u, false, false, true);
		// FK ON DELETE CASCADE : comptes, identités, réglages, clés PGP ;
		// carnet d'adresses : fichier storage/db/users/<id>.sqlite supprimé
		$this->app->users()->delete((int) $u['id']);
		$this->app->userDb()->deleteFor((int) $u['id']);
		$this->app->log('warning', "Utilisateur supprimé par admin : {$u['username']}");
		return JsonResponse::ok();
	}

	public function userSettingsSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$allowed = $this->app->allowedUserSettingKeys();
		foreach ((array) $request->json() as $key => $value) {
			$key = (string) $key;
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			// Mêmes règles que les défauts globaux (App::userSettingRules) —
			// valeur hors bornes = ignorée, jamais stockée.
			[$ok, $v] = $this->app->checkUserSetting($key, $value);
			if ($ok) {
				$this->app->settings()->set((int) $u['id'], $key, $v);
			}
		}
		return JsonResponse::ok();
	}

	public function userSettingsReset(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->settings()->deleteAllFor((int) $u['id']);
		return JsonResponse::ok();
	}

	public function userAccountDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$stmt = $this->app->db()->pdo()->prepare(
			'DELETE FROM accounts WHERE id = :id AND user_id = :u AND is_default = 0'
		);
		$stmt->execute(['id' => (int) $request->input('account_id'), 'u' => (int) $u['id']]);
		if (!$stmt->rowCount()) {
			return JsonResponse::error($this->t('errors.account_not_found_or_main'), 'not_found', 404);
		}
		return JsonResponse::ok();
	}

	public function userIdentitySave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		try {
			$id = $this->app->identities()->save((int) $u['id'], (array) $request->json());
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error($this->t('errors.invalid_identity'), 'validation', 400);
		} catch (\Throwable $e) {
			$code = $e->getCode();
			return JsonResponse::error($e->getMessage(), 'validation',
				($code >= 400 && $code < 600) ? $code : 400);
		}
		return JsonResponse::ok(['id' => $id]);
	}

	public function userIdentityDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->identities()->delete((int) $u['id'], (int) $request->input('identity_id'));
		return JsonResponse::ok();
	}

	public function userIdentityDefault(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->identities()->setDefault((int) $u['id'], (int) $request->input('identity_id'));
		return JsonResponse::ok();
	}

	public function about(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->aboutData());
	}

	// ─── Mises à jour (UpdateService — voir Service/UpdateService.php) ───

	public function updateCheck(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$force = (bool) $request->input('force', false);
		return $this->serviceApi(fn() => $this->app->updater()->check($force));
	}

	public function updateStatus(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return $this->serviceApi(fn() => $this->app->updater()->status());
	}

	public function updateDownload(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		return $this->serviceApi(fn() => $this->app->updater()->download());
	}

	public function updateApply(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		return $this->serviceApi(fn() => $this->app->updater()->apply());
	}

	public function updateReset(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		return $this->serviceApi(fn() => $this->app->updater()->reset());
	}

	// ─── Catalogue plugins (PluginPackageService — assets GitHub Releases) ───

	public function pluginsCatalog(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$force = (bool) $request->input('force', false);
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->catalog($force));
	}

	public function pluginInstall(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->install($name));
	}

	/** Désinstallation définitive : dossier supprimé + ligne DB purgée. */
	public function pluginRemove(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->remove($name));
	}

	/** Enveloppe commune : RuntimeException du service → errors.failed + code HTTP. */
	private function serviceApi(callable $fn): JsonResponse
	{
		try {
			return JsonResponse::ok($fn());
		} catch (\RuntimeException $e) {
			$s = $e->getCode();
			return JsonResponse::error(
				$this->t('errors.failed', ['msg' => $e->getMessage()]),
				'failed', ($s >= 400 && $s < 600) ? $s : 400);
		}
	}

	private function aboutData(): array
	{
		$extensions = [];
		foreach (['pdo','pdo_sqlite','pdo_mysql','sodium','intl','mbstring','openssl',
			'ldap','curl','zip','dom','xml','iconv','zlib','json','ctype'] as $e) {
			$extensions[$e] = extension_loaded($e);
		}
		$driver = '';
		$dbSize = null;
		try {
			$pdo = $this->app->db()->pdo();
			$driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
			if ($driver === 'sqlite') {
				$f = $pdo->query("SELECT file FROM pragma_database_list WHERE name = 'main'")->fetchColumn();
				$dbSize = is_string($f) && $f !== '' ? self::sqliteFileSize($f) : null;
			} elseif ($driver === 'mysql') {
				$dbSize = (int) $pdo->query(
					'SELECT COALESCE(SUM(data_length + index_length), 0)
					   FROM information_schema.tables WHERE table_schema = DATABASE()'
				)->fetchColumn();
			}
			// Bases contacts par utilisateur : toujours des fichiers SQLite.
			foreach (glob($this->app->storageDir() . '/db/users/*.sqlite') ?: [] as $f) {
				$dbSize = ($dbSize ?? 0) + self::sqliteFileSize($f);
			}
		} catch (\Throwable) {}
		return [
			'app_name' => $this->app->config()->get('app_name'),
			'version' => defined('APP_VERSION') ? APP_VERSION : '?',
			'repo_url' => $this->app->github()->repoUrl(),
			'php' => PHP_VERSION,
			'php_sapi' => PHP_SAPI,
			'memory_limit' => (string) ini_get('memory_limit'),
			'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
			'post_max_size' => (string) ini_get('post_max_size'),
			'db_driver' => $driver,
			'db_size' => $dbSize,
			'config_writable' => (new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))->writable(),
			'paths' => [
				'root' => $this->app->rootDir(),
				'install' => $this->app->installDir(),
				'storage' => $this->app->storageDir(),
			],
			'extensions' => $extensions,
		];
	}

	/** Taille d'une base SQLite : fichier principal + journal WAL non fusionné. */
	private static function sqliteFileSize(string $file): int
	{
		return (int) @filesize($file) + (int) @filesize($file . '-wal');
	}

	// ─── Schéma des réglages (formulaires générés) ───

	/**
	 * Schéma des réglages (méthode d'instance : le champ 'language' liste
	 * les fichiers Locale/*.php présents). Les label/help sont des CLÉS de
	 * traduction résolues côté client via t() — pass-through si inconnues.
	 */
	private function settingsSchema(): array
	{
		$langs = ['auto' => 'admin.lang_auto'];
		foreach ($this->app->languages() as $l) {
			$langs[$l] = 'admin.lang.' . $l;
		}
		// Champs « Valeurs par défaut » : clés plates du schéma utilisateur
		// préfixées en user_defaults.* pour l'écriture config. Le template par
		// défaut se gère dans l'onglet Templates, pas ici.
		$defaultFields = [];
		foreach ($this->userSettingsSchema() as $key => $f) {
			if ($key === 'theme') {
				continue;
			}
			$defaultFields['user_defaults.' . $key] = $f + ['group' => 'defaults'];
		}
		return [
			'general' => [
				'title' => 'common.general',
				'fields' => [
					'app_name' => ['label' => 'admin.f.app_name', 'type' => 'string'],
					'admin_path' => ['label' => 'admin.f.admin_path', 'type' => 'string',
						'help' => 'admin.f.admin_path_help'],
					'language' => ['label' => 'admin.f.language', 'type' => 'enum',
						'options' => $langs, 'help' => 'admin.f.language_help'],
					'session_lifetime' => ['label' => 'admin.f.session_lifetime', 'type' => 'integer'],
					'maintenance' => ['label' => 'admin.f.maintenance', 'type' => 'bool',
						'help' => 'admin.f.maintenance_help'],
					'update.channel' => ['label' => 'admin.f.update_channel', 'type' => 'enum',
						'options' => ['stable' => 'admin.channel_stable', 'beta' => 'admin.channel_beta'],
						'help' => 'admin.f.update_channel_help'],
				],
			],
			'domains' => [
				'title' => 'admin.domains',
				'help' => 'admin.domains_help',
				'fields' => [
					// enum sans options : la liste est alimentée côté JS
					// depuis les domaines de la table (voir admin.js).
					'default_domain' => ['label' => 'admin.f.default_domain', 'type' => 'enum',
						'options' => [], 'help' => 'admin.f.default_domain_help'],
					'restrict_domains' => ['label' => 'admin.f.restrict_domains', 'type' => 'bool',
						'help' => 'admin.f.restrict_domains_help'],
					'autodetect.enabled' => ['label' => 'admin.f.autodetect_enabled', 'type' => 'bool',
						'help' => 'admin.f.autodetect_enabled_help'],
					'autodetect.use_ispdb' => ['label' => 'admin.f.autodetect_use_ispdb', 'type' => 'bool',
						'help' => 'admin.f.autodetect_use_ispdb_help'],
					'autodetect.probe' => ['label' => 'admin.f.autodetect_probe', 'type' => 'bool',
						'help' => 'admin.f.autodetect_probe_help'],

				],
			],
			'database' => [
				'title' => 'admin.db_title',
				'help' => 'admin.db_help',
				'fields' => [
					'db_driver' => ['label' => 'admin.f.db_driver', 'type' => 'enum', 'options' => ['sqlite' => 'SQLite', 'mysql' => 'MariaDB / MySQL']],
					'db_host' => ['label' => 'admin.f.db_host', 'type' => 'string',
						'help' => 'admin.f.db_host_help'],
					'db_port' => ['label' => 'admin.f.db_port', 'type' => 'integer'],
					'db_name' => ['label' => 'admin.f.db_name', 'type' => 'string'],
					'db_user' => ['label' => 'admin.f.db_user', 'type' => 'string'],
					'db_pass' => ['label' => 'admin.f.db_pass', 'type' => 'password'],
				],
			],
			'features' => [
				'title' => 'admin.features',
				'fields' => [
					'image_proxy' => ['label' => 'admin.f.image_proxy', 'type' => 'bool',
						'help' => 'admin.f.image_proxy_help', 'group' => 'features'],
					'image_proxy_allowlist' => ['label' => 'admin.f.image_proxy_allowlist', 'type' => 'string',
						'help' => 'admin.f.image_proxy_allowlist_help', 'group' => 'features'],
					'attachment_max_size' => ['label' => 'admin.f.attachment_max_size', 'type' => 'integer',
						'help' => 'admin.f.attachment_max_size_help', 'group' => 'features'],
					'attachments_max_total' => ['label' => 'admin.f.attachments_max_total', 'type' => 'integer',
						'help' => 'admin.f.attachments_max_total_help', 'group' => 'features'],
					'contacts_enable' => ['label' => 'admin.f.contacts_enable', 'type' => 'bool', 'group' => 'features'],
					'contacts_suggestions_limit' => ['label' => 'admin.f.contacts_suggestions_limit', 'type' => 'integer', 'group' => 'features'],
					'allow_additional_accounts' => ['label' => 'admin.f.allow_additional_accounts', 'type' => 'bool', 'group' => 'features'],
					'allow_additional_identities' => ['label' => 'admin.f.allow_additional_identities', 'type' => 'bool', 'group' => 'features'],
					'popup_identity' => ['label' => 'admin.f.popup_identity', 'type' => 'bool',
						'help' => 'admin.f.popup_identity_help', 'group' => 'features'],
					'dangerous_actions' => ['label' => 'admin.f.dangerous_actions', 'type' => 'bool',
						'help' => 'admin.f.dangerous_actions_help', 'group' => 'features'],
					...$defaultFields,
				],
			],
			'security' => [
				'title' => 'admin.security',
				'fields' => [
					'admin_user' => ['label' => 'admin.f.admin_user', 'type' => 'string', 'group' => 'account'],
					// TOTP admin : provisionné via le plugin two-factor-auth
					// (sous-onglet dédié de cette page, API /api/admin/plugin/*).
					'admin_ip_allowlist' => ['label' => 'admin.f.admin_ip_allowlist', 'type' => 'string',
						'help' => 'admin.f.admin_ip_allowlist_help', 'group' => 'access'],
					'login_max_attempts' => ['label' => 'admin.f.login_max_attempts', 'type' => 'integer', 'group' => 'access'],
					'login_lock_seconds' => ['label' => 'admin.f.login_lock_seconds', 'type' => 'integer', 'group' => 'access'],
					'trusted_proxies' => ['label' => 'admin.f.trusted_proxies', 'type' => 'string',
						'help' => 'admin.f.trusted_proxies_help', 'group' => 'access'],
					'force_https' => ['label' => 'admin.f.force_https', 'type' => 'bool', 'group' => 'headers'],
					'hsts' => ['label' => 'admin.f.hsts', 'type' => 'bool',
						'help' => 'admin.f.hsts_help', 'group' => 'headers'],
					'cookie_samesite' => ['label' => 'admin.f.cookie_samesite', 'type' => 'enum', 'group' => 'headers',
						'options' => ['Strict' => 'Strict', 'Lax' => 'Lax', 'None' => 'admin.samesite_https'],
						'help' => 'admin.f.cookie_samesite_help'],
					'csp_extra' => ['label' => 'admin.f.csp_extra', 'type' => 'string',
						'help' => 'admin.f.csp_extra_help', 'group' => 'headers'],
					'secfetch_allow' => ['label' => 'admin.f.secfetch_allow', 'type' => 'string',
						'help' => 'admin.f.secfetch_allow_help', 'group' => 'headers'],
					'ssl.verify_certificate' => ['label' => 'admin.f.ssl.verify_certificate', 'type' => 'bool',
						'help' => 'admin.f.ssl.verify_certificate_help', 'group' => 'tls'],
					'ssl.allow_self_signed' => ['label' => 'admin.f.ssl.allow_self_signed', 'type' => 'bool',
						'help' => 'admin.f.ssl.allow_self_signed_help', 'group' => 'tls'],
					'ssl.cafile' => ['label' => 'admin.f.ssl.cafile', 'type' => 'string',
						'help' => 'admin.f.ssl.cafile_help', 'group' => 'tls'],
					'ssl.capath' => ['label' => 'admin.f.ssl.capath', 'type' => 'string',
						'help' => 'admin.f.ssl.capath_help', 'group' => 'tls'],
					'ssl.security_level' => ['label' => 'admin.f.ssl.security_level', 'type' => 'integer',
						'help' => 'admin.f.ssl.security_level_help', 'group' => 'tls'],
					'ssl.local_cert' => ['label' => 'admin.f.ssl.local_cert', 'type' => 'string',
						'help' => 'admin.f.ssl.local_cert_help', 'group' => 'tls'],
					'ssl.disable_compression' => ['label' => 'admin.f.ssl.disable_compression', 'type' => 'bool',
						'help' => 'admin.f.ssl.disable_compression_help', 'group' => 'tls'],
				],
			],
			'logs' => [
				'title' => 'admin.logs',
				'fields' => [
					'debug' => ['label' => 'admin.f.debug', 'type' => 'bool',
						'help' => 'admin.f.debug_help'],
					'logs.enabled' => ['label' => 'admin.f.logs.enabled', 'type' => 'bool'],
					'logs.level' => ['label' => 'admin.f.logs.level', 'type' => 'enum', 'options' => [
						'7' => 'DEBUG', '6' => 'INFO', '5' => 'NOTICE',
						'4' => 'WARNING', '3' => 'ERROR', '2' => 'CRITICAL',
					]],
					'logs.syslog' => ['label' => 'admin.f.logs.syslog', 'type' => 'bool',
						'help' => 'admin.f.logs.syslog_help'],
					'logs.time_zone' => ['label' => 'admin.f.logs.time_zone', 'type' => 'string',
						'help' => 'admin.f.logs.time_zone_help'],
					'auth_logging' => ['label' => 'admin.f.auth_logging', 'type' => 'bool',
						'help' => 'admin.f.auth_logging_help'],
					'auth_logging_filename' => ['label' => 'admin.f.auth_logging_filename', 'type' => 'string',
						'help' => 'admin.f.auth_logging_filename_help'],
					'auth_logging_format' => ['label' => 'admin.f.auth_logging_format', 'type' => 'string',
						'help' => 'admin.f.auth_logging_format_help'],
					'labs.smtp_show_server_errors' => ['label' => 'admin.f.labs.smtp_show_server_errors', 'type' => 'bool',
						'help' => 'admin.f.labs.smtp_show_server_errors_help'],
				],
			],
			'performance' => [
				'title' => 'admin.performance',
				'fields' => [
					'http.gzip' => ['label' => 'admin.f.http.gzip', 'type' => 'bool',
						'help' => 'admin.f.http.gzip_help', 'group' => 'http'],
					'http.gzip_level' => ['label' => 'admin.f.http.gzip_level', 'type' => 'integer',
						'help' => 'admin.f.http.gzip_level_help', 'group' => 'http'],
					'http.gzip_min_size' => ['label' => 'admin.f.http.gzip_min_size', 'type' => 'integer',
						'help' => 'admin.f.http.gzip_min_size_help', 'group' => 'http'],
					'http.etag' => ['label' => 'admin.f.http.etag', 'type' => 'bool',
						'help' => 'admin.f.http.etag_help', 'group' => 'http'],
					'http.assets_ttl' => ['label' => 'admin.f.http.assets_ttl', 'type' => 'integer',
						'help' => 'admin.f.http.assets_ttl_help', 'group' => 'http'],
					'http.favicon_ttl' => ['label' => 'admin.f.http.favicon_ttl', 'type' => 'integer',
						'help' => 'admin.f.http.favicon_ttl_help', 'group' => 'http'],
					'http.proxy_ttl' => ['label' => 'admin.f.http.proxy_ttl', 'type' => 'integer',
						'help' => 'admin.f.http.proxy_ttl_help', 'group' => 'http'],
					'cache.enabled' => ['label' => 'admin.f.cache.enabled', 'type' => 'bool',
						'help' => 'admin.f.cache.enabled_help', 'group' => 'cache'],
					'cache.domain_detect_ttl' => ['label' => 'admin.f.cache.domain_detect_ttl', 'type' => 'integer',
						'help' => 'admin.f.cache.domain_detect_ttl_help', 'group' => 'cache'],
					'cache.update_ttl' => ['label' => 'admin.f.cache.update_ttl', 'type' => 'integer',
						'help' => 'admin.f.cache.update_ttl_help', 'group' => 'cache'],
					'cache.gc_hours' => ['label' => 'admin.f.cache.gc_hours', 'type' => 'integer',
						'help' => 'admin.f.cache.gc_hours_help', 'group' => 'cache'],
				],
			],
		];
	}

	/**
	 * Schéma des réglages utilisateur (table user_settings), clés plates.
	 * Source unique : la section « Valeurs par défaut » du panneau global
	 * préfixe ces champs en user_defaults.* ; la modale d'édition d'un
	 * compte les applique directement.
	 */
	private function userSettingsSchema(): array
	{
		return [
			'messages_per_page' => ['label' => 'admin.f.user_defaults.messages_per_page', 'type' => 'integer'],
			'list_sort' => ['label' => 'admin.f.user_defaults.list_sort', 'type' => 'enum',
				'options' => [
					'REVERSE DATE' => 'app.sort.date_desc', 'DATE' => 'app.sort.date_asc',
					'FROM' => 'app.sort.from', 'TO' => 'app.sort.to',
					'SUBJECT' => 'app.sort.subject',
					'REVERSE SIZE' => 'app.sort.size_desc', 'SIZE' => 'app.sort.size_asc',
				]],
			'view_images' => ['label' => 'admin.f.user_defaults.view_images', 'type' => 'enum',
				'options' => ['ask' => 'settings.view_images.ask', 'match' => 'settings.view_images.match', 'always' => 'settings.view_images.always'],
				'help' => 'admin.f.user_defaults.view_images_help'],
			'msg_default_action' => ['label' => 'admin.f.user_defaults.msg_default_action', 'type' => 'enum',
				'options' => ['reply' => 'app.reply', 'reply_all' => 'app.reply_all']],
			'mark_read_delay' => ['label' => 'admin.f.user_defaults.mark_read_delay', 'type' => 'integer'],
			'autologout' => ['label' => 'admin.f.user_defaults.autologout', 'type' => 'integer'],
			'list_refresh' => ['label' => 'admin.f.user_defaults.list_refresh', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.list_refresh_help'],
			'notify_new_mail' => ['label' => 'admin.f.user_defaults.notify_new_mail', 'type' => 'bool',
				'help' => 'admin.f.user_defaults.notify_new_mail_help'],
			'contacts_autosave' => ['label' => 'admin.f.user_defaults.contacts_autosave', 'type' => 'bool',
				'help' => 'admin.f.user_defaults.contacts_autosave_help'],
			'folder_pane_width' => ['label' => 'admin.f.user_defaults.folder_pane_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.folder_pane_width_help'],
			'message_pane_width' => ['label' => 'admin.f.user_defaults.message_pane_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.message_pane_width_help'],
			'layout' => ['label' => 'admin.f.user_defaults.layout', 'type' => 'enum',
				'options' => ['horizontal' => 'settings.layout.horizontal', 'vertical' => 'settings.layout.vertical']],
			'message_pane_height' => ['label' => 'admin.f.user_defaults.message_pane_height', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.message_pane_height_help'],
			'compose_modal_width' => ['label' => 'admin.f.user_defaults.compose_modal_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.compose_modal_width_help'],
			'empty_trash_on_logout' => ['label' => 'admin.f.user_defaults.empty_trash_on_logout', 'type' => 'bool'],
			'empty_junk_on_logout' => ['label' => 'admin.f.user_defaults.empty_junk_on_logout', 'type' => 'bool'],
			'list_threads' => ['label' => 'admin.f.user_defaults.list_threads', 'type' => 'bool'],
			'compose_modal_height' => ['label' => 'admin.f.user_defaults.compose_modal_height', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.compose_modal_height_help'],
			'theme' => ['label' => 'settings.theme', 'type' => 'enum',
				'options' => $this->app->themes()->options()],
		];
	}

	// ─── Whitelist + validation ───

	private const EDITABLE = [
		'app_name' => 'str', 'default_domain' => 'str', 'admin_path' => 'key',
		'language' => 'str',
		'maintenance' => 'bool', 'session_lifetime' => 'int:0:720',
		'restrict_domains' => 'bool',
		'admin_user' => 'str',
		'login_max_attempts' => 'int:1:50', 'login_lock_seconds' => 'int:30:86400',
		'admin_ip_allowlist' => 'csv_ip', 'trusted_proxies' => 'csv_ip',
		'force_https' => 'bool', 'hsts' => 'bool',
		'cookie_samesite' => 'enum:Strict,Lax,None',
		'csp_extra' => 'str', 'secfetch_allow' => 'str',
		'image_proxy' => 'bool', 'image_proxy_allowlist' => 'csv_domain',
		'attachment_max_size' => 'int:1:100',
		'attachments_max_total' => 'int:1:1024',
		'contacts_enable' => 'bool', 'contacts_suggestions_limit' => 'int:1:100',
		'allow_additional_accounts' => 'bool', 'allow_additional_identities' => 'bool',
		'popup_identity' => 'bool', 'dangerous_actions' => 'bool',
		'auth_logging' => 'bool', 'auth_logging_filename' => 'filename',
		'auth_logging_format' => 'str',
		'debug' => 'bool',
	];

	private const SECTIONS = [
		'ssl' => [
			'verify_certificate' => 'bool', 'allow_self_signed' => 'bool',
			'cafile' => 'str', 'capath' => 'str', 'local_cert' => 'str',
			'disable_compression' => 'bool', 'security_level' => 'int:0:5',
		],
		'logs' => ['enabled' => 'bool', 'level' => 'int:0:7',
			'syslog' => 'bool', 'time_zone' => 'tz'],
		'labs' => ['smtp_show_server_errors' => 'bool'],
		// repo (fallback dev), token et manifest_url volontairement absents :
		// config.php uniquement, jamais modifiables via l'API admin
		'update' => ['channel' => 'enum:stable,beta'],
		'autodetect' => ['enabled' => 'bool', 'use_ispdb' => 'bool', 'probe' => 'bool'],
		'http' => [
			'gzip' => 'bool', 'gzip_level' => 'int:1:9',
			'gzip_min_size' => 'int:0:1048576', 'etag' => 'bool',
			'assets_ttl' => 'int:0:31536000', 'favicon_ttl' => 'int:0:31536000',
			'proxy_ttl' => 'int:0:604800',
		],
		'cache' => [
			'enabled' => 'bool', 'domain_detect_ttl' => 'int:60:604800',
			'update_ttl' => 'int:60:86400', 'gc_hours' => 'int:0:720',
		],
		// 'user_defaults' n'est pas ici : ses règles vivent dans
		// App::userSettingRules() (partagées avec les écritures par
		// utilisateur) et sont injectées dans settingsSet().
	];

	private static function validate(string $key, mixed $value, string $rule): mixed
	{
		$fail = fn() => throw new \RuntimeException("Valeur invalide pour $key", 400);
		return match (true) {
			$rule === 'bool' => (bool) $value,
			$rule === 'str' => self::str($value, 255) ?? $fail(),
			$rule === 'key' => preg_match('/^[a-z0-9_\-]{1,32}$/', (string) $value) ? (string) $value : $fail(),
			$rule === 'port' => self::intRange($value, 1, 65535) ?? $fail(),
			$rule === 'enc' => in_array($value, ['ssl', 'tls', 'none'], true) ? $value : $fail(),
			$rule === 'csv_ip' => self::csvIp($value) ?? $fail(),
			$rule === 'csv_domain' => self::csvDomain($value) ?? $fail(),
			$rule === 'filename' => self::filename($value) ?? $fail(),
			$rule === 'tz' => self::timeZone($value) ?? $fail(),
			str_starts_with($rule, 'enum:') => in_array($value, explode(',', substr($rule, 5)), true) ? $value : $fail(),
			str_starts_with($rule, 'int:') => self::intRange($value, ...array_map('intval', array_slice(explode(':', $rule), 1))) ?? $fail(),
			default => $fail(),
		};
	}

	private static function str(mixed $v, int $max): ?string
	{
		$s = trim((string) $v);
		return (strlen($s) <= $max && !preg_match('/[\r\n\x00-\x1F]/', $s)) ? $s : null;
	}

	private static function intRange(mixed $v, int $min, int $max): ?int
	{
		$i = filter_var($v, FILTER_VALIDATE_INT);
		return ($i !== false && $i >= $min && $i <= $max) ? $i : null;
	}

	private static function csvIp(mixed $v): ?string
	{
		$s = trim((string) $v);
		if ($s === '') return '';
		foreach (explode(',', $s) as $entry) {
			$entry = trim($entry);
			[$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
			if (!filter_var($ip, FILTER_VALIDATE_IP)) return null;
			if ($bits !== null && (!ctype_digit($bits) || (int) $bits > 128)) return null;
		}
		return $s;
	}

	private static function csvDomain(mixed $v): ?string
	{
		$s = trim((string) $v);
		if ($s === '') return '';
		foreach (explode(',', $s) as $d) {
			if (!preg_match('/^(\*\.)?[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/i', trim($d))) return null;
		}
		return $s;
	}

	private static function filename(mixed $v): ?string
	{
		$s = trim((string) $v);
		return ($s !== '' && preg_match('/^[a-zA-Z0-9._\-]{1,64}$/', $s)) ? $s : null;
	}

	private static function timeZone(mixed $v): ?string
	{
		$s = trim((string) $v);
		return ($s === '' || in_array($s, timezone_identifiers_list(), true)) ? $s : null;
	}

	/** 'language' : 'auto' ou un dictionnaire Locale/<lang>.php existant. */
	private function validLanguage(mixed $v): string
	{
		$v = strtolower(trim((string) $v));
		if (!in_array($v, ['auto', ...$this->app->languages()], true)) {
			throw new \RuntimeException('bad language', 400);
		}
		return $v;
	}

	/**
	 * 'default_domain' : '' (aucun suffixe), 'auto' (hôte HTTP) ou un
	 * nom de domaine valide. 'auto' passe la regex (label unique) mais
	 * conserve sa sémantique de sentinelle côté AuthService.
	 */
	private static function validDefaultDomain(mixed $v): string
	{
		$s = strtolower(trim((string) $v));
		if ($s === ''
			|| preg_match('/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/', $s)) {
			return $s;
		}
		throw new \RuntimeException('bad default_domain', 400);
	}

	// ─── Helpers ───

	private function requireAdmin(): void
	{
		$this->requireAdminIp();
		if (empty($_SESSION['admin'])) {
			throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
		}
		// Admin IMAP (session = user_id) : revalide le compte à chaque appel —
		// un utilisateur rétrogradé, bloqué ou supprimé perd le panel aussitôt.
		// Avant session_write_close() pour que unset() persiste.
		if (is_int($_SESSION['admin'])) {
			$u = $this->app->users()->findById($_SESSION['admin']);
			if (!$u || !(int) $u['is_admin'] || (int) ($u['disabled'] ?? 0)) {
				unset($_SESSION['admin']);
				throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
			}
		}
		// Libère le verrou du fichier de session : aucun handler admin
		// n'écrit $_SESSION après l'auth (login/logout n'en passent pas par ici).
		session_write_close();
	}

	private function requireAdminIp(): void
	{
		$list = trim((string) $this->app->config()->get('admin_ip_allowlist', ''));
		if ($list !== ''
			&& !\MXWebMail\Security\IpMatch::inList($this->app->clientIp(), $list)) {
			throw new \RuntimeException($this->t('errors.admin_ip'), 403);
		}
	}

	private function requireCsrf(): void
	{
		if (!Csrf::verifyRequest()) {
			throw new \RuntimeException($this->t('errors.csrf'), 403);
		}
	}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}
}
