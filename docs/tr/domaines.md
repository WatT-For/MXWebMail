# Etki alanları ve posta sunucuları

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

IMAP/SMTP/Sieve sunucuları admin panelinde **etki alanı başına**
yapılandırılır (*Etki alanları*). Her etki alanı kullanıcılarının posta
sunucusuna nasıl ulaşılacağını tanımlar.

## 1. Etki alanı alanları

| Alan | Değer |
|---|---|
| `name` | Etki alanı adı (örn. `example.com`) veya catch-all için `*`. |
| `imap_host` | IMAP sunucu hostu. |
| `imap_port` | IMAP portu (varsayılan `993`). |
| `imap_encryption` | `ssl` (varsayılan), `tls` (STARTTLS) veya `none`. |
| `smtp_host` | SMTP sunucu hostu (gönderim). |
| `smtp_port` | SMTP portu (varsayılan `465`). |
| `smtp_encryption` | `ssl` (varsayılan), `tls` veya `none`. |
| `sieve_host` | ManageSieve hostu (opsiyonel). |
| `sieve_port` | Sieve portu (varsayılan `4190`). |
| `active` | Etki alanını aç/kapat. |

## 2. Gelişmiş seçenekler (`options`, JSON)

`options` alanı etki alanı başına ince ayarlarla bir JSON nesnesi kabul
eder:

- `whitelist` - bu etki alanı için izin verilen adresler listesi;
- `secondary_only` - yalnızca **ek hesap** olarak kullanılabilen etki alanı
  (zaten bağlı kullanıcıdan hesap ekleme); birincil giriş "Etki alanı ek
  hesaplara ayrılmış" ile reddedilir;
- `oauth` - etki alanının OAuth2 sağlayıcısı (`google`, `microsoft`,
  `yahoo`): ek hesaplar sağlayıcı onayıyla (popup) eklenir, asla parolayla
  (bkz. §8);
- `imap.shortLogin` - girişi kes;
- `imap.lowerLogin` - girişi küçük harfe zorla;
- `imap.timeout` - IMAP bağlantı zaman aşımı;
- `imap.disabled_capabilities` - devre dışı bırakılacak IMAP capability'leri
  (kusurlu sunucular). Örnek: RFC 5267 dönüş seçeneklerini (`RETURN
  (PARTIAL …)`) uygulamadan `ESORT` ilan eden sunucu için `ESORT` -
  webmail bu durumu zaten algılar ve kendisi klasik sıralamaya geçer; anahtar
  yalnızca algılama uygulanmıyorsa iş görür (önbellek kapalı);
- `imap.body_text_limit` - mesaj açılışında çekilen metin gövdelerinin
  maks. boyutu (bayt) (varsayılan `0` = limitsiz; ~256 K tavanı çok uzun
  bültenlerin fetch'ini kısar);
- `imap.message_list_limit` - üzerinde (varsayılan klasörde `10000` mesaj)
  MailSo server-SORT'tan kaçınır ve UID listesini arka planda önbellekler;
- `smtp.useAuth` - SMTP kimlik doğrulamasını zorla/kapat;
- `smtp.timeout` - SMTP bağlantı zaman aşımı;
- `sieve.encryption` - Sieve bağlantı şifrelemesi.

## 3. `*` etki alanı (catch-all)

`*` etki alanı, beyan edilmemiş her etki alanına uygulanan **varsayılan
parametreleri** sağlar - ama yalnızca `restrict_domains = false` iken:
`restrict_domains` altında `*` yoksayılır ve birincil girişi yetkilendiremez.
Aynı posta sunucusu arkasında birden çok etki alanını servis etmenin en
basit yolu.

## 4. Girişte etki alanı çözümlemesi

Girişte etki alanı şöyle belirlenir:

1. Kullanıcı `@etkialanı` ile giriş girerse bu etki alanı kullanılır.
2. Aksi halde `default_domain` uygulanır: `auto` (varsayılan) = `www.` ve
   port olmadan HTTP hostu; sabit ad; veya `''` (yok).
3. `restrict_domains = true` ise (varsayılan), yalnızca tabloda
   **adlandırılmış** etki alanları kabul edilir (`*` ve algılama
   yoksayılır) - **herhangi bir girişten önce en az bir etki alanı
   oluşturun**.

## 5. Otomatik algılama

`autodetect.*` (varsayılan kapalı) bilinmeyen etki alanının yapılandırmasını
giriş anında keşfetmeye izin verir:

- `enabled`: algılamayı etkinleştirir;
- `use_ispdb`: Mozilla tabanını sorgular (`autoconfig.thunderbird.net`);
- `probe`: `imap.`/`mail.`/`smtp.<etkialanı>` portlarını zorlar.

Algılama sırası: DNS SRV > Mozilla autoconfig > MX > port sondası.

Sieve kaynaktan bağımsız algılanır: `_sieve._tcp` (SRV), yoksa `sieve.<etkialanı>`
üzerinde **4190** port sondası (`probe` aktifse veya *Algıla* düğmesiyle),
sonra bulunan IMAP hostunda - Dovecot ManageSieve'i neredeyse her zaman
aynı hostta sunar. Şifreleme yapılandırmaya gerek yok: sunucu ilan
edince bağlantı otomatik STARTTLS'e geçer.

*Etki alanları* sayfasının **Algıla** düğmesi aynı algılamayı istek üzerine
`autodetect.enabled`'den bağımsız çalıştırır ve etki alanı alanlarını
doldurur.

> `/setup/` kurucusu da vhost hostunda (ve ebeveynlerinde) bu algılamayı
> deneyerek ilk etki alanını otomatik oluşturur.

## 6. Test

**Bağlantıyı test et** düğmesi modalın aktif sekmesinin protokolünü
doğrular (IMAP, SMTP veya Sieve; diğer sekmeler IMAP'i test eder). Her
değişiklikten sonra kullanın; kullanıcılar bağlanmadan önce kimlik
bilgileri, portlar ve şifreleme doğru olsun.

## 7. Tipik durum: tek posta sunucusu

Tek sunucu için (örn. `mail.example.com`'da `example.com`'u sunan
Postfix + Dovecot):

1. `imap_host = mail.example.com`, `smtp_host = mail.example.com` ve
   muhtemelen `sieve_host` ile `example.com` etki alanını oluşturun.
2. `default_domain = auto` ve `restrict_domains = true` bırakın.
3. Kullanıcılar `user@example.com` veya sadece `user` ile girer (etki
   alanı HTTP hostundan çıkarılır).

Çok-etki-alanlı varyant: `restrict_domains = false` ile `*` etki alanı
catch-all görevi görür - her beyan edilmemiş etki alanı onun
parametrelerini kalıtır (bkz. §3; dikkat, birincil giriş o zaman tüm
etki alanlarında mümkün kalır - `secondary_only` onu reddedebilir, bkz. §8).

## 8. Ek hesap olarak genel etki alanları (Gmail, Outlook, Yahoo)

Gmail, Outlook.com ve Yahoo artık düz parolayla IMAP kimlik doğrulaması
kabul etmiyor - bu hesapların eklenmesi **OAuth2** ile olur (popup'ta
onay, sonra IMAP ve SMTP için XOAUTH2/OAUTHBEARER).

OAuth2 uygulama kimlik bilgileri (client ID, secret, Microsoft tenant)
**her kullanıcının kendi** bilgileridir: admin hiçbir uygulama kaydetmez -
yalnızca etki alanında sağlayıcıyı yetkilendirir. Her kullanıcı sağlayıcının
geliştirici konsolunda kendi uygulamasını yaratır.

### Yapılandırma (admin)

Bu üç etki alanı **kurulumda önceden oluşturulur** (ek hesap + OAuth
sağlayıcısı zaten ayarlı). Sildiyseniz şöyle yeniden oluşturun:

1. **Etki alanını oluşturun** (*Etki alanları > Ekle* veya *Algıla*
   düğmesi): Gmail için `imap.gmail.com:993` / `smtp.gmail.com:465`,
   Outlook için `outlook.office365.com:993` / `smtp.office365.com:587`,
   Yahoo için `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465`.
2. **Yalnızca ek hesap**'ı işaretleyin (birincil giriş reddedilir) ve
   **Auth OAuth2**'de sağlayıcıyı seçin.

### Uygulama kaydı (kullanıcı)

Kullanıcı etki alanının hesabını ilk ekleyişinde webmail uygulama kimlik
bilgilerini ister ve kullanıcı için kaydeder - sonra *Ayarlar > OAuth2
uygulamaları*'nda yönetilir (sağlayıcı başına bir uygulama, o sağlayıcının
tüm hesapları için yeniden kullanılır). Konsolda bildirilecek redirect URI:
`<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > OAuth Web
  istemcisi, `https://mail.google.com/` scope'u): scope *restricted* -
  "Testing" modundaki uygulama ~100 test kullanıcısıyla sınırlı ve
  refresh token'ları ~7 günde sürer; üretim Google doğrulaması gerektirir.
- **Microsoft** (Entra > App registrations, kişisel hesapları kapsayan
  `signInAudience`): "Office 365 Exchange Online" temsilci izinleri
  `IMAP.AccessAsUser.All` + `SMTP.Send`, `offline_access` scope'u örtük.
  Onayda "doğrulanmamış yayıncı" banner'ı mümkün (engellemiyor).
- **Yahoo** (Developer Console > OpenID Connect + Mail API uygulaması,
  `openid`, `mail-r`, `mail-w` scope'ları): Mail API erişimi **önceden
  Yahoo onayı** gerektirir (başvuru formu + elle inceleme) ve redirect URI
  yayılımı ~24 saat sürebilir.

### Çalışma

- Kullanıcı hesabı hesaplar menüsünden veya *Ayarlar > Hesaplar*'dan ekler:
  adres girişi > (ilk sefer: uygulama kimlik bilgileri girişi) > onay
  popup'ı > hesap sağlayıcı rozetiyle görünür. *Ayarlar > Hesaplar* ayrıca
  değiştirme, kimlik bilgisi güncelleme, ana hesaba yükseltme ve silme
  sağlar.
- Ek hesap **ana hesaba yükseltilebilir** (*Ayarlar > Hesaplar*'daki
  yıldız) - etki alanının `secondary_only` bayrağı yalnızca *birincil
  girişi* engeller, bu yükseltmeyi değil.
- *Refresh token* şifreli saklanır (`accounts.imap_pass`); her
  IMAP/SMTP bağlantısı geçici access token değiştirir. Microsoft ve Yahoo
  **her kullanımda refresh token'ı yeniden yayınlar**: tabanda otomatik
  değiştirilir.
- Uygulama secret'ı şifrelenir (`oauth_apps.secret`, parolalarla aynı
  mekanizma) ve arayüze asla döndürülmez - düzenlemede "boş = değişiklik
  yok".
- **Uygulama değişikliği veya silinmesi** bağlı hesapları bozar: refresh
  token orijinal client_id'ye bağlı kalır - hesabı silip yeniden ekleyin.
- Bu sağlayıcılarda ManageSieve yok: Sieve filtreleri OAuth hesaplarında
  kullanılamaz.

`*` etki alanında `secondary_only`'nin normal iç etki alanlarıyla birleşimi
birincil girişi listelenen etki alanlarına ayırırken ek hesapları her
yerde açık bırakır - `restrict_domains` olmadan kullanışlı.
