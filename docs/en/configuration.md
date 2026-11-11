# Configuration reference

*Translated from French - the French version is authoritative.*

The `app/config/config.php` file only defines the keys that **override** default
values (`app/config/config.example.php`, loaded by `Config.php`). Copy
`config.example.php` to `config.php` then adapt. The admin panel edits most of
these keys per section; only a few require manual editing (`update.*` mainly,
but also `import_max_size`, `smime_ca_path`, `logs.max_size` and `logs.keep`).

Associative arrays are deep-merged; lists are replaced.

## Storage

| Key | Default | Role |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) or full PDO DSN (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | PDO user (MariaDB). |
| `db_pass` | `''` | PDO password (MariaDB). |

## Application

| Key | Default | Role |
|---|---|---|
| `app_name` | `MXWebMail` | Display name (title, PWA, TOTP). |
| `favicon` | `''` | Favicon file uploaded to `storage/branding`, served via `/favicon.ico`; empty = default icon. |
| `website_url` | `https://mxrelay.fr/webmail/` | Official website link (admin button); empty = hidden. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | "Sponsor" link to the donation page (admin button + webmail settings); empty = hidden. |
| `debug` | `false` | Returns internal errors to API clients (never in production). |
| `language` | `auto` | `auto` = browser's `Accept-Language`; otherwise code of an installed language (`fr`, `en`…). Final fallback `en`. |

## Domains

| Key | Default | Role |
|---|---|---|
| `default_domain` | `auto` | Suffix of a login typed without `@domain`: `auto` = HTTP host (without `www.` or port), `''` = none, or a fixed domain. |
| `restrict_domains` | `true` | Only named domains of the `domains` table are accepted at login (neither `*` nor detection applies). |
| `autodetect.enabled` | `false` | Automatic detection of an unknown domain's config at login. |
| `autodetect.use_ispdb` | `false` | Fallback to the Mozilla database (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Port probing of `imap.`/`mail.`/`smtp.`/`sieve.<domain>`. |

IMAP/SMTP/Sieve servers are configured **per domain** in the admin (see
[domaines.md](domaines.md)). The `*` domain serves as catch-all parameters.

## Security

| Key | Default | Role |
|---|---|---|
| `admin_path` | `admin` | URL segment of the admin panel. |
| `admin_user` | `''` | Local admin account; empty = fallback to an `is_admin` user verified via IMAP. |
| `admin_password_hash` | `''` | `password_hash('password', PASSWORD_DEFAULT)` of the local admin account. |
| `admin_totp_secret` | `''` | TOTP secret (base32) of the local admin account, written by the `two-factor-auth` plugin; empty = disabled. Not required if the plugin is disabled. |
| `login_max_attempts` | `5` | Login attempts before temporary lockout. |
| `login_lock_seconds` | `300` | Lockout duration (seconds). |
| `admin_ip_allowlist` | `''` | IPs/CIDRs (CSV) allowed on the admin; empty = all. |
| `session_lifetime` | `0` | Session duration in hours; `0` = session cookie. |
| `remember_days` | `30` | Lifetime of "Remember me" tokens in days (1-365). |
| `maintenance` | `false` | Maintenance mode: `/` serves a dedicated page in 503 (all publics, session preserved) and the user `/api/*` answers 503 - `/api/admin/*` and `/api/ping` stay active. Logged-in users switch to the maintenance page at their next API request; it reloads by itself at the end. |
| `force_https` | `false` | Redirects HTTP > HTTPS (301). |
| `hsts` | `false` | Emits `Strict-Transport-Security` when the request is HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (default, required for the OAuth2 return) / `Strict` (breaks the OAuth2 flow) / `None` (requires HTTPS). |
| `csp_extra` | `''` | Additional CSP directives (e.g. `img-src https:`). |
| `trusted_proxies` | `''` | IPs/CIDRs (CSV) of trusted reverse proxies. Required to make `X-Forwarded-*` reliable (rate-limit, allowlist, logs). |
| `secfetch_allow` | `''` | Additional `Sec-Fetch-*` rules (e.g. `site=same-origin;mode=cors`). |

## Updates

The official repository is locked in `GitHubClient::REPO` (`WatT-For/MXWebMail`)
and **public**: checking and downloading work without a token.
This section - shared by the core updater and the plugin catalog (read in the
repo at ref `update.plugins_ref`, each downloaded file verified by git blob
sha1) - is not editable via the admin (except `channel`).

| Key | Default | Role |
|---|---|---|
| `update.repo` | `''` | Dev fallback if `GitHubClient::REPO` is empty (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (the only setting exposed in the admin). |
| `update.manifest_url` | `''` | Dev: release-format JSON manifest, bypasses the GitHub API. |
| `update.plugins_ref` | `'release'` | Git ref sourcing the plugin catalog: `'release'` (tag of the latest release), `'main'` (branch - pushing `plugin.json` publishes the update) or any literal ref. |
| `update.plugins_local` | `''` | Dev/tests: absolute path of a plugin folder - catalog + install from disk, offline and without remote verification. Takes precedence over `plugins_ref`. |

## Features

| Key | Default | Role |
|---|---|---|
| `attachment_max_size` | `25` | Max MB per file. Must be ≤ `attachments_max_total` (beyond that, the limit has no effect: the upload already bounds to the `min` of the two - the admin refuses the inconsistent save). |
| `attachments_max_total` | `25` | Cumulative MB per message. |
| `allow_additional_accounts` | `true` | The user can add/manage secondary IMAP accounts (Settings > Accounts). |
| `allow_additional_identities` | `true` | The user can create additional identities. |
| `popup_identity` | `false` | Opens the identity setting at first login if the name is empty. |
| `dangerous_actions` | `true` | Allows emptying/deleting a folder, deleting messages (including moving to trash). |
| `contacts_enable` | `true` | Address book + autocompletion. |
| `contacts_suggestions_limit` | `20` | Max number of autocompletion suggestions (1-100). |
| `import_max_size` | `100` | Max size of an imported `.mbox` (MB) - `.eml` keeps the `attachment_max_size` ceiling. Not editable in the admin. |
| `smime_ca_path` | `''` | CA path (PEM file) for S/MIME trust; empty = crypto signature verified only (`PKCS7_NOVERIFY`). Not editable in the admin. |

## `user_defaults` - user setting defaults

Overridable by the user. The keys writable via `/api/settings` are these plus
those declared by plugins (`settings.allowed` hook).

| Key | Default | Role |
|---|---|---|
| `messages_per_page` | `50` | Messages per page (5-200). |
| `list_sort` | `REVERSE DATE` | List sorting (whitelisted IMAP SORT criterion). |
| `view_images` | `ask` | External images: `always` / `ask` / `match` (known sender). |
| `msg_default_action` | `reply` | Default reply button: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Seconds before marking an opened message read (0 = immediate). |
| `compose_autosave` | `60` | Seconds between automatic draft saves (0 = never). |
| `mdn_mode` | `ask` | Read receipts: `ask` (request banner) / `never` (hidden). |
| `autologout` | `30` | Idle minutes before logout (0 = never). Does not revoke the "Remember me" token: the session is silently restored on the next visit. |
| `list_refresh` | `5` | Minutes between automatic refreshes (0 = never). |
| `notify_new_mail` | `false` | Browser notification on new mail. |
| `contacts_autosave` | `true` | Auto-save the recipient to contacts. |
| `folder_pane_width` | `240` | Folder column width (px, 140-600). |
| `message_pane_width` | `340` | Message list column width (px, 200-800). |
| `layout` | `horizontal` | Reading layout: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Message list height in vertical mode (px, 120-1200). |
| `compose_modal_width` | `0` | Compose modal width (px, 0 = Bootstrap default). |
| `compose_modal_height` | `0` | Compose modal height (px, 0 = auto). |
| `identity_modal_width` | `0` | Identity modal width (px, 0 = Bootstrap default). |
| `identity_modal_height` | `0` | Identity modal height (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Empty the trash on logout. |
| `empty_junk_on_logout` | `false` | Empty the spam folder on logout. |
| `list_threads` | `false` | Group messages into conversations (IMAP THREAD). |
| `after_delete` | `next` | After deleting/moving the displayed mail: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Mail view keyboard shortcuts (help via the `?` key). |
| `theme` | `default` | Interface template (folder `app/templates/<name>`). |

## TLS (outgoing IMAP/SMTP/Sieve connections)

| Key | Default | Role |
|---|---|---|
| `ssl.verify_certificate` | `true` | Verify the certificate (`false` = self-signed cert). |
| `ssl.allow_self_signed` | `false` | Accept self-signed certificates. |
| `ssl.cafile` / `ssl.capath` | `''` | Custom CA (absolute path / folder). |
| `ssl.disable_compression` | `true` | Disables TLS compression. |
| `ssl.security_level` | `1` | OpenSSL security level (0-5). |
| `ssl.local_cert` | `''` | Optional client certificate. |

## External image proxy

| Key | Default | Role |
|---|---|---|
| `image_proxy` | `true` | Serve remote images via `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Allowed domains (CSV, `*.domain` ok); empty = all (private IPs always blocked). |

## HTTP - compression & browser cache

Editable in the admin, *Performance > HTTP* section.

| Key | Default | Role |
|---|---|---|
| `http.gzip` | `true` | Compresses text/JSON responses in PHP (gzip) - also works without `mod_deflate`. Ignored if `zlib.output_compression` is active. **Behind Apache/nginx: `false` advised** - the server compresses as a filter (streaming) and PHP avoids an in-memory `gzencode`. Keep `true` for `php -S` (dev, no `mod_deflate`). |
| `http.gzip_level` | `6` | Compression level 1-9. |
| `http.gzip_min_size` | `1024` | Minimum body size (bytes) to compress. |
| `http.etag` | `true` | ETag/Last-Modified + 304 responses on PHP-served assets (theme, plugins, favicon). |
| `http.assets_ttl` | `86400` | `max-age` of theme/plugin assets (`/assets/theme|plugin/*`). `0` = systematic revalidation. With `?v=` (mtime of the served file), `immutable` is emitted. |
| `http.favicon_ttl` | `3600` | `max-age` of `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (private) of images via `/api/proxy` - also serves as TTL for the disk cache (`storage/cache/proxy`). `0` = no cache (re-fetch + SSRF revalidation on each display). Purge managed by `cache.gc_hours`: keep `proxy_ttl ≤ cache.gc_hours`. |

Private/API responses never get public cache; HTML is `no-cache`.

**Production**: enable **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ the number of PHP files - the code invalidates
its own caches via `opcache_invalidate`, `opcache.validate_timestamps` can stay
on). Application caches (`classmap.php`, plugin manifests, i18n dictionaries,
`themedata.php`) are managed by the code - no manual invalidation needed.

## Application cache (file)

Editable in the admin, *Performance > Server cache* section.

| Key | Default | Role |
|---|---|---|
| `cache.enabled` | `true` | Enables the `app/storage/cache` file cache (IMAP message lists indexed on the folder's ETag, DNS, domain detection, update check, folder GC). Affects neither the autoloader (`classmap.php`) nor `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL of the IMAP/SMTP negative detection cache (s). |
| `cache.update_ttl` | `3600` | TTL of the update check result (s). |
| `cache.gc_hours` | `168` | Purge interval of expired cache files (h); `0` = never. |

## MailSo logs (IMAP/SMTP/Sieve protocols)

| Key | Default | Role |
|---|---|---|
| `logs.enabled` | `true` | Writes `storage/logs/mail.log` (secrets masked). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` to trace the protocol. |
| `logs.syslog` | `false` | Duplicate to `syslog()` if file logging is off. |
| `logs.time_zone` | `''` | Timezone of timestamps (e.g. `Europe/Paris`); empty = server. |
| `logs.max_size` | `5` | Rotation beyond N MiB - applies to `mail.log`, `app.log` and the auth log. |
| `logs.keep` | `3` | Rotations kept (`app.1.log` … `app.{keep}.log`, cap 20). |

## Authentication log (fail2ban-style)

| Key | Default | Role |
|---|---|---|
| `auth_logging` | `false` | Enables the dedicated authentication log. |
| `auth_logging_filename` | `auth.log` | File inside `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholders: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` is `AUTH OK`, `AUTH FAILED (<http code>)` or `AUTH FAILED (totp)`.
The log covers webmail login and admin login (login prefixed `admin:`). For the
fail2ban wiring (filter, jail, logrotate), see [fail2ban.md](fail2ban.md).

## Labs / compatibility

| Key | Default | Role |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Show raw SMTP errors to the client (diagnostics, may reveal internal details). |
