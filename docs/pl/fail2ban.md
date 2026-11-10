# Fail2ban

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

MXWebMail może pisać dedykowany dziennik uwierzytelniania, przeznaczony do
odczytu przez [fail2ban](https://github.com/fail2ban/fail2ban) w celu
banowania IP mnożących nieudane logowania. Ta strona opisuje włączenie
dziennika i pełną integrację fail2ban (filtr, jail, rotacja).

## Włączenie

Trzy klucze `app/config/config.php` (referencja:
[configuration.md](configuration.md#dziennik-uwierzytelniania-styl-fail2ban)),
edytowalne również w adminie, sekcja *Logi*:

| Klucz | Domyślnie | Rola |
|---|---|---|
| `auth_logging` | `false` | Włącza dziennik. |
| `auth_logging_filename` | `auth.log` | Plik w `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Format wiersza. |

Przy domyślnym formacie wiersz wygląda tak:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` przyjmuje `AUTH OK`, `AUTH FAILED (<kod http>)` lub
  `AUTH FAILED (totp)` (wprowadzono błędny kod TOTP - początkowa prośba o
  drugi czynnik nie jest niepowodzeniem i nic nie loguje).
- Próby na **login admin** są objęte: login dostaje prefiks `admin:`
  (np. `admin:admintest`). Prawidłowy login IMAP bez praw admina jest
  logowany jako `AUTH FAILED (403)`.
- Próby już zablokowane przez wewnętrzny rate-limit są logowane jako
  `AUTH FAILED (429)` - liczą się więc też dla fail2ban (recydywa).
- Końce linii są neutralizowane w loginie i wyniku: sfałszowany wpis nie
  może wstrzyknąć fałszywych wierszy do pliku.

## Filtr

`/etc/fail2ban/filter.d/mxwebmail.conf` (dla domyślnego formatu):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Jeśli `auth_logging_format` jest dostosowany, dostosuj `failregex` (i
`datepattern`, jeśli `{date:FMT}` się zmienia - fail2ban wymaga rozpoznawalnego
znacznika czasu na początku wiersza lub identyfikowalnego przez
`datepattern`).

Sprawdź, że filtr pasuje do rzeczywistego pliku:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (lub `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Następnie `fail2ban-client reload` i `fail2ban-client status mxwebmail`.

## Punkty uwagi

- **Strefa czasowa**: fail2ban porównuje znaczniki czasu wierszy z czasem
  systemowym. Zostaw `logs.time_zone` puste (= strefa serwera) lub ustaw na
  **tę samą** strefę co system, na którym działa fail2ban - przesunięcie
  zafałszuje `findtime` i bana.
- **Reverse proxy**: bez wypełnionego `trusted_proxies` dziennik zawiera IP
  proxy - fail2ban zbanowałby wtedy proxy i odciął dostęp **wszystkim**
  użytkownikom. Zadeklaruj zaufane proxy i zweryfikuj, że logowane IP to
  IP klienta.
- **Rotacja**: plik rośnie bez limitu. Przykład `logrotate`:

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

- **Uzupełnienie, nie zastępstwo**: fail2ban działa na poziomie sieci;
  wewnętrzny rate-limit (`login_max_attempts`, `login_lock_seconds` - zob.
  [securite.md](securite.md#rate-limit-logowania)) pozostaje pierwszą linią
  obrony aplikacji. Oba są niezależne i kumulowalne.
