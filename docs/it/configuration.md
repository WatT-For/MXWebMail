# Riferimento di configurazione

*Tradotto dal francese - la versione francese fa fede.*

Il file `app/config/config.php` definisce solo le chiavi che **sovrascrivono**
i valori predefiniti (`app/config/config.example.php`, caricato da
`Config.php`). Copiate `config.example.php` in `config.php` e adattatelo. Il
pannello di amministrazione modifica la maggior parte di queste chiavi per
sezione; solo alcune richiedono modifica manuale (`update.*` soprattutto, ma
anche `import_max_size`, `smime_ca_path`, `logs.max_size` e `logs.keep`).

Gli array associativi sono fusi in profondità; le liste sono sostituite.

## Archiviazione

| Chiave | Default | Ruolo |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) o DSN PDO completo (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Utente PDO (MariaDB). |
| `db_pass` | `''` | Password PDO (MariaDB). |

## Applicazione

| Chiave | Default | Ruolo |
|---|---|---|
| `app_name` | `MXWebMail` | Nome mostrato (titolo, PWA, TOTP). |
| `favicon` | `''` | File favicon caricato in `storage/branding`, servito via `/favicon.ico`; vuoto = icona predefinita. |
| `website_url` | `https://mxrelay.fr/webmail/` | Link al sito ufficiale (pulsante admin); vuoto = nascosto. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Link "Sponsor" alla pagina delle donazioni (pulsante admin + impostazioni webmail); vuoto = nascosto. |
| `debug` | `false` | Rinvia gli errori interni ai client API (mai in produzione). |
| `language` | `auto` | `auto` = `Accept-Language` del browser; altrimenti codice di una lingua installata (`fr`, `en`…). Fallback finale `en`. |

## Domini

| Chiave | Default | Ruolo |
|---|---|---|
| `default_domain` | `auto` | Suffisso di un login digitato senza `@dominio`: `auto` = host HTTP (senza `www.` né porta), `''` = nessuno, o un dominio fisso. |
| `restrict_domains` | `true` | Solo i domini nominati della tabella `domains` sono accettati al login (né `*` né il rilevamento si applicano). |
| `autodetect.enabled` | `false` | Rilevamento automatico della configurazione di un dominio sconosciuto al login. |
| `autodetect.use_ispdb` | `false` | Fallback sulla base Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Sondaggio porte `imap.`/`mail.`/`smtp.`/`sieve.<dominio>`. |

I server IMAP/SMTP/Sieve si configurano **per dominio** nell'admin (vedi
[domaines.md](domaines.md)). Il dominio `*` serve da parametri catch-all.

## Sicurezza

| Chiave | Default | Ruolo |
|---|---|---|
| `admin_path` | `admin` | Segmento URL del pannello admin. |
| `admin_user` | `''` | Account admin locale; vuoto = fallback su un utente `is_admin` verificato via IMAP. |
| `admin_password_hash` | `''` | `password_hash('password', PASSWORD_DEFAULT)` dell'account admin locale. |
| `admin_totp_secret` | `''` | Segreto TOTP (base32) dell'account admin locale, scritto dal plugin `two-factor-auth`; vuoto = disattivato. Non richiesto se il plugin è disattivato. |
| `login_max_attempts` | `5` | Tentativi di connessione prima del blocco temporaneo. |
| `login_lock_seconds` | `300` | Durata del blocco (secondi). |
| `admin_ip_allowlist` | `''` | IP/CIDR (CSV) autorizzati sull'admin; vuoto = tutti. |
| `session_lifetime` | `0` | Durata di sessione in ore; `0` = cookie di sessione. |
| `remember_days` | `30` | Durata di vita dei token "Resta connesso" in giorni (1-365). |
| `maintenance` | `false` | Modalità manutenzione: `/` serve una pagina dedicata in 503 (tutti i pubblici, sessione conservata) e l'`/api/*` utente risponde 503 - `/api/admin/*` e `/api/ping` restano attivi. Gli utenti connessi passano alla pagina di manutenzione alla loro prossima richiesta API; questa si ricarica da sola alla fine. |
| `force_https` | `false` | Reindirizza HTTP > HTTPS (301). |
| `hsts` | `false` | Emette `Strict-Transport-Security` quando la richiesta è HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (default, richiesto per il ritorno OAuth2) / `Strict` (rompe il flusso OAuth2) / `None` (richiede HTTPS). |
| `csp_extra` | `''` | Direttive CSP aggiuntive (es. `img-src https:`). |
| `trusted_proxies` | `''` | IP/CIDR (CSV) dei reverse proxy fidati. Obbligatorio per rendere affidabile `X-Forwarded-*` (rate-limit, allowlist, log). |
| `secfetch_allow` | `''` | Regole `Sec-Fetch-*` aggiuntive (es. `site=same-origin;mode=cors`). |

## Aggiornamenti

Il repository ufficiale è bloccato in `GitHubClient::REPO`
(`WatT-For/MXWebMail`) ed è **pubblico**: verifica e download funzionano senza
token. Questa sezione - condivisa dall'updater core e dal
catalogo plugin (letto nel repo al ref `update.plugins_ref`, ogni file
scaricato verificato per sha1 di blob git) - non è modificabile via l'admin
(tranne `channel`).

| Chiave | Default | Ruolo |
|---|---|---|
| `update.repo` | `''` | Fallback dev se `GitHubClient::REPO` è vuoto (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (unica impostazione esposta nell'admin). |
| `update.manifest_url` | `''` | Dev: manifest JSON in formato release, bypassa l'API GitHub. |
| `update.plugins_ref` | `'release'` | Ref git sorgente del catalogo plugin: `'release'` (tag dell'ultima release), `'main'` (branch - un push di `plugin.json` pubblica l'aggiornamento) o qualsiasi ref letterale. |
| `update.plugins_local` | `''` | Dev/test: percorso assoluto di una cartella di plugin - catalogo + install da disco, offline e senza verifica remota. Prevale su `plugins_ref`. |

## Funzionalità

| Chiave | Default | Ruolo |
|---|---|---|
| `attachment_max_size` | `25` | MB massimo per file. Deve essere ≤ `attachments_max_total` (oltre, il limite non ha effetto: l'upload limita già al `min` dei due - l'admin rifiuta il salvataggio incoerente). |
| `attachments_max_total` | `25` | MB cumulati per messaggio. |
| `allow_additional_accounts` | `true` | L'utente può aggiungere/gestire account IMAP secondari (Impostazioni > Account). |
| `allow_additional_identities` | `true` | L'utente può creare identità aggiuntive. |
| `popup_identity` | `false` | Apre l'impostazione di identità al primo login se il nome è vuoto. |
| `dangerous_actions` | `true` | Autorizza svuotare/eliminare una cartella, eliminare messaggi (incluso lo spostamento nel cestino). |
| `contacts_enable` | `true` | Rubrica + autocompletamento. |
| `contacts_suggestions_limit` | `20` | Numero max di suggerimenti di autocompletamento (1-100). |
| `import_max_size` | `100` | Dimensione max di un `.mbox` importato (MB) - gli `.eml` conservano il tetto `attachment_max_size`. Non modificabile nell'admin. |
| `smime_ca_path` | `''` | Percorso CA (file PEM) per la fiducia S/MIME; vuoto = solo firma crittografica verificata (`PKCS7_NOVERIFY`). Non modificabile nell'admin. |

## `user_defaults` - predefiniti delle impostazioni utente

Sovrascrivibili dall'utente. Le chiavi autorizzate in scrittura via
`/api/settings` sono queste più quelle dichiarate dai plugin (hook
`settings.allowed`).

| Chiave | Default | Ruolo |
|---|---|---|
| `messages_per_page` | `50` | Messaggi per pagina (5-200). |
| `list_sort` | `REVERSE DATE` | Ordinamento della lista (criterio IMAP SORT in whitelist). |
| `view_images` | `ask` | Immagini esterne: `always` / `ask` / `match` (mittente noto). |
| `msg_default_action` | `reply` | Pulsante di risposta predefinito: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Secondi prima di segnare letto un messaggio aperto (0 = immediato). |
| `compose_autosave` | `60` | Secondi tra salvataggi automatici della bozza (0 = mai). |
| `mdn_mode` | `ask` | Conferme di lettura: `ask` (banner di richiesta) / `never` (nascosto). |
| `autologout` | `30` | Minuti di inattività prima della disconnessione (0 = mai). Non revoca il token "Resta connesso": la sessione è ripristinata silenziosamente alla visita seguente. |
| `list_refresh` | `5` | Minuti tra aggiornamenti automatici (0 = mai). |
| `notify_new_mail` | `false` | Notifica del browser all'arrivo di una mail. |
| `contacts_autosave` | `true` | Salvare automaticamente il destinatario nei contatti. |
| `folder_pane_width` | `240` | Larghezza colonna cartelle (px, 140-600). |
| `message_pane_width` | `340` | Larghezza colonna lista messaggi (px, 200-800). |
| `layout` | `horizontal` | Disposizione di lettura: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Altezza lista messaggi in modo verticale (px, 120-1200). |
| `compose_modal_width` | `0` | Larghezza modale composizione (px, 0 = default Bootstrap). |
| `compose_modal_height` | `0` | Altezza modale composizione (px, 0 = auto). |
| `identity_modal_width` | `0` | Larghezza modale identità (px, 0 = default Bootstrap). |
| `identity_modal_height` | `0` | Altezza modale identità (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Svuotare il cestino alla disconnessione. |
| `empty_junk_on_logout` | `false` | Svuotare la cartella spam alla disconnessione. |
| `list_threads` | `false` | Raggruppare i messaggi in conversazioni (IMAP THREAD). |
| `after_delete` | `next` | Dopo eliminazione/spostamento della mail mostrata: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Scorciatoie da tastiera della vista mail (aiuto via il tasto `?`). |
| `theme` | `default` | Template di interfaccia (cartella `app/templates/<name>`). |

## TLS (connessioni in uscita IMAP/SMTP/Sieve)

| Chiave | Default | Ruolo |
|---|---|---|
| `ssl.verify_certificate` | `true` | Verificare il certificato (`false` = certificato autofirmato). |
| `ssl.allow_self_signed` | `false` | Accettare i certificati autofirmati. |
| `ssl.cafile` / `ssl.capath` | `''` | CA personalizzata (percorso assoluto / cartella). |
| `ssl.disable_compression` | `true` | Disattiva la compressione TLS. |
| `ssl.security_level` | `1` | Livello di sicurezza OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Eventuale certificato client. |

## Proxy delle immagini esterne

| Chiave | Default | Ruolo |
|---|---|---|
| `image_proxy` | `true` | Servire le immagini remote via `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Domini autorizzati (CSV, `*.dominio` ok); vuoto = tutti (IP privati sempre bloccati). |

## HTTP - compressione & cache del browser

Modificabili nell'admin, sezione *Prestazioni > HTTP*.

| Chiave | Default | Ruolo |
|---|---|---|
| `http.gzip` | `true` | Comprime le risposte testo/JSON in PHP (gzip) - funziona anche senza `mod_deflate`. Ignorato se `zlib.output_compression` è attivo. **Dietro Apache/nginx: `false` consigliato** - il server comprime come filtro (streaming) e PHP evita un `gzencode` in memoria. Tenere `true` per `php -S` (dev, niente `mod_deflate`). |
| `http.gzip_level` | `6` | Livello di compressione 1-9. |
| `http.gzip_min_size` | `1024` | Dimensione minima del corpo (byte) per comprimere. |
| `http.etag` | `true` | ETag/Last-Modified + risposte 304 sugli asset serviti da PHP (tema, plugin, favicon). |
| `http.assets_ttl` | `86400` | `max-age` degli asset tema/plugin (`/assets/theme|plugin/*`). `0` = rivalidazione sistematica. Con `?v=` (mtime del file servito), `immutable` è emesso. |
| `http.favicon_ttl` | `3600` | `max-age` di `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (privato) delle immagini via `/api/proxy` - serve anche da TTL alla cache su disco (`storage/cache/proxy`). `0` = nessuna cache (re-fetch + rivalidazione SSRF a ogni visualizzazione). Purga gestita da `cache.gc_hours`: tenere `proxy_ttl ≤ cache.gc_hours`. |

Le risposte private/API non ricevono mai cache pubblica; l'HTML è `no-cache`.

**Produzione**: attivare **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ il numero di file PHP - il codice invalida
da sé le proprie cache via `opcache_invalidate`, `opcache.validate_timestamps`
può restare attivo). Le cache applicative (`classmap.php`, manifest dei
plugin, dizionari i18n, `themedata.php`) sono gestite dal codice - nessuna
invalidazione manuale richiesta.

## Cache applicativa (file)

Modificabili nell'admin, sezione *Prestazioni > Cache server*.

| Chiave | Default | Ruolo |
|---|---|---|
| `cache.enabled` | `true` | Attiva la cache file `app/storage/cache` (liste di messaggi IMAP indicizzate sull'ETag della cartella, DNS, rilevamento dominio, verifica aggiornamento, GC della cartella). Non tocca né l'autoloader (`classmap.php`) né `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL della cache negativa di rilevamento IMAP/SMTP (s). |
| `cache.update_ttl` | `3600` | TTL del risultato di verifica aggiornamento (s). |
| `cache.gc_hours` | `168` | Intervallo di purga dei file di cache scaduti (h); `0` = mai. |

## Log MailSo (protocolli IMAP/SMTP/Sieve)

| Chiave | Default | Ruolo |
|---|---|---|
| `logs.enabled` | `true` | Scrive `storage/logs/mail.log` (segreti mascherati). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` per tracciare il protocollo. |
| `logs.syslog` | `false` | Duplicato verso `syslog()` se il log file è inattivo. |
| `logs.time_zone` | `''` | Fuso dei timestamp (es. `Europe/Paris`); vuoto = server. |
| `logs.max_size` | `5` | Rotazione oltre N MiB - vale per `mail.log`, `app.log` e il log di auth. |
| `logs.keep` | `3` | Rotazioni conservate (`app.1.log` … `app.{keep}.log`, tetto 20). |

## Log di autenticazione (stile fail2ban)

| Chiave | Default | Ruolo |
|---|---|---|
| `auth_logging` | `false` | Attiva il log di autenticazione dedicato. |
| `auth_logging_filename` | `auth.log` | File in `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholder: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` vale `AUTH OK`, `AUTH FAILED (<codice http>)` o
`AUTH FAILED (totp)`. Il log copre il login webmail e il login admin (login
con prefisso `admin:`). Per il collegamento fail2ban (filtro, jail,
logrotate), vedi [fail2ban.md](fail2ban.md).

## Labs / compatibilità

| Chiave | Default | Ruolo |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Mostrare gli errori SMTP grezzi al client (diagnostica, può rivelare dettagli interni). |
