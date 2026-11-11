# Troubleshooting / FAQ

*Translated from French - the French version is authoritative.*

## Installation

**Every URL redirects to `/setup/`**
Normal: the `app/storage/INSTALLED` lock is absent. Finish the installation, or
create the file manually after writing `config.php`.

**The wizard reports "config/ not writable"**
Non-blocking warning: copy the displayed content into `app/config/config.php`
by hand, then restart the installation. Also check that `app/storage/` is
writable (blocking).

**The MariaDB connection test fails**
Check host/port/credentials, and that the user has rights to create the
database (`CREATE DATABASE`). The charset must be `utf8mb4`.

## Connection / login

**No user can log in after installation**
With `restrict_domains = true` (default), **at least one named domain** must be
in the table (the `*` wildcard is ignored for this check). The installer tries
to detect it; otherwise create it in *Domains*.

**Login refused without a reason (blocked account)**
- Too many attempts: wait `login_lock_seconds` (default 300 s), or purge
  `app/storage/cache/login_attempts.json`.
- The `black-list`/`white-list` plugins may refuse the login (404/403 or generic
  error depending on `auth_error`).
- A `disabled` user is rejected even with a valid session.

**Admin login does not work**
- Local account (`admin_user`): check `admin_password_hash` and the optional
  TOTP.
- IMAP fallback: the user must have `is_admin = 1`.
- `admin_ip_allowlist` may be blocking your IP.

## Mail servers

**Cannot connect to the IMAP/SMTP server**
Use the **Test connection** button in *Domains* (result per protocol). Frequent
causes: inconsistent port/encryption (993/ssl vs 143/tls), self-signed
certificate (see `ssl.verify_certificate`/`ssl.allow_self_signed`), or firewall.

**Self-signed certificate rejected**
`ssl.verify_certificate = false` or `ssl.allow_self_signed = true` (only use
with full knowledge of the consequences).

**The domain is not detected automatically**
`autodetect.enabled` is disabled by default. Enable it, or use the
**Detect** button on the *Domains* page.

**"Report as spam" does not filter subsequent mails**
The button moves the message to the Junk folder (or sets the `$Junk` IMAP
keyword if the server has no Junk folder) - it teaches the server nothing.
Sieve filters only apply at delivery: the *Anti-spam* template of the wizard
(Settings > Filters) files mails flagged `X-Spam-Flag: YES` by the MTA.
Automatic learning (spam/ham) requires server-side configuration - Dovecot
IMAPSieve (report-spam/ham script triggered by copying to/from Junk, which the
app's MOVE triggers) or rspamd/sa-learn.

## Security / HTTPS

**The site stays on HTTP behind a reverse proxy**
Set `trusted_proxies` (proxy IP) so that `X-Forwarded-Proto` is honored, then
enable `force_https` / `hsts`.

**The rate-limit or allowlist does not see the real IP**
Same cause: without `trusted_proxies`, `REMOTE_ADDR` is used as is.

**The favicon does not display**
The uploaded favicon must be ≤ 512 KB (ico/png/svg/jpg/webp); otherwise the
default icon remains.

## Data / logs

**Loss of `app.key`**
All stored credentials become undecryptable: users must log in again. Always
back up `app.key`.

**Where are the logs?**
`app/storage/logs/app.log` (application), `mail.log` (IMAP/SMTP/Sieve
protocols, if `logs.enabled`), and the auth log (`auth_logging`). Viewable in
the admin (*Logs*). fail2ban integration: [fail2ban.md](fail2ban.md).

**Switching to MariaDB wiped the data**
Expected behavior: changing `db_dsn` starts over on a blank schema (per-user
contact files are kept). There is no data migration tool.

## Updates

**"Failure: Not found on GitHub … private repo or missing resource"**
The official repository is locked (`GitHubClient::REPO` = `WatT-For/MXWebMail`)
and **public**: no token is required. GitHub returns 404 - not 403 - for a
missing resource or insufficient access; the message names the faulty endpoint
(`releases/latest`, `commits/…` or a `raw` path of the plugin catalog). A
persistent 404 generally means a missing resource (no release published,
unknown `update.plugins_ref`). The "private repo" case only concerns a fork
configured via `update.repo` + PAT in `config.php`.

**HTTP 404 on `…/releases/download/…` (plugin catalog or update download)**
On the public official repo, `browser_download_url` works. The case only
matters for a private fork via `update.repo`: `browser_download_url` returns
404 there even with a valid token - fixed as of 0.36.1, assets are then
downloaded via the API endpoint `releases/assets/<id>` when a PAT is
configured in `config.php`.

**The site answers 503 "update" / maintenance page during an update**
Normal: the `app/storage/update/applying` flag cuts the user API while files
are copied (never a mix of old/new code). It is removed at the end; if it
remains after a crash, it expires by itself after 15 min - or delete
`app/storage/update/applying` manually.

**"Pending migrations" after an update**
Finalization (`/api/admin/update/finalize`) did not run: *Finalize* button in
*About*, or from the CLI `php app/bin/post-update.php`. Without action,
migrations still apply on the next request (lazy migration), but it is better
to control them.

**Rolling back after an update**
*Restore* button in *About*: restores the files from
`app/storage/update/backup-<ts>` (the last 3 backups are kept), deletes
created files and copies back the saved SQLite databases. Under **MariaDB**,
migrations are not reversible: restore the database from your `mysqldump`
first, then restart the rollback (forced confirmation is offered). To start
from scratch: reinstall the original package and restore `app/storage/`
(including `app.key` and `db/`).

**"Database schema newer than the code" banner**
`meta.schema_ahead`: the installed code is older than the schema (file rollback
without database restore, or an older package deployed). Restore the database
or reapply the matching version; migrations stay suspended while the gap
exists.

## Miscellaneous

**"Resend as is" (bounce): does the recipient see the mail as spam?**
Possibly: resending breaks SPF/DMARC at the final end (the sending server no
longer matches the original sender) - normal bounce behavior, the `Resent-*`
headers document the retransmission. To keep authentication, prefer a regular
forward.

**Under Nginx, are private folders accessible?**
No, if `root` points to `public/` and the `location ~ /\.` + `^/(app|dist)`
block is present. Never point `root` to the project root.

**403 "cross_site" error on a legitimate request**
The `Sec-Fetch-*` protection rejected the request. If you integrate a
legitimate external API, add a rule in `secfetch_allow`.

**Debugging an internal error (500)**
Temporarily enable `debug = true` (never in production) to see the message,
then check `app.log`.
