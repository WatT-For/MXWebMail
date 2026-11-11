# Sorun giderme / SSS

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

## Kurulum

**Tüm URL'ler `/setup/`'a yönlendiriyor**
Normal: `app/storage/INSTALLED` kilidi yok. Kurulumu bitirin veya
`config.php` yerleştirdikten sonra dosyayı elle oluşturun.

**Sihirbaz "config/ yazılamaz" gösteriyor**
Engellemeyen uyarı: görüntülenen içeriği `app/config/config.php`'ye elle
kopyalayın, sonra kurulumu yeniden çalıştırın. Ayrıca `app/storage/`'ın
yazılabilir olduğunu doğrulayın (engelleyici).

**MariaDB bağlantı testi geçmiyor**
Host/port/kimlik bilgilerini ve kullanıcının taban oluşturma hakkını
(`CREATE DATABASE`) kontrol edin. Charset `utf8mb4` olmalı.

## Bağlantı / giriş

**Kurulumdan sonra hiçbir kullanıcı giremiyor**
`restrict_domains = true` (varsayılan) ile tabloda **en az bir adlandırılmış
etki alanı** gerekir (wildcard `*` bu doğrulama için yoksayılır). Kurucu
onu algılamaya çalışır; aksi halde *Etki alanları*'nda oluşturun.

**Giriş sebepsiz reddediliyor (hesap engellendi)**
- Çok fazla deneme: `login_lock_seconds` bekleyin (varsayılan 300 s) veya
  `app/storage/cache/login_attempts.json`'ı temizleyin.
- `black-list`/`white-list` eklentileri girişi reddedebilir (404/403 veya
  `auth_error`'a göre genel hata).
- `disabled` kullanıcı geçerli oturumla bile reddedilir.

**Admin girişi çalışmıyor**
- Yerel hesap (`admin_user`): `admin_password_hash`'i ve olası TOTP'yi
  kontrol edin.
- IMAP fallback: kullanıcının `is_admin = 1` olması gerekir.
- `admin_ip_allowlist` IP'nizi engelliyor olabilir.

## Posta sunucuları

**IMAP/SMTP sunucusuna bağlanılamıyor**
*Etki alanları*'ndaki **Bağlantıyı test et** düğmesini kullanın (protokol
başına sonuç). Sık nedenler: tutarsız port/şifreleme (993/ssl vs 143/tls),
self-signed sertifika (bkz.
`ssl.verify_certificate`/`ssl.allow_self_signed`) veya güvenlik duvarı.

**Self-signed sertifika reddedildi**
`ssl.verify_certificate = false` veya `ssl.allow_self_signed = true`
(yalnızca sonuçlarının tam bilinciyle kullanın).

**Etki alanı otomatik algılanmıyor**
`autodetect.enabled` varsayılan olarak kapalı. Etkinleştirin veya *Etki
alanları* sayfasının **Algıla** düğmesini kullanın.

**"Spam olarak işaretle" sonraki mailleri filtrelemiyor**
Düğme mesajı İstenmeyen klasörüne taşır (veya sunucuda Junk klasörü yoksa
IMAP keyword `$Junk` koyar) - sunucuya hiçbir şey öğretmez. Sieve
filtreleri yalnızca teslimatta uygulanır: sihirbazın *Anti-spam* şablonu
(Ayarlar > Filtreler) MTA'nın `X-Spam-Flag: YES` işaretlediği mailleri
ayırır. Otomatik öğrenme (spam/ham) sunucu tarafı yapılandırma gerektirir -
Dovecot IMAPSieve (Junk'a/ondan kopyalamayla tetiklenen report-spam/ham
betiği, uygulamanın MOVE'unu tetikler) veya rspamd/sa-learn.

## Güvenlik / HTTPS

**Site ters proxy arkasında HTTP'de kalıyor**
`X-Forwarded-Proto`'nun dikkate alınması için `trusted_proxies`'i (proxy
IP'leri) doldurun, sonra `force_https` / `hsts`'yi etkinleştirin.

**Rate-limit veya allowlist gerçek IP'yi görmüyor**
Aynı neden: `trusted_proxies` olmadan `REMOTE_ADDR` olduğu gibi kullanılır.

**Favicon görünmüyor**
Yüklenen favicon ≤ 512 KB olmalı (ico/png/svg/jpg/webp); aksi halde
varsayılan ikon kalır.

## Veri / günlükler

**`app.key` kaybı**
Saklı tüm kimlik bilgileri çözülemez hale gelir: kullanıcılar yeniden
bağlanmalı. Her zaman `app.key`'in kopyasını alın.

**Günlükler nerede?**
`app/storage/logs/app.log` (uygulama), `mail.log` (IMAP/SMTP/Sieve
protokolleri, `logs.enabled` ise) ve auth günlüğü (`auth_logging`).
Admin'de görünür (*Günlükler*). fail2ban entegrasyonu:
[fail2ban.md](fail2ban.md).

**MariaDB'ye geçiş verileri boşalttı**
Beklenen davranış: `db_dsn` değişikliği boş şemayla başlar (kullanıcı
başına kişi dosyaları korunur). Veri taşıma aracı yok.

## Güncellemeler

**"Hata: GitHub'da bulunamadı … özel repo veya eksik kaynak"**
Resmi repo kilitli (`GitHubClient::REPO` = `WatT-For/MXWebMail`) ve
**genel**: token gerekmez. GitHub eksik kaynak veya yetersiz erişim için
403 değil 404 döndürür; mesaj suçlu uç noktayı gösterir (`releases/latest`,
`commits/…` veya eklenti kataloğunun `raw` yolu). Kalıcı 404 genelde eksik
kaynak anlamına gelir (yayınlanmış release yok, bilinmeyen
`update.plugins_ref`). "Özel repo" durumu yalnızca `update.repo` +
`config.php`'de PAT ile yapılandırılmış bir fork'u ilgilendirir.

**`…/releases/download/…` üzerinde HTTP 404 (eklenti kataloğu veya güncelleme indirmesi)**
Genel resmi repoda `browser_download_url` çalışır. Durum yalnızca
`update.repo` üzerinden özel bir fork için geçerlidir:
`browser_download_url` orada geçerli token ile bile 404 döndürür -
0.36.1'den itibaren düzeltildi, `config.php`'de PAT yapılandırılmışsa
assetler `releases/assets/<id>` API uç noktasından indirilir.

**Güncelleme sırasında site 503 "update" / bakım sayfası veriyor**
Normal: `app/storage/update/applying` bayrağı dosya kopyalama sırasında
kullanıcı API'sini keser (asla eski/yeni kod karışımı olmaz). Sonda
kaldırılır; hata sonrası kalırsa 15 dk sonra kendiliğinden sürer - veya
`app/storage/update/applying`'i elle silin.

**Güncelleme sonrası "Bekleyen geçişler"**
Sonuçlandırma (`/api/admin/update/finalize`) çalışmadı: *Hakkında*'daki
*Sonuçlandır* düğmesi veya CLI'de `php app/bin/post-update.php`. Eylemsiz
geçişler yine de sonraki istekte uygulanır (tembel geçiş) ama kontrol
etmek tercih edilir.

**Güncelleme sonrası geri alma (rollback)**
*Hakkında*'daki *Geri yükle* düğmesi: `app/storage/update/backup-<ts>`'den
dosyaları geri yükler (son 3 yedek saklanır), oluşturulan dosyaları siler
ve kaydedilen SQLite tabanlarını geri yazar. **MariaDB** altında geçişler
geri alınamaz: önce tabanı `mysqldump`'ınızdan geri yükleyin, sonra
rollback'i yeniden çalıştırın (zorlama onayı önerilir). Sıfırdan başlamak
için: kaynak paketi yeniden kurun ve `app/storage/`'ı geri yükleyin
(`app.key` ve `db/` dahil).

**"Veritabanı şeması koddan yeni" banner'ı**
`meta.schema_ahead`: kurulu kod şemadan eski (taban geri yüklenmeden dosya
geriletmesi veya daha eski paket dağıtıldı). Tabanı geri yükleyin veya
karşılık gelen sürümü yeniden uygulayın; fark varken geçişler askıda
kalır.

## Çeşitli

**"Olduğu gibi ilet" (bounce): alıcı maili spam olarak mı görüyor?**
Mümkün: iletim son tarafta SPF/DMARC'ı bozar (gönderen sunucu artık
orijinal göndericiye karşılık gelmez) - normal bounce davranışı,
`Resent-*` başlıkları yeniden iletimi belgeler. Kimlik doğrulamayı korumak
için klasik yönlendirmeyi tercih edin.

**Nginx altında özel klasörler erişilebilir mi?**
`root` `public/`'i gösteriyorsa ve `location ~ /\.` + `^/(app|dist)` bloğu
varsa hayır. `root`'u asla proje köküne yöneltmeyin.

**Yasal istekte 403 "cross_site" hatası**
`Sec-Fetch-*` koruması isteği reddetti. Yasal bir harici API entegre
ediyorsanız `secfetch_allow`'a kural ekleyin.

**İç hata ayıklama (500)**
Mesajı görmek için geçici `debug = true` açın (asla üretimde değil), sonra
`app.log`'a bakın.
