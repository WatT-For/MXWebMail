# Database

*Vertaald uit het Frans - de Franse versie is leidend.*

MXWebMail werkt met **SQLite** (standaard), **MariaDB >= 10.x** of
**MySQL >= 8.0**. De driver wordt gekozen via `db_dsn` in `config.php`.

## 1. Verbinding

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (WAL-modus +
  `foreign_keys`).
- Anders een volledige PDO-DSN (bijv.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) met
  `db_user`/`db_pass`.

PDO-opties: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migraties

Het schema is **portable** tussen SQLite/MariaDB/MySQL (auto-increment
primaire sleutels aangepast aan de driver; MySQL >= 8.0 vereist voor de
SQL-windowfuncties gebruikt in v12). Migraties zijn versieerd
(`meta.schema_version`) en worden automatisch toegepast bij de eerste
databasetoegang (`App::db()`), ook tijdens de installatie. Onder de
`mysql`-driver worden reeds aanwezige objecten (kolom, index) via
`information_schema` gedetecteerd: een re-run na een mislukte migratie halverwege
is idempotent.

Na een code-update triggert de tab *Over* de **afronding**
(`/api/admin/update/finalize`): de migraties van de nieuwe versie draaien in
admin-context, daarna worden de contactdatabases per gebruiker op hun beurt
gemigreerd (een falende database wordt gelogd en opnieuw geprobeerd bij de
volgende toegang). Als reserve: `php app/bin/post-update.php` speelt de
migraties opnieuw via CLI. Is het schema nieuwer dan de code
(bestands-rollback zonder databaseherstel), dan wordt `meta.schema_ahead`
gezet en gemeld in *Over* - de migraties zijn dan opgeschort.

## 3. Globale tabellen

| Tabel | Inhoud |
|---|---|
| `meta` | sleutel/waarde-paren (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (uniek), `display_name`, `is_admin`, `disabled`, `session_epoch` (verhoogd door "Afmelding afdwingen" - weigert actieve sessies bij de volgende request), `created_at`, `last_login_at`. |
| `accounts` | IMAP-accounts van gebruikers: `email`, `imap_user`, `imap_pass` (versleuteld), `is_default` (hoofdaccount, wijzigbaar in Instellingen > Accounts), `position` (weergavevolgorde van subaccounts, herschikbaar via drag-and-drop in Instellingen > Accounts), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` bevat dan het versleutelde *refresh token*). |
| `oauth_apps` | OAuth2-applicaties per gebruiker: `provider`, `client_id`, `secret` (versleuteld), `tenant` - een app per `(user_id, provider)` (zie [domaines.md](domaines.md) §8). |
| `user_settings` | gebruikersinstellingen (sleutel/waarde), PK `(user_id, key)`. |
| `identities` | verzendidentiteiten: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | mailservers per domein (zie [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (alleen admin bij 0 - standaard), `config` (JSON) per plugin. |
| `themes` | `config` (JSON) en `allowed` per thema. |
| `pgp_keys` | OpenPGP-sleutelbos (`key_data` armored, `is_private`). |
| `auth_tokens` | "Ingelogd blijven"-tokens: `selector` (uniek), `token` (SHA-256 van de validator, bij elk gebruik geroteerd), `agent` (User-Agent), `ip` (bij uitgifte), `last_ip` (laatste herstel), `created_at`, `last_used_at`, `expires_at`. |

De vroegere tabellen `contacts` / `contact_emails` zijn uit de globale
database verplaatst (zie §5).

## 4. `app.key`

`app/storage/app.key` is een 32-byte hoofdsleutel (64 hex-tekens), aangemaakt
bij de eerste versleuteling van een credential. Hij versleutelt
(XSalsa20-Poly1305, libsodium secretbox):

- IMAP-wachtwoorden in sessie en in `accounts.imap_pass` (en de
  OAuth2-*refresh tokens* van accounts met `auth != 'password'`);
- de OAuth2-app-geheimen van gebruikers (`oauth_apps.secret`);
- plugin-geheimen (bijv. CardDAV-wachtwoord).

**Maak er een backup van en verlies hem nooit**: zijn verlies maakt alle
opgeslagen credentials ongeldig (gebruikers moeten opnieuw inloggen). Hij
mag nooit worden geserveerd of gecommit.

## 5. Databases per gebruiker (contacten)

Contacten zijn geisoleerd in individuele SQLite-databases:
`app/storage/db/users/<id>.sqlite` (tabellen `contacts`, `contact_emails`).
Dit geldt **ook onder MariaDB**: persoonlijke gegevens blijven in
SQLite-bestanden.

De migratie `contacts_moved` kopieert eenmalig de contacten van de globale
database naar deze bestanden (transactie + `INSERT OR IGNORE`,
zelfherstellend), en verwijdert daarna de globale tabellen.

## 6. Van SQLite naar MariaDB/MySQL overstappen

1. In de admin (*Database*), vul de MariaDB DSN/host/poort/naam/gebruiker/
   wachtwoord in. De verbindingstest is **verplicht** en maakt de database
   aan (`utf8mb4`, collatie `utf8mb4_unicode_ci`) als die niet bestaat.
2. De migraties spelen het schema na op de nieuwe database.

> **Belangrijk**: de wijziging van `db_dsn` begint opnieuw op een **leeg
> schema** - gegevens (gebruikers, domeinen, instellingen) worden **niet
> gemigreerd**. Er is geen export-tool. De contactbestanden per gebruiker
> blijven wel ongewijzigd.

## 7. Backup / herstel

- SQLite: kopieer `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) en
  `app.key`.
- MariaDB/MySQL: maak een dump van de database met `mysqldump` (of
  `mariadb-dump` onder MariaDB), plus `app.key` en `db/users/*.sqlite`.
