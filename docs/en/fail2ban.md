# Fail2ban

*Translated from French - the French version is authoritative.*

MXWebMail can write a dedicated authentication log, designed to be read by
[fail2ban](https://github.com/fail2ban/fail2ban) to ban IPs that multiply
login failures. This page describes enabling the log and a complete fail2ban
integration (filter, jail, rotation).

## Enabling

Three keys of `app/config/config.php` (reference:
[configuration.md](configuration.md#authentication-log-fail2ban-style)),
also editable in the admin, *Logs* section:

| Key | Default | Role |
|---|---|---|
| `auth_logging` | `false` | Enables the log. |
| `auth_logging_filename` | `auth.log` | File inside `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Line format. |

With the default format, a line looks like:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` is `AUTH OK`, `AUTH FAILED (<http code>)` or `AUTH FAILED (totp)`
  (wrong TOTP code entered - the initial second-factor prompt is not a failure
  and logs nothing).
- Attempts on the **admin login** are covered: the login is prefixed
  `admin:` (e.g. `admin:admintest`). A valid IMAP login without admin rights is
  logged `AUTH FAILED (403)`.
- Attempts already blocked by the internal rate-limit are logged
  `AUTH FAILED (429)` - so they count for fail2ban too (repeat offense).
- Line breaks are neutralized in the login and the result: a forged entry
  cannot inject fake lines into the file.

## Filter

`/etc/fail2ban/filter.d/mxwebmail.conf` (for the default format):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

If `auth_logging_format` is customized, adapt `failregex` (and `datepattern` if
`{date:FMT}` changes - fail2ban requires a recognizable timestamp at the start
of the line or identifiable via `datepattern`).

Check that the filter matches the real file:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (or `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Then `fail2ban-client reload` and `fail2ban-client status mxwebmail`.

## Points of caution

- **Timezone**: fail2ban compares line timestamps to the system time. Leave
  `logs.time_zone` empty (= server timezone) or set it to the **same** timezone
  as the system running fail2ban - an offset skews `findtime` and the ban.
- **Reverse proxy**: without `trusted_proxies`, the log contains the proxy's
  IP - fail2ban would then ban the proxy and cut off access for **all** users.
  Declare trusted proxies and check that the logged IP is the client's.
- **Rotation**: the file grows without limit. `logrotate` example:

  ```
  /var/www/mxwebmail/app/storage/logs/auth.log {
      weekly
      rotate 8
      missingok
      notifempty
      compress
      copytruncate
  }
  ```

- **Complement, not replacement**: fail2ban acts at the network level; the
  internal rate-limit (`login_max_attempts`, `login_lock_seconds` - see
  [securite.md](securite.md#login-rate-limit)) remains the first line of
  application defense. Both are independent and cumulative.
