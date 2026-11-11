# Administration

*Translated from French - the French version is authoritative.*

The admin panel is reachable at `/{admin_path}` (default `/admin`). It is a
**global** administration (multi-domain); there are no fine-grained roles.

## 1. Admin authentication

Two modes, depending on configuration:

1. **Local account** (`admin_user` + `admin_password_hash` in `config.php`):
   login/password, optionally TOTP (`admin_totp_secret`). TOTP is provisioned
   and enforced by the `two-factor-auth` plugin ("Two-factor authentication"
   sub-tab of *Security*, with QR code) - plugin disabled = not required at
   login.
2. **IMAP fallback** (if no local account): a user authenticated via IMAP whose
   `is_admin = 1` field opens the admin. That right is revalidated on every
   request (a demoted/blocked/deleted account is immediately rejected).

The login rate-limit also applies to the admin, as does `admin_ip_allowlist`
when set. An IMAP admin cannot demote, block or delete themselves.

## 2. Panel sections

The panel is made of tabs (hash `#/<page>[/<sub-tab>]`).

### General

`app_name`, `admin_path`, `language` (list of installed languages + `auto`),
`session_lifetime`, `remember_days` (lifetime of "Remember me" tokens),
`maintenance`, `update.channel`. Also manages the **favicon** (upload ≤ 512 KB:
ico/png/svg/jpg/webp, content validated). **Maintenance** mode shows a
dedicated page (503) on `/`: logged-in users are switched to it at their next
API request (session preserved) and it reloads by itself at the end; the admin
(`/{admin_path}` + `/api/admin/*`) stays fully reachable.

### Database

SQLite or MariaDB/MySQL driver, host/port/name/user/password. A test
(`/api/admin/test-db`) is mandatory before writing: the server validates the
connection and creates the database if needed. See
[base-de-donnees.md](base-de-donnees.md).

The **Backup** card downloads a zip archive (`config.php`, `app.key`, SQLite
databases, `storage/branding`) - **sensitive** (credentials + encryption key),
to be kept in a safe place. A MariaDB database is not covered: `mysqldump`
server-side.

### Features

*Features* and *Defaults* sub-tabs:

- image proxy + allowlist, attachment sizes;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`, `popup_identity`,
  `dangerous_actions`;
- all `user_defaults.*` settings (user defaults).

### Performance

*HTTP* and *Server cache* sub-tabs:

- `http.*`: gzip compression (enable, level, minimum size), `http.etag`, TTL of
  theme/plugin assets, of the favicon and of the image proxy;
- `cache.*`: application file cache (enable, TTL of domain detection and update
  check, purge interval).

Key details in [configuration.md](configuration.md) (*HTTP* and *Application
cache* sections).

### Security

*Account*, *Two-factor authentication* (if the `two-factor-auth` plugin is
enabled), *Access*, *Headers*, *TLS* sub-tabs:

- local admin account (`admin_user`) and **admin password change** - fill both
  fields then *Save*: the password is only changed if they are filled and
  identical;
- admin **TOTP** (sub-tab of the `two-factor-auth` plugin): secret generation,
  QR code to scan, confirmation code required before activation (the secret is
  never displayed after saving);
- `admin_ip_allowlist` ("add my IP" button), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- full `ssl.*` section (outgoing certificates).

### Logs

*Viewer* and *Settings* sub-tabs:

- viewer for `app.log`, `mail.log` and the auth log (last 200 lines), with a
  "Clear" button to truncate the selected file;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domains

Per-domain mail server configuration - see [domaines.md](domaines.md). The
*Settings* tab groups resolution (`default_domain`, `restrict_domains`,
`autodetect.*`). For "secondary account only + OAuth provider" domains (Gmail,
Outlook, Yahoo), the admin only allows the provider (`oauth` option of the
domain modal): OAuth2 app credentials are registered by each user (*Settings >
OAuth2 Apps*).

### Users

List + user search, with a refresh button (icon) to reload the list. Per user
(modal, *Profile*, *Settings*, *Accounts/Identities*, *Sessions* tabs):

- `display_name`, `is_admin`, `disabled`;
- effective user settings (edit + reset - the reset restores the
  `user_defaults` but **preserves** the internal `plugin.*` and `user_folders`
  keys, e.g. 2FA secret, folder provenance);
- secondary IMAP accounts (deletion, except the default account);
- identities (create/edit/delete, default identity);
- **Sessions** tab: "Remember me" tokens listed and revocable; **Force logout**
  revokes tokens and kills active sessions (session epoch incremented,
  rejection at the next request).

Deleting a user cascades and also deletes their contacts database
(`storage/db/users/<id>.sqlite`).

### Extensions (plugins)

Each plugin is listed under its **display name** (manifest `label` field,
translated - the technical `name` stays displayed next to it in `code`).

Enable/disable each plugin + **configuration** modal generated from
`config.schema.json` (types `string`, `text`, `integer`, `number`, `password`,
`bool`, `enum`, `color`, `list`). The **User access** column restricts an
enabled plugin to the admin surface (page `/{admin_path}`, `/api/admin/*`,
admin sessions): it becomes inert for users - see [plugins.md](plugins.md).
Unchecked by default (to be allowed explicitly); absent for plugins declaring
only `admin.*` hooks (no user surface).

**Install / update from GitHub**: plugins are read directly from the locked
repository (`GitHubClient::REPO`, same as the core updater) at ref
`update.plugins_ref` - `'release'` = tag of the latest release by default,
`'main'` in dev (pushing `plugin.json` publishes the update, no release
required). Each file downloaded via `raw.githubusercontent.com` is verified by
git blob sha1.
The catalog is loaded when the tab opens (cache shared with the core check)
and refreshable via the **refresh** button at top right; uninstalled catalog
entries are listed at the end of the plugins table:

- **Update** button on installed plugins when a newer version is published (the
  old folder is backed up to `storage/update/plugins/backup-<name>-<ts>` - a
  single one kept, automatic rollback on failure);
- **Install** button on catalog entries not present - a newly installed plugin
  is **disabled** by default;
- **Uninstall** button: **permanent** deletion of the folder and configuration
  (`plugins` row purged), no backup;
- remote `plugin.json` `min_app`/`max_app`: an entry incompatible with
  `APP_VERSION` is marked *incompatible* and its install refused.

### Webmail migration (RainLoop / SnappyMail)

If the `webmail-migration` plugin is enabled, a **Webmail migration** page
appears in the side menu (before *About*): it migrates data from a
`data/_data_/_default_/` folder of a **RainLoop or SnappyMail** install, given
by **server path** or **ZIP upload** (extracted in `storage/tmp`, purged via
the dedicated button or after 2 h).

- **Analyze**: read-only inventory (`*.ini`/`*.json` domains, accounts found,
  contact/identity/key/settings volumes per user);
- **Run the import**: idempotent import - missing domains, contacts (per-account
  books + global + PDO DSN declared in `application.ini`, email frequencies and
  types preserved, deleted contacts excluded), identities (default = empty
  `Id`), known user settings (messages per page, refresh, contact auto-save,
  system folders, theme if it exists) and armored PGP keys. Re-runnable without
  duplicates.

Only accounts matching an **existing user** are imported; the others are listed
in the report. Passwords of additional accounts are not recoverable (encrypted
by the source webmail) - to be recreated manually. Sieve filters live on the
mail server: nothing to import.

On the user side, the *Settings > Contacts* tab also offers an **import**
`.vcf`/`.csv`/`.sqlite` (`AddressBook.sqlite` book from RainLoop/SnappyMail - in
a multi-user book only the account's contacts are taken) and an **export**
`.vcf`, covering any other book, including a manual SnappyMail export.

### Themes

- **default** theme (writes `user_defaults.theme`);
- **allowance** per theme (`allowed`): a user can only pick an allowed theme
  (the default always stays selectable);
- theme **configuration** modal (same types + `color`);
- display of the inheritance chain (`extends`).

See [themes.md](themes.md).

### About

Version (`APP_VERSION`), PHP/SAPI/limits, database driver and size, paths,
loaded extensions. A link to the official website is shown in the
navbar. The header card also embeds **updates** via GitHub Releases (check,
download, apply, restore):

- *Check* > *Download* > *Apply* > automatic migration finalization; states
  kept in `storage/update/state.json`;
- on extraction, `files.sha256` (per-file package manifest) is verified, the
  package prerequisites (`APP_REQ_PHP`/`APP_REQ_FROM` in its `App.php`: PHP
  version, minimum source version) are checked, and locally modified files are
  reported before applying;
- during the copy, the user API answers 503 (`storage/update/applying` flag,
  maintenance page on the web side - expires after 15 min on failure);
- each overwritten file is backed up to `storage/update/backup-<ts>` with
  `manifest.json` (version, `schema_version`, lists); under SQLite the database
  (`webmail.sqlite` + `db/users/*.sqlite`) is copied after a WAL checkpoint -
  under MariaDB, external backup (`mysqldump`) remains the admin's
  responsibility (warning before applying);
- *Restore* (rollback): restores files from the last backup, deletes created
  files and copies back the SQLite databases; under MariaDB, refused if the
  schema has moved forward (restore the dump then confirm forcing);
- the "schema newer than code" banner reports a file rollback without database
  restore;
- *Reset* action to abandon a downloaded/extracted package.

> The official repository is locked in `GitHubClient::REPO` and **public**: the
> check works with no configuration or token (it is disabled only while the
> constant is not set). `update.channel` stays editable here;
> `update.repo` is set manually in `config.php`.

## 3. Writing `config.php`

The admin writes `config.php` **per section**, atomically (temporary file +
`rename`, `chmod 0600`), preserving unprovided keys. If `app/config/` is not
writable, the interface says so (`saved_readonly`) and changes are shown but
not persisted.

## 4. Testing the connection

In *Domains*, **Test connection** validates the protocol of the modal's active
tab (IMAP, SMTP or Sieve - other tabs test IMAP), and **Detect** fills the
fields without saving. Detection (`DomainDetector`) works independently of
`autodetect.enabled`. Manually saving a domain clears the "auto-detected"
marker (`options.detected`).
