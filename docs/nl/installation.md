# Installatie

*Vertaald uit het Frans - de Franse versie is leidend.*

## 1. Vereisten

- **PHP ≥ 8.4** met de extensies: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, en `pdo_sqlite` **of**
  `pdo_mysql`. `ldap` is optioneel (alleen LDAP-plugins).
  **`opcache` sterk aanbevolen in productie** (`opcache.enable=1`) - de
  applicatie laadt tientallen PHP-bestanden per request.
- Een bereikbare **IMAP**-server (en SMTP, ManageSieve optioneel) voor de
  mail.
- **Geen dependencies te installeren**: de repo is zelfvoorzienend
  (eigen autoloader, MailSo, Sabre, assets en gevendoreerde JS-libs).
- De document root van de webserver moet **`public/`** zijn, nooit de
  projectroot.

## 2. Mapstructuur

```
public/            webroot - index.php, define.php, assets/, .htaccess
app/               al het niet-publieke (verplaatsbaar buiten de webroot)
app/src/           applicatiecode + gevendoreerde libraries
app/config/        config.php (niet gecommit) + config.example.php
app/plugins/       extensies (leeg bij installatie - plugins uit de GitHub-catalogus)
app/templates/     thema's/pagina's
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - nooit in webroot
```

`public/define.php` definieert `MXWM_APP_DIR` (pad van de `app/`-map). Om
`app/` buiten de webroot te brengen, verplaats de map en pas dat pad aan
(absoluut of relatief).

## 3. Installatie via de `/setup/`-wizard (aanbevolen)

1. Wijs de vhost op `public/`.
2. Open `https://webmail.example.com/`. Zolang het slot
   `app/storage/INSTALLED` niet bestaat, wordt elke URL doorgestuurd naar
   `/setup/` - de wizard wordt door de applicatie zelf geserveerd (geen
   installatiebestand in de webroot).
3. De wizard verloopt in 5 stappen:
   - **Vereisten**: controleert PHP, de extensies, de aanwezigheid van de
     gevendoreerde bestanden en de beschrijfbaarheid van `app/storage/`
     (blokkerend) en `app/config/` (niet blokkerend - de `config.php` kan
     met de hand worden aangemaakt).
   - **Applicatie**: weergavenaam (`app_name`), admin-pad (`admin_path`),
     maximale cumulatieve bijlaggrootte (`attachments_max_total`, begrensd
     tot 25 MB standaard en afgetopt door
     `upload_max_filesize`/`post_max_size`). De limiet per bestand
     `attachment_max_size` wordt afgeleid geschreven: `min(25,
     attachments_max_total)`.
   - **Database**: SQLite (standaard) of MariaDB/MySQL. Bij MariaDB wordt
     een echte verbindingstest gedaan; de database wordt aangemaakt
     (`utf8mb4`) bij de installatie.
   - **Beheerder**: lokaal admin-account (`admin_user` + wachtwoord ≥ 8
     tekens). Laat u deze velden leeg, dan valt de admin-login terug op een
     `is_admin`-gebruiker geauthenticeerd via IMAP (zie
     [administration.md](administration.md)).
   - **Samenvatting**: controle van de waarden, daarna knop **Installeren**.
4. De installatie schrijft `app/config/config.php` (een bestaand bestand
   wordt gearchiveerd als `config.<timestamp>.bak`), past de migraties toe,
   probeert het **hostingdomein te detecteren** (SRV > Mozilla-autoconfig >
   MX > sondering) en voegt het aan de tabel `domains` toe, daarna wordt het
   slot `app/storage/INSTALLED` gezet.
   Bij de klik op **Installeren** verschijnt een voortgangsscherm op volle
   pagina: de domeindetectie kan de bewerking tot ~2 minuten laten duren -
   sluit de pagina niet (de installatie loopt server-side toch door). Drie
   publieke domeinen worden ook **vooraf als subaccounts geautoriseerd** -
   `gmail.com`, `outlook.com`, `yahoo.com` (OAuth2 vereist, primaire login
   geweigerd; zie [domaines.md](domaines.md) §8). Verwijderbaar in admin >
   *Domeinen*.
5. **Na de installatie**: `/setup` antwoordt nu 404 (niets te verwijderen)
   en **maak een backup van `app/storage/app.key`** (zie §6).

> Als `app/config/` niet beschrijfbaar is, toont de wizard de handmatig aan
> te maken `config.php`-inhoud. De installatie gaat verder zodra het bestand
> op zijn plaats staat.

## 4. Handmatige installatie

```bash
cp app/config/config.example.php app/config/config.php   # waarden aanpassen
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

De migratie wordt toch toegepast bij de eerste toegang (`App::db()`), dus
volstaat het `config.php` aan te maken en daarna `app/storage/INSTALLED` (de
inhoud doet er weinig toe, alleen `is_file()` wordt getest).
`app/storage/app.key` wordt automatisch aangemaakt bij de eerste
credential-versleuteling.

## 5. Webdeployment

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

De root-`.htaccess` kan naar `public/` herschrijven als transitievangnet,
maar de officiële document root is `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Moet >= attachment_max_size zijn (25 MB standaard).
    # attachments_max_total (bijlagen-cumul) wordt bij het verzenden
    # gecontroleerd: elk bestand komt in een aparte request binnen.
    client_max_body_size 30m;

    # Dotfiles nooit geserveerd
    location ~ /\.(?!well-known) { deny all; }

    # Vangnet: privémap geweigerd ook als root per vergissing op de root wijst
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Geversioeerde assets /assets/vX.Y.Z/… > echt bestand (equivalent van de
    # RewriteRule van Apache's .htaccess). Lange TTL: de versie verandert bij
    # elke release. De URL's /assets/theme|plugin/ passeren via try_files
    # > PHP (hun headers blijven door de "http"-config beheerd).
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

Opmerkingen:

- De `.htaccess`-bestanden zijn inert onder Nginx: het blok `location ~ /\.`
  verbergt de dotfiles, en `^/(app|dist)` beschermt de privémap.
- `autoindex` staat standaard uit (equivalent van `Options -Indexes`).
- Optionele compressie: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Onder Apache dekt `public/.htaccess` al `mod_deflate` + de asset-TTL's; de
  PHP-compressie (`http.gzip`) werkt in beide gevallen.

### Performance (serveraanbevelingen)

- **HTTP/2 (Apache)**: `a2enmod http2` daarna `Protocols h2 h2c http/1.1` in
  de VirtualHost (`h2` volstaat onder TLS; `h2c` is alleen nuttig voor
  niet-browser-clients - Edge/Firefox implementeren h2 enkel in HTTPS).
  `mod_http2` vereist `mpm_event` (al de standaard bij PHP-FPM) - het is
  onverenigbaar met `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` aangepast aan de load (de standaard 5
  volstaat nauwelijks voor enkele tabs/gebruikers tegelijk: de poll, de
  `cid:`-afbeeldingen, de bijlagen en de afbeeldingen-proxy paralleliseren
  de requests).
- **OPcache**: standaard actief in het Debian PHP-pakket (`opcache.enable=1`
  in FPM) - controleer `opcache_get_status()` bij twijfel.

## 6. Stappen na installatie

1. **Backup `app/storage/app.key`**: versleutelt alle opgeslagen credentials
   (IMAP-wachtwoorden, plugin-geheimen). Zijn verlies dwingt gebruikers
   opnieuw in te loggen; hij mag nooit geserveerd of gecommit worden.
2. **Maak minstens een domein aan** in de admin (pagina *Domeinen*): met
   `restrict_domains = true` (standaard) is geen login mogelijk zonder een
   geconfigureerd domein. De installer probeert het automatisch te
   detecteren.
3. **Installeer de plugins** via de admin, tab *Extensies*: de release-zip
   bevat geen plugins meer - ze worden gedownload, bijgewerkt en
   verwijderd via die catalogus (zie
   [administration.md](administration.md#extensies-plugins) en
   [plugins.md](plugins.md)). Bij een kern-update blijven geinstalleerde
   plugins behouden (`app/plugins/` wordt van de kopie uitgesloten).
4. Controleer `force_https` / `hsts` als u achter een reverse proxy zit (zie
   [securite.md](securite.md) voor `trusted_proxies`).
5. Stel `admin_ip_allowlist` in als het admin-paneel tot bepaalde IP's
   beperkt moet blijven.
6. **Updates**: gebeuren via de admin (*Over*) via GitHub Releases -
   `files.sha256`-verificatie per bestand, backup van bestanden + SQLite-
   database, begeleide rollback en georkestreerde databasemigraties. Detail
   in [administration.md](administration.md#over) en reserveprocedures in
   [depannage.md](depannage.md#updates).

## 7. Hoofd-URL's

- `/` - webmail (login > mappen > berichten)
- `/{admin_path}` - beheerpaneel (standaard `/admin`)
- `#/@settings/<tab>` - gebruikersinstellingen (algemeen, identiteiten,
  contacten, Sieve-filters, OpenPGP, plugin-tabs)
