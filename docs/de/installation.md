# Installation

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

## 1. Voraussetzungen

- **PHP ≥ 8.4** mit den Erweiterungen: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom` und `pdo_sqlite` **oder**
  `pdo_mysql`. `ldap` ist optional (nur LDAP-Plugins).
  **`opcache` in Produktion dringend empfohlen** (`opcache.enable=1`) - die
  Anwendung lädt pro Request Dutzende PHP-Dateien.
- Ein erreichbarer **IMAP**-Server (plus SMTP, ManageSieve optional) für Mail.
- **Keine Abhängigkeit zu installieren**: das Repo ist autark (hausgemachter
  Autoloader, MailSo, Sabre, vendorte Assets und JS-Bibliotheken).
- Der Document-Root des Webservers muss **`public/`** sein, niemals der
  Projektstamm.

## 2. Ordnerstruktur

```
public/            Web-Root - index.php, define.php, assets/, .htaccess
app/               alles Nicht-Öffentliche (kann aus dem Web-Root verschoben werden)
app/src/           Anwendungscode + vendorte Bibliotheken
app/config/        config.php (nicht versioniert) + config.example.php
app/plugins/       Erweiterungen (bei Installation leer - Plugins aus dem GitHub-Katalog)
app/templates/     Themes/Seiten
app/storage/       Laufzeit: db, cache, logs, tmp, app.key, INSTALLED - nie im Webroot
```

`public/define.php` definiert `MXWM_APP_DIR` (Pfad des `app/`-Ordners). Um
`app/` aus dem Web-Root zu nehmen, den Ordner verschieben und diesen Pfad
anpassen (absolut oder relativ).

## 3. Installation über den `/setup/`-Assistenten (empfohlen)

1. Den vhost auf `public/` zeigen lassen.
2. `https://webmail.example.com/` öffnen. Solange die Sperre
   `app/storage/INSTALLED` nicht existiert, wird jede URL auf `/setup/`
   umgeleitet - der Assistent wird von der Anwendung selbst ausgeliefert (keine
   Installationsdatei im Webroot).
3. Der Assistent läuft in 5 Schritten:
   - **Voraussetzungen**: prüft PHP, Erweiterungen, Vorhandensein der vendorten
     Dateien und Schreibbarkeit von `app/storage/` (blockierend) und
     `app/config/` (nicht blockierend - `config.php` kann von Hand erstellt
     werden).
   - **Anwendung**: Anzeigename (`app_name`), Admin-Pfad (`admin_path`),
     maximale kumulative Anhanggröße (`attachments_max_total`, standardmäßig
     auf 25 MB begrenzt und durch `upload_max_filesize`/`post_max_size`
     beschränkt). Das Limit pro Datei `attachment_max_size` wird abgeleitet
     geschrieben: `min(25, attachments_max_total)`.
   - **Datenbank**: SQLite (Standard), MariaDB >= 10.x oder MySQL >= 8.0. Bei
     MariaDB/MySQL wird ein echter Verbindungstest durchgeführt; die Datenbank
     wird bei der Installation angelegt (`utf8mb4`).
   - **Administrator**: lokales Admin-Konto (`admin_user` + Passwort ≥ 8
     Zeichen). Lassen Sie diese Felder leer, fällt der Admin-Login auf einen
     via IMAP authentifizierten `is_admin`-Benutzer zurück (siehe
     [administration.md](administration.md)).
   - **Zusammenfassung**: Prüfung der Werte, dann Schaltfläche
     **Installieren**.
4. Die Installation schreibt `app/config/config.php` (eine bestehende Datei
   wird als `config.<Zeitstempel>.bak` archiviert), wendet die Migrationen an,
   versucht die **Hosting-Domain zu erkennen** (SRV > Mozilla-Autoconfig > MX >
   Sondierung), trägt sie in die `domains`-Tabelle ein und setzt dann die
   Sperre `app/storage/INSTALLED`.
   Beim Klick auf **Installieren** erscheint ein ganzseitiger
   Fortschrittsbildschirm: die Domain-Erkennung kann den Vorgang bis zu ~2
   Minuten dauern lassen - die Seite nicht schließen (die Installation würde
   serverseitig trotzdem fertig). Drei öffentliche Domains werden außerdem
   **als Zweitkonten vorab autorisiert** - `gmail.com`, `outlook.com`,
   `yahoo.com` (OAuth2 erforderlich, Primär-Login abgelehnt; siehe
   [domaines.md](domaines.md) §8). Löschbar in Admin > *Domains*.
5. **Nach der Installation**: `/setup` antwortet nun 404 (nichts zu löschen)
   und **`app/storage/app.key` sichern** (siehe §6).

> Ist `app/config/` nicht beschreibbar, zeigt der Assistent den manuell zu
> erstellenden `config.php`-Inhalt. Die Installation geht weiter, sobald die
> Datei vorhanden ist.

## 4. Manuelle Installation

```bash
cp app/config/config.example.php app/config/config.php   # Werte anpassen
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

Die Migration wird ohnehin beim ersten Zugriff angewendet (`App::db()`), es
genügt also, `config.php` und dann `app/storage/INSTALLED` anzulegen (der
Inhalt ist egal, nur `is_file()` wird getestet). `app/storage/app.key` wird
bei der ersten Verschlüsselung von Zugangsdaten automatisch erzeugt.

## 5. Web-Bereitstellung

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

Das Root-`.htaccess` kann übergangsweise auf `public/` umschreiben, aber der
offizielle Document-Root ist `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Muss >= attachment_max_size sein (25 MB standardmäßig).
    # attachments_max_total (kumulative Anhänge) wird beim Senden geprüft:
    # jede Datei kommt in einer eigenen Anfrage.
    client_max_body_size 30m;

    # Dotfiles nie ausliefern
    location ~ /\.(?!well-known) { deny all; }

    # Netz: privater Ordner verweigert, selbst wenn root irrtümlich auf den Stamm zeigt
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Versionierte Assets /assets/vX.Y.Z/… > reale Datei (Äquivalent des
    # Apache-.htaccess-RewriteRule). Lange TTL: die Version ändert sich bei
    # jeder Release. /assets/theme|plugin/-URLs gehen über try_files
    # > PHP (ihre Header bleiben von der "http"-Konfig verwaltet).
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

Anmerkungen:

- `.htaccess`-Dateien sind unter Nginx inert: der Block `location ~ /\.`
  verbirgt die Dotfiles, und `^/(app|dist)` schützt den privaten Ordner.
- `autoindex` ist standardmäßig deaktiviert (Äquivalent von
  `Options -Indexes`).
- Optionale Kompression: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Unter Apache deckt `public/.htaccess` bereits `mod_deflate` + Asset-TTLs ab;
  die PHP-Kompression (`http.gzip`) funktioniert in beiden Fällen.

### Performance (Server-Empfehlungen)

- **HTTP/2 (Apache)**: `a2enmod http2`, dann `Protocols h2 h2c http/1.1` im
  VirtualHost (`h2` allein genügt unter TLS; `h2c` ist nur für Nicht-Browser-Clients
  nützlich - Edge/Firefox implementieren h2 nur über HTTPS). `mod_http2`
  verlangt `mpm_event` (bereits Standard mit PHP-FPM) - es ist inkompatibel mit
  `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` an die Last angepasst (der Standard 5 reicht
  kaum für einige gleichzeitige Tabs/Benutzer: Polling, `cid:`-Bilder, Anhänge
  und der Bild-Proxy parallelisieren die Anfragen).
- **OPcache**: im Debian-PHP-Paket standardmäßig aktiv (`opcache.enable=1` in
  FPM) - im Zweifel `opcache_get_status()` prüfen.

## 6. Schritte nach der Installation

1. **`app/storage/app.key` sichern**: er verschlüsselt alle gespeicherten
   Zugangsdaten (IMAP-Passwörter, Plugin-Geheimnisse). Sein Verlust zwingt die
   Benutzer zur Neuanmeldung; er darf nie ausgeliefert oder versioniert werden.
2. **Mindestens eine Domain anlegen** im Admin (Seite *Domains*): mit
   `restrict_domains = true` (Standard) ist ohne konfigurierte Domain kein
   Login möglich. Der Installer versucht sie automatisch zu erkennen.
3. **Plugins installieren** aus dem Admin, Tab *Erweiterungen*: der
   Release-Zip liefert keine Plugins mehr - sie werden aus diesem Katalog
   heruntergeladen, aktualisiert und deinstalliert (siehe
   [administration.md](administration.md#erweiterungen-plugins) und
   [plugins.md](plugins.md)). Bei einem Kern-Update bleiben bereits
   installierte Plugins erhalten (`app/plugins/` ist von der Kopie
   ausgeschlossen).
4. `force_https` / `hsts` prüfen, wenn Sie hinter einem Reverse Proxy sind
   (siehe [securite.md](securite.md) für `trusted_proxies`).
5. `admin_ip_allowlist` setzen, wenn das Admin-Panel auf bestimmte IPs
   beschränkt werden soll.
6. **Updates**: erfolgen aus dem Admin (*Über*) via GitHub Releases -
   `files.sha256`-Prüfung pro Datei, Datei- + SQLite-Datenbank-Backup,
   assistiertes Rollback und orchestrierte Datenbank-Migrationen. Details in
   [administration.md](administration.md#über) und Notfallverfahren in
   [depannage.md](depannage.md#aktualisierungen).

## 7. Wichtigste URLs

- `/` - Webmail (Login > Ordner > Nachrichten)
- `/{admin_path}` - Admin-Panel (Standard `/admin`)
- `#/@settings/<tab>` - Benutzereinstellungen (Allgemein, Identitäten,
  Kontakte, Sieve-Filter, OpenPGP, Plugin-Tabs)
