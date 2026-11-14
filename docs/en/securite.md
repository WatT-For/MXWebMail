# Security

*Translated from French - the French version is authoritative.*

## 1. Built-in security model

### "Remember me" (persistent tokens)

The "Remember me" checkbox issues a `mxwm_remember` cookie
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, lifetime
`remember_days` - 30 days by default). The `auth_tokens` table only stores the
**SHA-256 hash** of the validator; it is **rotated on each use** (a stolen
cookie already presented is detectable and invalidated). The restore
re-decrypts the IMAP password (`CredentialCrypt`) and re-checks the
credentials - a failure deletes the token. Each token records the **IP at
issuance** (`ip`), the **last known IP** (`last_ip`, on each restore) and the
User-Agent - visible in Settings > Sessions to spot an unknown session. Tokens
are listed and revocable there, and the current token is revoked on **manual**
logout (inactivity logout `autologout` preserves it: the session is then
silently restored on the next visit). Any new session (regular login or
restore) resumes on the last consulted account - falling back to the
default account.

### CSRF

Every POST requires a session CSRF token (`X-CSRF-Token`, form field or JSON
key); plugin APIs (`/api/plugin/…`) require it regardless of the HTTP method.
A second layer, based on the **`Sec-Fetch-*`** headers, rejects cross-site
requests except GET navigation to a document - even without user activation,
since the browser strips `Sec-Fetch-User` after each redirect (portals,
rewritten links) (`secfetch_allow` can extend these rules).

### HTTP headers

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, optional HSTS, and a
**CSP** with a per-request nonce (`script-src` includes `unsafe-eval`, required
by Knockout). Attachments served inline get `Content-Security-Policy: sandbox`.

### Credential encryption

IMAP passwords (and plugin secrets) are encrypted **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) with the `app/storage/app.key` master
key, in session and in the database. See
[base-de-donnees.md](base-de-donnees.md).

### OAuth2 secondary accounts

Public domains (Gmail, Outlook, Yahoo - see [domaines.md](domaines.md)) are
added via **authorization code + PKCE**: the anti-CSRF `state` and the verifier
are held in session, the `redirect_uri` is rebuilt server-side (host filtered
like `force_https`). Only the *refresh token* is persisted, encrypted like a
password; the ephemeral *access token* exists only in memory for the duration
of the connection (XOAUTH2/OAUTHBEARER). Refresh token rotation (Microsoft,
Yahoo) is reapplied to the database on each exchange. The `id_token` email
serves as a consistency check; the proof of ownership remains the OAuth IMAP
connection itself. The callback (`/api/oauth/callback`) only returns a
mini-page `postMessage`>`window.close()` for the consent popup.

OAuth2 app credentials are **per user** (`oauth_apps` table, entered on first
account addition or in settings): the `secret` is encrypted with the same
master key and never sent back to the interface. A deleted or edited app
invalidates the accounts that depend on it (the refresh token stays bound to
the original client_id).

### Login rate-limit

`login_max_attempts` (5) then blocking for `login_lock_seconds` (300 s). The
counter is persisted in `storage/cache/login_attempts.json` (key
`sha256(IP|login)`, purge of entries inactive > 1 h, writes under `flock`
lock). An active block is neither extended nor re-recorded by rejected attempts
(429). Also applies to admin login. A fail2ban-style authentication log is
available (`auth_logging*`, placeholders `{request:ip}`, `{imap:login}`,
`{result}`; CR/LF neutralized): it covers webmail and admin logins (login
prefixed `admin:`) as well as TOTP code failures. Full integration:
[fail2ban.md](fail2ban.md).

### Message HTML sanitization

Message bodies are sanitized in the browser (inert DOMParser + allowlist):
restricted tags and attributes (no `script`/`iframe`/`object`/`on*`…), blocking
of `javascript:`/`vbscript:`/`data:` schemes, neutralization of trapping styles
(`position:fixed`, hidden content), class prefixing. CSP is the second layer.

### Sending messages

- **From**: strict whitelist - the account address or a declared identity
  (IDN accepted in both forms); any other value is refused (403).
- **Recipients**: To/Cc/Bcc fields go through a strict parse - an invalid
  segment is not silently dropped, sending is refused (400) with the list of
  unparseable addresses.
- **CR/LF**: neutralized in the subject, threading headers (In-Reply-To,
  References), referenced folder names and MIME parameters of attachments
  (`filename=`).
- **Bcc**: the header is stripped from data sent to the SMTP server but kept in
  the "Sent items" copy (visible to the sender only).
- **Editor**: the TinyMCE iframe carries a `Content-Security-Policy` blocking
  any remote load - the content of a forwarded/pasted message cannot exfiltrate
  a read receipt when opened.
- **Temporary attachments**: user-scoped tokens, sanitized name (`basename` +
  control characters stripped), purged after 24 h.
- **SMTP errors**: the client receives a generic message; raw server responses
  are only exposed if `labs.smtp_show_server_errors` is enabled (diagnostics,
  may reveal internal details).

### OpenPGP

Encryption/decryption happens **entirely in the browser** (OpenPGP.js). The
server only stores the armored keyring; private keys are protected by their
passphrase and are never decrypted server-side. **Only the message body is
encrypted**: sending attachments is refused when encryption is enabled (they
would go out in clear).

### Image proxy

Remote images go through `/api/proxy`: `http(s)` only, optional allowlist
(wildcards), **anti-SSRF** (private/reserved IPs refused, revalidated on
**every** redirect - 3 max), timeout, bounded size. The body's real MIME is
checked against a **bitmap allowlist** (png, jpeg, gif, webp, avif, bmp, ico) -
SVG and any active type are refused - and the response carries
`Content-Security-Policy: sandbox` + `nosniff`. Logs only keep scheme+host+path
(never the query, which may contain tokens).

### IP and proxy management

`X-Forwarded-For`/`X-Forwarded-Proto` are only honored if `REMOTE_ADDR` is in
`trusted_proxies` (CIDR). HTTPS detection (`isHttps`) follows the same rule.

## 2. Hardening recommendations

- **Document root = `public/`** only; never point it at the project root. The
  private folders (`app/`, `config/`, `storage/`…) have their own protective
  `.htaccess`, but that is only a safety net.
- **Mandatory HTTPS**: enable `force_https` + `hsts` in production.
- **Behind a reverse proxy** (Nginx, HAProxy, Cloudflare…): set
  `trusted_proxies` to make rate-limit, allowlist and logs reliable; without
  it, every `X-Forwarded-*` is ignored.
- **Restrict the admin**: `admin_ip_allowlist` (IP/CIDR CSV) and a non-trivial
  `admin_path`; enable **admin TOTP** via the `two-factor-auth` plugin (the
  `admin_totp_secret` secret is only required while the plugin is enabled).
- **Local admin account**: prefer `admin_user` + `admin_password_hash` (strong
  password) over the IMAP fallback for administration.
- **Back up `app.key`** outside the webroot; never commit it.
- **`debug = false`** in production (do not expose internal errors).
- **The `/setup` wizard** is built into the core: it answers 404 once the
  `app/storage/INSTALLED` lock is in place - no install file left to delete in
  the webroot.
- Check `ssl.verify_certificate`: `false` is only acceptable for controlled
  self-signed certificates.
- In maintenance mode, `maintenance = true` serves a dedicated maintenance page
  in 503 on `/` (logged-in users are switched at their next API request,
  session preserved) and cuts the user `/api/*` (admin and `/api/ping` stay
  active).

## 3. Known limitations

- The `GitHubClient::REPO` repository is locked to `WatT-For/MXWebMail`:
  installed packages (core zip of the release, plugin files read in the repo at
  ref `update.plugins_ref` via the git API + `raw.githubusercontent.com`, each
  verified by git blob sha1) only come from that repository - no arbitrary
  configurable URL. The core package is verified at two levels: asset sha256
  sum (release `<zip>.sha256` file) then `files.sha256` (embedded per-file
  manifest, checked after extraction); the `APP_REQ_PHP`/`APP_REQ_FROM`
  constants of the package's `App.php` declare the prerequisites (PHP version,
  minimum source version) enforced before copying. The official repo is
  **public**: the API, assets and `raw` work without a token (GitHub's anonymous
  API quota is covered by the `cache.update_ttl` cache). An optional GitHub PAT
  (`Contents: Read` access to the repo) configured in `config.php` may raise the
  quota or serve a private fork via `update.repo` - assets are then served via
  the `releases/assets/<id>` endpoint (`browser_download_url` returns 404 on a
  private repo even when authenticated).
  `update.plugins_local` and `update.manifest_url` (alternative release
  manifest) are reserved for dev/tests - editable only via `config.php`, never
  via the admin API. During file copying, the `storage/update/applying` flag
  cuts the user API (503, expires after 15 min).
- Switching SQLite > MariaDB does not migrate data (blank schema).
- OpenPGP private keys are stored armored server-side (the passphrase protects
  the key itself, no extra wrapping).
