# Yapılandırma referansı

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

`app/config/config.php` dosyası yalnızca varsayılanları **geçersiz kılan**
anahtarları tanımlar (`Config.php` tarafından yüklenen
`app/config/config.example.php`). `config.example.php`'yi `config.php`'ye
kopyalayın ve uyarlayın. Yönetim paneli bu anahtarların çoğunu bölüm
bölüm düzenler; yalnızca bazıları elle düzenleme gerektirir (`update.*`
başta, ayrıca `import_max_size`, `smime_ca_path`, `logs.max_size` ve
`logs.keep`).

İlişkisel diziler derinlemesine birleştirilir; listeler değiştirilir.

## Depolama

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) veya tam PDO DSN (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | PDO kullanıcısı (MariaDB). |
| `db_pass` | `''` | PDO parolası (MariaDB). |

## Uygulama

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `app_name` | `MXWebMail` | Görünen ad (başlık, PWA, TOTP). |
| `favicon` | `''` | `storage/branding`'e yüklenen favicon dosyası, `/favicon.ico` üzerinden sunulur; boş = varsayılan ikon. |
| `website_url` | `https://mxrelay.fr/webmail/` | Resmi site bağlantısı (admin düğmesi); boş = gizli. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Bağış sayfasına "Sponsor" bağlantısı (admin düğmesi + webmail ayarları); boş = gizli. |
| `debug` | `false` | İç hataları API istemcilerine döndürür (asla üretimde). |
| `language` | `auto` | `auto` = tarayıcının `Accept-Language`'i; aksi halde kurulu dil kodu (`fr`, `en`…). Son fallback `en`. |

## Etki alanları

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `default_domain` | `auto` | `@etkialanı` olmadan girilen girişin soneki: `auto` = HTTP hostu (`www.` ve port olmadan), `''` = yok, veya sabit etki alanı. |
| `restrict_domains` | `true` | Girişte yalnızca `domains` tablosunun adlandırılmış etki alanları kabul edilir (ne `*` ne algılama uygulanır). |
| `autodetect.enabled` | `false` | Bilinmeyen etki alanının girişte otomatik yapılandırma algılaması. |
| `autodetect.use_ispdb` | `false` | Mozilla tabanına fallback (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | `imap.`/`mail.`/`smtp.`/`sieve.<etkialanı>` port sondası. |

IMAP/SMTP/Sieve sunucuları admin'de **etki alanı başına** yapılandırılır
(bkz. [domaines.md](domaines.md)). `*` etki alanı catch-all parametreleri
olarak hizmet eder.

## Güvenlik

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `admin_path` | `admin` | Admin panelinin URL segmenti. |
| `admin_user` | `''` | Yerel admin hesabı; boş = IMAP ile doğrulanan `is_admin` kullanıcısına fallback. |
| `admin_password_hash` | `''` | Yerel admin hesabının `password_hash('parola', PASSWORD_DEFAULT)`'u. |
| `admin_totp_secret` | `''` | Yerel admin hesabının TOTP gizli anahtarı (base32), `two-factor-auth` eklentisi tarafından yazılır; boş = devre dışı. Eklenti kapalıyken zorlanmaz. |
| `login_max_attempts` | `5` | Geçici engellemeden önce giriş denemeleri. |
| `login_lock_seconds` | `300` | Engelleme süresi (saniye). |
| `admin_ip_allowlist` | `''` | Admin için izinli IP/CIDR (CSV); boş = hepsi. |
| `session_lifetime` | `0` | Oturum süresi saatte; `0` = oturum cookie'si. |
| `remember_days` | `30` | "Oturum açık kalsın" belirteçlerinin ömrü günde (1-365). |
| `maintenance` | `false` | Bakım modu: `/` 503'te özel sayfa sunar (tüm kitleler, oturum korunur) ve kullanıcı `/api/*`'si 503 yanıtlar - `/api/admin/*` ve `/api/ping` aktif kalır. Bağlı kullanıcılar sonraki API isteğinde bakım sayfasına aktarılır; sonda kendini yeniden yükler. |
| `force_https` | `false` | HTTP > HTTPS yönlendirir (301). |
| `hsts` | `false` | İstek HTTPS olduğunda `Strict-Transport-Security` yayar. |
| `cookie_samesite` | `Lax` | `Lax` (varsayılan, OAuth2 dönüşü için gerekli) / `Strict` (OAuth2 akışını bozar) / `None` (HTTPS gerektirir). |
| `csp_extra` | `''` | Ek CSP yönergeleri (örn. `img-src https:`). |
| `trusted_proxies` | `''` | Güvenilen ters proxy IP/CIDR'leri (CSV). `X-Forwarded-*`'ı güvenilir kılmak için zorunlu (rate-limit, allowlist, günlükler). |
| `secfetch_allow` | `''` | Ek `Sec-Fetch-*` kuralları (örn. `site=same-origin;mode=cors`). |

## Güncellemeler

Resmi repo `GitHubClient::REPO`'da kilitli (`WatT-For/MXWebMail`) ve **genel**:
denetim ve indirme token olmadan çalışır. Bu
bölüm - çekirdek güncelleyici ve eklenti kataloğu (repoda
`update.plugins_ref` ref'inde okunan, indirilen her dosya sha1 git-blob ile
doğrulanır) tarafından ortak - admin'de düzenlenebilir değil (`channel`
hariç).

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `update.repo` | `''` | `GitHubClient::REPO` boşsa dev fallback (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (admin'de ortaya konan tek ayar). |
| `update.manifest_url` | `''` | Dev: release biçiminde JSON manifestosu, GitHub API'sini atlar. |
| `update.plugins_ref` | `'release'` | Eklenti kataloğu kaynağının git-ref'i: `'release'` (son release'in etiketi), `'main'` (dal - `plugin.json` push'u güncelleme yayınlar) veya herhangi literal ref. |
| `update.plugins_local` | `''` | Dev/testler: eklenti klasörünün mutlak yolu - diskten katalog + kurulum, çevrimdışı ve uzak doğrulama yok. `plugins_ref`'i önceler. |

## İşlevsellik

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `attachment_max_size` | `25` | Dosya başına maks. MB. `attachments_max_total`'dan küçük veya eşit olmalı (üzerinde limit etkisiz: upload zaten ikisinin `min`'iyle sınırlar - admin tutarsız kaydı reddeder). |
| `attachments_max_total` | `25` | Mesaj başına toplam MB. |
| `allow_additional_accounts` | `true` | Kullanıcı ek IMAP hesapları ekleyebilir/yönetebilir (Ayarlar > Hesaplar). |
| `allow_additional_identities` | `true` | Kullanıcı ek kimlikler oluşturabilir. |
| `popup_identity` | `false` | Ad boşsa ilk girişte kimlik ayarını açar. |
| `dangerous_actions` | `true` | Klasörü boşaltmayı/silmeyi, mesajları silmeyi (çöp kutusuna taşıma dahil) yetkilendirir. |
| `contacts_enable` | `true` | Adres defteri + otomatik tamamlama. |
| `contacts_suggestions_limit` | `20` | Maks. otomatik tamamlama önerisi sayısı (1-100). |
| `import_max_size` | `100` | İçe aktarılan `.mbox`'un maks. boyutu (MB) - `.eml`'ler `attachment_max_size` tavanını korur. Admin'de düzenlenemez. |
| `smime_ca_path` | `''` | S/MIME güveni için CA yolu (PEM dosyası); boş = yalnızca kriptografik imza doğrulanır (`PKCS7_NOVERIFY`). Admin'de düzenlenemez. |

## `user_defaults` - kullanıcı ayarlarının varsayılanları

Kullanıcı tarafından geçersiz kılınabilir. `/api/settings` üzerinden yazmaya
izin verilen anahtarlar bunlar artı eklentilerin bildirdikleri (hook
`settings.allowed`).

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `messages_per_page` | `50` | Sayfa başına mesaj (5-200). |
| `list_sort` | `REVERSE DATE` | Liste sıralaması (whitelist'teki IMAP SORT kriteri). |
| `view_images` | `ask` | Dış görüntüler: `always` / `ask` / `match` (bilinen gönderici). |
| `msg_default_action` | `reply` | Varsayılan yanıt düğmesi: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Açılan mesajın okundu işaretlenmesinden önceki saniyeler (0 = hemen). |
| `compose_autosave` | `60` | Taslak otomatik kayıtları arası saniyeler (0 = asla). |
| `mdn_mode` | `ask` | Okundu bildirimleri: `ask` (istek banner'ı) / `never` (gizli). |
| `autologout` | `30` | Çıkışa dek hareketsizlik dakikaları (0 = asla). "Oturum açık kalsın" belirtecini iptal etmez: oturum sonraki ziyarette sessizce geri yüklenir. |
| `list_refresh` | `5` | Otomatik yenilemeler arası dakikalar (0 = asla). |
| `notify_new_mail` | `false` | Mail gelişinde tarayıcı bildirimi. |
| `contacts_autosave` | `true` | Alıcıyı otomatik kişilere kaydet. |
| `folder_pane_width` | `240` | Klasör sütunu genişliği (px, 140-600). |
| `message_pane_width` | `340` | Mesaj listesi sütunu genişliği (px, 200-800). |
| `layout` | `horizontal` | Okuma düzeni: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Dikey modda mesaj listesi yüksekliği (px, 120-1200). |
| `compose_modal_width` | `0` | Yazma modalı genişliği (px, 0 = Bootstrap varsayılanı). |
| `compose_modal_height` | `0` | Yazma modalı yüksekliği (px, 0 = auto). |
| `identity_modal_width` | `0` | Kimlik modalı genişliği (px, 0 = Bootstrap varsayılanı). |
| `identity_modal_height` | `0` | Kimlik modalı yüksekliği (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Çıkışta çöp kutusunu boşalt. |
| `empty_junk_on_logout` | `false` | Çıkışta spam klasörünü boşalt. |
| `list_threads` | `false` | Mesajları sohbetlerde grupla (IMAP THREAD). |
| `after_delete` | `next` | Gösterilen mail silindikten/taşındıktan sonra: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Posta görünümü klavye kısayolları (yardım `?` tuşuyla). |
| `theme` | `default` | Arayüz şablonu (`app/templates/<name>` klasörü). |

## TLS (giden IMAP/SMTP/Sieve bağlantıları)

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `ssl.verify_certificate` | `true` | Sertifikayı doğrula (`false` = self-signed sertifika). |
| `ssl.allow_self_signed` | `false` | Self-signed sertifikaları kabul et. |
| `ssl.cafile` / `ssl.capath` | `''` | Özel CA (mutlak yol / klasör). |
| `ssl.disable_compression` | `true` | TLS sıkıştırmayı kapatır. |
| `ssl.security_level` | `1` | OpenSSL güvenlik seviyesi (0-5). |
| `ssl.local_cert` | `''` | Olası istemci sertifikası. |

## Dış görüntü proxy'si

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `image_proxy` | `true` | Uzak görüntüleri `/api/proxy` üzerinden sun. |
| `image_proxy_allowlist` | `''` | İzinli etki alanları (CSV, `*.etkialanı` ok); boş = hepsi (özel IP'ler her zaman engelli). |

## HTTP - sıkıştırma & tarayıcı önbelleği

Admin'de düzenlenebilir, *Performans > HTTP* bölümü.

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `http.gzip` | `true` | Metin/JSON yanıtları PHP'de sıkıştırır (gzip) - `mod_deflate` olmadan da çalışır. `zlib.output_compression` aktifse yoksayılır. **Apache/nginx arkasında: `false` önerilir** - sunucu filtre olarak sıkıştırır (streaming) ve PHP bellekte `gzencode`'dan kaçınır. `php -S` için `true` tutun (dev, `mod_deflate` yok). |
| `http.gzip_level` | `6` | Sıkıştırma seviyesi 1-9. |
| `http.gzip_min_size` | `1024` | Sıkıştırılacak minimum gövde boyutu (bayt). |
| `http.etag` | `true` | PHP tarafından sunulan assetlerde ETag/Last-Modified + 304 yanıtları (tema, eklentiler, favicon). |
| `http.assets_ttl` | `86400` | Tema/eklenti assetlerinin `max-age`'i (`/assets/theme|plugin/*`). `0` = sistematik yeniden doğrulama. `?v=` ile (sunulan dosyanın mtime'ı) `immutable` yayınlanır. |
| `http.favicon_ttl` | `3600` | `/favicon.ico`'nun `max-age`'i. |
| `http.proxy_ttl` | `86400` | `/api/proxy` görüntülerinin `max-age`'i (özel) - ayrıca disk önbelleği TTL'i görevi görür (`storage/cache/proxy`). `0` = önbellek yok (her gösterimde re-fetch + SSRF yeniden doğrulama). Temizlik `cache.gc_hours` ile yönetilir: `proxy_ttl ≤ cache.gc_hours` tutun. |

Özel/API yanıtları asla public önbellek almaz; HTML `no-cache`'dir.

**Üretim**: **opcache**'i etkinleştirin (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ PHP dosya sayısı - kod kendi
önbelleklerini `opcache_invalidate` ile geçersiz kılar,
`opcache.validate_timestamps` aktif kalabilir). Uygulama önbellekleri
(`classmap.php`, eklenti manifestoları, i18n sözlükleri, `themedata.php`)
kod tarafından yönetilir - elle geçersizleştirme gerekmez.

## Uygulama önbelleği (dosya)

Admin'de düzenlenebilir, *Performans > Sunucu önbelleği* bölümü.

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `cache.enabled` | `true` | `app/storage/cache` dosya önbelleğini etkinleştirir (klasörün ETag'ına indeksli IMAP mesaj listeleri, DNS, etki alanı algılama, güncelleme denetimi, klasör GC). Ne otomatik yükleyiciyi (`classmap.php`) ne `login_attempts.json`'u etkiler. |
| `cache.domain_detect_ttl` | `86400` | IMAP/SMTP algılama negatif önbelleği TTL (s). |
| `cache.update_ttl` | `3600` | Güncelleme denetimi sonucu TTL (s). |
| `cache.gc_hours` | `168` | Süresi geçmiş önbellek dosyalarının temizlik aralığı (h); `0` = asla. |

## MailSo günlükleri (IMAP/SMTP/Sieve protokolleri)

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `logs.enabled` | `true` | `storage/logs/mail.log` yazar (gizli anahtarlar maskelenir). |
| `logs.level` | `LOG_WARNING` | Protokolü izlemek için `LOG_DEBUG`. |
| `logs.syslog` | `false` | Dosya günlüğü aktif değilse `syslog()`'a yineleme. |
| `logs.time_zone` | `''` | Zaman damgalarının saat dilimi (örn. `Europe/Paris`); boş = sunucu. |
| `logs.max_size` | `5` | N MiB üzerinde rotasyon - `mail.log`, `app.log` ve auth günlüğü için geçerli. |
| `logs.keep` | `3` | Saklanan rotasyonlar (`app.1.log` … `app.{keep}.log`, tavan 20). |

## Kimlik doğrulama günlüğü (fail2ban tarzı)

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `auth_logging` | `false` | Özel kimlik doğrulama günlüğünü etkinleştirir. |
| `auth_logging_filename` | `auth.log` | `storage/logs` içinde dosya. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Yer tutucular: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` şunları alır: `AUTH OK`, `AUTH FAILED (<http kodu>)` veya
`AUTH FAILED (totp)`. Günlük webmail girişini ve admin girişini
(`admin:` önekli giriş) kapsar. fail2ban bağlantısı için (filtre, jail,
logrotate) bkz. [fail2ban.md](fail2ban.md).

## Labs / uyumluluk

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Ham SMTP hatalarını istemciye göster (teşhis, iç detayları açığa çıkarabilir). |
