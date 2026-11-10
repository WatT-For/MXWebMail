# Plugins

*Translated from French - the French version is authoritative.*

Plugins are extensions confined in `app/plugins/<name>/`, loaded when enabled
(from the admin, *Extensions* tab). They register on core **hooks**.

> **Read-only session**: GET endpoints close the PHP session early
> (`session_write_close`) to release the lock. Hooks called from a GET
> (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **must never write to
> `$_SESSION`** - those writes would be silently lost.

## 0. Distribution via the GitHub repository

Plugins are **not included** in the `mxwebmail-<ver>.zip` release zip: they are
read directly from the repository at a **configurable ref**
(`update.plugins_ref` - `'release'` = tag of the latest release by default,
`'main'` or any git ref). The catalog (`PluginPackageService`, *Extensions*
tab - uninstalled entries appear at the end of the table) is built from the
git API:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` lists files under
  `app/plugins/` with their **git blob sha1** and size;
- each plugin's `plugin.json` is read via `raw.githubusercontent.com` (pinned on
  the commit sha) - version, description, `min_app`/`max_app`;
- **Install / update**: each plugin file is downloaded via `raw`, verified
  (size + tree blob sha1), then `plugin.json` validated (matching name, semver
  version, sane `main`, `APP_VERSION` compatibility) before replacing the
  folder - an installed plugin is **disabled** by default; the old folder goes
  to `storage/update/plugins/backup-<name>-<ts>` (a single one kept, automatic
  rollback);
- **Uninstall**: permanent deletion of the folder + purge of the `plugins` row
  (configuration lost), no backup.

Publishing a plugin version = bumping `version` in its `plugin.json` and
pushing to the branch tracked by `update.plugins_ref` - no release or asset
required.

The repository is locked in `GitHubClient::REPO` (same constant as the core
updater) - no arbitrary URL is accepted. `update.channel` and the release cache
(`cache.update_ttl`) are shared with the core update check; the ref>commit is
cached at that TTL, the tree and manifests are addressed by their sha
(immutable). The static `check` requires a semver `version` in `plugin.json`.

For offline dev/tests, `update.plugins_local` (absolute path of a plugin folder
in `config.php`) serves as the source: catalog and install by disk copy, no
remote verification.

An enabled plugin can also be **restricted to the admin surface** (*User
access* column of the *Extensions* tab, `user_allowed` flag in the `plugins`
table, **unchecked by default**): it is then only loaded on the `/{admin_path}`
page, `/api/admin/*` routes and admin sessions. Elsewhere it is completely
inert (no hook, `/api/plugin/<name>/*` > 404) - including for `is_admin` users
not logged into the panel. Admin login hooks (`admin.post-login`,
`admin.totp-required`, `admin.mounts`) keep working. A plugin whose hooks
declared in the manifest are all `admin.*` hooks has no user surface: the
column then shows no switch for it (`-`).

## 1. Official catalog plugins

| Plugin | Role | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Adds `X-Originating-IP` (sender IP) to outgoing mail. | `message.before-send` |
| `anti-spoofing` | Spoofing detection in the reader: inconsistent Return-Path/Reply-To/Sender, display name imitating a brand (homoglyphs and confusables detected), lure domain (trapped subdomain, typosquat, cross-TLD), SPF/DKIM/DMARC evaluated in alignment with the sender domain (verdicts read from `Authentication-Results`, `Received-SPF` or SpamAssassin/rspamd headers) - subdomains of protected domains are legitimate. | `imap.message-headers`, `message.view` |
| `avatars` | Sender avatar: known service icons, Gravatar, domain favicon, initials. | `app.data` |
| `black-list` | Login blacklist (wildcards + exceptions) - checked on the **resolved** email (post-mapping). | `auth.login.mapped` |
| `white-list` | Login whitelist (wildcards + exceptions) - checked on the **resolved** email (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Contact sync from a CardDAV book (Nextcloud, Radicale, iCloud…) - **HTTPS + public host required**. | `app.data` |
| `change-password` | Password change (poppassd, PDO, LDAP, Mailcow drivers). | `password.change`, `app.data` |
| `color-tags` | Color labels on messages (manual + automatic rules). | `app.data`, `message.meta` |
| `ics-viewer` | Preview of `.ics` invitations/attachments in the reader **+ iTIP reply** (Accept/Maybe/Decline on `METHOD:REQUEST`, sent by SMTP to the organizer). | `app.data` |
| `imap-contacts-suggestions` | Contact suggestions from senders of an IMAP folder. | `contacts.provider` |
| `ldap-contacts` | Contact suggestions from an LDAP directory (autocompletion). | `contacts.provider` |
| `ldap-login` | LDAP resolution: uid/alias > real IMAP address before login. | `auth.login.mapping` |
| `mailbox-detect` | System folder detection and creation of missing ones. | `app.data` |
| `pwa` | Makes the webmail installable (manifest + minimal service worker, icons). | `app.head`, `app.routes` |
| `webmail-migration` | Admin import page from a **RainLoop or SnappyMail** `data` folder (server path or ZIP): domains, `AddressBook.sqlite`/PDO books, identities, settings, system folders, PGP keys - dry-run scan then idempotent import, unknown accounts skipped. | `admin.mounts` |
| `two-factor-auth` | Two-factor TOTP (Google Authenticator, Aegis…) - user accounts **and** local admin account (QR provisioning in a dedicated sub-tab of Admin > Security; required at admin login while the plugin is enabled). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Admin announcement banner (app page, optionally login page - maintenance). Plain text only. | `app.head` |
| `attachments-clamav` | ClamAV scan (`clamd`, `zINSTREAM`) of uploaded attachments and imports - rejection on detection, configurable `on_error` policy. | `attachment.upload` |
| `filelink` | Attachments too large for SMTP sent as an **expirable download link** (`/filelink/<token>` - forced attachment, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Spam/ham learning on each (un)flagging: external command (`rspamc`, `sa-learn`) or IMAP copy to a dovecot-antispam folder. | `message.spam` |
| `wkd-keys` | Automatic discovery of recipients' PGP keys: **WKD** (advanced + direct), **Autocrypt** header harvesting, optional `keys.openpgp.org` fallback - import offered at compose. | `app.data`, `message.view` |
| `seasonal-decorations` | Discreet monochrome scenery in banners by date (New Year, Easter, April Fools', Halloween, Christmas): SVG object library recomposed into `mask-image` layers via `scenes.json` ({svg, pos, size}), automatic contrasting color - adjustable windows, optional outside session, no animation. | `app.head` |
| `link-confirmation` | Confirmation modal before opening a link from a mail body (anti-phishing): real destination shown, flagging of suspicious links (text ≠ URL, literal IP, punycode, credentials in URL), trusted domains and "don't ask again for this domain". | `app.head` |
| `always-cc-bcc` | Systematic Cc or Bcc recipients on send (compliance archive), per account or all - Bcc stays invisible. | `message.before-send` |
| `report-abuse` | "Report" button (action bar): forwards checked mails as attached `.eml` to an administered abuse address, optional spam flagging. | `app.data` |
| `vcf-viewer` | Preview of `.vcf` attachments in the reader + one-click import into the address book (button integrated into the frontend when `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Per-account send quota (messages/hour, messages/day, recipients/day) - 429 rejection beyond. Webmail only. | `message.before-send` |
| `login-notify` | Alert mail to the account on login from an unknown IP (Gmail-style) - delivered unread to the inbox, per-user opt-out. | `auth.post-login`, `app.data` |
| `sso-header` | SSO via authentication proxy (trusted user header) - requires declared proxy IPs and an IMAP master password. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Admin page aggregating DMARC (rua) reports from a dedicated IMAP box - XML/gzip parsing, aggregates by domain and source IP. | `admin.mounts` |

## 2. Plugin configuration

In the admin (*Extensions* > ⚙ button), configuration is generated from the
plugin's `config.schema.json` file. Supported types: `string`, `text`,
`integer`, `number`, `password`, `bool`, `enum`, `color`, `list` - `color`
renders a native picker + a `#RRGGBB` field (empty value accepted: the field
falls back to the plugin's default). Values are stored in the `plugins` table
(`config` column, JSON).

Notable configuration details:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (default `*`), `pass_min_length` (10), `pass_min_strength`
  (70), `check_hibp` (k-anonymity), then driver-specific parameters
  (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` with placeholders `:email
  :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (`%u` placeholder), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (`%q` placeholder), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (a protected domain also covers its
  subdomains, e.g. `accountprotection.microsoft.com`), `brands` (detected in
  the display name after normalization of accents and confusable characters -
  "AppIe TV" is recognized as "Apple TV"), and the switches
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  SPF/DKIM/DMARC results are only taken into account when aligned with the
  `From` domain: a `dkim=fail` on the carrier's signature or an `spf=fail` on a
  relayed (SRS) mailfrom does not trigger an alert.
- **`two-factor-auth`** - `issuer` (default `app_name`), `label` (`email` =
  full address, `local` = part before `@`; `local` prevents TOTP apps from
  inferring a brand icon from the domain, e.g. Yahoo) and `icon_url`
  (non-standard `image=` parameter, displayed by Aegis/andOTP/FreeOTP - ignored
  by Authy; empty = the instance's public favicon). The plugin also provisions
  the local admin account's 2FA: `admin_totp_secret` secret in `config.php`
  (via the admin API `/api/admin/plugin/two-factor-auth/*`), pending in
  session, enforced at admin login via the `admin.post-login` hook. Disabling
  the plugin disables admin enforcement.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  values separated by spaces, commas or semicolons), `exceptions`, `auth_error`
  (403 "account not authorized" > generic 401). Beware the semantics inherited
  from the legacy plugin: for black-list, `exceptions` = blacklisted emails
  that **stay allowed**; for white-list, if `exceptions` is empty every email
  outside the whitelist is rejected, but if it is set **only the exception
  emails are rejected** and everything else passes - do not put "also allowed"
  addresses there (inverse expected effect).

- **`link-confirmation`** - `mode` (`all` = every external link, `suspicious` =
  suspicious links only), `whitelist` (trusted domains, one per line or
  separated by space/comma/semicolon - subdomains included), `warn_mailto`
  (also confirm `mailto:`/`tel:`, disabled by default) and `remember` ("don't
  ask again for this domain" checkbox - stored in the browser via localStorage,
  not server-side).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (targets, empty =
  inactive), `senders` (sender wildcards, `*` = all).
- **`report-abuse`** - `abuse_address` (required, empty = inactive),
  `subject_prefix`, `mark_spam` (also flags as spam via `message.spam` - feeds
  learning).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day` (0 =
  unlimited), `exempt` (wildcards of exempted accounts). Only bounds the
  webmail: a direct IMAP/SMTP client bypasses the quota.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (per-user opt-out Settings tab).
- **`sso-header`** - `header` (`X-Forwarded-User` or `REMOTE_USER`),
  `trusted_ips` (**required, empty = inert plugin**), `master_pass` (required;
  *same* mode = `master=yes` passdb), `master_user` (optional: `user*master`
  login), `allowed_domains`, `hide_form`. The instance must not be reachable
  bypassing the proxy (the header would be forgeable).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (reports IMAP
  box), `folder`, `batch_size`, `delete_processed`, `processed_folder`. The
  plugin's own IMAP connection (domain options do not apply).

Some plugins have no admin schema: their configuration is **per user** (e.g.
`carddav-contacts`, `color-tags`, `two-factor-auth` for account state).

## 3. Building a plugin

### Minimal structure

```
app/plugins/my-plugin/
├── plugin.json          # manifest
├── MyPlugin.php         # main class
├── config.schema.json   # (optional) admin configuration schema
├── langs/<lang>.php     # dictionaries (keys prefixed plugin.<name>.)
└── js/ or assets/       # (optional) assets served via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "my-plugin",
	"label": "plugin.my-plugin.label",
	"class": "MyPlugin",
	"main": "MyPlugin.php",
	"version": "0.1.0",
	"description": "plugin.my-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (optional) is the plugin's display name in Admin > Extensions
(`plugin.<name>.label` i18n key recommended, literal text accepted; the
technical `name` stays displayed alongside and serves as identifier).

`min_app`/`max_app` (optional, semver `x.y.z`) bound compatibility with
`APP_VERSION` - install or update is refused out of range, and the catalog
marks the entry *incompatible*.

### Main class

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MyPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Available hooks

- **Core**: `app.routes`, `app.head` (HTML injected into the `<head>` of
  `app.php`, `login.php` and `maintenance.php` - the `admin*.php` views do not
  emit it), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > `{js, plugin, section, tab?, label?}`
  descriptor rendered by `pluginMount` in the admin panel - without `tab`:
  inline block in the zone named `section`, e.g. `security-account`; with `tab`
  + `label`: dedicated sub-tab of the page named `section`, e.g. `section:
  'security', tab: '2fa'`),
  `admin.post-login` (`run`, emitted at local admin account login - throwing
  `TotpRequiredException` requires a second factor), `admin.totp-required`
  (`first` > boolean: shows the TOTP field on the admin login page).

Each plugin declares in `hooks` the hooks it listens to. A manifest declaring
only `admin.*` hooks marks the plugin as purely admin - the *User access*
column of the Extensions tab shows no switch for it. The core calls
`HookRegistry::run('hook', …)`; the plugin implements a method of the same name
(or an `on<Hook>` per core convention).

### Generic API

Every plugin is callable via `/api/plugin/<name>/<action>` (GET/POST) > method
`api<Action>(Request $r, ?array $user)`. The CSRF token is required on all
methods (the `api.js` client always sends it) - direct calls without the
`X-CSRF-Token` header are rejected with 403.

On the admin side, `/api/admin/plugin/<name>/<action>` (GET/POST) > method
`apiAdmin<Action>(Request $r)`: admin session (`$_SESSION['admin']`) + CSRF
required; there is no `$user` (the local admin account is not a `users` row).
Beware: `requireAdmin` closes the session for writing - to store state there
(e.g. pending secret), reopen it via `$this->app->startSession()` then
`session_write_close()`.

### User tab

To add a tab in `#/@settings/…`, provide JS served via
`/assets/plugin/<name>/<file>` and declare the tab (see the `change-password`,
`carddav-contacts`, `color-tags`, `two-factor-auth` plugins as examples).

### Translations

Keys prefixed `plugin.<name>.` in `langs/<lang>.php`;
`description`/`label`/`help` of manifests and schemas accept these keys
(pass-through otherwise). PHP side: `$this->t('key')`, JS side: `ctx.t('key')`
in `mount(element, ctx)`.
