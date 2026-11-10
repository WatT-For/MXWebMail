# Risoluzione dei problemi / FAQ

*Tradotto dal francese - la versione francese fa fede.*

## Installazione

**Tutti gli URL reindirizzano a `/setup/`**
Normale: il lock `app/storage/INSTALLED` è assente. Completate
l'installazione, o create il file manualmente dopo aver scritto `config.php`.

**La procedura segnala "config/ non scrivibile"**
Avviso non bloccante: copiate il contenuto mostrato in
`app/config/config.php` a mano, poi rilanciate l'installazione. Verificate
anche che `app/storage/` sia scrivibile (bloccante).

**Il test di connessione MariaDB fallisce**
Verificate host/porta/credenziali, e che l'utente abbia i diritti per creare
il database (`CREATE DATABASE`). Il charset deve essere `utf8mb4`.

## Connessione / login

**Nessun utente può connettersi dopo l'installazione**
Con `restrict_domains = true` (predefinito), serve **almeno un dominio
nominato** in tabella (il wildcard `*` è ignorato per questa verifica).
L'installer tenta di rilevarlo; altrimenti createlo in *Domini*.

**Login rifiutato senza motivo (account bloccato)**
- Troppi tentativi: attendete `login_lock_seconds` (predefinito 300 s), o
  purgate `app/storage/cache/login_attempts.json`.
- I plugin `black-list`/`white-list` possono rifiutare il login (404/403 o
  errore generico secondo `auth_error`).
- Un utente `disabled` è respinto anche con una sessione valida.

**Il login admin non funziona**
- Account locale (`admin_user`): verificate `admin_password_hash` e
  l'eventuale TOTP.
- Fallback IMAP: l'utente deve avere `is_admin = 1`.
- `admin_ip_allowlist` può bloccare il vostro IP.

## Server mail

**Impossibile connettersi al server IMAP/SMTP**
Usate il pulsante **Testa la connessione** in *Domini* (risultato per
protocollo). Cause frequenti: porta/cifratura incoerente (993/ssl vs 143/tls),
certificato autofirmato (vedi
`ssl.verify_certificate`/`ssl.allow_self_signed`), o firewall.

**Certificato autofirmato rifiutato**
`ssl.verify_certificate = false` o `ssl.allow_self_signed = true` (da usare
solo con piena consapevolezza).

**Il dominio non è rilevato automaticamente**
`autodetect.enabled` è disattivato di default. Attivatelo, o usate il
pulsante **Rileva** della pagina *Domini*.

**"Segnala come spam" non filtra le mail successive**
Il pulsante sposta il messaggio nella cartella Posta indesiderata (o pone il
keyword IMAP `$Junk` se il server non ha cartella Junk) - non insegna nulla
al server. I filtri Sieve si applicano solo alla consegna: il modello
*Anti-spam* della procedura (Impostazioni > Filtri) smista le mail marcate
`X-Spam-Flag: YES` dall'MTA. L'apprendimento automatico (spam/ham) richiede
configurazione lato server - Dovecot IMAPSieve (script report-spam/ham
innescato dalla copia verso/da Junk, che il MOVE dell'app innesca) o
rspamd/sa-learn.

## Sicurezza / HTTPS

**Il sito resta in HTTP dietro un reverse proxy**
Compilate `trusted_proxies` (IP del proxy) affinché `X-Forwarded-Proto` sia
onorato, poi attivate `force_https` / `hsts`.

**Il rate-limit o l'allowlist non vedono l'IP reale**
Stessa causa: senza `trusted_proxies`, `REMOTE_ADDR` è usato così com'è.

**Il favicon non si mostra**
Il favicon caricato deve essere ≤ 512 KB (ico/png/svg/jpg/webp); altrimenti
resta l'icona predefinita.

## Dati / log

**Perdita di `app.key`**
Tutte le credenziali memorizzate diventano indecifrabili: gli utenti devono
riconnettersi. Fate sempre il backup di `app.key`.

**Dove sono i log?**
`app/storage/logs/app.log` (applicativo), `mail.log` (protocolli
IMAP/SMTP/Sieve, se `logs.enabled`), e il log di auth (`auth_logging`).
Visibili nell'admin (*Log*). Integrazione fail2ban:
[fail2ban.md](fail2ban.md).

**Passare a MariaDB ha svuotato i dati**
Comportamento atteso: il cambio di `db_dsn` riparte su uno schema vergine (i
file dei contatti per utente sono conservati). Non c'è strumento di
migrazione dati.

## Aggiornamenti

**"Fallimento: Non trovato su GitHub … repo privato o risorsa assente"**
Il repository ufficiale è bloccato (`GitHubClient::REPO` =
`WatT-For/MXWebMail`). GitHub restituisce 404 - non 403 - per una risorsa
assente o un accesso insufficiente; il messaggio indica l'endpoint colpevole
(`releases/latest`, `commits/…` o un percorso `raw` del catalogo plugin). Un
404 persistente significa in genere una risorsa assente (nessuna release
pubblicata, `update.plugins_ref` sconosciuto).

**HTTP 404 su `…/releases/download/…` (catalogo plugin o download di aggiornamento)**
Su repo privato, il `browser_download_url` degli asset restituisce 404 anche
con token valido. Corretto dalla 0.36.1: gli asset sono scaricati via
l'endpoint API `releases/assets/<id>` quando un PAT è configurato in
`config.php`.

**Il sito risponde 503 "update" / pagina di manutenzione durante un aggiornamento**
Normale: il flag `app/storage/update/applying` taglia l'API utente durante la
copia dei file (mai mix di codice vecchio/nuovo). È rimosso alla fine; se
resta dopo un crash, scade da solo dopo 15 min - o eliminate
`app/storage/update/applying` manualmente.

**"Migrazioni in attesa" dopo un aggiornamento**
La finalizzazione (`/api/admin/update/finalize`) non è girata: pulsante
*Finalizza* in *Informazioni*, o da CLI `php app/bin/post-update.php`. Senza
azione, le migrazioni si applicano comunque alla prossima richiesta
(migrazione lazy), ma è preferibile controllarle.

**Tornare indietro dopo un aggiornamento (rollback)**
Pulsante *Ripristina* in *Informazioni*: ripristina i file da
`app/storage/update/backup-<ts>` (gli ultimi 3 backup sono conservati),
elimina i file creati e ricopia i database SQLite salvati. Sotto **MariaDB**,
le migrazioni non sono reversibili: ripristinate prima il database dal
vostro `mysqldump`, poi rilanciate il rollback (viene proposta la conferma
forzata). Per ripartire da zero: reinstallate il pacchetto originale e
ripristinate `app/storage/` (inclusi `app.key` e `db/`).

**Banner "schema del database più recente del codice"**
`meta.schema_ahead`: il codice installato è più vecchio dello schema
(rollback file senza ripristino del database, o pacchetto più vecchio
deployato). Ripristinate il database o riapplicate la versione
corrispondente; le migrazioni restano sospese finché esiste il gap.

## Varie

**"Reinvia così com'è" (bounce): il destinatario vede la mail come spam?**
Possibile: il reinvio rompe SPF/DMARC sul finale (il server di invio non
corrisponde più al mittente originale) - comportamento normale dei bounce,
gli header `Resent-*` documentano la ritrasmissione. Per conservare
l'autenticazione, preferite l'inoltro classico.

**Sotto Nginx, le cartelle private sono accessibili?**
No, se il `root` punta su `public/` e il blocco `location ~ /\.` +
`^/(app|dist)` è presente. Non puntate mai il `root` sulla radice del
progetto.

**Errore 403 "cross_site" su una richiesta legittima**
La protezione `Sec-Fetch-*` ha respinto la richiesta. Se integrate un'API
esterna legittima, aggiungete una regola in `secfetch_allow`.

**Debug di un errore interno (500)**
Attivate temporaneamente `debug = true` (mai in produzione) per vedere il
messaggio, poi consultate `app.log`.
