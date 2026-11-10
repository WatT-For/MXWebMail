# Fail2ban

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

MXWebMail kann ein dediziertes Authentifizierungsprotokoll schreiben, das dafür
gedacht ist, von [fail2ban](https://github.com/fail2ban/fail2ban) gelesen zu
werden, um IPs zu sperren, die Anmeldefehler häufen. Diese Seite beschreibt die
Aktivierung des Protokolls und eine vollständige fail2ban-Integration (Filter,
Jail, Rotation).

## Aktivierung

Drei Schlüssel von `app/config/config.php` (Referenz:
[configuration.md](configuration.md#authentifizierungsprotokoll-fail2ban-stil)),
ebenfalls im Admin editierbar, Bereich *Logs*:

| Schlüssel | Standard | Zweck |
|---|---|---|
| `auth_logging` | `false` | Aktiviert das Protokoll. |
| `auth_logging_filename` | `auth.log` | Datei in `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Zeilenformat. |

Mit dem Standardformat sieht eine Zeile so aus:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` ist `AUTH OK`, `AUTH FAILED (<http-Code>)` oder `AUTH FAILED
  (totp)` (falscher TOTP-Code eingegeben - die anfängliche
  Zweitfaktor-Aufforderung ist kein Fehler und protokolliert nichts).
- Versuche auf den **Admin-Login** werden erfasst: der Login wird mit
  `admin:` präfixiert (z. B. `admin:admintest`). Ein gültiger IMAP-Login ohne
  Admin-Rechte wird als `AUTH FAILED (403)` protokolliert.
- Bereits vom internen Rate-Limit blockierte Versuche werden als
  `AUTH FAILED (429)` protokolliert - sie zählen also auch für fail2ban
  (Wiederholung).
- Zeilenumbrüche werden im Login und im Ergebnis neutralisiert: ein gefälschter
  Eintrag kann keine falschen Zeilen in die Datei einschleusen.

## Filter

`/etc/fail2ban/filter.d/mxwebmail.conf` (für das Standardformat):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Ist `auth_logging_format` angepasst, `failregex` anpassen (und `datepattern`,
wenn sich `{date:FMT}` ändert - fail2ban verlangt einen erkennbaren Zeitstempel
am Zeilenanfang oder identifizierbar via `datepattern`).

Prüfen, dass der Filter auf die reale Datei passt:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (oder `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Dann `fail2ban-client reload` und `fail2ban-client status mxwebmail`.

## Punkte zur Vorsicht

- **Zeitzone**: fail2ban vergleicht die Zeitstempel der Zeilen mit der
  Systemzeit. `logs.time_zone` leer lassen (= Zeitzone des Servers) oder auf
  dieselbe Zeitzone wie das System einstellen, das fail2ban ausführt - ein
  Versatz verfälscht `findtime` und die Sperre.
- **Reverse Proxy**: ohne eingetragenes `trusted_proxies` enthält das
  Protokoll die IP des Proxys - fail2ban würde dann den Proxy sperren und den
  Zugang für **alle** Benutzer abschneiden. Die vertrauenswürdigen Proxys
  deklarieren und prüfen, dass die protokollierte IP die des Clients ist.
- **Rotation**: die Datei wächst unbegrenzt. `logrotate`-Beispiel:

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

- **Ergänzung, kein Ersatz**: fail2ban wirkt auf Netzwerkebene; das interne
  Rate-Limit (`login_max_attempts`, `login_lock_seconds` - siehe
  [securite.md](securite.md#rate-limit-beim-login)) bleibt die erste
  Verteidigungslinie der Anwendung. Beide sind unabhängig und kumulierbar.
