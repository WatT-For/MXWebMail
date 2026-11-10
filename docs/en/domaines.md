# Domains and mail servers

*Translated from French - the French version is authoritative.*

IMAP/SMTP/Sieve servers are configured **per domain** in the admin panel
(*Domains*). Each domain describes how to reach the mail server of its users.

## 1. Fields of a domain

| Field | Value |
|---|---|
| `name` | Domain name (e.g. `example.com`), or `*` for the catch-all. |
| `imap_host` | IMAP server host. |
| `imap_port` | IMAP port (default `993`). |
| `imap_encryption` | `ssl` (default), `tls` (STARTTLS) or `none`. |
| `smtp_host` | SMTP server host (sending). |
| `smtp_port` | SMTP port (default `465`). |
| `smtp_encryption` | `ssl` (default), `tls` or `none`. |
| `sieve_host` | ManageSieve host (optional). |
| `sieve_port` | Sieve port (default `4190`). |
| `active` | Enable/disable the domain. |

## 2. Advanced options (`options`, JSON)

The `options` field accepts a JSON object with fine-grained per-domain
settings:

- `whitelist` - list of addresses allowed for this domain;
- `secondary_only` - the domain can only be used as a **secondary account**
  (account addition from an already logged-in user); primary login is refused
  with "Domain reserved for secondary accounts";
- `oauth` - OAuth2 provider of the domain (`google`, `microsoft`, `yahoo`):
  secondary accounts are added through provider consent (popup), never by
  password (see §8);
- `imap.shortLogin` - truncate the login;
- `imap.lowerLogin` - force the login to lowercase;
- `imap.timeout` - IMAP connection timeout;
- `imap.disabled_capabilities` - IMAP capabilities to disable (buggy servers).
  Example: `ESORT` for a server advertising it without implementing the
  RFC 5267 return options (`RETURN (PARTIAL ...)`) - the webmail already
  detects that case and switches to classic sorting by itself; the key is only
  useful when detection does not apply (cache disabled);
- `imap.body_text_limit` - max size (bytes) of text bodies fetched when opening
  a message (default `0` = unlimited; a ~256 K ceiling saves fetching very long
  newsletters);
- `imap.message_list_limit` - beyond this (default `10000` messages in the
  folder), MailSo avoids server-side SORT and caches the UID list in the
  background;
- `smtp.useAuth` - force/disable SMTP authentication;
- `smtp.timeout` - SMTP connection timeout;
- `sieve.encryption` - encryption of the Sieve connection.

## 3. `*` domain (catch-all)

The `*` domain provides the **default parameters** applied to any undeclared
domain - but only when `restrict_domains = false`: under `restrict_domains`,
`*` is ignored and cannot allow a primary login. It is the simplest way to
serve several domains behind the same mail server.

## 4. Domain resolution at login

At login, the domain is determined as follows:

1. If the user types a login with `@domain`, that domain is used.
2. Otherwise `default_domain` applies: `auto` (default) = HTTP host without
   `www.` or port; a fixed name; or `''` (none).
3. If `restrict_domains = true` (default), only **named** domains present in
   the table are accepted (`*` and detection are ignored) - **so create at
   least one domain before any login**.

## 5. Automatic detection

`autodetect.*` (disabled by default) discovers the configuration of an unknown
domain at login time:

- `enabled`: enables detection;
- `use_ispdb`: queries the Mozilla database (`autoconfig.thunderbird.net`);
- `probe`: probes `imap.`/`mail.`/`smtp.<domain>`.

Detection order is: DNS SRV > Mozilla autoconfig > MX > port probing.

Sieve is detected whatever the source: `_sieve._tcp` (SRV), otherwise probing
port **4190** on `sieve.<domain>` (if `probe` is active or via the *Detect*
button) then on the found IMAP host - Dovecot almost always serves ManageSieve
on the same host. Encryption does not need configuring: the connection switches
to STARTTLS automatically when the server advertises it.

The **Detect** button of the *Domains* page runs the same detection on demand,
independently of `autodetect.enabled`, and fills the domain fields.

> The `/setup/` installer also attempts this detection on the vhost's host (and
> its parents) to create the first domain automatically.

## 6. Testing

The **Test connection** button validates the protocol of the modal's active tab
(IMAP, SMTP or Sieve; other tabs test IMAP). Use it after each change to check
credentials, ports and encryptions before letting users log in.

## 7. Common case: a single mail server

For a single server (e.g. Postfix + Dovecot on `mail.example.com` serving
`example.com`):

1. Create the `example.com` domain with `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, and optionally `sieve_host`.
2. Keep `default_domain = auto` and `restrict_domains = true`.
3. Users log in with `user@example.com` or just `user` (the domain is inferred
   from the HTTP host).

Multi-domain variant: a `*` domain with `restrict_domains = false` acts as a
catch-all - any undeclared domain inherits its parameters (see §3; beware,
primary login then stays possible on every domain - `secondary_only` can deny
it, see §8).

## 8. Public domains as secondary accounts (Gmail, Outlook, Yahoo)

Gmail, Outlook.com and Yahoo no longer accept plain-password IMAP
authentication - adding these accounts goes through **OAuth2** (consent in a
popup, then XOAUTH2/OAUTHBEARER for IMAP and SMTP).

OAuth2 app credentials (client ID, secret, Microsoft tenant) are **specific to
each user**: the admin registers no app - he only allows the provider on the
domain. Each user creates their own application in the provider's developer
console.

### Configuration (admin)

These three domains are **pre-created at installation** (secondary account +
OAuth provider already set). If you deleted them, recreate them like this:

1. **Create the domain** (*Domains > Add* or *Detect* button):
   `imap.gmail.com:993` / `smtp.gmail.com:465` for Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` for Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` for Yahoo.
2. Tick **Secondary account only** (primary login refused) and choose the
   provider in **OAuth2 Auth**.

### App registration (user)

On the first addition of an account of the domain, the webmail asks for the app
credentials and stores them for the user - manageable afterwards in *Settings >
OAuth2 Apps* (one app per provider, reused for all accounts of that provider).
Redirect URI to declare in the console: `<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > Web OAuth client,
  scope `https://mail.google.com/`): *restricted* scope - an app in "Testing"
  mode is limited to ~100 test users and its refresh tokens expire after ~7
  days; production requires Google verification.
- **Microsoft** (Entra > App registrations, `signInAudience` including personal
  accounts): delegated permissions "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, implicit `offline_access` scope.
  Possible "unverified publisher" banner at consent (non-blocking).
- **Yahoo** (Developer Console > OpenID Connect + Mail API app, scopes
  `openid`, `mail-r`, `mail-w`): Mail API access requires **prior approval by
  Yahoo** (request form + manual review) and redirect URI propagation can take
  ~24 h.

### How it works

- The user adds the account from the accounts menu or *Settings > Accounts*:
  entering the address > (first time: entering app credentials) > consent popup
  > the account appears with a provider badge. *Settings > Accounts* also
  allows switching, updating credentials, promoting to primary and deleting.
- A secondary account can be **promoted to primary** (star in *Settings >
  Accounts*) - the domain's `secondary_only` flag only blocks the primary
  *login*, not this promotion.
- The *refresh token* is stored encrypted (`accounts.imap_pass`); each
  IMAP/SMTP connection exchanges an ephemeral access token. Microsoft and Yahoo
  **reissue a refresh token on each use**: it is replaced automatically in the
  database.
- The app secret is encrypted (`oauth_apps.secret`, same mechanism as
  passwords) and never sent back to the interface - "empty = unchanged" when
  editing.
- **Editing or deleting an app** breaks the associated accounts: the refresh
  token stays bound to the original client_id - delete then re-add the account.
- No ManageSieve at these providers: Sieve filters are unavailable on OAuth
  accounts.

Combining `secondary_only` on the `*` domain with normal internal domains
reserves primary login for listed domains while leaving secondary accounts
open everywhere - useful without `restrict_domains`.
