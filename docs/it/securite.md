# Sicurezza

*Tradotto dal francese - la versione francese fa fede.*

## 1. Modello di sicurezza integrato

### "Resta connesso" (token persistenti)

La casella "Resta connesso" emette un cookie `mxwm_remember`
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, durata `remember_days`
- 30 giorni di default). La tabella `auth_tokens` memorizza solo l'**hash
SHA-256** del validator; questo è **ruotato a ogni uso** (un cookie rubato
già presentato è rilevabile e invalidato). Il ripristino ri-decifra la
password IMAP (`CredentialCrypt`) e ri-verifica le credenziali - un fallimento
elimina il token. Ogni token registra l'**IP all'emissione** (`ip`), l'**ultimo
IP** noto (`last_ip`, a ogni ripristino) e lo User-Agent - visibili in
Impostazioni > Sessioni per individuare una sessione sconosciuta. I token vi
sono elencati e revocabili, e il token corrente è revocato alla disconnessione
**manuale** (la disconnessione per inattività `autologout` lo preserva: la
sessione è allora ripristinata silenziosamente alla visita seguente). Ogni
nuova sessione (login normale o ripristino) si posiziona sull'ultimo account
consultato - con ripiego sull'account predefinito.

### CSRF

Ogni POST richiede un token CSRF di sessione (`X-CSRF-Token`, campo di
formulario o chiave JSON); le API dei plugin (`/api/plugin/…`) lo richiedono
qualunque sia il metodo HTTP. Un secondo strato, basato sugli header
**`Sec-Fetch-*`**, respinge le richieste cross-site salvo navigazione GET
verso un documento - anche senza attivazione utente, poiché il browser rimuove
`Sec-Fetch-User` dopo ogni redirect (portali, link riscritti)
(`secfetch_allow` permette di estendere queste regole).

### Header HTTP

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, HSTS opzionale, e una
**CSP** con nonce per richiesta (la direttiva `script-src` include
`unsafe-eval`, richiesto da Knockout). Gli allegati serviti inline ricevono
`Content-Security-Policy: sandbox`.

### Cifratura delle credenziali

Le password IMAP (e i segreti dei plugin) sono cifrate **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) con la chiave master
`app/storage/app.key`, in sessione e nel database. Vedi
[base-de-donnees.md](base-de-donnees.md).

### Account secondari OAuth2

I domini pubblici (Gmail, Outlook, Yahoo - vedi [domaines.md](domaines.md)) si
aggiungono via **authorization code + PKCE**: lo `state` anti-CSRF e il
verifier sono tenuti in sessione, il `redirect_uri` è ricostruito lato server
(host filtrato come `force_https`). Solo il *refresh token* è persistito,
cifrato come una password; l'*access token* effimero esiste solo in memoria
per la durata della connessione (XOAUTH2/OAUTHBEARER). La rotazione del
refresh token (Microsoft, Yahoo) è riapplicata nel database a ogni scambio.
L'email dell'`id_token` serve da controllo di coerenza; la prova di
appartenenza resta la connessione IMAP OAuth stessa. Il callback
(`/api/oauth/callback`) restituisce solo una mini-pagina
`postMessage`>`window.close()` per il popup di consenso.

Le credenziali di applicazione OAuth2 sono **per utente** (tabella
`oauth_apps`, inserite alla prima aggiunta di account o nelle impostazioni):
il `secret` è cifrato con la stessa chiave master e non è mai rinviato
all'interfaccia. Un'app eliminata o modificata invalida gli account che ne
dipendono (il refresh token resta legato al client_id d'origine).

### Rate-limit del login

`login_max_attempts` (5) poi blocco `login_lock_seconds` (300 s). Il contatore
è persistito in `storage/cache/login_attempts.json` (chiave `sha256(IP|login)`,
purga delle voci inattive > 1 h, scrittura sotto lock `flock`). Un blocco
attivo non è né prolungato né re-registrato dai tentativi respinti (429). Si
applica anche al login admin. Un log di autenticazione stile fail2ban è
disponibile (`auth_logging*`, placeholder `{request:ip}`, `{imap:login}`,
`{result}`; CR/LF neutralizzati): copre i login webmail e admin (login con
prefisso `admin:`) e i fallimenti di codice TOTP. Integrazione completa:
[fail2ban.md](fail2ban.md).

### Sanitizzazione dell'HTML dei messaggi

Il corpo dei messaggi è sanitizzato nel browser (DOMParser inerte +
allowlist): tag e attributi ristretti (niente `script`/`iframe`/`object`/`on*`…),
blocco degli schemi `javascript:`/`vbscript:`/`data:`, neutralizzazione degli
stili insidiosi (`position:fixed`, contenuto nascosto), prefissaggio delle
classi. La CSP è il secondo strato.

### Invio di messaggi

- **From**: whitelist stretta - l'indirizzo dell'account o un'identità
  dichiarata (IDN accettato in entrambe le forme); ogni altro valore è
  rifiutato (403).
- **Destinatari**: i campi To/Cc/Bcc passano per un parse stretto - un
  segmento invalido non è scartato silenziosamente, l'invio è rifiutato (400)
  con la lista degli indirizzi incompresi.
- **CR/LF**: neutralizzati nell'oggetto, negli header di threading
  (In-Reply-To, References), nei nomi di cartelle referenziati e nei
  parametri MIME degli allegati (`filename=`).
- **Bcc**: l'header è rimosso dai dati inviati al server SMTP ma conservato
  nella copia "Elementi inviati" (visibile solo al mittente).
- **Editor**: l'iframe TinyMCE porta una `Content-Security-Policy` che blocca
  ogni caricamento remoto - il contenuto di un messaggio inoltrato/incollato
  non può esfiltrare una conferma di lettura all'apertura.
- **Allegati temporanei**: token con scope utente, nome sanitizzato
  (`basename` + caratteri di controllo eliminati), purga a 24 h.
- **Errori SMTP**: il client riceve un messaggio generico; le risposte grezze
  del server sono esposte solo se `labs.smtp_show_server_errors` è attivo
  (diagnostica, può rivelare dettagli interni).

### OpenPGP

La cifratura/decifratura avviene **interamente nel browser** (OpenPGP.js). Il
server memorizza solo il portachiavi armored; le chiavi private sono
protette dalla loro passphrase e non sono mai decifrate lato server. **Solo
il corpo del messaggio è cifrato**: l'invio di allegati è rifiutato quando la
cifratura è attiva (uscirebbero in chiaro).

### Proxy delle immagini

Le immagini remote passano per `/api/proxy`: solo `http(s)`, allowlist
opzionale (wildcards), **anti-SSRF** (rifiuto degli IP privati/riservati,
rivalidato a **ogni** redirect - 3 massimo), timeout, dimensione limitata. Il
MIME reale del corpo è verificato contro una **allowlist bitmap** (png, jpeg,
gif, webp, avif, bmp, ico) - SVG e ogni tipo attivo sono rifiutati - e la
risposta porta `Content-Security-Policy: sandbox` + `nosniff`. I log
conservano solo scheme+host+path (mai la query, che può contenere token).

### Gestione degli IP e dei proxy

`X-Forwarded-For`/`X-Forwarded-Proto` sono presi in conto solo se
`REMOTE_ADDR` figura in `trusted_proxies` (CIDR). Il rilevamento HTTPS
(`isHttps`) segue la stessa regola.

## 2. Raccomandazioni di hardening

- **Document root = `public/`** soltanto; non puntare mai sulla radice del
  progetto. Le cartelle private (`app/`, `config/`, `storage/`…) hanno i loro
  `.htaccess` di protezione, ma è solo una rete di sicurezza.
- **HTTPS obbligatorio**: attivate `force_https` + `hsts` in produzione.
- **Dietro un reverse proxy** (Nginx, HAProxy, Cloudflare…): compilate
  `trusted_proxies` per rendere affidabili rate-limit, allowlist e log; senza,
  ogni `X-Forwarded-*` è ignorato.
- **Limitate l'admin**: `admin_ip_allowlist` (IP/CIDR CSV) e un `admin_path`
  non banale; attivate il **TOTP admin** via il plugin `two-factor-auth` (il
  segreto `admin_totp_secret` è richiesto solo finché il plugin è attivo).
- **Account admin locale**: preferite `admin_user` + `admin_password_hash`
  (password forte) al fallback IMAP per l'amministrazione.
- **Backup di `app.key`** fuori dal webroot; mai versionarlo.
- **`debug = false`** in produzione (non esporre gli errori interni).
- **La procedura `/setup`** è integrata nel core: risponde 404 una volta
  posto il lock `app/storage/INSTALLED` - nessun file di installazione da
  eliminare nel webroot.
- Verificate `ssl.verify_certificate`: `false` è accettabile solo per
  certificati autofirmati controllati.
- In modalità manutenzione, `maintenance = true` serve una pagina di
  manutenzione dedicata in 503 su `/` (utenti connessi spostati alla prossima
  richiesta API, sessione conservata) e taglia l'`/api/*` utente (admin e
  `/api/ping` restano attivi).

## 3. Limiti noti

- Il repository `GitHubClient::REPO` è bloccato su `WatT-For/MXWebMail`: i
  pacchetti installati (zip core della release, file plugin letti nel repo al
  ref `update.plugins_ref` via l'API git + `raw.githubusercontent.com`,
  ciascuno verificato per sha1 di blob git) provengono solo da quel
  repository - nessun URL arbitrario configurabile. Il pacchetto core è
  verificato a due livelli: somma sha256 dell'asset (file `<zip>.sha256`
  della release) poi `files.sha256` (manifest per-file incorporato,
  controllato dopo l'estrazione); le costanti `APP_REQ_PHP`/`APP_REQ_FROM`
  dell'`App.php` del pacchetto dichiarano i prerequisiti (versione PHP,
  versione sorgente minima) applicati prima della copia. Il repo ufficiale è
  **pubblico**: l'API, gli asset e i `raw` funzionano senza token (la quota
  anonima dell'API GitHub è coperta dalla cache `cache.update_ttl`). Un PAT
  GitHub opzionale (accesso `Contents: Read` al repo) configurato in
  `config.php` può elevare la quota o servire un fork privato via `update.repo`
  - gli asset passano allora per l'endpoint `releases/assets/<id>` (il
  `browser_download_url` restituisce 404 su repo privato anche autenticato).
  `update.plugins_local` e `update.manifest_url` (manifest di release
  alternativo) sono riservati a dev/test - modificabili solo via
  `config.php`, mai via l'API admin. Durante la copia dei file, il flag
  `storage/update/applying` taglia l'API utente (503, scade dopo 15 min).
- Il cambio SQLite > MariaDB non migra i dati (schema vergine).
- Le chiavi private OpenPGP sono memorizzate armored lato server (la
  passphrase protegge la chiave stessa, nessun incapsulamento supplementare).
