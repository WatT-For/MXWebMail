# Plugin

*Tradotto dal francese - la versione francese fa fede.*

I plugin sono estensioni confinate in `app/plugins/<name>/`, caricate quando
attivate (dall'admin, scheda *Estensioni*). Si registrano su **hook** del
core.

> **Sessione in sola lettura**: gli endpoint GET chiudono la sessione PHP
> presto (`session_write_close`) per liberare il lock. Gli hook chiamati da
> un GET (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **non devono mai scrivere
> `$_SESSION`** - quelle scritture sarebbero silenziosamente perse.

## 0. Distribuzione via il repository GitHub

I plugin **non sono inclusi** nello zip di release `mxwebmail-<ver>.zip`: sono
letti direttamente nel repository a un **ref configurabile**
(`update.plugins_ref` - `'release'` = tag dell'ultima release di default,
`'main'` o qualsiasi ref git). Il catalogo (`PluginPackageService`, scheda
*Estensioni* - le voci non installate figurano in fondo alla tabella) è
costruito dall'API git:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` elenca i file sotto
  `app/plugins/` con il loro **sha1 di blob git** e la dimensione;
- il `plugin.json` di ogni plugin è letto via `raw.githubusercontent.com`
  (pinnato sullo sha del commit) - versione, descrizione, `min_app`/`max_app`;
- **Installare / aggiornare**: ogni file del plugin è scaricato via `raw`,
  verificato (dimensione + sha1 di blob del tree), poi `plugin.json`
  validato (nome concordante, versione semver, `main` sano, compatibilità
  `APP_VERSION`) prima di sostituire la cartella - un plugin installato è
  **disattivato** di default; la vecchia cartella va in
  `storage/update/plugins/backup-<name>-<ts>` (una sola conservata, rollback
  automatico);
- **Disinstallare**: eliminazione definitiva della cartella + purge della
  riga `plugins` (configurazione persa), senza backup.

Pubblicare una versione di plugin = bumpare `version` nel suo `plugin.json` e
pushare sul branch seguito da `update.plugins_ref` - nessuna release né
asset richiesto.

Il repository è bloccato in `GitHubClient::REPO` (stessa costante dell'updater
core) - nessun URL arbitrario è accettato. `update.channel` e la cache release
(`cache.update_ttl`) sono condivisi con la verifica di aggiornamento del
core; il ref>commit è cacheato a quel TTL, il tree e i manifest sono
indirizzati dal loro sha (immutabili). Il `check` statico richiede `version`
semver in `plugin.json`.

Per dev/test offline, `update.plugins_local` (percorso assoluto di una
cartella di plugin in `config.php`) serve da fonte: catalogo e installazione
per copia da disco, senza verifica remota.

Un plugin attivato può anche essere **riservato alla superficie admin**
(colonna *Accesso utenti* della scheda *Estensioni*, flag `user_allowed`
nella tabella `plugins`, **deselezionato di default**): è allora caricato
solo sulla pagina `/{admin_path}`, sulle rotte `/api/admin/*` e sulle
sessioni admin. Altrove è totalmente inerte (nessun hook,
`/api/plugin/<name>/*` > 404) - anche per gli utenti `is_admin` non connessi
al pannello. Gli hook di login admin (`admin.post-login`,
`admin.totp-required`, `admin.mounts`) continuano a funzionare. Un plugin i
cui hook dichiarati nel manifest sono tutti hook `admin.*` non ha alcuna
superficie utente: la colonna non mostra allora uno switch per lui (`-`).

## 1. Plugin del catalogo ufficiale

| Plugin | Ruolo | Hook |
|---|---|---|
| `add-x-originating-ip-header` | Aggiunge `X-Originating-IP` (IP del mittente) agli invii. | `message.before-send` |
| `anti-spoofing` | Rilevamento di usurpazione nel lettore: Return-Path/Reply-To/Sender incoerenti, display-name che imita un marchio (omoglifi e confondibili rilevati), dominio esca (sottodominio trappola, typosquat, trans-TLD), SPF/DKIM/DMARC valutati in allineamento con il dominio del mittente (verdetti letti in `Authentication-Results`, `Received-SPF` o gli header SpamAssassin/rspamd) - i sottodomini dei domini protetti sono legittimi. | `imap.message-headers`, `message.view` |
| `avatars` | Avatar del mittente: icone di servizi noti, Gravatar, favicon del dominio, iniziali. | `app.data` |
| `black-list` | Lista nera di connessione (wildcard + eccezioni) - verificata sull'email **risolta** (post-mapping). | `auth.login.mapped` |
| `white-list` | Lista bianca di connessione (wildcard + eccezioni) - verificata sull'email **risolta** (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Sincronizzazione dei contatti da una rubrica CardDAV (Nextcloud, Radicale, iCloud…) - **HTTPS + host pubblico richiesti**. | `app.data` |
| `change-password` | Cambio di password (driver poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Etichette di colore sui messaggi (manuali + regole automatiche). | `app.data`, `message.meta` |
| `ics-viewer` | Anteprima degli inviti/allegati `.ics` nel lettore **+ risposta iTIP** (Accetta/Forse/Rifiuta su `METHOD:REQUEST`, inviata via SMTP all'organizzatore). | `app.data` |
| `imap-contacts-suggestions` | Suggerimenti di contatti dai mittenti di una cartella IMAP. | `contacts.provider` |
| `ldap-contacts` | Suggerimenti di contatti da una directory LDAP (autocompletamento). | `contacts.provider` |
| `ldap-login` | Risoluzione LDAP: uid/alias > indirizzo IMAP reale prima del login. | `auth.login.mapping` |
| `mailbox-detect` | Rilevamento delle cartelle di sistema e creazione delle mancanti. | `app.data` |
| `pwa` | Rende il webmail installabile (manifest + service worker minimale, icone). | `app.head`, `app.routes` |
| `webmail-migration` | Pagina admin di importazione da una cartella `data` **RainLoop o SnappyMail** (percorso server o ZIP): domini, rubriche `AddressBook.sqlite`/PDO, identità, impostazioni, cartelle sistema, chiavi PGP - scansione dry-run poi importazione idempotente, account sconosciuti ignorati. | `admin.mounts` |
| `two-factor-auth` | TOTP a due fattori (Google Authenticator, Aegis…) - account utenti **e** account admin locale (provisioning QR in una sotto-scheda dedicata di Admin > Sicurezza; richiesto al login admin finché il plugin è attivato). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Banner di annuncio dell'amministratore (pagina app, opzionalmente pagina di login - manutenzione). Solo testo in chiaro. | `app.head` |
| `attachments-clamav` | Scansione ClamAV (`clamd`, `zINSTREAM`) degli allegati caricati e delle importazioni - rifiuto su rilevamento, politica `on_error` configurabile. | `attachment.upload` |
| `filelink` | Allegati troppo grandi per l'SMTP inviati come **link di download scadibile** (`/filelink/<token>` - attachment forzato, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Apprendimento spam/ham a ogni (de)marcatura: comando esterno (`rspamc`, `sa-learn`) o copia IMAP verso una cartella dovecot-antispam. | `message.spam` |
| `wkd-keys` | Scoperta automatica delle chiavi PGP dei destinatari: **WKD** (advanced + direct), raccolta degli header **Autocrypt**, ripiego `keys.openpgp.org` opzionale - importazione proposta alla composizione. | `app.data`, `message.view` |
| `seasonal-decorations` | Paesaggio monocromatico discreto nei banner secondo la data (Capodanno, Pasqua, 1 aprile, Halloween, Natale): libreria di oggetti SVG ricomposta in livelli `mask-image` via `scenes.json` ({svg, pos, size}), colore contrastato automatico - finestre regolabili, opzionale fuori sessione, senza animazione. | `app.head` |
| `link-confirmation` | Modale di conferma prima dell'apertura di un link dal corpo di una mail (anti-phishing): destinazione reale mostrata, segnalazione dei link sospetti (testo ≠ URL, IP letterale, punycode, credenziali nell'URL), domini fidati e "non chiedere più per questo dominio". | `app.head` |
| `always-cc-bcc` | Destinatari Cc o Bcc sistematici all'invio (archivio di conformità), per account o tutti - il Bcc resta invisibile. | `message.before-send` |
| `report-abuse` | Pulsante "Segnala" (barra delle azioni): inoltra le mail spuntate come `.eml` allegati verso un indirizzo abuse amministrato, marcatura spam opzionale. | `app.data` |
| `vcf-viewer` | Anteprima degli allegati `.vcf` nel lettore + importazione in un clic nella rubrica (pulsante integrato nel frontend quando `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Quota di invio per account (messaggi/ora, messaggi/giorno, destinatari/giorno) - rifiuto 429 oltre. Solo webmail. | `message.before-send` |
| `login-notify` | Mail di allerta all'account a una connessione da un IP sconosciuto (modello Gmail) - depositata non letta nella inbox, opt-out per utente. | `auth.post-login`, `app.data` |
| `sso-header` | SSO via proxy di autenticazione (header utente fidato) - richiede IP di proxy dichiarati e una password master IMAP. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Pagina admin che aggrega i rapporti DMARC (rua) di una inbox IMAP dedicata - parsing XML/gzip, aggregati per dominio e IP sorgente. | `admin.mounts` |

## 2. Configurazione di un plugin

Nell'admin (*Estensioni* > pulsante ⚙), la configurazione è generata dal file
`config.schema.json` del plugin. Tipi supportati: `string`, `text`,
`integer`, `number`, `password`, `bool`, `enum`, `color`, `list` - `color`
rende un selettore nativo + un campo `#RRGGBB` (il valore vuoto è accettato:
il campo ricade sul ripiego del plugin). I valori sono memorizzati nella
tabella `plugins` (colonna `config`, JSON).

Dettagli di configurazione notevoli:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (default `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimato), poi i parametri propri
  del driver (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` con placeholder
  `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (placeholder `%q`), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (un dominio protetto copre anche
  i suoi sottodomini, es. `accountprotection.microsoft.com`), `brands`
  (rilevati nel nome visualizzato dopo normalizzazione degli accenti e dei
  caratteri confondibili - "AppIe TV" è riconosciuto come "Apple TV"), e gli
  interruttori
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  I risultati SPF/DKIM/DMARC sono presi in conto solo quando allineati con il
  dominio del `From`: un `dkim=fail` sulla firma del trasportatore o uno
  `spf=fail` su un mailfrom relayed (SRS) non innesca un alert.
- **`two-factor-auth`** - `issuer` (default `app_name`), `label` (`email` =
  indirizzo completo, `local` = parte prima di `@`; `local` evita che le app
  TOTP deducano un'icona di marca dal dominio, es. Yahoo) e `icon_url`
  (parametro `image=` non standard, mostrato da Aegis/andOTP/FreeOTP -
  ignorato da Authy; vuoto = favicon pubblico dell'istanza). Il plugin
  provisiona anche la 2FA dell'account admin locale: segreto
  `admin_totp_secret` in `config.php` (via l'API admin
  `/api/admin/plugin/two-factor-auth/*`), pending in sessione, richiesto al
  login admin via l'hook `admin.post-login`. Disattivare il plugin
  disattiva l'enforcement admin.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcard;
  valori separati da spazi, virgole o punto e virgola), `exceptions`,
  `auth_error` (403 "account non autorizzato" > 401 generico). Attenzione
  alla semantica ereditata dal plugin legacy: per black-list, `exceptions` =
  email della lista nera che **restano autorizzate**; per white-list, se
  `exceptions` è vuoto ogni email fuori dalla lista bianca è rifiutata, ma
  se è compilata **solo le email delle eccezioni sono rifiutate** e tutto il
  resto passa - non metterci indirizzi "autorizzati in più" (effetto inverso
  atteso).

- **`link-confirmation`** - `mode` (`all` = ogni link esterno, `suspicious` =
  solo i link sospetti), `whitelist` (domini fidati, uno per riga o separati
  da spazio/virgola/punto e virgola - sottodomini inclusi), `warn_mailto`
  (confermare anche `mailto:`/`tel:`, disattivato di default) e `remember`
  (casella "non chiedere più per questo dominio" - memorizzato nel browser
  via localStorage, non lato server).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (destinazioni,
  vuoto = inattivo), `senders` (wildcard di mittenti, `*` = tutti).
- **`report-abuse`** - `abuse_address` (richiesto, vuoto = inattivo),
  `subject_prefix`, `mark_spam` (marca anche come spam via `message.spam` -
  alimenta l'apprendimento).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = illimitato), `exempt` (wildcard di account esentati). Limita solo il
  webmail: un client IMAP/SMTP diretto aggira la quota.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (scheda Impostazioni di opt-out per utente).
- **`sso-header`** - `header` (`X-Forwarded-User` o `REMOTE_USER`),
  `trusted_ips` (**richiesto, vuoto = plugin inerte**), `master_pass`
  (richiesto; modo *same* = passdb `master=yes`), `master_user` (opzionale:
  login `user*master`), `allowed_domains`, `hide_form`. L'istanza non deve
  essere raggiungibile aggirando il proxy (l'header sarebbe falsificabile).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (inbox IMAP
  dei rapporti), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Connessione IMAP propria del plugin (le opzioni del
  dominio non si applicano).

Alcuni plugin non hanno schema admin: la loro configurazione è **per utente**
(es. `carddav-contacts`, `color-tags`, `two-factor-auth` per lo stato
dell'account).

## 3. Creare un plugin

### Struttura minima

```
app/plugins/mio-plugin/
├── plugin.json          # manifest
├── MioPlugin.php        # classe principale
├── config.schema.json   # (opzionale) schema di configurazione admin
├── langs/<lang>.php     # dizionari (chiavi con prefisso plugin.<name>.)
└── js/ o assets/        # (opzionale) asset serviti via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "mio-plugin",
	"label": "plugin.mio-plugin.label",
	"class": "MioPlugin",
	"main": "MioPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mio-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (opzionale) è il nome visualizzato del plugin in Admin > Estensioni
(chiave i18n `plugin.<name>.label` raccomandata, testo letterale accettato;
il nome tecnico `name` resta mostrato accanto e serve da identificatore).

`min_app`/`max_app` (opzionali, semver `x.y.z`) limitano la compatibilità con
`APP_VERSION` - l'installazione o l'aggiornamento è rifiutato fuori
intervallo, e il catalogo marca la voce *incompatibile*.

### Classe principale

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MioPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Hook disponibili

- **Core**: `app.routes`, `app.head` (HTML iniettato nel `<head>` di
  `app.php`, `login.php` e `maintenance.php` - le viste `admin*.php` non lo
  emettono), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > descrittore `{js, plugin, section,
  tab?, label?}` reso da `pluginMount` nel pannello admin - senza `tab`:
  blocco inline nella zona chiamata `section`, es. `security-account`; con
  `tab` + `label`: sotto-scheda dedicata della pagina chiamata `section`, es.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, emesso al login dell'account admin locale -
  lanciare `TotpRequiredException` richiede un secondo fattore),
  `admin.totp-required` (`first` > booleano: mostra il campo TOTP sulla
  pagina di login admin).

Ogni plugin dichiara in `hooks` gli hook che ascolta. Un manifest che
dichiara solo hook `admin.*` marca il plugin come puramente admin - la
colonna *Accesso utenti* della scheda Estensioni non mostra switch per lui.
Il core chiama `HookRegistry::run('hook', …)`; il plugin implementa un metodo
dello stesso nome (o un `on<Hook>` secondo la convenzione del core).

### API generica

Ogni plugin è chiamabile via `/api/plugin/<name>/<action>` (GET/POST) >
metodo `api<Action>(Request $r, ?array $user)`. Il token CSRF è richiesto su
tutti i metodi (il client `api.js` lo invia sistematicamente) - le chiamate
dirette senza header `X-CSRF-Token` sono respinte in 403.

Lato admin, `/api/admin/plugin/<name>/<action>` (GET/POST) > metodo
`apiAdmin<Action>(Request $r)`: sessione admin (`$_SESSION['admin']`) + CSRF
richiesto; non c'è `$user` (l'account admin locale non è una riga `users`).
Attenzione: `requireAdmin` chiude la sessione in scrittura - per
memorizzarvi uno stato (es. segreto in attesa), riaprirla via
`$this->app->startSession()` poi `session_write_close()`.

### Scheda utente

Per aggiungere una scheda in `#/@settings/…`, fornite JS servito via
`/assets/plugin/<name>/<file>` e dichiarate la scheda (vedi i plugin
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth` come
esempi).

### Traduzioni

Chiavi con prefisso `plugin.<name>.` in `langs/<lang>.php`;
`description`/`label`/`help` dei manifest e degli schemi accettano queste
chiavi (pass-through altrimenti). Lato PHP: `$this->t('chiave')`, lato JS:
`ctx.t('chiave')` in `mount(element, ctx)`.
