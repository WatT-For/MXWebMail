<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\Csrf;




final class SystemController extends BaseController
{
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

	public function testDb(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		try {
			// Test = vérification de connexion uniquement : pas de
			// CREATE DATABASE - un bouton "tester" ne doit rien écrire.
			$this->validatedDbConfig((array) $request->json(), false);
			return JsonResponse::ok(['message' => $this->t('admin.conn_success')]);
		} catch (\Throwable $e) {
			return JsonResponse::error(
				$this->t('errors.failed', ['msg' => $e->getMessage()]), 'db_failed');
		}
	}

	public function logs(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$map = $this->logFilesMap();
		$name = $map[$request->input('file', 'app')] ?? 'app.log';
		$file = $this->app->storageDir() . '/logs/' . $name;
		$lines = is_file($file) ? $this->tail($file, 200) : [];
		return JsonResponse::ok($lines);
	}

	public function logsClear(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$map = $this->logFilesMap();
		$key = (string) $request->input('file', '');
		// Action destructive : pas de fallback silencieux sur app.log -
		// une clé hors whitelist est refusée.
		if (!isset($map[$key])) {
			return JsonResponse::error($this->t('errors.forbidden'), 'bad_file', 400);
		}
		$file = $this->app->storageDir() . '/logs/' . $map[$key];
		if (is_file($file)) {
			file_put_contents($file, '', LOCK_EX);
		}
		return JsonResponse::ok();
	}

	public function settingsGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->settingsData());
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
					default => $this->validate($key, $input[$key], $rule),
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
		// message - au-delà elle serait sans effet (l'upload borne déjà à
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
						$sub[$key] = $this->validate($full, $input[$full], $rule);
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

	/**
	 * Sauvegarde zip streamée : config.php, app.key, bases SQLite,
	 * branding. SENSIBLE (credentials + cle de chiffrement) - admin seul,
	 * GET = lecture seule (meme convention que les autres GET admin).
	 * Une base MariaDB n'est PAS couverte : mysqldump cote serveur.
	 */
	public function backup(Request $request): Response
	{
		$this->requireAdmin();
		$root = MXWM_APP_DIR;
		$storage = $this->app->storageDir();
		$zip = new \MXWebMailLib\Stream\ZIP();
		$zip->pushHttpHeaders('mxwebmail-backup-' . date('Ymd-His'));
		$zip->addFile($root . '/config/config.php', 'config/config.php');
		$zip->addFile($storage . '/app.key', 'storage/app.key');
		$zip->addFile($storage . '/INSTALLED', 'storage/INSTALLED');
		foreach ((array) glob($storage . '/db/*.sqlite') as $f) {
			$zip->addFile($f, 'storage/db/' . basename($f));
		}
		foreach ((array) glob($storage . '/branding/*') as $f) {
			is_file($f) && $zip->addFile($f, 'storage/branding/' . basename($f));
		}
		$zip->addFromString('LISEZMOI.txt',
			"Sauvegarde MXWebMail - " . date('c') . "\r\n"
			. "Contenu : config.php, cle de chiffrement app.key, bases SQLite,\r\n"
			. "assets de marque (branding/).\r\n"
			. "Non couvert : base MariaDB (mysqldump cote serveur), boites IMAP,\r\n"
			. "logs, cache, fichiers temporaires, avatars caches.\r\n"
			. "SENSIBLE : contient la cle de chiffrement et les identifiants de\r\n"
			. "config.php - conserver en lieu sur.\r\n");
		$zip->close();
		return new Response('', 200);
	}

	public function about(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->aboutData());
	}

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

	/** Migrations post-update : requête nouvelle > nouveau code chargé. */
	public function updateFinalize(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		return $this->serviceApi(fn() => $this->app->updater()->finalize());
	}

	public function updateRollback(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$force = (bool) $request->input('force', false);
		return $this->serviceApi(fn() => $this->app->updater()->rollback($force));
	}

	public function updateReset(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		return $this->serviceApi(fn() => $this->app->updater()->reset());
	}

	/** Valide + teste la config DB du POST ; retourne les clés db_* à écrire.
	 *  $create : réservé au save - la base est créée puis sélectionnée. */
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

	/** Whitelist clé > nom de fichier (basename : anti-traversée). */
	private function logFilesMap(): array
	{
		return ['app' => 'app.log', 'mail' => 'mail.log',
			'auth' => basename((string) $this->app->config()->get('auth_logging_filename', 'auth.log'))];
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

	/**
	 * Schéma des réglages (méthode d'instance : le champ 'language' liste
	 * les fichiers Locale/*.php présents). Les label/help sont des CLÉS de
	 * traduction résolues côté client via t() - pass-through si inconnues.
	 */
	private function settingsSchema(): array
	{
		$langs = ['auto' => 'admin.lang_auto'];
		foreach ($this->app->languages() as $l) {
			$langs[$l] = 'admin.lang.' . $l;
		}
		// Champs "Valeurs par défaut" : clés plates du schéma utilisateur
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
					'remember_days' => ['label' => 'admin.f.remember_days', 'type' => 'integer'],
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
			'storage' => $this->storageBreakdown(),
			'extensions' => $extensions,
		];
	}

	/** Taille récursive par sous-dossier de storage/ (octets), tri décroissant. */
	private function storageBreakdown(): array
	{
		$out = [];
		foreach (glob($this->app->storageDir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
			$size = 0;
			foreach (new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($dir,
						\FilesystemIterator::SKIP_DOTS)) as $f) {
				$size += $f->getSize();
			}
			$out[] = ['name' => basename($dir), 'bytes' => $size];
		}
		usort($out, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
		return $out;
	}

	/** 'language' : 'auto' ou un dictionnaire Locale/<lang>.php existant. */
	private function validLanguage(mixed $v): string
	{
		$v = strtolower(trim((string) $v));
		if (!in_array($v, ['auto', ...$this->app->languages()], true)) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => 'language']), 400);
		}
		return $v;
	}

	private const FAVICON_MAX = 524288; // 512 Ko

	private const FAVICON_TYPES = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'webp'];

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

	/** Taille d'une base SQLite : fichier principal + journal WAL non fusionné. */
	private static function sqliteFileSize(string $file): int
	{
		return (int) @filesize($file) + (int) @filesize($file . '-wal');
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
		throw new \RuntimeException($this->t('errors.invalid_value', ['key' => 'default_domain']), 400);
	}
}
