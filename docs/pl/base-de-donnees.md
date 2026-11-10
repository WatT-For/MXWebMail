# Baza danych

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

MXWebMail działa z **SQLite** (domyślnie) lub **MariaDB/MySQL**. Sterownik
wybiera się przez `db_dsn` w `config.php`.

## 1. Połączenie

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (tryb WAL +
  `foreign_keys`).
- W przeciwnym razie pełny DSN PDO (np.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) z
  `db_user`/`db_pass`.

Opcje PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migracje

Schemat jest **przenośny** między SQLite/MariaDB (klucze główne
autoincrementowane dopasowane do sterownika). Migracje są wersjonowane
(`meta.schema_version`) i stosowane automatycznie przy pierwszym dostępie do
bazy (`App::db()`), także podczas instalacji.

Po aktualizacji kodu, zakładka *Informacje* uruchamia **finalizację**
(`/api/admin/update/finalize`): migracje nowej wersji wykonują się w
kontekście admina, potem bazy kontaktów per użytkownik są migrowane z
kolei (baza w błędzie jest logowana i ponawiana przy następnym dostępie).
Zapasowo: `php app/bin/post-update.php` odtwarza migracje w CLI. Jeśli
schemat jest nowszy niż kod (rollback plików bez przywrócenia bazy), jest
ustawiane `meta.schema_ahead` i sygnalizowane w *Informacje* - migracje są
wtedy zawieszone.

## 3. Tabele globalne

| Tabela | Zawartość |
|---|---|
| `meta` | pary klucz/wartość (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (unikalny), `display_name`, `is_admin`, `disabled`, `session_epoch` (inkrementowany przez "Wymuś wylogowanie" - odrzuca aktywne sesje przy kolejnym żądaniu), `created_at`, `last_login_at`. |
| `accounts` | konta IMAP użytkowników: `email`, `imap_user`, `imap_pass` (zaszyfrowane), `is_default` (konto główne, zmienialne w Ustawienia > Konta), `position` (kolejność wyświetlania kont dodatkowych, zmieniana przeciągnij-i-upuść w Ustawienia > Konta), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` przechowuje wtedy zaszyfrowany *refresh token*). |
| `oauth_apps` | aplikacje OAuth2 per użytkownik: `provider`, `client_id`, `secret` (zaszyfrowany), `tenant` - jedna apka na `(user_id, provider)` (zob. [domaines.md](domaines.md) §8). |
| `user_settings` | ustawienia użytkownika (klucz/wartość), PK `(user_id, key)`. |
| `identities` | tożsamości wysyłkowe: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | serwery poczty per domena (zob. [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (zarezerwowane dla admina jeśli 0 - domyślnie), `config` (JSON) per wtyczka. |
| `themes` | `config` (JSON) i `allowed` per motyw. |
| `pgp_keys` | brelok OpenPGP (`key_data` armored, `is_private`). |
| `auth_tokens` | tokeny "Zapamiętaj logowanie": `selector` (unikalny), `token` (SHA-256 walidatora, rotowany przy każdym użyciu), `agent` (User-Agent), `ip` (przy wydaniu), `last_ip` (ostatnie przywrócenie), `created_at`, `last_used_at`, `expires_at`. |

Dawne tabele `contacts` / `contact_emails` zostały przeniesione poza bazę
globalną (zob. §5).

## 4. `app.key`

`app/storage/app.key` to klucz główny 32 bajty (64 hex), generowany przy
pierwszym szyfrowaniu poświadczenia. Szyfruje (XSalsa20-Poly1305, libsodium
secretbox):

- hasła IMAP w sesji i w `accounts.imap_pass` (oraz *refresh tokeny* OAuth2
  kont `auth != 'password'`);
- sekrety aplikacji OAuth2 użytkowników (`oauth_apps.secret`);
- sekrety wtyczek (np. hasło CardDAV).

**Zrób kopię i nigdy go nie zgub**: jego utrata unieważnia wszystkie
przechowane poświadczenia (użytkownicy muszą się zalogować ponownie). Nigdy
nie może być serwowany ani wersjonowany.

## 5. Bazy per użytkownik (kontakty)

Kontakty są izolowane w indywidualnych bazach SQLite:
`app/storage/db/users/<id>.sqlite` (tabele `contacts`, `contact_emails`).
Dotyczy to **także pod MariaDB**: dane osobiste pozostają w plikach SQLite.

Migracja `contacts_moved` kopiuje jednorazowo kontakty z bazy globalnej do
tych plików (transakcja + `INSERT OR IGNORE`, samonaprawcza), a potem
usuwa tabele globalne.

## 6. Przejście z SQLite na MariaDB

1. W adminie (*Baza danych*), podaj DSN/host/port/nazwę/użytkownika/hasło
   MariaDB. Test połączenia jest **obowiązkowy** i tworzy bazę (`utf8mb4`,
   kolacja `utf8mb4_unicode_ci`) jeśli nie istnieje.
2. Migracje odtwarzają schemat na nowej bazie.

> **Ważne**: zmiana `db_dsn` startuje na **czystym schemacie** - dane
> (użytkownicy, domeny, ustawienia) **nie są migrowane**. Nie ma narzędzia
> eksportu. Pliki kontaktów per użytkownik pozostają za to niezmienione.

## 7. Kopia zapasowa / przywracanie

- SQLite: skopiuj `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) i
  `app.key`.
- MariaDB: zrób dump bazy przez `mysqldump`, plus `app.key` i
  `db/users/*.sqlite`.
