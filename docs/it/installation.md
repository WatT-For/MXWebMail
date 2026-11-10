# Installazione

*Tradotto dal francese - la versione francese fa fede.*

## 1. Prerequisiti

- **PHP ≥ 8.4** con le estensioni: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, e `pdo_sqlite` **o**
  `pdo_mysql`. `ldap` è opzionale (solo plugin LDAP).
  **`opcache` fortemente raccomandato in produzione** (`opcache.enable=1`) -
  l'applicazione carica decine di file PHP per richiesta.
- Un server **IMAP** accessibile (e SMTP, ManageSieve opzionale) per la posta.
- **Nessuna dipendenza da installare**: il repo è autosufficiente (autoloader
  interno, MailSo, Sabre, asset e librerie JS vendored).
- Il document root del web server deve essere **`public/`**, mai la radice
  del progetto.

## 2. Struttura delle cartelle

```
public/            radice web - index.php, define.php, assets/, .htaccess
app/               tutto il non-pubblico (spostabile fuori dalla radice web)
app/src/           codice applicativo + librerie vendored
app/config/        config.php (non versionato) + config.example.php
app/plugins/       estensioni (vuota all'installazione - plugin dal catalogo GitHub)
app/templates/     temi/pagine
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - mai nel webroot
```

`public/define.php` definisce `MXWM_APP_DIR` (percorso della cartella `app/`).
Per portare `app/` fuori dalla radice web, spostate la cartella e adattate
quel percorso (assoluto o relativo).

## 3. Installazione via la procedura `/setup/` (raccomandata)

1. Puntate il vhost su `public/`.
2. Aprite `https://webmail.example.com/`. Finché il lock
   `app/storage/INSTALLED` non esiste, ogni URL è reindirizzata a `/setup/` -
   la procedura è servita dall'applicazione stessa (nessun file di
   installazione nel webroot).
3. La procedura si svolge in 5 passi:
   - **Prerequisiti**: verifica PHP, le estensioni, la presenza dei file
     vendored e la scrivibilità di `app/storage/` (bloccante) e
     `app/config/` (non bloccante - il `config.php` può essere creato a
     mano).
   - **Applicazione**: nome mostrato (`app_name`), percorso admin
     (`admin_path`), dimensione cumulata massima degli allegati
     (`attachments_max_total`, limitata a 25 MB di default e vincolata da
     `upload_max_filesize`/`post_max_size`). Il limite per file
     `attachment_max_size` è scritto derivato: `min(25,
     attachments_max_total)`.
   - **Database**: SQLite (default) o MariaDB/MySQL. Con MariaDB, viene
     effettuato un test di connessione reale; il database è creato
     (`utf8mb4`) all'installazione.
   - **Amministratore**: account admin locale (`admin_user` + password ≥ 8
     caratteri). Se lasciate questi campi vuoti, il login admin ricade su un
     utente `is_admin` autenticato via IMAP (vedi
     [administration.md](administration.md)).
   - **Riepilogo**: controllo dei valori poi pulsante **Installa**.
4. L'installazione scrive `app/config/config.php` (un file esistente è
   archiviato come `config.<timestamp>.bak`), applica le migrazioni, tenta di
   **rilevare il dominio di hosting** (SRV > autoconfig Mozilla > MX >
   sondaggio) e lo aggiunge alla tabella `domains`, poi pone il lock
   `app/storage/INSTALLED`.
   Al clic su **Installa**, una schermata di progresso a piena pagina
   appare: il rilevamento del dominio può far durare l'operazione fino a ~2
   minuti - non chiudete la pagina (l'installazione terminerebbe comunque
   lato server). Tre domini pubblici sono anche **pre-autorizzati come
   account secondari** - `gmail.com`, `outlook.com`, `yahoo.com` (OAuth2
   richiesto, login primario rifiutato; vedi [domaines.md](domaines.md) §8).
   Eliminabili in admin > *Domini*.
5. **Dopo l'installazione**: `/setup` risponde ormai 404 (niente da
   eliminare) e **fate il backup di `app/storage/app.key`** (vedi §6).

> Se `app/config/` non è scrivibile, la procedura mostra il contenuto di
> `config.php` da creare manualmente. L'installazione continua una volta il
> file in posizione.

## 4. Installazione manuale

```bash
cp app/config/config.example.php app/config/config.php   # modificare i valori
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

La migrazione è comunque applicata al primo accesso (`App::db()`), basta
quindi creare `config.php` poi `app/storage/INSTALLED` (il contenuto importa
poco, solo `is_file()` è testato). `app/storage/app.key` è generato
automaticamente alla prima cifratura di credenziale.

## 5. Distribuzione web

### Apache

```apache
<VirtualHost *:443>
    ServerName webmail.example.com
    DocumentRoot /var/www/mxwebmail/public
    <Directory /var/www/mxwebmail/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

L'`.htaccess` radice può riscrivere verso `public/` come rete di transizione,
ma il document root ufficiale è `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Deve essere >= attachment_max_size (25 MB di default).
    # attachments_max_total (cumulo allegati) è verificato all'invio: ogni
    # file arriva in richiesta separata.
    client_max_body_size 30m;

    # Dotfile mai serviti
    location ~ /\.(?!well-known) { deny all; }

    # Rete: cartella privata rifiutata anche se root punta per errore alla radice
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Asset versionati /assets/vX.Y.Z/… > file reale (equivalente del
    # RewriteRule dell'.htaccess di Apache). TTL lungo: la versione cambia a
    # ogni release. Gli URL /assets/theme|plugin/ passano per try_files
    # > PHP (i loro header restano gestiti dalla config "http").
    location ~ ^/assets/v[0-9.]+/ {
        rewrite ^/assets/v[0-9.]+/(.*)$ /assets/$1 last;
    }
    location ~ ^/assets/(img|js|vendor)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Osservazioni:

- Gli `.htaccess` sono inerti sotto Nginx: è il blocco `location ~ /\.` che
  nasconde i dotfile, e `^/(app|dist)` che protegge la cartella privata.
- `autoindex` è disattivato di default (equivalente di `Options -Indexes`).
- Compressione opzionale: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Sotto Apache, `public/.htaccess` copre già `mod_deflate` + i TTL degli
  asset; la compressione PHP (`http.gzip`) funziona in entrambi i casi.

### Prestazioni (raccomandazioni server)

- **HTTP/2 (Apache)**: `a2enmod http2` poi `Protocols h2 h2c http/1.1` nel
  VirtualHost (`h2` basta sotto TLS; `h2c` è utile solo ai client non
  browser - Edge/Firefox implementano h2 solo in HTTPS). `mod_http2` richiede
  `mpm_event` (già il default con PHP-FPM) - è incompatibile con
  `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` adattato al carico (il default 5 basta a
  malapena per qualche scheda/utente simultaneo: il poll, le immagini `cid:`,
  gli allegati e il proxy delle immagini parallelizzano le richieste).
- **OPcache**: attivo di default nel pacchetto PHP Debian
  (`opcache.enable=1` in FPM) - verificate `opcache_get_status()` in caso di
  dubbio.

## 6. Passi post-installazione

1. **Fate il backup di `app/storage/app.key`**: cifra tutte le credenziali
   memorizzate (password IMAP, segreti dei plugin). La sua perdita obbliga gli
   utenti a riconnettersi; non deve mai essere servito né versionato.
2. **Create almeno un dominio** nell'admin (pagina *Domini*): con
   `restrict_domains = true` (default), nessun login è possibile senza un
   dominio configurato. L'installer tenta di rilevarlo automaticamente.
3. **Installate i plugin** dall'admin, scheda *Estensioni*: lo zip di release
   non imbarca più i plugin - sono scaricati, aggiornati e disinstallati da
   quel catalogo (vedi
   [administration.md](administration.md#estensioni-plugin) e
   [plugins.md](plugins.md)). All'aggiornamento del core, i plugin già
   installati sono preservati (`app/plugins/` è escluso dalla copia).
4. Verificate `force_https` / `hsts` se siete dietro un reverse proxy (vedi
   [securite.md](securite.md) per `trusted_proxies`).
5. Impostate `admin_ip_allowlist` se il pannello admin deve essere ristretto
   a certi IP.
6. **Aggiornamenti**: si fanno dall'admin (*Informazioni*) via le GitHub
   Releases - verifica `files.sha256` per file, backup file + database
   SQLite, rollback assistito e migrazioni di database orchestrate. Dettaglio
   in [administration.md](administration.md#informazioni) e procedure di
   ripiego in [depannage.md](depannage.md#aggiornamenti).

## 7. URL principali

- `/` - webmail (login > cartelle > messaggi)
- `/{admin_path}` - pannello di amministrazione (default `/admin`)
- `#/@settings/<scheda>` - impostazioni utente (generale, identità, contatti,
  filtri Sieve, OpenPGP, schede dei plugin)
