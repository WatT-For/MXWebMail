<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Import des données d'une installation RainLoop ou SnappyMail
 * (dossier data/_data_/_default_ — format de stockage RainLoop).
 * Page admin de niveau supérieur (hook admin.mounts, section « page »).
 *
 * Importé : domaines (.ini/.json), contacts (AddressBook.sqlite par compte,
 * globale ou DSN PDO), identités, réglages utilisateur, dossiers spéciaux,
 * clés PGP armored. Non importable : mots de passe des comptes additionnels
 * (chiffrés par la clé du compte principal), filtres Sieve (côté serveur).
 *
 * API : /api/admin/plugin/webmail-migration/{upload|scan|run|reset}
 */
final class WebmailMigration extends PluginBase
{
	private const UPLOAD_MAX  = 64 * 1024 * 1024; // zip accepté max
	private const TMP_TTL     = 7200;             // purge des extractions > 2 h
	private const TMP_PREFIX  = 'webmail-migration-';
	private const SOURCE      = 'rainloop';

	/** prop_type → clé de properties (hors EMAIL/UID/FN traités à part). */
	private const PROP_MAP = [
		15 => 'first', 16 => 'last', 17 => 'middle', 18 => 'nick',
		20 => 'prefix', 21 => 'suffix', 31 => 'tel', 32 => 'url',
		40 => 'bday', 110 => 'note',
	];

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('admin.mounts', fn() => [
			'js'      => 'migration.js',
			'plugin'  => 'webmail-migration',
			'section' => 'page',
			'tab'     => 'webmail-migration',
			'label'   => 'plugin.webmail-migration.tab',
			'icon'    => 'fa-file-import',
		]);
	}

	// ─── API admin ───

	/** Upload du dossier data en .zip → extraction en storage/tmp. */
	public function apiAdminUpload(Request $request): array
	{
		$this->requireAdmin();
		if (!class_exists('ZipArchive')) {
			throw new \RuntimeException($this->t('plugin.webmail-migration.no_zip'), 400);
		}
		$f = $request->files['file'] ?? null;
		if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			throw new \RuntimeException($this->t('errors.bad_upload'), 400);
		}
		if ((int) ($f['size'] ?? 0) > self::UPLOAD_MAX) {
			throw new \RuntimeException($this->t('errors.file_too_big',
				['mb' => self::UPLOAD_MAX / 1048576]), 400);
		}
		$zip = new \ZipArchive();
		if ($zip->open((string) $f['tmp_name']) !== true) {
			throw new \RuntimeException($this->t('plugin.webmail-migration.bad_zip'), 400);
		}
		$id = bin2hex(random_bytes(8));
		$dest = $this->tmpDir() . '/' . self::TMP_PREFIX . $id;
		mkdir($dest, 0700, true);
		$count = 0;
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
			// Anti zip-slip : chemins relatifs uniquement, jamais de « .. ».
			if ($name === '' || $name[0] === '/' || str_contains($name, '..')
				|| preg_match('/^[a-zA-Z]:/', $name)) {
				continue;
			}
			$target = $dest . '/' . $name;
			if (str_ends_with($name, '/')) {
				is_dir($target) || mkdir($target, 0700, true);
				continue;
			}
			is_dir(dirname($target)) || mkdir(dirname($target), 0700, true);
			$in = $zip->getStream($zip->getNameIndex($i));
			if (!$in) {
				continue;
			}
			$out = fopen($target, 'wb');
			$out && stream_copy_to_stream($in, $out) !== false && $count++;
			$out && fclose($out);
			fclose($in);
		}
		$zip->close();
		if (!$count) {
			$this->rmTree($dest);
			throw new \RuntimeException($this->t('plugin.webmail-migration.bad_zip'), 400);
		}
		try {
			$root = $this->detectDataDir($dest);
		} catch (\Throwable $e) {
			$this->rmTree($dest); // pas un dossier data : ne pas garder l'extraction
			throw $e;
		}
		return ['upload' => $id, 'files' => $count, 'root' => $root];
	}

	/** Inventaire sans écriture : domaines, comptes, volumes par utilisateur. */
	public function apiAdminScan(Request $request): array
	{
		$this->requireAdmin();
		$this->gcTmp();
		$dir = $this->dataDir($request);
		return $this->scan($dir);
	}

	/** Import effectif. Ré-exécutable (dédup par email/clé/uid). */
	public function apiAdminRun(Request $request): array
	{
		$this->requireAdmin();
		$dir = $this->dataDir($request);
		return $this->run($dir);
	}

	/** Purge des extractions temporaires (uploads zip). */
	public function apiAdminReset(Request $request): array
	{
		$this->requireAdmin();
		$n = 0;
		foreach (glob($this->tmpDir() . '/' . self::TMP_PREFIX . '*', GLOB_ONLYDIR) ?: [] as $d) {
			$this->rmTree($d);
			$n++;
		}
		return ['purged' => $n];
	}

	// ─── Résolution de la source ───

	private function requireAdmin(): void
	{
		if (empty($_SESSION['admin'])) {
			throw new \RuntimeException($this->t('errors.admin_required'), 403);
		}
	}

	private function tmpDir(): string
	{
		$dir = $this->app->storageDir() . '/tmp';
		is_dir($dir) || mkdir($dir, 0700, true);
		return $dir;
	}

	/** Chemin serveur ou uploadId → racine data SnappyMail. */
	private function dataDir(Request $request): string
	{
		$upload = trim((string) $request->input('upload', ''));
		if ($upload !== '') {
			if (!preg_match('/^[a-f0-9]{16}$/', $upload)) {
				throw new \RuntimeException($this->t('plugin.webmail-migration.bad_upload_id'), 400);
			}
			return $this->detectDataDir($this->tmpDir() . '/' . self::TMP_PREFIX . $upload);
		}
		$path = trim((string) $request->input('path', ''));
		if ($path === '') {
			throw new \RuntimeException($this->t('plugin.webmail-migration.path_required'), 400);
		}
		$real = realpath($path);
		if ($real === false || !is_dir($real)) {
			throw new \RuntimeException($this->t('plugin.webmail-migration.path_missing'), 400);
		}
		return $this->detectDataDir($real);
	}

	/**
	 * Tolère de pointer la racine SnappyMail, data/, _data_/_default_/
	 * ou directement _default_ : descend jusqu'au niveau porteur de storage/,
	 * domains/ ou AddressBook.sqlite.
	 */
	private function detectDataDir(string $dir): string
	{
		foreach ([
			$dir,
			$dir . '/data/_data_/_default_',
			$dir . '/_data_/_default_',
			$dir . '/_default_',
			$dir . '/data',
		] as $c) {
			if (is_dir($c . '/storage') || is_dir($c . '/domains')
				|| is_file($c . '/AddressBook.sqlite')) {
				return str_replace('\\', '/', $c);
			}
		}
		throw new \RuntimeException($this->t('plugin.webmail-migration.invalid_data_dir'), 400);
	}

	// ─── Inventaire ───

	private function scan(string $dir): array
	{
		$report = ['dir' => $dir, 'domains' => [], 'users' => [], 'warnings' => []];

		foreach (array_merge(
			glob("$dir/domains/*.ini") ?: [], glob("$dir/domains/*.json") ?: []) as $f) {
			$d = $this->parseDomain($f);
			if ($d) {
				$d['exists'] = $this->app->domains()->exists($d['name']);
				$report['domains'][] = $d;
			}
		}

		foreach ($this->listAccountDirs($dir) as $email => $udir) {
			$report['users'][] = $this->scanUser($dir, $email, $udir);
		}

		// Carnet global : contacts des emails sans dossier storage/<domain>/<local>
		foreach ($this->globalAddressBookCounts($dir) as $email => $n) {
			if (!isset($report['users']) || !in_array($email,
					array_column($report['users'], 'email'), true)) {
				$report['users'][] = [
					'email' => $email, 'exists' => $this->userExists($email),
					'contacts' => $n, 'identities' => 0, 'pgp' => 0,
					'settings' => false, 'additional' => [],
				];
			}
		}
		return $report;
	}

	/** @return array<string, string> email => chemin du dossier compte */
	private function listAccountDirs(string $dir): array
	{
		$out = [];
		foreach (glob("$dir/storage/*/*", GLOB_ONLYDIR) ?: [] as $udir) {
			$domain = basename(dirname($udir));
			$local  = basename($udir);
			if ($local === '' || str_starts_with($local, '.')) {
				continue; // .sign_me/.sessions/.pgp ne sont pas des comptes
			}
			$out[$local . '@' . $domain] = $udir;
		}
		return $out;
	}

	private function scanUser(string $dir, string $email, string $udir): array
	{
		$u = [
			'email' => $email, 'exists' => $this->userExists($email),
			'contacts' => 0, 'identities' => 0, 'pgp' => 0,
			'settings' => is_file("$udir/settings"), 'additional' => [],
		];
		$ab = "$udir/AddressBook.sqlite";
		if (is_file($ab)) {
			$u['contacts'] = $this->sqliteContactCount($ab);
		}
		$ids = $this->readJson("$udir/identities");
		$u['identities'] = is_array($ids) ? count($ids) : 0;
		$add = $this->readJson("$udir/additionalaccounts");
		$u['additional'] = is_array($add) ? array_keys($add) : [];
		foreach (glob("$udir/.pgp/*") ?: [] as $f) {
			$u['pgp'] += substr_count((string) @file_get_contents($f), '-----BEGIN PGP');
		}
		return $u;
	}

	private function userExists(string $email): bool
	{
		return $this->app->users()->findByUsername(mb_strtolower($email)) !== null;
	}

	private function readJson(string $file): ?array
	{
		if (!is_file($file)) {
			return null;
		}
		$d = json_decode((string) file_get_contents($file), true);
		return is_array($d) ? $d : null;
	}

	// ─── Carnets d'adresses ───

	/**
	 * Localise les carnets : sqlite par compte, globale
	 * (data/AddressBook.sqlite) ou DSN PDO (configs/application.ini).
	 * @return array<int, array{kind:string, email?:string, path?:string, dsn?:string, user?:string, pass?:string}>
	 */
	private function addressBooks(string $dir, ?string $accountDir = null): array
	{
		if ($accountDir !== null) {
			return is_file("$accountDir/AddressBook.sqlite")
				? [['kind' => 'sqlite', 'path' => "$accountDir/AddressBook.sqlite"]]
				: [];
		}
		$out = [];
		if (is_file("$dir/AddressBook.sqlite")) {
			$out[] = ['kind' => 'sqlite', 'path' => "$dir/AddressBook.sqlite"];
		}
		// [contacts] de configs/application.ini : DSN mysql/pgsql éventuel
		$ini = @parse_ini_file("$dir/configs/application.ini", true) ?: [];
		$c = (array) ($ini['contacts'] ?? []);
		$type = (string) ($c['type'] ?? '');
		if (in_array($type, ['mysql', 'pgsql'], true) && trim((string) ($c['pdo_dsn'] ?? '')) !== '') {
			$out[] = [
				'kind' => 'pdo',
				'dsn'  => trim((string) $c['pdo_dsn']),
				'user' => trim((string) ($c['pdo_user'] ?? '')),
				'pass' => (string) ($c['pdo_password'] ?? ''),
			];
		}
		return $out;
	}

	private function abUserCount(string $file): int
	{
		try {
			$pdo = new \PDO('sqlite:' . $file, null, null,
				[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
			return (int) $pdo->query('SELECT COUNT(*) FROM rainloop_users')->fetchColumn();
		} catch (\Throwable) {
			return 0;
		}
	}

	private function sqliteContactCount(string $file): int
	{
		try {
			$pdo = new \PDO('sqlite:' . $file, null, null,
				[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
			return (int) $pdo->query(
				'SELECT COUNT(*) FROM rainloop_ab_contacts WHERE deleted = 0')->fetchColumn();
		} catch (\Throwable) {
			return 0;
		}
	}

	/**
	 * Contacts par email dans les carnets globaux (data/ + DSN).
	 * @return array<string, int>
	 */
	private function globalAddressBookCounts(string $dir): array
	{
		$out = [];
		foreach ($this->addressBooks($dir) as $ab) {
			try {
				foreach ($this->readAddressBook($ab, null) as $email => $items) {
					$out[$email] = ($out[$email] ?? 0) + count($items);
				}
			} catch (\Throwable) {}
		}
		return $out;
	}

	/**
	 * Lit un carnet et groupe les items par email utilisateur
	 * (rainloop_users.rl_email). $onlyEmail filtre un seul compte.
	 * @return array<string, array<int, array>>
	 */
	private function readAddressBook(array $ab, ?string $onlyEmail): array
	{
		$pdo = $ab['kind'] === 'sqlite'
			? new \PDO('sqlite:' . $ab['path'], null, null,
				[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION])
			: new \PDO((string) $ab['dsn'], (string) ($ab['user'] ?? ''),
				(string) ($ab['pass'] ?? ''), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

		// rainloop_users : id_user → rl_email (association au compte)
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
			if (!$email || ($onlyEmail !== null && $email !== mb_strtolower($onlyEmail))) {
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
					$key = self::PROP_MAP[$type];
					$item['properties'][$key] = $item['properties'][$key] ?? $val;
				} elseif ($type === 250 && $val !== '') { // CUSTOM
					$key = trim((string) ($p['prop_value_custom'] ?: $p['prop_type_str']));
					$item['properties']['x-' . substr(preg_replace('/[^a-z0-9\-]/i', '-',
						strtolower($key ?: 'custom')), 0, 40)] = $val;
				}
				// 251 JCARD : doublon structuré des props — ignoré
			}
			$item['name'] = trim((string) ($c['display'] ?? '')) !== ''
				? trim((string) $c['display']) : $item['name'];
			$out[$email][] = $item;
		}
		return $out;
	}

	/** prop_type_str « work,home » → premier type significatif. */
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

	// ─── Domaines ───

	/** .ini (clés plates historiques) et .json (format structuré récent). */
	private function parseDomain(string $file): ?array
	{
		$name = strtolower((string) preg_replace('/\.(ini|json)$/', '', basename($file)));
		if (str_ends_with($file, '.json')) {
			$d = $this->readJson($file);
			if (!$d) {
				return null;
			}
			$i = (array) ($d['IMAP'] ?? []); $s = (array) ($d['SMTP'] ?? []); $v = (array) ($d['Sieve'] ?? []);
			return $this->buildDomain($name, [
				'imap_host' => $i['host'] ?? '', 'imap_port' => $i['port'] ?? 993,
				'imap_secure' => $i['type'] ?? 1,
				'imap_short_login' => $i['shortLogin'] ?? false,
				'smtp_host' => $s['host'] ?? '', 'smtp_port' => $s['port'] ?? 465,
				'smtp_secure' => $s['type'] ?? 1,
				'smtp_short_login' => $s['shortLogin'] ?? false,
				'smtp_auth' => $s['useAuth'] ?? true,
				'smtp_set_sender' => $s['setSender'] ?? false,
				'sieve_use' => $v['enabled'] ?? false,
				'sieve_host' => $v['host'] ?? '', 'sieve_port' => $v['port'] ?? 4190,
				'sieve_secure' => $v['type'] ?? 1,
				'white_list' => $d['white_list'] ?? ($d['whiteList'] ?? ''),
			]);
		}
		$d = @parse_ini_file($file);
		return $d ? $this->buildDomain($name, $d) : null;
	}

	/** secure/type SnappyMail : 0=NONE, 1=SSL, 2=STARTTLS. */
	private static function enc(mixed $v): string
	{
		return match ((int) $v) { 0 => 'none', 2 => 'tls', default => 'ssl' };
	}

	private function buildDomain(string $name, array $d): ?array
	{
		if ($name === '' || empty($d['imap_host']) || empty($d['smtp_host'])) {
			return null;
		}
		$options = [];
		$wl = trim(preg_replace('/[\r\n]+/', ',', (string) ($d['white_list'] ?? '')));
		if ($wl !== '') {
			$options['whitelist'] = $wl;
		}
		!empty($d['imap_short_login']) && $options['imap']['shortLogin'] = true;
		!empty($d['smtp_short_login']) && $options['smtp']['shortLogin'] = true;
		isset($d['smtp_auth']) && !$d['smtp_auth'] && $options['smtp']['useAuth'] = false;
		!empty($d['smtp_set_sender']) && $options['smtp']['setSender'] = true;
		return [
			'name'            => $name,
			'imap_host'       => (string) $d['imap_host'],
			'imap_port'       => (int) ($d['imap_port'] ?? 993),
			'imap_encryption' => self::enc($d['imap_secure'] ?? 1),
			'smtp_host'       => (string) $d['smtp_host'],
			'smtp_port'       => (int) ($d['smtp_port'] ?? 465),
			'smtp_encryption' => self::enc($d['smtp_secure'] ?? 1),
			'sieve_host'      => !empty($d['sieve_use']) || !empty($d['sieve_host'])
				? (string) ($d['sieve_host'] ?? '') : null,
			'sieve_port'      => (int) ($d['sieve_port'] ?? 4190),
			'options'         => $options ?: null,
		];
	}

	// ─── Exécution ───

	private function run(string $dir): array
	{
		$report = ['domains' => ['added' => 0, 'skipped' => 0],
			'users' => [], 'skipped_users' => [], 'warnings' => []];

		// Phase 1 : domaines
		foreach (array_merge(glob("$dir/domains/*.ini") ?: [],
				glob("$dir/domains/*.json") ?: []) as $f) {
			$d = $this->parseDomain($f);
			if (!$d) {
				continue;
			}
			if ($this->app->domains()->exists($d['name'])) {
				$report['domains']['skipped']++;
				continue;
			}
			$this->app->domains()->save($d);
			$report['domains']['added']++;
		}

		// Phase 2 : carnets globaux → items par email
		$global = [];
		foreach ($this->addressBooks($dir) as $ab) {
			try {
				foreach ($this->readAddressBook($ab, null) as $email => $items) {
					$global[$email] = array_merge($global[$email] ?? [], $items);
				}
			} catch (\Throwable $e) {
				$report['warnings'][] = "addressbook: {$e->getMessage()}";
			}
		}

		// Phase 3 : par compte storage/<domain>/<local>
		$seen = [];
		foreach ($this->listAccountDirs($dir) as $email => $udir) {
			$seen[mb_strtolower($email)] = true;
			$report['users'][] = $this->importUser($email, $udir, $global);
		}
		// Comptes présents uniquement dans le carnet global
		foreach ($global as $email => $items) {
			if (isset($seen[$email])) {
				continue;
			}
			$u = $this->userFor($email);
			if (!$u) {
				$report['skipped_users'][] = $email;
				continue;
			}
			$r = $this->app->contacts()->import((int) $u['id'], $items, self::SOURCE);
			$report['users'][] = ['email' => $email, 'contacts' => $r,
				'identities' => 0, 'settings' => [], 'pgp' => 0,
				'additional' => [], 'warnings' => []];
		}
		return $report;
	}

	private function userFor(string $email): ?array
	{
		return $this->app->users()->findByUsername(mb_strtolower($email));
	}

	private function importUser(string $email, string $udir, array $global): array
	{
		$u = $this->userFor($email);
		$out = ['email' => $email, 'contacts' => null, 'identities' => 0,
			'settings' => [], 'pgp' => 0, 'additional' => [], 'warnings' => []];
		if (!$u) {
			$out['skipped'] = true;
			return $out;
		}
		$uid = (int) $u['id'];

		// Contacts : carnet du compte + sa part du carnet global
		$items = $global[mb_strtolower($email)] ?? [];
		foreach ($this->addressBooks('', $udir) as $ab) {
			try {
				foreach ($this->readAddressBook($ab, $email) as $items2) {
					array_push($items, ...$items2);
				}
			} catch (\Throwable $e) {
				$out['warnings'][] = "addressbook: {$e->getMessage()}";
			}
		}
		// Un carnet par-compte peut ne pas référencer rainloop_users :
		// lecture non filtrée, mais seulement si la base est mono-utilisateur
		// (évite d'aspirer les contacts d'autres comptes).
		if (!$items) {
			foreach ($this->addressBooks('', $udir) as $ab) {
				if ($ab['kind'] !== 'sqlite' || $this->abUserCount($ab['path']) > 1) {
					continue;
				}
				try {
					foreach ($this->readAddressBook($ab, null) as $items2) {
						array_push($items, ...$items2);
					}
				} catch (\Throwable) {}
			}
		}
		$out['contacts'] = $items
			? $this->app->contacts()->import($uid, $items, self::SOURCE) : null;

		// Identités → compte par défaut de l'utilisateur
		$accountId = $this->defaultAccountId($uid);
		foreach ((array) $this->readJson("$udir/identities") as $idn) {
			if ($this->importIdentity($uid, $accountId, (array) $idn)) {
				$out['identities']++;
			}
		}

		// Réglages utilisateur (clés connues validées par checkUserSetting)
		$out['settings'] = $this->importSettings($uid, $accountId, $udir);

		// Clés PGP : tout bloc armored trouvé dans .pgp/
		foreach (glob("$udir/.pgp/*") ?: [] as $f) {
			$out['pgp'] += $this->importPgp($uid, $email, (string) @file_get_contents($f));
		}

		// Comptes additionnels : listés (mots de passe indéchiffrables)
		$add = $this->readJson("$udir/additionalaccounts");
		$out['additional'] = is_array($add) ? array_keys($add) : [];
		if ($out['additional']) {
			$out['warnings'][] = $this->t('plugin.webmail-migration.additional_note');
		}
		return $out;
	}

	private function defaultAccountId(int $uid): ?int
	{
		$id = $this->app->db()->pdo()->query(
			"SELECT id FROM accounts WHERE user_id = $uid
			 ORDER BY is_default DESC, id LIMIT 1")->fetchColumn();
		return $id === false ? null : (int) $id;
	}

	/**
	 * Identité SnappyMail (JSON stocké, clés capitalisées : Id/Email/Name/
	 * ReplyTo/Bcc/Signature…). L'identité principale a Id === ''
	 * (IsAccountIdentities) → is_default côté mxwebmail.
	 */
	private function importIdentity(int $uid, ?int $accountId, array $idn): bool
	{
		$email = trim((string) ($idn['Email'] ?? $idn['email'] ?? ''));
		if ($email === '' || !$accountId) {
			return false;
		}
		foreach ($this->app->identities()->allForUser($uid) as $ex) {
			if (mb_strtolower((string) $ex['email']) === mb_strtolower($email)) {
				return false; // déjà présente
			}
		}
		$this->app->identities()->save($uid, [
			'account_id' => $accountId,
			'name'       => (string) ($idn['Name'] ?? $idn['name'] ?? ''),
			'email'      => $email,
			'reply_to'   => (string) ($idn['ReplyTo'] ?? $idn['replyTo'] ?? '') ?: null,
			'bcc'        => (string) ($idn['Bcc'] ?? $idn['bcc'] ?? '') ?: null,
			'signature'  => (string) ($idn['Signature'] ?? $idn['signature'] ?? '') ?: null,
			'is_default' => (string) ($idn['Id'] ?? $idn['id'] ?? '?') === '',
		]);
		return true;
	}

	/**
	 * Fichier `settings` : seules les clés connues sont reprises
	 * (checkUserSetting = même barrière de validation que l'UI).
	 */
	private function importSettings(int $uid, ?int $accountId, string $udir): array
	{
		$s = $this->readJson("$udir/settings");
		if (!$s) {
			return [];
		}
		$applied = [];
		$map = [
			'MessagesPerPage'      => 'messages_per_page',
			'CheckMailInterval'    => 'list_refresh',
			'ContactsAutosave'     => 'contacts_autosave',
			'DesktopNotifications' => 'notify_new_mail',
			'Theme'                => 'theme',
		];
		$settings = $this->app->settings();
		foreach ($map as $from => $to) {
			if (!array_key_exists($from, $s)) {
				continue;
			}
			[$ok, $v] = $this->app->checkUserSetting($to, $s[$from]);
			if ($ok && $v !== null) {
				$settings->set($uid, $to, $v);
				$applied[] = $to;
			}
		}
		// Dossiers système : special_folders est indexé par account_id.
		if ($accountId) {
			$roles = ['SentFolder' => 'sent', 'DraftsFolder' => 'drafts',
				'TrashFolder' => 'trash', 'JunkFolder' => 'junk',
				'ArchiveFolder' => 'archive'];
			$fm = [];
			foreach ($roles as $from => $role) {
				$v = trim((string) ($s[$from] ?? ''));
				if ($v !== '') {
					$fm[$role] = $v;
				}
			}
			if ($fm) {
				$all = (array) $settings->get($uid, 'special_folders', []);
				$all[(string) $accountId] = $fm + (array) ($all[(string) $accountId] ?? []);
				$settings->set($uid, 'special_folders', $all);
				$applied[] = 'special_folders';
			}
		}
		return $applied;
	}

	/** Tout bloc armored d'un fichier .pgp → pgp_keys (dédup sur l'armure). */
	private function importPgp(int $uid, string $email, string $raw): int
	{
		if ($raw === '' || !preg_match_all(
				'/-----BEGIN PGP (PUBLIC|PRIVATE) KEY BLOCK-----.*?-----END PGP \1 KEY BLOCK-----/s',
				$raw, $m)) {
			return 0;
		}
		$pdo = $this->app->db()->pdo();
		$has = $pdo->prepare('SELECT 1 FROM pgp_keys WHERE user_id = :u AND key_data = :k');
		$ins = $pdo->prepare(
			'INSERT INTO pgp_keys (user_id, email, key_data, is_private) VALUES (:u,:e,:k,:p)');
		$n = 0;
		foreach ($m[0] as $i => $armor) {
			$has->execute(['u' => $uid, 'k' => $armor]);
			if ($has->fetchColumn()) {
				continue;
			}
			$ins->execute(['u' => $uid, 'e' => mb_strtolower($email),
				'k' => $armor, 'p' => $m[1][$i] === 'PRIVATE' ? 1 : 0]);
			$n++;
		}
		return $n;
	}

	// ─── Utilitaires ───

	private function gcTmp(): void
	{
		foreach (glob($this->tmpDir() . '/' . self::TMP_PREFIX . '*', GLOB_ONLYDIR) ?: [] as $d) {
			if (time() - (int) @filemtime($d) > self::TMP_TTL) {
				$this->rmTree($d);
			}
		}
	}

	private function rmTree(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $f) {
			$f->isDir() ? rmdir((string) $f) : unlink((string) $f);
		}
		rmdir($dir);
	}
}
