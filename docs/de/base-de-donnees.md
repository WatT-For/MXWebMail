# Datenbank

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

MXWebMail läuft mit **SQLite** (Standard) oder **MariaDB/MySQL**. Der Treiber
wird über `db_dsn` in `config.php` gewählt.

## 1. Verbindung

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (WAL-Modus +
  `foreign_keys`).
- Andernfalls ein vollständiger PDO-DSN (z. B.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) mit
  `db_user`/`db_pass`.

PDO-Optionen: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migrationen

Das Schema ist **portabel** zwischen SQLite/MariaDB (Auto-Increment-Primärschlüssel
an den Treiber angepasst). Migrationen sind versioniert
(`meta.schema_version`) und werden beim ersten Datenbankzugriff automatisch
angewendet (`App::db()`), auch während der Installation.

Nach einem Code-Update löst der Tab *Über* die **Finalisierung** aus
(`/api/admin/update/finalize`): die Migrationen der neuen Version laufen im
Admin-Kontext, dann werden die Kontaktdatenbanken pro Benutzer ihrerseits
migriert (eine fehlgeschlagene Datenbank wird protokolliert und beim nächsten
Zugriff erneut versucht). Als Notfalloption:
`php app/bin/post-update.php` spielt die Migrationen per CLI nach. Ist das
Schema neuer als der Code (Datei-Rollback ohne Datenbank-Wiederherstellung),
wird `meta.schema_ahead` gesetzt und in *Über* gemeldet - die Migrationen sind
dann ausgesetzt.

## 3. Globale Tabellen

| Tabelle | Inhalt |
|---|---|
| `meta` | Schlüssel/Wert-Paare (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (eindeutig), `display_name`, `is_admin`, `disabled`, `session_epoch` (durch "Abmeldung erzwingen" erhöht - weist aktive Sitzungen bei der nächsten Anfrage ab), `created_at`, `last_login_at`. |
| `accounts` | IMAP-Konten der Benutzer: `email`, `imap_user`, `imap_pass` (verschlüsselt), `is_default` (Hauptkonto, änderbar in Einstellungen > Konten), `position` (Anzeigereihenfolge der Zweitkonten, per Drag-and-drop in Einstellungen > Konten sortierbar), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` speichert dann das verschlüsselte *Refresh-Token*). |
| `oauth_apps` | OAuth2-Anwendungen pro Benutzer: `provider`, `client_id`, `secret` (verschlüsselt), `tenant` - eine App pro `(user_id, provider)` (siehe [domaines.md](domaines.md) §8). |
| `user_settings` | Benutzereinstellungen (Schlüssel/Wert), PK `(user_id, key)`. |
| `identities` | Sende-Identitäten: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | Mailserver pro Domain (siehe [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (nur Admin bei 0 - Standard), `config` (JSON) pro Plugin. |
| `themes` | `config` (JSON) und `allowed` pro Theme. |
| `pgp_keys` | OpenPGP-Schlüsselbund (`key_data` armored, `is_private`). |
| `auth_tokens` | "Angemeldet bleiben"-Tokens: `selector` (eindeutig), `token` (SHA-256 des Validators, bei jeder Nutzung rotiert), `agent` (User-Agent), `ip` (bei Ausstellung), `last_ip` (letzte Wiederherstellung), `created_at`, `last_used_at`, `expires_at`. |

Die früheren Tabellen `contacts` / `contact_emails` wurden aus der globalen
Datenbank ausgelagert (siehe §5).

## 4. `app.key`

`app/storage/app.key` ist ein 32-Byte-Masterschlüssel (64 Hex-Zeichen), der
beim ersten Verschlüsseln von Zugangsdaten erzeugt wird. Er verschlüsselt
(XSalsa20-Poly1305, libsodium secretbox):

- IMAP-Passwörter in der Sitzung und in `accounts.imap_pass` (sowie die
  OAuth2-*Refresh-Tokens* von Konten mit `auth != 'password'`);
- die OAuth2-App-Geheimnisse der Benutzer (`oauth_apps.secret`);
- Plugin-Geheimnisse (z. B. CardDAV-Passwort).

**Sichern Sie ihn und verlieren Sie ihn niemals**: sein Verlust macht alle
gespeicherten Zugangsdaten ungültig (Benutzer müssen sich neu anmelden). Er
darf niemals ausgeliefert oder versioniert werden.

## 5. Datenbanken pro Benutzer (Kontakte)

Kontakte sind in einzelnen SQLite-Datenbanken isoliert:
`app/storage/db/users/<id>.sqlite` (Tabellen `contacts`, `contact_emails`).
Das gilt **auch unter MariaDB**: persönliche Daten bleiben in SQLite-Dateien.

Die Migration `contacts_moved` kopiert die Kontakte einmalig von der globalen
Datenbank in diese Dateien (Transaktion + `INSERT OR IGNORE`, selbstheilend)
und löscht dann die globalen Tabellen.

## 6. Von SQLite zu MariaDB wechseln

1. Im Admin (*Datenbank*) DSN/Host/Port/Name/Benutzer/Passwort von MariaDB
   eintragen. Der Verbindungstest ist **obligatorisch** und legt die Datenbank
   an (`utf8mb4`, Kollation `utf8mb4_unicode_ci`), falls sie nicht existiert.
2. Die Migrationen spielen das Schema auf der neuen Datenbank nach.

> **Wichtig**: die Änderung von `db_dsn` startet auf einem **leeren Schema** -
> Daten (Benutzer, Domains, Einstellungen) werden **nicht migriert**. Es gibt
> kein Export-Tool. Die Kontaktdateien pro Benutzer bleiben hingegen
> unverändert.

## 7. Sicherung / Wiederherstellung

- SQLite: `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) und `app.key`
  kopieren.
- MariaDB: die Datenbank mit `mysqldump` sichern, plus `app.key` und
  `db/users/*.sqlite`.
