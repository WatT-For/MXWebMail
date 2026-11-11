# Amministrazione

*Tradotto dal francese - la versione francese fa fede.*

Il pannello di amministrazione è accessibile su `/{admin_path}` (default
`/admin`). È un'amministrazione **globale** (multi-dominio); non ci sono ruoli
finemente graduati.

## 1. Autenticazione admin

Due modalità, secondo la configurazione:

1. **Account locale** (`admin_user` + `admin_password_hash` in `config.php`):
   login/password, eventualmente TOTP (`admin_totp_secret`). Il TOTP è
   provisionato e applicato dal plugin `two-factor-auth` (sotto-scheda
   "Autenticazione a due fattori" di *Sicurezza*, con codice QR) - plugin
   disattivato = non richiesto al login.
2. **Fallback IMAP** (se nessun account locale): un utente autenticato via
   IMAP con il campo `is_admin = 1` apre l'admin. Questo diritto è
   riconvalidato a ogni richiesta (un account retrocesso/bloccato/eliminato
   è immediatamente respinto).

Il rate-limit di connessione si applica anche all'admin, così come
`admin_ip_allowlist` se compilato. Un admin IMAP non può retrocedersi,
bloccarsi né eliminarsi da solo.

## 2. Sezioni del pannello

Il pannello si compone di schede (hash `#/<pagina>[/<sotto-scheda>]`).

### Generale

`app_name`, `admin_path`, `language` (elenco delle lingue installate +
`auto`), `session_lifetime`, `remember_days` (durata dei token "Resta
connesso"), `maintenance`, `update.channel`. Gestisce anche il **favicon**
(upload ≤ 512 KB: ico/png/svg/jpg/webp, contenuto validato). La modalità
**manutenzione** mostra una pagina dedicata (503) su `/`: gli utenti connessi
vi sono spostati alla loro prossima richiesta API (sessione conservata) e si
ricarica da sola alla fine; l'admin (`/{admin_path}` + `/api/admin/*`) resta
pienamente accessibile.

### Database

Driver SQLite o MariaDB/MySQL, host/porta/nome/utente/password. Un test
(`/api/admin/test-db`) è obbligatorio prima della scrittura: il server
valida la connessione e crea il database se necessario. Vedi
[base-de-donnees.md](base-de-donnees.md).

La scheda **Backup** scarica un archivio zip (`config.php`, `app.key`,
database SQLite, `storage/branding`) - **sensibile** (credenziali + chiave di
cifratura), da conservare in luogo sicuro. Un database MariaDB non è coperto:
`mysqldump` lato server.

### Funzionalità

Sotto-schede *Funzionalità* e *Default*:

- proxy delle immagini + allowlist, dimensioni degli allegati;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- tutte le impostazioni `user_defaults.*` (default utenti).

### Prestazioni

Sotto-schede *HTTP* e *Cache server*:

- `http.*`: compressione gzip (attivazione, livello, dimensione minima),
  `http.etag`, TTL degli asset tema/plugin, del favicon e del proxy delle
  immagini;
- `cache.*`: cache file applicativa (attivazione, TTL del rilevamento dominio
  e della verifica di aggiornamento, intervallo di purga).

Dettaglio delle chiavi in [configuration.md](configuration.md) (sezioni
*HTTP* e *Cache applicativa*).

### Sicurezza

Sotto-schede *Account*, *Autenticazione a due fattori* (se il plugin
`two-factor-auth` è attivato), *Accesso*, *Header*, *TLS*:

- account admin locale (`admin_user`) e **cambio della password** admin -
  compilare i due campi poi *Salva*: la password è modificata solo se sono
  compilati e identici;
- **TOTP** admin (sotto-scheda del plugin `two-factor-auth`): generazione del
  segreto, codice QR da scansionare, codice di conferma obbligatorio prima
  dell'attivazione (il segreto non è mai mostrato dopo il salvataggio);
- `admin_ip_allowlist` (pulsante "aggiungi il mio IP"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- sezione `ssl.*` completa (certificati in uscita).

### Log

Sotto-schede *Visualizzatore* e *Impostazioni*:

- visualizzatore dei log `app.log`, `mail.log` e del log di auth (ultime 200
  righe), con pulsante "Svuota" per troncare il file selezionato;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domini

Configurazione dei server mail per dominio - vedi [domaines.md](domaines.md).
La scheda *Impostazioni* raggruppa la risoluzione (`default_domain`,
`restrict_domains`, `autodetect.*`). Per i domini "solo account secondario +
provider OAuth" (Gmail, Outlook, Yahoo), l'admin autorizza solo il provider
(opzione `oauth` del modale di dominio): le credenziali di applicazione
OAuth2 sono registrate da ogni utente (*Impostazioni > Applicazioni OAuth2*).

### Utenti

Lista + ricerca utenti, con pulsante di aggiornamento (icona) per ricaricare
la lista. Per utente (modale, schede *Profilo*, *Impostazioni*,
*Account/Identità*, *Sessioni*):

- `display_name`, `is_admin`, `disabled`;
- impostazioni utente effettive (modifica + reset - il reset ripristina i
  `user_defaults` ma **preserva** le chiavi interne `plugin.*` e
  `user_folders`, es. segreto 2FA, provenienza delle cartelle);
- account IMAP secondari (eliminazione, tranne l'account predefinito);
- identità (creazione/modifica/eliminazione, identità predefinita);
- scheda **Sessioni**: token "Resta connesso" elencati e revocabili;
  **Forza disconnessione** revoca i token e uccide le sessioni attive
  (session_epoch incrementato, rifiuto alla richiesta seguente).

L'eliminazione di un utente è a cascata ed elimina anche il suo database dei
contatti (`storage/db/users/<id>.sqlite`).

### Estensioni (plugin)

Ogni plugin è elencato sotto il suo **nome visualizzato** (campo `label` del
manifest, tradotto - il nome tecnico `name` resta mostrato accanto in
`code`).

Attivazione/disattivazione di ogni plugin + modale di **configurazione**
generata da `config.schema.json` (tipi `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). La colonna **Accesso utenti**
limita un plugin attivato alla superficie admin (pagina `/{admin_path}`,
`/api/admin/*`, sessioni admin): diventa inerte per gli utenti - vedi
[plugins.md](plugins.md). Deselezionata di default (da autorizzare
esplicitamente); è assente per i plugin che dichiarano solo hook `admin.*`
(nessuna superficie utente).

**Installazione / aggiornamento da GitHub**: i plugin sono letti direttamente
nel repository bloccato (`GitHubClient::REPO`, stesso dell'updater core) al
ref `update.plugins_ref` - `'release'` = tag dell'ultima release di default,
`'main'` in dev (un push di `plugin.json` pubblica l'aggiornamento, nessuna
release richiesta). Ogni file scaricato via `raw.githubusercontent.com` è
verificato per sha1 di blob git.
Il catalogo è caricato all'apertura della scheda (cache condivisa con il
check core) ed è aggiornabile via il pulsante **aggiorna** in alto a destra;
le voci del catalogo non installate sono elencate in fondo alla tabella dei
plugin:

- pulsante **Aggiorna** sui plugin installati quando una versione più recente
  è pubblicata (la vecchia cartella è salvata in
  `storage/update/plugins/backup-<name>-<ts>` - una sola conservata, rollback
  automatico in caso di fallimento);
- pulsante **Installa** sulle voci del catalogo non presenti - un plugin
  appena installato è **disattivato** di default;
- pulsante **Disinstalla**: eliminazione **definitiva** della cartella e
  della configurazione (riga `plugins` purgata), senza backup;
- `min_app`/`max_app` del `plugin.json` remoto: una voce incompatibile con
  `APP_VERSION` è marcata *incompatibile* e la sua installazione è rifiutata.

### Migrazione webmail (RainLoop / SnappyMail)

Se il plugin `webmail-migration` è attivato, una pagina **Migrazione webmail**
appare nel menu laterale (prima di *Informazioni*): migra i dati di una
cartella `data/_data_/_default_/` di un'installazione **RainLoop o
SnappyMail**, indicata per **percorso server** o **upload ZIP** (estrazione
in `storage/tmp`, purgata via il pulsante dedicato o dopo 2 h).

- **Analizza**: inventario senza scrittura (domini `*.ini`/`*.json`, account
  trovati, volumi contatti/identità/chiavi/impostazioni per utente);
- **Avvia l'importazione**: importazione idempotente - domini mancanti,
  contatti (rubriche per account + globale + DSN PDO dichiarata in
  `application.ini`, frequenze e tipi di email conservati, contatti eliminati
  esclusi), identità (default = `Id` vuota), impostazioni utente note
  (messaggi per pagina, aggiornamento, auto-salvataggio contatti, cartelle
  sistema, tema se esiste) e chiavi PGP armored. Ri-eseguibile senza
  duplicati.

Solo gli account corrispondenti a un **utente esistente** sono importati; gli
altri sono elencati nel rapporto. Le password degli account aggiuntivi non
sono recuperabili (cifrate dal webmail d'origine) - da ricreare manualmente.
I filtri Sieve vivono sul server mail: niente da importare.

Lato utente, la scheda *Impostazioni > Contatti* offre anche
un'**importazione** `.vcf`/`.csv`/`.sqlite` (rubrica `AddressBook.sqlite`
RainLoop/SnappyMail - in una rubrica multi-utente solo i contatti
dell'account sono ripresi) e un'**esportazione** `.vcf`, che copre ogni altra
rubrica, inclusa un'esportazione manuale SnappyMail.

### Temi

- tema **predefinito** (scrive `user_defaults.theme`);
- **autorizzazione** per tema (`allowed`): un utente può scegliere solo un
  tema autorizzato (il predefinito resta sempre selezionabile);
- modale di **configurazione** del tema (stessi tipi + `color`);
- visualizzazione della catena di ereditarietà (`extends`).

Vedi [themes.md](themes.md).

### Informazioni

Versione (`APP_VERSION`), PHP/SAPI/limiti, driver e dimensione del database,
percorsi, estensioni caricate. Un link al sito ufficiale è
mostrato nella navbar. La scheda di testata integra anche gli
**aggiornamenti** via GitHub Releases (verifica, download, applicazione,
ripristino):

- *Verifica* > *Scarica* > *Applica* > finalizzazione automatica delle
  migrazioni; stati conservati in `storage/update/state.json`;
- all'estrazione, `files.sha256` (manifest per-file del pacchetto) è
  verificato, i prerequisiti del pacchetto (`APP_REQ_PHP`/`APP_REQ_FROM` nel
  suo `App.php`: versione PHP, versione sorgente minima) sono controllati, e
  i file modificati localmente sono segnalati prima dell'applicazione;
- durante la copia, l'API utente risponde 503 (flag
  `storage/update/applying`, pagina di manutenzione lato web - scade dopo
  15 min in caso di fallimento);
- ogni file sovrascritto è salvato in `storage/update/backup-<ts>` con
  `manifest.json` (versione, `schema_version`, liste); sotto SQLite il
  database (`webmail.sqlite` + `db/users/*.sqlite`) è copiato dopo
  checkpoint WAL - sotto MariaDB, il backup esterno (`mysqldump`) resta a
  carico dell'admin (avviso prima dell'applicazione);
- *Ripristina* (rollback): ripristina i file dell'ultimo backup, elimina i
  file creati e ricopia i database SQLite; sotto MariaDB, rifiuto se lo
  schema è avanzato (ripristinate il dump poi confermate il forzamento);
- il banner "schema più recente del codice" segnala un rollback file senza
  ripristino del database;
- azione *Reinizializza* per abbandonare un pacchetto scaricato/estratto.

> Il repository ufficiale è bloccato in `GitHubClient::REPO` ed è **pubblico**:
> la verifica funziona senza configurazione né token (è disattivata solo
> finché la costante non è compilata). `update.channel` resta modificabile
> qui; `update.repo` si imposta manualmente in `config.php`.

## 3. Scrittura di `config.php`

L'admin scrive `config.php` **per sezione**, atomicamente (file temporaneo +
`rename`, `chmod 0600`), preservando le chiavi non fornite. Se `app/config/`
non è scrivibile, l'interfaccia lo indica (`saved_readonly`) e le modifiche
sono mostrate ma non persistite.

## 4. Testare la connessione

In *Domini*, **Testa la connessione** valida il protocollo della scheda
attiva del modale (IMAP, SMTP o Sieve - le altre schede testano IMAP), e
**Rileva** riempie i campi senza salvare. Il rilevamento (`DomainDetector`)
funziona indipendentemente da `autodetect.enabled`. Salvare manualmente un
dominio cancella il marcatore "auto-rilevato" (`options.detected`).
