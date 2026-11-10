# Fail2ban

*Tradotto dal francese - la versione francese fa fede.*

MXWebMail può scrivere un log di autenticazione dedicato, pensato per essere
letto da [fail2ban](https://github.com/fail2ban/fail2ban) per bannare gli IP
che moltiplicano i fallimenti di accesso. Questa pagina descrive l'attivazione
del log e un'integrazione fail2ban completa (filtro, jail, rotazione).

## Attivazione

Tre chiavi di `app/config/config.php` (riferimento:
[configuration.md](configuration.md#log-di-autenticazione-stile-fail2ban)),
modificabili anche nell'admin, sezione *Log*:

| Chiave | Predefinito | Ruolo |
|---|---|---|
| `auth_logging` | `false` | Attiva il log. |
| `auth_logging_filename` | `auth.log` | File in `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Formato di riga. |

Con il formato predefinito, una riga assomiglia a:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` vale `AUTH OK`, `AUTH FAILED (<codice http>)` o
  `AUTH FAILED (totp)` (codice TOTP errato inserito - la richiesta iniziale di
  secondo fattore non è un fallimento e non registra nulla).
- I tentativi sul **login admin** sono coperti: il login è prefissato
  `admin:` (es. `admin:admintest`). Un login IMAP valido ma non admin viene
  registrato `AUTH FAILED (403)`.
- I tentativi già bloccati dal rate-limit interno sono registrati
  `AUTH FAILED (429)` - quindi contano anche per fail2ban (recidiva).
- Gli a capo sono neutralizzati nel login e nel risultato: una voce falsificata
  non può iniettare righe false nel file.

## Filtro

`/etc/fail2ban/filter.d/mxwebmail.conf` (per il formato predefinito):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Se `auth_logging_format` è personalizzato, adattare `failregex` (e
`datepattern` se `{date:FMT}` cambia - fail2ban richiede un timestamp
riconoscibile a inizio riga o identificabile via `datepattern`).

Verificate che il filtro corrisponda al file reale:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (o `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Poi `fail2ban-client reload` e `fail2ban-client status mxwebmail`.

## Punti di attenzione

- **Fuso orario**: fail2ban confronta il timestamp delle righe con l'ora di
  sistema. Lasciate `logs.time_zone` vuoto (= fuso del server) o impostatelo
  sullo **stesso** fuso del sistema che esegue fail2ban - uno scostamento
  falsifica `findtime` e il ban.
- **Reverse proxy**: senza `trusted_proxies` compilato, il log contiene l'IP
  del proxy - fail2ban bannerebbe il proxy e taglierebbe l'accesso a **tutti**
  gli utenti. Dichiarate i proxy fidati e verificate che l'IP registrato sia
  quello del client.
- **Rotazione**: il file cresce senza limite. Esempio `logrotate`:

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

- **Complemento, non sostituzione**: fail2ban agisce a livello di rete; il
  rate-limit interno (`login_max_attempts`, `login_lock_seconds` - vedi
  [securite.md](securite.md#rate-limit-del-login)) resta la prima linea di
  difesa dell'applicazione. I due sono indipendenti e cumulabili.
