# Database

*Tradotto dal francese - la versione francese fa fede.*

MXWebMail funziona con **SQLite** (predefinito), **MariaDB >= 10.x** o
**MySQL >= 8.0**. Il driver si sceglie tramite `db_dsn` in `config.php`.

## 1. Connessione

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (modalità WAL +
  `foreign_keys`).
- Altrimenti un DSN PDO completo (es.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) con
  `db_user`/`db_pass`.

Opzioni PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migrazioni

Lo schema è **portabile** tra SQLite/MariaDB/MySQL (chiavi primarie
auto-incrementali adattate al driver; MySQL >= 8.0 richiesto per le funzioni
finestra SQL usate in v12). Le migrazioni sono versionate
(`meta.schema_version`) e applicate automaticamente al primo accesso al
database (`App::db()`), anche durante l'installazione. Sotto il driver `mysql`,
gli oggetti già presenti (colonna, indice) sono rilevati via
`information_schema`: una riesecuzione dopo un fallimento a metà migrazione è
idempotente.

Dopo un aggiornamento del codice, la scheda *Informazioni* avvia la
**finalizzazione** (`/api/admin/update/finalize`): le migrazioni della nuova
versione girano in contesto admin, poi i database dei contatti per utente
vengono migrati a loro volta (un database in errore viene registrato e
ritentato al prossimo accesso). Come ripiego:
`php app/bin/post-update.php` riesegue le migrazioni da CLI. Se lo schema è più
recente del codice (rollback dei file senza ripristino del database),
`meta.schema_ahead` viene impostato e segnalato in *Informazioni* - le
migrazioni restano allora sospese.

## 3. Tabelle globali

| Tabella | Contenuto |
|---|---|
| `meta` | coppie chiave/valore (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (univoco), `display_name`, `is_admin`, `disabled`, `session_epoch` (incrementato da "Forza disconnessione" - respinge le sessioni attive alla richiesta successiva), `created_at`, `last_login_at`. |
| `accounts` | account IMAP degli utenti: `email`, `imap_user`, `imap_pass` (cifrato), `is_default` (account principale, modificabile in Impostazioni > Account), `position` (ordine di visualizzazione degli account secondari, riordinabile via drag-and-drop in Impostazioni > Account), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` memorizza allora il *refresh token* cifrato). |
| `oauth_apps` | applicazioni OAuth2 per utente: `provider`, `client_id`, `secret` (cifrato), `tenant` - un'app per `(user_id, provider)` (vedi [domaines.md](domaines.md) §8). |
| `user_settings` | impostazioni utente (chiave/valore), PK `(user_id, key)`. |
| `identities` | identità di invio: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | server mail per dominio (vedi [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (riservato admin se 0 - predefinito), `config` (JSON) per plugin. |
| `themes` | `config` (JSON) e `allowed` per tema. |
| `pgp_keys` | portachiavi OpenPGP (`key_data` armored, `is_private`). |
| `auth_tokens` | token "Resta connesso": `selector` (univoco), `token` (SHA-256 del validator, ruotato a ogni uso), `agent` (User-Agent), `ip` (all'emissione), `last_ip` (ultimo ripristino), `created_at`, `last_used_at`, `expires_at`. |

Le vecchie tabelle `contacts` / `contact_emails` sono state spostate fuori dal
database globale (vedi §5).

## 4. `app.key`

`app/storage/app.key` è una chiave master di 32 byte (64 hex), generata alla
prima cifratura di credenziale. Cifra (XSalsa20-Poly1305, libsodium secretbox):

- le password IMAP in sessione e in `accounts.imap_pass` (e i *refresh token*
  OAuth2 degli account `auth != 'password'`);
- i segreti delle applicazioni OAuth2 degli utenti (`oauth_apps.secret`);
- i segreti dei plugin (es. password CardDAV).

**Fate il backup e non perdetela mai**: la sua perdita invalida tutte le
credenziali memorizzate (gli utenti devono riconnettersi). Non deve mai essere
servita né versionata.

## 5. Database per utente (contatti)

I contatti sono isolati in database SQLite individuali:
`app/storage/db/users/<id>.sqlite` (tabelle `contacts`, `contact_emails`).
Questo vale **anche sotto MariaDB**: i dati personali restano in file SQLite.

La migrazione `contacts_moved` copia una sola volta i contatti dal database
globale a questi file (transazione + `INSERT OR IGNORE`, auto-riparante), poi
elimina le tabelle globali.

## 6. Passare da SQLite a MariaDB/MySQL

1. Nell'admin (*Database*), compilate DSN/host/porta/nome/utente/password di
   MariaDB. Il test di connessione è **obbligatorio** e crea il database
   (`utf8mb4`, collation `utf8mb4_unicode_ci`) se non esiste.
2. Le migrazioni rieseguono lo schema sul nuovo database.

> **Importante**: il cambio di `db_dsn` riparte su uno **schema vergine** - i
> dati (utenti, domini, impostazioni) **non vengono migrati**. Non esiste uno
> strumento di export. I file dei contatti per utente, invece, restano
> invariati.

## 7. Backup / ripristino

- SQLite: copiate `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) e
  `app.key`.
- MariaDB/MySQL: fate un dump del database con `mysqldump` (o `mariadb-dump`
  sotto MariaDB), più `app.key` e `db/users/*.sqlite`.
