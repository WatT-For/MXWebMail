<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

use MXWebMail\Config\Config;
use PDO;

/**
 * Connexion PDO unique + migrations embarquées.
 * PDO pour les données, fichiers pour les flux : toute la persistance passe ici.
 */
final class Database
{
	private const PDO_OPTIONS = [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES   => false,
	];

	/** @param array<int, string[]> $migrations index = version de schéma */
	private function __construct(private PDO $pdo, private array $migrations) {}

	/** Base globale : 'sqlite' > storage/db/webmail.sqlite, sinon DSN PDO complet. */
	public static function connect(Config $config, string $storageDir): self
	{
		$dsn = $config->get('db_dsn', 'sqlite');
		if ($dsn === 'sqlite') {
			$dbDir = $storageDir . '/db';
			if (!is_dir($dbDir)) {
				mkdir($dbDir, 0700, true);
			}
			$dsn = 'sqlite:' . $dbDir . '/webmail.sqlite';
		}

		$pdo = new PDO(
			$dsn,
			$config->get('db_user') ?: null,
			$config->get('db_pass') ?: null,
			self::PDO_OPTIONS
		);

		if (str_starts_with($dsn, 'sqlite:')) {
			// busy_timeout : évite SQLITE_BUSY si deux requêtes écrivent en
			// concurrence (le verrou de session PHP n'est plus garant de la
			// sérialisation depuis la fermeture anticipée des GET read-only).
			$pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
		}
		return new self($pdo, self::globalMigrations($pdo));
	}

	/** Fichier SQLite par utilisateur (contacts). Le dossier parent est créé par l'appelant. */
	public static function sqliteFile(string $path): self
	{
		$pdo = new PDO('sqlite:' . $path, null, null, self::PDO_OPTIONS);
		// auto_vacuum avant toute table : les réécritures de sync rendent les pages au FS
		$pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA auto_vacuum=INCREMENTAL; PRAGMA busy_timeout=5000;');
		return new self($pdo, self::userMigrations());
	}

	public function pdo(): PDO
	{
		return $this->pdo;
	}

	/**
	 * Migrations : chaque entrée s'applique une fois (suivi dans `meta.schema_version`).
	 * SQL portable SQLite/MariaDB - pas de type exotique.
	 */
	public function migrate(): void
	{
		try {
			// Une seule requête pour les deux clés (2 SELECT à chaque connexion
			// avant, même schéma à jour).
			$meta = $this->metaMap(['schema_version', 'schema_ahead']);
			$version = (int) ($meta['schema_version'] ?? 0);
			$ahead = $meta['schema_ahead'] ?? null;
			$max = max(array_keys($this->migrations));
			if ($version > $max) {
				// Schéma plus récent que le code : rollback fichiers ou branche
				// plus ancienne. On ne touche à rien ; meta.schema_ahead est
				// remonté dans l'admin ("À propos") pour signaler l'écart.
				if ($ahead !== (string) $version) {
					$this->setMeta('schema_ahead', (string) $version);
				}
				return;
			}
			// Code revenu au niveau (ou au-delà) du schéma > le flag tombe.
			if ($ahead !== null && $ahead !== '') {
				$this->setMeta('schema_ahead', '');
			}
			if ($version >= $max) {
				return; // schéma à jour : aucun DDL par requête
			}
		} catch (\PDOException) {
			$version = 0; // table meta absente > migration complète
		}

		$this->pdo->exec(
			// `key` est cité : mot réservé MariaDB/MySQL (erreur 1064 non
			// échappé), accepté aussi par SQLite.
			'CREATE TABLE IF NOT EXISTS meta (
				`key` VARCHAR(64) PRIMARY KEY,
				value TEXT
			)'
		);

		// Transaction par version : sous SQLite le DDL est transactionnel
		// (échec = retour complet à l'état $version) ; sous MariaDB/MySQL le
		// DDL auto-commit et PDO le détecte - commit() jetterait "no active
		// transaction". Les ALTER sont idempotents (ADD COLUMN IF NOT EXISTS
		// sous MariaDB, alreadyApplied() sinon) donc un re-run après échec
		// est sain.
		$useTx = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
		$isMysql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
		foreach ($this->migrations as $i => $sqlList) {
			if ($i <= $version) {
				continue;
			}
			try {
				if ($useTx) {
					$this->pdo->beginTransaction();
				}
				foreach ($sqlList as $sql) {
					if ($isMysql && $this->alreadyApplied($sql)) {
						continue;
					}
					$this->pdo->exec($sql);
				}
				$this->setMeta('schema_version', (string) $i);
				if ($useTx) {
					$this->pdo->commit();
				}
			} catch (\Throwable $e) {
				if ($this->pdo->inTransaction()) {
					$this->pdo->rollBack();
				}
				throw $e;
			}
		}
	}

	/**
	 * Driver mysql (MySQL et MariaDB) : le DDL auto-commit - un échec à
	 * mi-migration laisse des objets créés et le re-run échouerait sur
	 * "Duplicate column/key name". MySQL ne connaît pas IF NOT EXISTS pour
	 * ADD COLUMN / CREATE INDEX : existence vérifiée dans information_schema
	 * (DATABASE() NULL > 0 > statement exécuté normalement). SQLite est
	 * couvert par la transaction et n'appelle jamais cette méthode.
	 */
	private function alreadyApplied(string $sql): bool
	{
		if (preg_match(
			'/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i',
			$sql, $m)) {
			$st = $this->pdo->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
			$st->execute([$m[1], $m[2]]);
			return (bool) $st->fetchColumn();
		}
		if (preg_match(
			'/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s+ON\s+`?(\w+)`?/i',
			$sql, $m)) {
			$st = $this->pdo->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
			$st->execute([$m[2], $m[1]]);
			return (bool) $st->fetchColumn();
		}
		return false;
	}

	public function meta(string $key): ?string
	{
		$stmt = $this->pdo->prepare('SELECT value FROM meta WHERE `key` = :k');
		$stmt->execute(['k' => $key]);
		$v = $stmt->fetchColumn();
		return $v === false ? null : (string) $v;
	}

	/** Lecture groupée de clés meta en une seule requête > map key => value. */
	private function metaMap(array $keys): array
	{
		$in = implode(',', array_fill(0, count($keys), '?'));
		$stmt = $this->pdo->prepare("SELECT `key`, value FROM meta WHERE `key` IN ($in)");
		$stmt->execute(array_values($keys));
		return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
	}

	public function setMeta(string $key, string $value): void
	{
		if (str_starts_with($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite')) {
			$this->pdo->prepare('INSERT INTO meta (`key`, value) VALUES (:k, :v)
				ON CONFLICT(`key`) DO UPDATE SET value = excluded.value')
				->execute(['k' => $key, 'v' => $value]);
		} else {
			$this->pdo->prepare('INSERT INTO meta (`key`, value) VALUES (:k, :v)
				ON DUPLICATE KEY UPDATE value = VALUES(value)')
				->execute(['k' => $key, 'v' => $value]);
		}
	}

	/** @return array<int, string[]> index = version de schéma */
	private static function globalMigrations(PDO $pdo): array
	{
		$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		$pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY' : 'INTEGER AUTO_INCREMENT PRIMARY KEY';
		// ADD COLUMN IF NOT EXISTS : MariaDB uniquement (MySQL ne le supporte
		// pas, SQLite le gère via la transaction). Rend un re-run idempotent
		// après un échec à mi-migration - relayé par alreadyApplied() sur le
		// driver mysql (MySQL compris, sans IF NOT EXISTS).
		$isMaria = \str_contains((string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'MariaDB');
		$addCol = $isMaria ? 'ADD COLUMN IF NOT EXISTS' : 'ADD COLUMN';
		// CREATE INDEX IF NOT EXISTS : supporté par SQLite et MariaDB, pas
		// par MySQL (erreur 1064) - alreadyApplied() assure l'idempotence.
		$createIndex = ($driver === 'sqlite' || $isMaria)
			? 'CREATE INDEX IF NOT EXISTS' : 'CREATE INDEX';
		return [

		1 => [
			"CREATE TABLE IF NOT EXISTS users (
				id            $pk,
				username      VARCHAR(255) NOT NULL UNIQUE,
				display_name  VARCHAR(255) DEFAULT NULL,
				is_admin      INTEGER NOT NULL DEFAULT 0,
				created_at    INTEGER NOT NULL,
				last_login_at INTEGER DEFAULT NULL
			)",

			"CREATE TABLE IF NOT EXISTS accounts (
				id         $pk,
				user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				email      VARCHAR(255) NOT NULL,
				imap_user  VARCHAR(255) NOT NULL,
				imap_pass  TEXT NOT NULL,
				is_default INTEGER NOT NULL DEFAULT 0,
				UNIQUE (user_id, email)
			)",

			"CREATE TABLE IF NOT EXISTS user_settings (
				user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				`key`   VARCHAR(128) NOT NULL,
				value   TEXT,
				PRIMARY KEY (user_id, `key`)
			)",

			"CREATE TABLE IF NOT EXISTS identities (
				id         $pk,
				account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
				name       VARCHAR(255) NOT NULL DEFAULT '',
				email      VARCHAR(255) NOT NULL,
				reply_to   VARCHAR(255) DEFAULT NULL,
				bcc        VARCHAR(255) DEFAULT NULL,
				signature  TEXT DEFAULT NULL,
				is_default INTEGER NOT NULL DEFAULT 0
			)",

			"CREATE TABLE IF NOT EXISTS domains (
				id              $pk,
				name            VARCHAR(255) NOT NULL UNIQUE,
				imap_host       VARCHAR(255) NOT NULL,
				imap_port       INTEGER NOT NULL DEFAULT 993,
				imap_encryption VARCHAR(8) NOT NULL DEFAULT 'ssl',
				smtp_host       VARCHAR(255) NOT NULL,
				smtp_port       INTEGER NOT NULL DEFAULT 465,
				smtp_encryption VARCHAR(8) NOT NULL DEFAULT 'ssl',
				sieve_host      VARCHAR(255) DEFAULT NULL,
				sieve_port      INTEGER DEFAULT 4190,
				active          INTEGER NOT NULL DEFAULT 1
			)",

			// Les tables contacts sont déplacées dans storage/db/users/<id>.sqlite
			// par App::importLegacyContacts() puis droppées - schéma conservé pour
			// l'historique des migrations.
			"CREATE TABLE IF NOT EXISTS contacts (
				id         $pk,
				user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				name       VARCHAR(255) NOT NULL DEFAULT '',
				frequency  INTEGER NOT NULL DEFAULT 0,
				properties TEXT DEFAULT NULL
			)",

			"CREATE TABLE IF NOT EXISTS contact_emails (
				id         $pk,
				contact_id INTEGER NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
				email      VARCHAR(255) NOT NULL,
				type       VARCHAR(32) DEFAULT NULL
			)",

			// Jetons "se souvenir de moi" : cookie mxwm_remember =
			// selector:validator ; seul le sha256 du validator est stocké.
			"CREATE TABLE IF NOT EXISTS auth_tokens (
				id           $pk,
				user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				selector     VARCHAR(32) NOT NULL UNIQUE,
				token        VARCHAR(64) NOT NULL,
				agent        VARCHAR(255) DEFAULT NULL,
				created_at   INTEGER NOT NULL,
				last_used_at INTEGER DEFAULT NULL,
				expires_at   INTEGER NOT NULL
			)",

			"CREATE TABLE IF NOT EXISTS pgp_keys (
				id         $pk,
				user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				email      VARCHAR(255) NOT NULL,
				key_data   TEXT NOT NULL,
				is_private INTEGER NOT NULL DEFAULT 0
			)",

			"CREATE TABLE IF NOT EXISTS plugins (
				name    VARCHAR(64) PRIMARY KEY,
				enabled INTEGER NOT NULL DEFAULT 0,
				config  TEXT DEFAULT NULL
			)",
		],

		// Contacts importés depuis une source externe (carddav, ldap…)
		2 => [
			"ALTER TABLE contacts $addCol source VARCHAR(32) NOT NULL DEFAULT ''",
			"ALTER TABLE contacts $addCol source_uid VARCHAR(255) DEFAULT NULL",
		],

		// Options avancées par domaine : JSON {imap:{…}, smtp:{…}, sieve:{…}, whitelist:"…"}
		3 => [
			"ALTER TABLE domains $addCol options TEXT DEFAULT NULL",
		],

		// Config des templates d'interface (app/templates/<name>/config.schema.json)
		4 => [
			"CREATE TABLE IF NOT EXISTS themes (
				name   VARCHAR(64) PRIMARY KEY,
				config TEXT DEFAULT NULL
			)",
		],

		// Blocage d'un compte par l'admin (login refusé, sessions tuées via isLoggedIn)
		5 => [
			"ALTER TABLE users $addCol disabled INTEGER NOT NULL DEFAULT 0",
		],

		// Templates proposables dans les réglages utilisateur (le défaut l'est toujours)
		6 => [
			"ALTER TABLE themes $addCol allowed INTEGER NOT NULL DEFAULT 1",
		],

		// Type d'authentification du compte : 'password' ou provider OAuth2
		// ('google','microsoft','yahoo' - imap_pass = refresh token chiffré)
		7 => [
			"ALTER TABLE accounts $addCol auth VARCHAR(16) NOT NULL DEFAULT 'password'",
		],

		// Applications OAuth2 par utilisateur : les identifiants d'app
		// (client_id/secret/tenant) sont saisis et stockés par utilisateur -
		// secret chiffré CredentialCrypt. Une app par (utilisateur, provider).
		8 => [
			"CREATE TABLE IF NOT EXISTS oauth_apps (
				id         $pk,
				user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				provider   VARCHAR(16) NOT NULL,
				client_id  VARCHAR(255) NOT NULL,
				secret     TEXT NOT NULL DEFAULT '',
				tenant     VARCHAR(255) NOT NULL DEFAULT '',
				UNIQUE (user_id, provider)
			)",
		],

		// "Utilisable par les utilisateurs" : plugin activé mais non chargé hors
		// contexte admin - refusé par défaut, à autoriser dans l'onglet Extensions
		9 => [
			"ALTER TABLE plugins $addCol user_allowed INTEGER NOT NULL DEFAULT 0",
		],

		// IP à l'émission et à la dernière utilisation des jetons
		// "se souvenir de moi" (45 = IPv6) - affichées dans
		// Paramètres > Sessions.
		10 => [
			"ALTER TABLE auth_tokens $addCol ip VARCHAR(45) DEFAULT NULL",
			"ALTER TABLE auth_tokens $addCol last_ip VARCHAR(45) DEFAULT NULL",
		],

		// Plugin "admin-stats" retiré du cœur : purge de sa ligne d'état
		// (enabled/user_allowed/config) sur les instances où il était activé -
		// équivalent de PluginPackageService::remove().
		11 => [
			"DELETE FROM plugins WHERE name = 'admin-stats'",
		],

		// Ordre d'affichage des comptes (réordonnable dans Réglages > Comptes) :
		// backfill = ordre visuel actuel (principal puis alphabétique). La
		// table dérivée contourne la contrainte d'auto-référence MySQL/MariaDB.
		12 => [
			"ALTER TABLE accounts $addCol position INTEGER NOT NULL DEFAULT 0",
			'UPDATE accounts SET position = (
				SELECT n FROM (
					SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY is_default DESC, email) AS n
					FROM accounts
				) r WHERE r.id = accounts.id
			)',
		],

		// Index de jointure : chargement des identités par compte et
		// UserRepository::all balayaient la table sans index dédié.
		13 => [
			"$createIndex identities_account ON identities(account_id)",
		],

		// Epoch de session : incrémenté par l'admin pour tuer les sessions
		// PHP vivantes d'un utilisateur (comparaison dans App::isLoggedIn).
		14 => [
			"ALTER TABLE users $addCol session_epoch INTEGER NOT NULL DEFAULT 0",
		],
		];
	}

	/**
	 * Schéma des bases par utilisateur (storage/db/users/<id>.sqlite) - SQLite only.
	 * user_id est conservé pour garder le SQL de ContactRepository identique.
	 * @return array<int, string[]>
	 */
	private static function userMigrations(): array
	{
		return [
		1 => [
			"CREATE TABLE IF NOT EXISTS contacts (
				id         INTEGER PRIMARY KEY,
				user_id    INTEGER NOT NULL,
				name       VARCHAR(255) NOT NULL DEFAULT '',
				frequency  INTEGER NOT NULL DEFAULT 0,
				properties TEXT DEFAULT NULL,
				source     VARCHAR(32) NOT NULL DEFAULT '',
				source_uid VARCHAR(255) DEFAULT NULL
			)",

			"CREATE TABLE IF NOT EXISTS contact_emails (
				id         INTEGER PRIMARY KEY,
				contact_id INTEGER NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
				email      VARCHAR(255) NOT NULL,
				type       VARCHAR(32) DEFAULT NULL
			)",

			'CREATE INDEX IF NOT EXISTS ce_email   ON contact_emails(email)',
			'CREATE INDEX IF NOT EXISTS ce_contact ON contact_emails(contact_id)',
			'CREATE INDEX IF NOT EXISTS c_source   ON contacts(source)',
		],
		];
	}
}
