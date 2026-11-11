# Database

*Translated from French - the French version is authoritative.*

MXWebMail runs on **SQLite** (default), **MariaDB >= 10.x** or **MySQL >= 8.0**.
The driver is chosen via `db_dsn` in `config.php`.

## 1. Connection

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (WAL mode + `foreign_keys`).
- Otherwise a full PDO DSN (e.g. `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`)
  with `db_user`/`db_pass`.

PDO options: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migrations

The schema is **portable** across SQLite/MariaDB/MySQL (auto-increment primary
keys adapted to the driver; MySQL >= 8.0 required for the SQL window functions
used in v12). Migrations are versioned (`meta.schema_version`) and applied
automatically on first database access (`App::db()`), including during
installation. Under the `mysql` driver, objects already present (column, index)
are detected via `information_schema`: a re-run after a mid-migration failure
is idempotent.

After a code update, the *About* tab triggers **finalization**
(`/api/admin/update/finalize`): the new version's migrations run in admin
context, then per-user contact databases are migrated in turn (a failing
database is logged and retried on its next access). As a fallback:
`php app/bin/post-update.php` replays the migrations from the CLI. If the schema
is newer than the code (file rollback without database restore),
`meta.schema_ahead` is set and reported in *About* - migrations are then
suspended.

## 3. Global tables

| Table | Content |
|---|---|
| `meta` | key/value pairs (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (unique), `display_name`, `is_admin`, `disabled`, `session_epoch` (incremented by "Force logout" - rejects active sessions on the next request), `created_at`, `last_login_at`. |
| `accounts` | user IMAP accounts: `email`, `imap_user`, `imap_pass` (encrypted), `is_default` (primary account, changeable in Settings > Accounts), `position` (display order of secondary accounts, reorderable by drag-and-drop in Settings > Accounts), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` then stores the encrypted *refresh token*). |
| `oauth_apps` | per-user OAuth2 applications: `provider`, `client_id`, `secret` (encrypted), `tenant` - one app per `(user_id, provider)` (see [domaines.md](domaines.md) §8). |
| `user_settings` | user settings (key/value), PK `(user_id, key)`. |
| `identities` | sending identities: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | per-domain mail servers (see [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (admin-only when 0 - default), `config` (JSON) per plugin. |
| `themes` | `config` (JSON) and `allowed` per theme. |
| `pgp_keys` | OpenPGP keyring (`key_data` armored, `is_private`). |
| `auth_tokens` | "Remember me" tokens: `selector` (unique), `token` (SHA-256 of the validator, rotated on each use), `agent` (User-Agent), `ip` (at issuance), `last_ip` (last restore), `created_at`, `last_used_at`, `expires_at`. |

The former `contacts` / `contact_emails` tables were moved out of the global
database (see §5).

## 4. `app.key`

`app/storage/app.key` is a 32-byte master key (64 hex chars), generated on the
first credential encryption. It encrypts (XSalsa20-Poly1305, libsodium
secretbox):

- IMAP passwords in session and in `accounts.imap_pass` (and OAuth2
  *refresh tokens* of accounts with `auth != 'password'`);
- users' OAuth2 application secrets (`oauth_apps.secret`);
- plugin secrets (e.g. CardDAV password).

**Back it up and never lose it**: losing it invalidates every stored credential
(users must log in again). It must never be served or committed.

## 5. Per-user databases (contacts)

Contacts are isolated in individual SQLite databases:
`app/storage/db/users/<id>.sqlite` (tables `contacts`, `contact_emails`). This
applies **under MariaDB as well**: personal data stays in SQLite files.

The `contacts_moved` migration copies contacts once from the global database to
these files (transaction + `INSERT OR IGNORE`, self-healing), then drops the
global tables.

## 6. Switching from SQLite to MariaDB/MySQL

1. In the admin (*Database*), fill in the MariaDB DSN/host/port/name/user/
   password. The connection test is **mandatory** and creates the database
   (`utf8mb4`, `utf8mb4_unicode_ci` collation) if it does not exist.
2. Migrations replay the schema on the new database.

> **Important**: changing `db_dsn` starts over on a **blank schema** - data
> (users, domains, settings) is **not migrated**. There is no export tool.
> Per-user contact files, however, are unchanged.

## 7. Backup / restore

- SQLite: copy `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) and `app.key`.
- MariaDB/MySQL: dump the database with `mysqldump` (or `mariadb-dump` on
  MariaDB), plus `app.key` and `db/users/*.sqlite`.
