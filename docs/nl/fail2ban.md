# Fail2ban

*Vertaald uit het Frans - de Franse versie is leidend.*

MXWebMail kan een toegewijd authenticatielog schrijven, bedoeld om door
[fail2ban](https://github.com/fail2ban/fail2ban) te worden gelezen om IP's te
bannen die inlogfouten opstapelen. Deze pagina beschrijft het activeren van
het log en een volledige fail2ban-integratie (filter, jail, rotatie).

## Activering

Drie sleutels van `app/config/config.php` (referentie:
[configuration.md](configuration.md#authenticatielog-fail2ban-stijl)), ook
bewerkbaar in de admin, sectie *Logs*:

| Sleutel | Standaard | Rol |
|---|---|---|
| `auth_logging` | `false` | Activeert het log. |
| `auth_logging_filename` | `auth.log` | Bestand in `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Regelindeling. |

Met het standaardformaat ziet een regel er zo uit:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` is `AUTH OK`, `AUTH FAILED (<http-code>)` of
  `AUTH FAILED (totp)` (foute TOTP-code ingevoerd - de eerste
  tweedefactor-aanvraag is geen fout en logt niets).
- Pogingen op de **admin-login** worden gedekt: de login krijgt het prefix
  `admin:` (bijv. `admin:admintest`). Een geldige IMAP-login zonder
  admin-rechten wordt gelogd als `AUTH FAILED (403)`.
- Al door de interne rate-limit geblokkeerde pogingen worden gelogd als
  `AUTH FAILED (429)` - ze tellen dus ook mee voor fail2ban (recidive).
- Regeleinden worden geneutraliseerd in de login en het resultaat: een
  vervalste entry kan geen valse regels in het bestand injecteren.

## Filter

`/etc/fail2ban/filter.d/mxwebmail.conf` (voor het standaardformaat):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Als `auth_logging_format` is aangepast, pas `failregex` aan (en `datepattern`
als `{date:FMT}` wijzigt - fail2ban vereist een herkenbare timestamp aan het
begin van de regel of identificeerbaar via `datepattern`).

Controleer dat de filter op het echte bestand matcht:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (of `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Daarna `fail2ban-client reload` en `fail2ban-client status mxwebmail`.

## Aandachtspunten

- **Tijdzone**: fail2ban vergelijkt de timestamps van regels met de
  systeemtijd. Laat `logs.time_zone` leeg (= tijdzone van de server) of stel
  hem op **dezelfde** zone in als het systeem dat fail2ban draait - een
  afwijking vertekent `findtime` en de ban.
- **Reverse proxy**: zonder ingevuld `trusted_proxies` bevat het log het IP
  van de proxy - fail2ban zou dan de proxy bannen en de toegang voor **alle**
  gebruikers afsnijden. Declareer de vertrouwde proxies en controleer dat het
  gelogde IP dat van de client is.
- **Rotatie**: het bestand groeit onbeperkt. `logrotate`-voorbeeld:

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

- **Aanvulling, geen vervanging**: fail2ban werkt op netwerk niveau; de
  interne rate-limit (`login_max_attempts`, `login_lock_seconds` - zie
  [securite.md](securite.md#rate-limit-van-de-login)) blijft de eerste
  verdedigingslinie van de applicatie. Beiden zijn onafhankelijk en
  cumuleerbaar.
