# Installation

*Translated from French - the French version is authoritative.*

## 1. Requirements

- **PHP ≥ 8.4** with extensions: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, and `pdo_sqlite` **or**
  `pdo_mysql`. `ldap` is optional (LDAP plugins only).
  **`opcache` strongly recommended in production** (`opcache.enable=1`) - the
  application loads dozens of PHP files per request.
- An accessible **IMAP** server (plus SMTP, ManageSieve optional) for mail.
- **No dependency to install**: the repo is self-contained (home-made
  autoloader, MailSo, Sabre, vendored assets and JS libraries).
- The web server's document root must be **`public/`**, never the project root.

## 2. Folder structure

```
public/            web root - index.php, define.php, assets/, .htaccess
app/               everything non-public (can be moved out of the web root)
app/src/           application code + vendored libraries
app/config/        config.php (not versioned) + config.example.php
app/plugins/       extensions (empty at install - plugins from the GitHub catalog)
app/templates/     themes/pages
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - never in webroot
```

`public/define.php` defines `MXWM_APP_DIR` (path of the `app/` folder). To move
`app/` out of the web root, move the folder then adapt that path (absolute or
relative).

## 3. Installation via the `/setup/` wizard (recommended)

1. Point the vhost at `public/`.
2. Open `https://webmail.example.com/`. As long as the `app/storage/INSTALLED`
   lock does not exist, every URL redirects to `/setup/` - the wizard is served
   by the application itself (no install file in the webroot).
3. The wizard runs in 5 steps:
   - **Requirements**: checks PHP, extensions, presence of vendored files and
     writability of `app/storage/` (blocking) and `app/config/` (non-blocking -
     `config.php` can be created by hand).
   - **Application**: display name (`app_name`), admin path (`admin_path`),
     maximum cumulative attachment size (`attachments_max_total`, capped at
     25 MB by default and bounded by `upload_max_filesize`/`post_max_size`).
     The per-file limit `attachment_max_size` is written derived:
     `min(25, attachments_max_total)`.
   - **Database**: SQLite (default) or MariaDB/MySQL. With MariaDB, a real
     connection test is performed; the database is created (`utf8mb4`) at
     install time.
   - **Administrator**: local admin account (`admin_user` + password ≥ 8
     characters). If you leave these fields empty, admin login falls back to an
     `is_admin` user authenticated via IMAP (see
     [administration.md](administration.md)).
   - **Summary**: value check then **Install** button.
4. The install writes `app/config/config.php` (an existing file is archived as
   `config.<timestamp>.bak`), applies migrations, tries to **detect the hosting
   domain** (SRV > Mozilla autoconfig > MX > probing) and adds it to the
   `domains` table, then sets the `app/storage/INSTALLED` lock.
   On clicking **Install**, a full-page progress screen appears: domain
   detection can make the operation last up to ~2 minutes - do not close the
   page (the install would still finish server-side). Three public domains are
   also **pre-authorized as secondary accounts** - `gmail.com`, `outlook.com`,
   `yahoo.com` (OAuth2 required, primary login refused; see
   [domaines.md](domaines.md) §8). Deletable in admin > *Domains*.
5. **After installation**: `/setup` now answers 404 (nothing to delete) and
   **back up `app/storage/app.key`** (see §6).

> If `app/config/` is not writable, the wizard shows the `config.php` content
> to create manually. Installation continues once the file is in place.

## 4. Manual installation

```bash
cp app/config/config.example.php app/config/config.php   # edit the values
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

The migration is applied on first access anyway (`App::db()`), so creating
`config.php` then `app/storage/INSTALLED` is enough (the content does not
matter, only `is_file()` is tested). `app/storage/app.key` is generated
automatically on the first credential encryption.

## 5. Web deployment

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

The root `.htaccess` can rewrite to `public/` as a transitional net, but the
official document root is `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Must be >= attachment_max_size (25 MB by default).
    # attachments_max_total (cumulative attachments) is checked at send:
    # each file arrives in a separate request.
    client_max_body_size 30m;

    # Dotfiles never served
    location ~ /\.(?!well-known) { deny all; }

    # Net: private folder refused even if root mistakenly points to the root
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Versioned assets /assets/vX.Y.Z/… > real file (equivalent of the
    # Apache .htaccess RewriteRule). Long TTL: the version changes with
    # each release. /assets/theme|plugin/ URLs go through try_files
    # > PHP (their headers stay managed by the "http" config).
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

Notes:

- `.htaccess` files are inert under Nginx: the `location ~ /\.` block hides
  dotfiles, and `^/(app|dist)` protects the private folder.
- `autoindex` is disabled by default (equivalent of `Options -Indexes`).
- Optional compression: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Under Apache, `public/.htaccess` already covers `mod_deflate` + asset TTLs;
  PHP compression (`http.gzip`) works in both cases.

### Performance (server recommendations)

- **HTTP/2 (Apache)**: `a2enmod http2` then `Protocols h2 h2c http/1.1` in the
  VirtualHost (`h2` alone suffices under TLS; `h2c` is only useful to
  non-browser clients - Edge/Firefox only implement h2 over HTTPS).
  `mod_http2` requires `mpm_event` (already the default with PHP-FPM) - it is
  incompatible with `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` sized to the load (the default 5 barely
  suffices for a few simultaneous tabs/users: polling, `cid:` images,
  attachments and the image proxy parallelize requests).
- **OPcache**: enabled by default in the Debian PHP package
  (`opcache.enable=1` in FPM) - check `opcache_get_status()` when in doubt.

## 6. Post-installation steps

1. **Back up `app/storage/app.key`**: it encrypts all stored credentials
   (IMAP passwords, plugin secrets). Losing it forces users to log in again;
   it must never be served or committed.
2. **Create at least one domain** in the admin (*Domains* page): with
   `restrict_domains = true` (default), no login is possible without a
   configured domain. The installer tries to detect it automatically.
3. **Install plugins** from the admin, *Extensions* tab: the release zip no
   longer ships plugins - they are downloaded, updated and uninstalled from
   that catalog (see [administration.md](administration.md#extensions-plugins)
   and [plugins.md](plugins.md)). On core update, already-installed plugins are
   preserved (`app/plugins/` is excluded from the copy).
4. Check `force_https` / `hsts` if you are behind a reverse proxy (see
   [securite.md](securite.md) for `trusted_proxies`).
5. Set `admin_ip_allowlist` if the admin panel must be restricted to certain
   IPs.
6. **Updates**: done from the admin (*About*) via GitHub Releases - per-file
   `files.sha256` verification, file + SQLite database backup, assisted
   rollback and orchestrated database migrations. Details in
   [administration.md](administration.md#about) and fallback procedures in
   [depannage.md](depannage.md#updates).

## 7. Main URLs

- `/` - webmail (login > folders > messages)
- `/{admin_path}` - admin panel (default `/admin`)
- `#/@settings/<tab>` - user settings (general, identities, contacts, Sieve
  filters, OpenPGP, plugin tabs)
