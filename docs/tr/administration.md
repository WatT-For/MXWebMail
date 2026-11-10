# Yönetim

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

Yönetim paneli `/{admin_path}`'te erişilebilir (varsayılan `/admin`). Bu
**küresel** yönetimdir (çok-etki-alanlı); ayrıntılı roller yok.

## 1. Admin kimlik doğrulaması

Yapılandırmaya göre iki mod:

1. **Yerel hesap** (`admin_user` + `config.php`'de `admin_password_hash`):
   giriş/parola, muhtemelen TOTP (`admin_totp_secret`). TOTP
   `two-factor-auth` eklentisi tarafından sağlanır ve zorlanır (*Güvenlik*
   bölümünün "İki faktörlü kimlik doğrulama" alt-sekmesi, QR koduyla) -
   eklenti kapalı = girişte gerekmez.
2. **IMAP fallback** (yerel hesap yoksa): `is_admin = 1` alanlı IMAP ile
   doğrulanmış kullanıcı admin'i açar. Bu hak her istekte yeniden doğrulanır
   (düşürülmüş/engellenmiş/silinmiş hesap anında reddedilir).

Rate-limit girişi admin'e de uygulanır, doluysa `admin_ip_allowlist` de.
IMAP admin kendini düşüremez, engelleyemez veya silemez.

## 2. Panel bölümleri

Panel sekmelerden oluşur (hash `#/<sayfa>[/<alt-sekme>]`).

### Genel

`app_name`, `admin_path`, `language` (kurulu diller listesi + `auto`),
`session_lifetime`, `remember_days` ("Oturum açık kalsın" belirteç ömrü),
`maintenance`, `update.channel`. Ayrıca **favicon**'u yönetir (≤ 512 KB
yükleme: ico/png/svg/jpg/webp, içerik doğrulanır). **Bakım** modu `/`'de
özel sayfa (503) gösterir: bağlı kullanıcılar sonraki API isteğinde oraya
aktarılır (oturum korunur) ve sonda kendini yeniden yükler; admin
(`/{admin_path}` + `/api/admin/*`) tamamen erişilebilir kalır.

### Veritabanı

SQLite veya MariaDB/MySQL sürücüsü, host/port/ad/kullanıcı/parola.
Yazımdan önce test (`/api/admin/test-db`) zorunlu: sunucu bağlantıyı
doğrular ve gerekirse tabanı oluşturur. Bkz.
[base-de-donnees.md](base-de-donnees.md).

**Yedekleme** kartı bir zip arşivi indirir (`config.php`, `app.key`,
SQLite tabanları, `storage/branding`) - **hassas** (kimlik bilgileri +
şifreleme anahtarı), güvenli yerde saklayın. MariaDB tabanı kapsanmaz:
sunucu tarafında `mysqldump`.

### İşlevsellik

*İşlevsellik* ve *Varsayılanlar* alt-sekmeleri:

- görüntü proxy'si + allowlist, ek boyutları;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- tüm `user_defaults.*` ayarları (kullanıcı varsayılanları).

### Performans

*HTTP* ve *Sunucu önbelleği* alt-sekmeleri:

- `http.*`: gzip sıkıştırma (etkinleştirme, seviye, minimum boyut),
  `http.etag`, tema/eklenti assetleri, favicon ve görüntü proxy'si
  TTL'leri;
- `cache.*`: dosya uygulama önbelleği (etkinleştirme, etki alanı algılama
  ve güncelleme denetimi TTL'leri, temizlik aralığı).

Anahtar detayları [configuration.md](configuration.md)'de (*HTTP* ve
*Uygulama önbelleği* bölümleri).

### Güvenlik

*Hesap*, *İki faktörlü kimlik doğrulama* (`two-factor-auth` eklentisi
aktifse), *Erişim*, *Başlıklar*, *TLS* alt-sekmeleri:

- yerel admin hesabı (`admin_user`) ve **admin parola değişimi** - iki
  alanı da doldurun sonra *Kaydet*: parola yalnızca dolu ve aynıysa
  değiştirilir;
- admin **TOTP** (`two-factor-auth` eklenti alt-sekmesi): gizli anahtar
  üretimi, taranacak QR kodu, etkinleştirmeden önce zorunlu onay kodu
  (gizli anahtar kayıttan sonra asla gösterilmez);
- `admin_ip_allowlist` ("IP'mi ekle" düğmesi), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- tam `ssl.*` bölümü (giden sertifikalar).

### Günlükler

*Görüntüleyici* ve *Parametreler* alt-sekmeleri:

- `app.log`, `mail.log` ve auth günlüğü görüntüleyici (son 200 satır),
  seçili dosyayı kırpmak için "Temizle" düğmesi;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Etki alanları

Etki alanı başına posta sunucusu yapılandırması - bkz.
[domaines.md](domaines.md). *Parametreler* sekmesi çözümlemeyi gruplar
(`default_domain`, `restrict_domains`, `autodetect.*`). "yalnızca ek
hesap + OAuth sağlayıcısı" etki alanları için (Gmail, Outlook, Yahoo)
admin yalnızca sağlayıcıyı yetkilendirir (etki alanı modalının `oauth`
seçeneği): OAuth2 uygulama kimlik bilgilerini her kullanıcı kaydeder
(*Ayarlar > OAuth2 uygulamaları*).

### Kullanıcılar

Liste + kullanıcı arama, listeyi yeniden yüklemek için yenileme düğmesi
(ikon). Kullanıcı başına (modal, *Profil*, *Ayarlar*,
*Hesaplar/Kimlikler*, *Oturumlar* sekmeleri):

- `display_name`, `is_admin`, `disabled`;
- kullanıcının etkin ayarları (düzenleme + sıfırlama - sıfırlama
  `user_defaults`'a döndürür ama `plugin.*` ve `user_folders` iç anahtarlarını
  **korur**, örn. 2FA gizli anahtarı, klasör kökeni);
- ek IMAP hesapları (varsayılan hesap hariç silme);
- kimlikler (oluşturma/düzenleme/silme, varsayılan kimlik);
- **Oturumlar** sekmesi: "Oturum açık kalsın" belirteçleri listelenir ve
  iptal edilebilir; **Oturumu zorla kapat** belirteçleri iptal eder ve
  aktif oturumları öldürür (session_epoch artırılır, sonraki istekte ret).

Kullanıcı silme kaskadlıdır ve kişi tabanını da siler
(`storage/db/users/<id>.sqlite`).

### Uzantılar (eklentiler)

Her eklenti **görünen adı** altında listelenir (manifestonun çevrilmiş
`label` alanı - teknik `name` `code` içinde yanında gösterilir).

Her eklentiyi aç/kapat + `config.schema.json`'dan üretilen **yapılandırma**
modalı (`string`, `text`, `integer`, `number`, `password`, `bool`, `enum`,
`color`, `list` tipleri). **Kullanıcı erişimi** sütunu aktif eklentiyi
admin yüzeyine kısıtlar (`/{admin_path}` sayfası, `/api/admin/*`, admin
oturumları): kullanıcılar için tamamen etkisizleşir - bkz.
[plugins.md](plugins.md). Varsayılan işaretsiz (açıkça yetkilendirilecek);
yalnızca `admin.*` hook'ları bildiren eklentilerde yok (kullanıcı yüzeyi
yok).

**GitHub'dan kurulum / güncelleme**: eklentiler kilitli repoda doğrudan
okunur (`GitHubClient::REPO`, çekirdek güncelleyicisiyle aynı)
`update.plugins_ref` ref'inde - varsayılan `'release'` = son release'in
etiketi, dev'de `'main'` (`plugin.json` push'u güncelleme yayınlar, release
gerekmez). `raw.githubusercontent.com` üzerinden indirilen her dosya sha1
git-blob ile doğrulanır.
Katalog sekme açılışında yüklenir (çekirdek denetimiyle ortak önbellek) ve
sağ üstteki **yenile** düğmesiyle yenilenebilir; kurulu olmayan katalog
girdileri eklenti tablosunun sonunda listelenir:

- daha yeni sürüm yayınlanmışsa kurulu eklentilerde **Güncelle** düğmesi
  (eski klasör `storage/update/plugins/backup-<name>-<ts>`'ye yedeklenir -
  bir tane saklanır, hatada otomatik geri alma);
- eksik katalog girdilerinde **Kur** düğmesi - yeni kurulan eklenti
  varsayılan **kapalı**;
- **Kaldır** düğmesi: klasör ve yapılandırmanın **kesin** silinmesi
  (`plugins` satırı temizlenir), yedek yok;
- uzak `plugin.json`'ın `min_app`/`max_app`'ı: `APP_VERSION` ile
  uyumsuz girdi *uyumsuz* işaretlenir ve kurulumu reddedilir.

### Webmail göçü (RainLoop / SnappyMail)

`webmail-migration` eklentisi aktifse **Webmail göçü** sayfası yan menüde
görünür (*Hakkında*'dan önce): **sunucu yolu** veya **ZIP yükleme** ile
belirtilen **RainLoop veya SnappyMail** kurulumunun
`data/_data_/_default_/` klasörünün verilerini taşır (açma `storage/tmp`'de,
özel düğmeyle veya 2 saat sonra temizlenir).

- **Analiz et**: yazımsız envanter (`*.ini`/`*.json` etki alanları, bulunan
  hesaplar, kullanıcı başına kişi/kimlik/anahtar/ayar hacimleri);
- **İçe aktarımı çalıştır**: idempotent içe aktarım - eksik etki alanları,
  kişiler (hesap başına kitaplar + genel kitap + `application.ini`'de
  beyanlı PDO DSN, e-posta sıklıkları ve tipleri korunur, silinmiş kişiler
  hariç), kimlikler (varsayılan = boş `Id`), bilinen kullanıcı ayarları
  (sayfa başına mesaj, yenileme, otomatik kişi kaydı, sistem klasörleri,
  varsa tema) ve armored PGP anahtarları. Tekrarlanabilir, tekrarsız.

Yalnızca **mevcut kullanıcıya** karşılık gelen hesaplar içe aktarılır;
diğerleri raporda listelenir. Ek hesap parolaları kurtarılamaz (kaynak
webmail tarafından şifreli) - elle yeniden oluşturulacak. Sieve filtreleri
posta sunucusunda yaşar: içe aktarılacak bir şey yok.

Kullanıcı tarafında, *Ayarlar > Kişiler* sekmesi de `.vcf`/`.csv`/`.sqlite`
(RainLoop/SnappyMail `AddressBook.sqlite` kitabı - çok-kullanıcılı kitapta
yalnızca hesabın kişileri alınır) **içe aktarım** ve `.vcf` **dışa aktarım**
sunar, SnappyMail'in elle dışa aktarımı dahil herhangi başka kitabı
kapsar.

### Temalar

- **varsayılan** tema (`user_defaults.theme` yazar);
- tema başına **yetki** (`allowed`): kullanıcı yalnızca yetkili tema
  seçebilir (varsayılan her zaman seçilebilir kalır);
- tema **yapılandırma** modalı (aynı tipler + `color`);
- kalıtım zinciri gösterimi (`extends`).

Bkz. [themes.md](themes.md).

### Hakkında

Sürüm (`APP_VERSION`), PHP/SAPI/limitler, sürücü ve taban boyutu, yollar,
yüklü uzantılar. Resmi site bağlantısı navbar'da gösterilir.
Başlık kartı ayrıca GitHub Releases üzerinden **güncellemeleri** entegre
eder (denetim, indirme, uygulama, geri yükleme):

- *Denetle* > *İndir* > *Uygula* > otomatik geçiş sonuçlandırma; durumlar
  `storage/update/state.json`'da saklanır;
- açmada `files.sha256` (paketin dosya başına manifestosu) doğrulanır,
  paket gereksinimleri (kendi `App.php`'sinde `APP_REQ_PHP`/`APP_REQ_FROM`:
  PHP sürümü, minimum kaynak sürüm) denetlenir ve yerel değiştirilmiş
  dosyalar uygulamadan önce işaretlenir;
- kopyalama sırasında kullanıcı API'si 503 yanıtlar
  (`storage/update/applying` bayrağı, web tarafında bakım sayfası - hatada
  15 dk sonra sürer);
- her üzerine yazılan dosya `storage/update/backup-<ts>`'ye
  `manifest.json` ile yedeklenir (sürüm, `schema_version`, listeler);
  SQLite altında taban (`webmail.sqlite` + `db/users/*.sqlite`) WAL
  checkpoint sonrası kopyalanır - MariaDB altında dış kopya (`mysqldump`)
  admin'de kalır (uygulama öncesi uyarı);
- *Geri yükle* (rollback): son yedeğin dosyalarını geri yükler, oluşturulan
  dosyaları siler ve SQLite tabanlarını geri yazar; MariaDB altında şema
  ilerlediyse ret (dump'ı geri yükleyin sonra zorlamayı onaylayın);
- "şema koddan yeni" banner'ı taban geri yüklenmeden dosya geriletmesini
  işaretler;
- indirilen/açılan paketi terk etmek için *Sıfırla* eylemi.

> Resmi repo `GitHubClient::REPO`'da kilitli: dolu olana kadar denetim
> devre dışı. `update.channel` burada düzenlenebilir kalır; `update.repo`
> `config.php`'de elle ayarlanır.

## 3. `config.php` yazımı

Admin `config.php`'yi **bölüm bölüm**, atomik yazar (geçici dosya +
`rename`, `chmod 0600`), sağlanmayan anahtarları koruyarak. `app/config/`
yazılabilir değilse arayüz bunu belirtir (`saved_readonly`) ve değişiklikler
gösterilir ama kalıcı olmaz.

## 4. Bağlantı testi

*Etki alanları*'nda **Bağlantıyı test et** modalın aktif sekmesinin
protokolünü doğrular (IMAP, SMTP veya Sieve - diğer sekmeler IMAP'i test
eder) ve **Algıla** alanları kaydetmeden doldurur. Algılama
(`DomainDetector`) `autodetect.enabled`'den bağımsız çalışır. Elle etki
alanı kaydı "otomatik-algılanan" işaretini siler (`options.detected`).
