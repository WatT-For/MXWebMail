# Instalacja

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

## 1. Wymagania

- **PHP ≥ 8.4** z rozszerzeniami: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, oraz `pdo_sqlite` **lub**
  `pdo_mysql`. `ldap` jest opcjonalne (tylko wtyczki LDAP).
  **`opcache` mocno zalecane w produkcji** (`opcache.enable=1`) - aplikacja
  ładuje dziesiątki plików PHP na żądanie.
- Dostępny serwer **IMAP** (i SMTP, ManageSieve opcjonalnie) dla poczty.
- **Brak zależności do instalacji**: repo jest samowystarczalne (własny
  autoloader, MailSo, Sabre, assety i vendored biblioteki JS).
- Document root serwera web musi być **`public/`**, nigdy katalog główny
  projektu.

## 2. Struktura folderów

```
public/            webroot - index.php, define.php, assets/, .htaccess
app/               wszystko niepubliczne (przenoszalne poza webroot)
app/src/           kod aplikacji + vendored biblioteki
app/config/        config.php (niewersjonowany) + config.example.php
app/plugins/       rozszerzenia (puste przy instalacji - wtyczki z katalogu GitHub)
app/templates/     motywy/strony
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - nigdy w webroot
```

`public/define.php` definiuje `MXWM_APP_DIR` (ścieżka folderu `app/`). By
wynieść `app/` poza webroot, przenieś folder i dostosuj tę ścieżkę
(bezwzględna lub względna).

## 3. Instalacja przez kreator `/setup/` (zalecana)

1. Skieruj vhost na `public/`.
2. Otwórz `https://webmail.example.com/`. Dopóki blokada
   `app/storage/INSTALLED` nie istnieje, każdy URL jest przekierowywany na
   `/setup/` - kreator jest serwowany przez samą aplikację (żaden plik
   instalacyjny w webroot).
3. Kreator przebiega w 5 krokach:
   - **Wymagania**: sprawdza PHP, rozszerzenia, obecność plików vendored i
     zapisywalność `app/storage/` (blokujące) i `app/config/` (nieblokujące
     - `config.php` można utworzyć ręcznie).
   - **Aplikacja**: wyświetlana nazwa (`app_name`), ścieżka admina
     (`admin_path`), maksymalny łączny rozmiar załączników
     (`attachments_max_total`, ograniczony do 25 MB domyślnie i ograniczony
     przez `upload_max_filesize`/`post_max_size`). Limit per plik
     `attachment_max_size` jest zapisywany pochodnie: `min(25,
     attachments_max_total)`.
   - **Baza danych**: SQLite (domyślnie) lub MariaDB/MySQL. Przy MariaDB
     wykonywany jest prawdziwy test połączenia; baza jest tworzona
     (`utf8mb4`) przy instalacji.
   - **Administrator**: lokalne konto admina (`admin_user` + hasło ≥ 8
     znaków). Jeśli zostawisz te pola puste, login admina spada na
     użytkownika `is_admin` uwierzytelnionego przez IMAP (zob.
     [administration.md](administration.md)).
   - **Podsumowanie**: kontrola wartości potem przycisk **Zainstaluj**.
4. Instalacja pisze `app/config/config.php` (istniejący plik jest
   archiwizowany jako `config.<timestamp>.bak`), stosuje migracje, próbuje
   **wykryć domenę hostingową** (SRV > autoconfig Mozilla > MX >
   sondowanie) i dodaje ją do tabeli `domains`, potem stawia blokadę
   `app/storage/INSTALLED`.
   Przy kliknięciu **Zainstaluj** pojawia się pełnoekranowy ekran postępu:
   detekcja domeny może wydłużyć operację do ~2 minut - nie zamykaj strony
   (instalacja dokończy się i tak po stronie serwera). Trzy publiczne
   domeny są też **wstępnie autoryzowane jako konta dodatkowe** -
   `gmail.com`, `outlook.com`, `yahoo.com` (wymagane OAuth2, login
   pierwotny odrzucany; zob. [domaines.md](domaines.md) §8). Usuwalne w
   admin > *Domeny*.
5. **Po instalacji**: `/setup` odpowiada już 404 (nic do usunięcia) i
   **zrób kopię `app/storage/app.key`** (zob. §6).

> Jeśli `app/config/` nie jest zapisywalny, kreator pokazuje zawartość
> `config.php` do utworzenia ręcznie. Instalacja kontynuuje po
> umieszczeniu pliku.

## 4. Instalacja ręczna

```bash
cp app/config/config.example.php app/config/config.php   # edytuj wartości
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

Migracja jest i tak stosowana przy pierwszym dostępie (`App::db()`),
wystarczy więc utworzyć `config.php` potem `app/storage/INSTALLED`
(zawartość nie ma znaczenia, tylko `is_file()` jest testowane).
`app/storage/app.key` jest generowany automatycznie przy pierwszym
szyfrowaniu poświadczenia.

## 5. Wdrożenie web

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

Root-`.htaccess` może przepisywać do `public/` jako siatka przejściowa, ale
oficjalny document root to `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Musi być >= attachment_max_size (25 MB domyślnie).
    # attachments_max_total (cumul załączników) jest weryfikowany przy
    # wysyłce: każdy plik przychodzi w osobnym żądaniu.
    client_max_body_size 30m;

    # Dotfiles nigdy serwowane
    location ~ /\.(?!well-known) { deny all; }

    # Siatka: prywatny folder odrzucany nawet jeśli root błędnie wskazuje na główny
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Wersjonowane assety /assets/vX.Y.Z/… > prawdziwy plik (ekwiwalent
    # RewriteRule .htaccess Apache). Długi TTL: wersja zmienia się przy
    # każdej release. URL-e /assets/theme|plugin/ przechodzą przez try_files
    # > PHP (ich nagłówki pozostają zarządzane przez konfigurację "http").
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

Uwagi:

- Pliki `.htaccess` są obojętne pod Nginx: to blok `location ~ /\.` ukrywa
  dotfiles, a `^/(app|dist)` chroni prywatny folder.
- `autoindex` jest domyślnie wyłączony (ekwiwalent `Options -Indexes`).
- Opcjonalna kompresja: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Pod Apache `public/.htaccess` pokrywa już `mod_deflate` + TTL assetów;
  kompresja PHP (`http.gzip`) działa w obu przypadkach.

### Wydajność (zalecenia serwerowe)

- **HTTP/2 (Apache)**: `a2enmod http2` potem `Protocols h2 h2c http/1.1` w
  VirtualHost (`h2` wystarczy pod TLS; `h2c` jest użyteczne tylko dla
  klientów niebędących przeglądarkami - Edge/Firefox implementują h2 tylko
  w HTTPS). `mod_http2` wymaga `mpm_event` (już domyślne przy PHP-FPM) -
  jest niekompatybilny z `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` dostosowane do obciążenia (domyślne 5
  ledwo wystarcza na kilka kart/użytkowników jednocześnie: poll, obrazy
  `cid:`, załączniki i proxy obrazów równoleglą żądania).
- **OPcache**: aktywne domyślnie w pakiecie PHP Debian (`opcache.enable=1`
  w FPM) - sprawdź `opcache_get_status()` w razie wątpliwości.

## 6. Kroki po instalacji

1. **Kopia `app/storage/app.key`**: szyfruje wszystkie przechowane
   poświadczenia (hasła IMAP, sekrety wtyczek). Jego utrata zmusza
   użytkowników do ponownego logowania; nigdy nie może być serwowany ani
   wersjonowany.
2. **Utwórz przynajmniej jedną domenę** w adminie (strona *Domeny*): przy
   `restrict_domains = true` (domyślnie) żaden login nie jest możliwy bez
   skonfigurowanej domeny. Instalator próbuje ją wykryć automatycznie.
3. **Zainstaluj wtyczki** z admina, zakładka *Rozszerzenia*: zip release
   nie zawiera już wtyczek - są pobierane, aktualizowane i odinstalowywane
   z tego katalogu (zob.
   [administration.md](administration.md#rozszerzenia-wtyczki) i
   [plugins.md](plugins.md)). Przy aktualizacji rdzenia już zainstalowane
   wtyczki są zachowane (`app/plugins/` jest wykluczony z kopiowania).
4. Sprawdź `force_https` / `hsts` jeśli jesteś za reverse proxy (zob.
   [securite.md](securite.md) dla `trusted_proxies`).
5. Ustaw `admin_ip_allowlist` jeśli panel admina ma być ograniczony do
   pewnych IP.
6. **Aktualizacje**: wykonuje się z admina (*Informacje*) przez GitHub
   Releases - weryfikacja `files.sha256` per plik, kopia plików + bazy
   SQLite, asystowany rollback i orkiestracja migracji bazy. Szczegóły w
   [administration.md](administration.md#informacje) i procedury zapasowe w
   [depannage.md](depannage.md#aktualizacje).

## 7. Główne URL-e

- `/` - webmail (login > foldery > wiadomości)
- `/{admin_path}` - panel administracyjny (domyślnie `/admin`)
- `#/@settings/<zakładka>` - ustawienia użytkownika (ogólne, tożsamości,
  kontakty, filtry Sieve, OpenPGP, zakładki wtyczek)
