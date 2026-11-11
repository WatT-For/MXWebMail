# Kurulum

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

## 1. Gereksinimler

- **PHP ≥ 8.4** uzantılarla: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, ve `pdo_sqlite` **veya**
  `pdo_mysql`. `ldap` opsiyonel (yalnızca LDAP eklentileri).
  **Üretimde `opcache` şiddetle önerilir** (`opcache.enable=1`) - uygulama
  istek başına onlarca PHP dosyası yükler.
- Posta için erişilebilir bir **IMAP** sunucusu (ve SMTP, opsiyonel
  ManageSieve).
- **Kurulacak bağımlılık yok**: repo kendi kendine yeter (özel otomatik
  yükleyici, MailSo, Sabre, assetler ve vendored JS kütüphaneleri).
- Web sunucusunun document root'u **`public/`** olmalı, asla proje kökü
  değil.

## 2. Klasör yapısı

```
public/            webroot - index.php, define.php, assets/, .htaccess
app/               public olmayan her şey (webroot dışına taşınabilir)
app/src/           uygulama kodu + vendored kütüphaneler
app/config/        config.php (sürümlenmez) + config.example.php
app/plugins/       uzantılar (kurulumda boş - GitHub kataloğundan eklentiler)
app/templates/     temalar/sayfalar
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - asla webroot'ta
```

`public/define.php`, `MXWM_APP_DIR`'i tanımlar (`app/` klasörünün yolu).
`app/`'ı webroot dışına çıkarmak için klasörü taşıyın ve bu yolu uyarlayın
(mutlak veya göreli).

## 3. `/setup/` sihirbazıyla kurulum (önerilen)

1. Vhost'u `public/`'e yönlendirin.
2. `https://webmail.example.com/`'u açın. `app/storage/INSTALLED` kilidi
   yokken her URL `/setup/`'a yönlendirilir - sihirbaz uygulamanın kendisi
   tarafından sunulur (webroot'ta kurulum dosyası yok).
3. Sihirbaz 5 adımda ilerler:
   - **Gereksinimler**: PHP, uzantıları, vendored dosya varlığını ve
     `app/storage/` (engelleyici) ile `app/config/` (engelleyici değil -
     `config.php` elle oluşturulabilir) yazılabilirliğini kontrol eder.
   - **Uygulama**: görünen ad (`app_name`), admin yolu (`admin_path`),
     toplam maksimum ek boyutu (`attachments_max_total`, varsayılan 25 MB
     ile sınırlı ve `upload_max_filesize`/`post_max_size` ile kısıtlı).
     Dosya başına limit `attachment_max_size` türetilerek yazılır:
     `min(25, attachments_max_total)`.
   - **Veritabanı**: SQLite (varsayılan), MariaDB >= 10.x veya MySQL >= 8.0.
     MariaDB/MySQL için gerçek bağlantı testi yapılır; taban kurulumda
     oluşturulur (`utf8mb4`).
   - **Yönetici**: yerel admin hesabı (`admin_user` + parola ≥ 8 karakter).
     Bu alanları boş bırakırsanız admin girişi IMAP ile doğrulanan
     `is_admin` kullanıcısına düşer (bkz.
     [administration.md](administration.md)).
   - **Özet**: değerlerin denetimi, sonra **Kur** düğmesi.
4. Kurulum `app/config/config.php` yazar (mevcut dosya
   `config.<timestamp>.bak` olarak arşivlenir), geçişleri uygular,
   **barındırma etki alanını algılamaya** çalışır (SRV > Mozilla
   autoconfig > MX > sonda) ve `domains` tablosuna ekler, sonra
   `app/storage/INSTALLED` kilidini koyar.
   **Kur** tıklamasında tam ekran ilerleme görünür: etki alanı algılaması
   işlemi ~2 dakikaya uzatabilir - sayfayı kapatmayın (kurulum sunucu
   tarafında zaten tamamlanır). Üç genel etki alanı da **ek hesap olarak
   önceden yetkilendirilir** - `gmail.com`, `outlook.com`, `yahoo.com`
   (OAuth2 gerekli, birincil giriş reddedilir; bkz.
   [domaines.md](domaines.md) §8). admin > *Etki alanları*'nda silinebilir.
5. **Kurulumdan sonra**: `/setup` artık 404 yanıtlar (silinecek bir şey
   yok) ve **`app/storage/app.key`'in kopyasını alın** (bkz. §6).

> `app/config/` yazılabilir değilse sihirbaz elle oluşturulacak
> `config.php` içeriğini gösterir. Dosya yerleştirildikten sonra kurulum
> devam eder.

## 4. Manuel kurulum

```bash
cp app/config/config.example.php app/config/config.php   # değerleri düzenleyin
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

Geçiş ilk erişimde (`App::db()`) zaten uygulanır; `config.php` sonra
`app/storage/INSTALLED` oluşturmak yeterli (içerik önemsiz, yalnızca
`is_file()` test edilir). `app/storage/app.key` ilk kimlik bilgisi
şifrelemesinde otomatik üretilir.

## 5. Web dağıtımı

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

Kök-`.htaccess` geçiş ağı olarak `public/`'e yeniden yazabilir ama resmi
document root `public/`'dir.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # >= attachment_max_size olmalı (varsayılan 25 MB).
    # attachments_max_total (ekler toplamı) gönderimde doğrulanır:
    # her dosya ayrı istekte gelir.
    client_max_body_size 30m;

    # Dotfiles asla sunulmaz
    location ~ /\.(?!well-known) { deny all; }

    # Ağ: root yanlışlıkla köke işaret etse bile özel klasör reddedilir
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Sürümlenmiş assetler /assets/vX.Y.Z/… > gerçek dosya (Apache
    # .htaccess RewriteRule eşdeğeri). Uzun TTL: sürüm her release'de değişir.
    # /assets/theme|plugin/ URL'leri try_files > PHP'den geçer
    # (başlıkları "http" yapılandırması yönetir).
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

Notlar:

- `.htaccess` dosyaları Nginx altında etkisiz: `location ~ /\.` bloğu
  dotfiles'ı gizler ve `^/(app|dist)` özel klasörü korur.
- `autoindex` varsayılan kapalı (`Options -Indexes` eşdeğeri).
- Opsiyonel sıkıştırma: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Apache altında `public/.htaccess` zaten `mod_deflate` + asset TTL'lerini
  kapsar; PHP sıkıştırma (`http.gzip`) iki durumda da çalışır.

### Performans (sunucu önerileri)

- **HTTP/2 (Apache)**: `a2enmod http2` sonra VirtualHost'ta
  `Protocols h2 h2c http/1.1` (`h2` TLS altında yeter; `h2c` yalnızca
  tarayıcı olmayan istemciler için yararlı - Edge/Firefox h2'yi yalnızca
  HTTPS'te uygular). `mod_http2` `mpm_event` gerektirir (PHP-FPM ile zaten
  varsayılan) - `mpm_prefork`/`mod_php` ile uyumsuz.
- **PHP-FPM**: `pm.max_children` yüke göre (varsayılan 5 birkaç
  sekme/kullanıcıyı zar zor karşılar: poll, `cid:` görselleri, ekler ve
  görüntü proxy'si istekleri paralelleştirir).
- **OPcache**: Debian PHP paketinde varsayılan aktif (FPM'de
  `opcache.enable=1`) - şüphede `opcache_get_status()` kontrol edin.

## 6. Kurulum sonrası adımlar

1. **`app/storage/app.key` yedeği**: saklı tüm kimlik bilgilerini şifreler
   (IMAP parolaları, eklenti gizli anahtarları). Kaybı kullanıcıları
   yeniden bağlanmaya zorlar; asla sunulmamalı veya sürümlenmemeli.
2. **Admin'de en az bir etki alanı oluşturun** (sayfa *Etki alanları*):
   `restrict_domains = true` (varsayılan) ile yapılandırılmış etki alanı
   olmadan giriş mümkün değil. Kurucu onu otomatik algılamaya çalışır.
3. **Eklentileri admin'den kurun**, sekme *Uzantılar*: release zip'i artık
   eklenti içermez - bu katalogdan indirilir, güncellenir ve kaldırılır
   (bkz. [administration.md](administration.md#uzantılar-eklentiler) ve
   [plugins.md](plugins.md)). Çekirdek güncellemesinde zaten kurulu
   eklentiler korunur (`app/plugins/` kopyalamadan hariç).
4. Ters proxy arkasındaysanız `force_https` / `hsts` kontrol edin (bkz.
   `trusted_proxies` için [securite.md](securite.md)).
5. Admin paneli belirli IP'lere sınırlanacaksa `admin_ip_allowlist`
   ayarlayın.
6. **Güncellemeler**: admin'den (*Hakkında*) GitHub Releases ile yapılır -
   dosya başına `files.sha256` doğrulaması, dosyalar + SQLite tabanlarının
   yedeği, yönetimli geri alma ve orkestre edilmiş taban geçişleri.
   Ayrıntılar [administration.md](administration.md#hakkında) ve yedek
   prosedürler [depannage.md](depannage.md#güncellemeler)'de.

## 7. Ana URL'ler

- `/` - webmail (giriş > klasörler > mesajlar)
- `/{admin_path}` - yönetim paneli (varsayılan `/admin`)
- `#/@settings/<sekme>` - kullanıcı ayarları (genel, kimlikler, kişiler,
  Sieve filtreleri, OpenPGP, eklenti sekmeleri)
