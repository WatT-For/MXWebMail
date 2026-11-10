# Eklentiler

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

Eklentiler `app/plugins/<name>/` içine kapalı uzantılardır, etkinleşince
yüklenir (admin'den, *Uzantılar* sekmesi). Çekirdeğin **hook**'larına
kaydolurlar.

> **Salt-okunur oturum**: GET uç noktaları PHP oturumunu erken kapatır
> (`session_write_close`) kilidi serbest bırakmak için. GET'ten çağrılan
> hook'lar (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **asla `$_SESSION`
> yazmamalıdır** - bu yazımlar sessizce kaybolurdu.

## 0. GitHub reposu üzerinden dağıtım

Eklentiler release zip'i `mxwebmail-<ver>.zip`'te **dahil değil**:
yapılandırılabilir bir **ref**'te (`update.plugins_ref` - varsayılan
`'release'` = son release'in etiketi, `'main'` veya herhangi git-ref)
repoda doğrudan okunurlar. Katalog (`PluginPackageService`, *Uzantılar*
sekmesi - kurulu olmayan girdiler tablonun sonunda) git-API'sinden
oluşturulur:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` `app/plugins/` altındaki
  dosyaları **sha1 git-blob**'ları ve boyutlarıyla listeler;
- her eklentinin `plugin.json`'ı `raw.githubusercontent.com` üzerinden
  okunur (commit sha'sına sabitlenmiş) - sürüm, açıklama,
  `min_app`/`max_app`;
- **Kur / güncelle**: eklentinin her dosyası `raw` üzerinden indirilir,
  doğrulanır (boyut + tree blobunun sha1'i), sonra `plugin.json` klasör
  değişiminden önce doğrulanır (uyan ad, semver sürüm, sağlam `main`,
  `APP_VERSION` uyumluluğu) - kurulu eklenti varsayılan **kapalı**; eski
  klasör `storage/update/plugins/backup-<name>-<ts>`'ye gider (bir tane
  saklanır, otomatik geri alma);
- **Kaldır**: klasörün kesin silinmesi + `plugins` satırının temizlenmesi
  (yapılandırma kaybolur), yedek yok.

Eklenti sürümü yayınlamak = `plugin.json`'ında `version` bump'ı ve
`update.plugins_ref`'in izlediği dala push - release veya asset gerekmez.

Repo `GitHubClient::REPO`'da kilitli (çekirdek güncelleyicisiyle aynı
sabit) - keyfi URL kabul edilmez. `update.channel` ve release önbelleği
(`cache.update_ttl`) çekirdek güncelleme denetimiyle ortak; ref>commit bu
TTL'e önbelleklenir, tree ve manifestolar sha'larına adreslenir (değişmez).
Statik `check` `plugin.json`'da semver `version` ister.

Çevrimdışı dev/testler için `update.plugins_local` (`config.php`'de eklenti
klasörünün mutlak yolu) kaynak görevi görür: disk kopyasıyla katalog ve
kurulum, uzak doğrulama yok.

Aktif eklenti ayrıca **admin yüzeyine ayrılabilir** (*Uzantılar* sekmesinin
*Kullanıcı erişimi* sütunu, `plugins` tablosunda `user_allowed` bayrağı,
**varsayılan işaretsiz**): o zaman yalnızca `/{admin_path}` sayfasında,
`/api/admin/*` rotalarında ve admin oturumlarında yüklenir. Başka yerde
tamamen etkisizdir (hook yok, `/api/plugin/<name>/*` > 404) - panele bağlı
olmayan `is_admin` kullanıcıları için bile. Admin girişi hook'ları
(`admin.post-login`, `admin.totp-required`, `admin.mounts`) çalışmaya devam
eder. Manifestosunda bildirilen hook'ların hepsi `admin.*` olan eklentinin
hiçbir kullanıcı yüzeyi yoktur: sütun onun için anahtar göstermez (`-`).

## 1. Resmi kataloğun eklentileri

| Eklenti | Görev | Hook'lar |
|---|---|---|
| `add-x-originating-ip-header` | Gönderimlere `X-Originating-IP` (göndericinin IP'si) ekler. | `message.before-send` |
| `anti-spoofing` | Okuyucuda sahtecilik algılama: tutarsız Return-Path/Reply-To/Sender, marka taklit eden display-name (homoglifler ve confusables algılanır), tuzak etki alanı (tuzak alt-etki-alanı, typosquat, trans-TLD), gönderici etki alanıyla hizalamada değerlendirilen SPF/DKIM/DMARC (kararlar `Authentication-Results`, `Received-SPF` veya SpamAssassin/rspamd başlıklarından okunur) - korunan etki alanlarının alt-etki-alanları yasaldır. | `imap.message-headers`, `message.view` |
| `avatars` | Gönderici avatarı: bilinen hizmet ikonları, Gravatar, etki alanı favicon'u, baş harfler. | `app.data` |
| `black-list` | Giriş kara listesi (wildcards + istisnalar) - **çözümlenmiş** e-postada doğrulanır (mapping sonrası). | `auth.login.mapped` |
| `white-list` | Giriş beyaz listesi (wildcards + istisnalar) - **çözümlenmiş** e-postada doğrulanır (mapping sonrası). | `auth.login.mapped` |
| `carddav-contacts` | CardDAV defterinden kişi senkronizasyonu (Nextcloud, Radicale, iCloud…) - **HTTPS + genel host gerekli**. | `app.data` |
| `change-password` | Parola değişimi (poppassd, PDO, LDAP, Mailcow sürücüleri). | `password.change`, `app.data` |
| `color-tags` | Mesajlarda renkli etiketler (manuel + otomatik kurallar). | `app.data`, `message.meta` |
| `ics-viewer` | Okuyucuda `.ics` davet/ek önizlemesi **+ iTIP yanıtı** (`METHOD:REQUEST`'te Kabul et/Belki/Reddet, SMTP ile organizatöre gönderilir). | `app.data` |
| `imap-contacts-suggestions` | IMAP klasörünün göndericilerinden kişi önerileri. | `contacts.provider` |
| `ldap-contacts` | LDAP dizininden kişi önerileri (otomatik tamamlama). | `contacts.provider` |
| `ldap-login` | LDAP çözümlemesi: uid/alias > girişten önce gerçek IMAP adresi. | `auth.login.mapping` |
| `mailbox-detect` | Sistem klasörlerinin algılanması ve eksiklerin oluşturulması. | `app.data` |
| `pwa` | Webmail'i kurulabilir kılar (manifesto + minimal service worker, ikonlar). | `app.head`, `app.routes` |
| `webmail-migration` | **RainLoop veya SnappyMail** `data` klasöründen içe aktarım admin sayfası (sunucu yolu veya ZIP): etki alanları, `AddressBook.sqlite`/PDO kitapları, kimlikler, ayarlar, sistem klasörleri, PGP anahtarları - dry-run tarama sonra idempotent içe aktarım, bilinmeyen hesaplar yoksayılır. | `admin.mounts` |
| `two-factor-auth` | İki faktörlü TOTP (Google Authenticator, Aegis…) - kullanıcı hesapları **ve** yerel admin hesabı (Admin > Güvenlik'in özel alt-sekmesinde QR sağlama; eklenti aktifken admin girişinde zorunlu). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Yönetici duyuru banner'ı (app sayfası, opsiyonel giriş sayfası - bakım). Yalnızca düz metin. | `app.head` |
| `attachments-clamav` | Yüklenen eklerin ve içe aktarımların ClamAV taraması (`clamd`, `zINSTREAM`) - algılamada ret, yapılandırılabilir `on_error` politikası. | `attachment.upload` |
| `filelink` | SMTP için çok büyük ekler **süreli indirme bağlantısı** olarak gönderilir (`/filelink/<token>` - zorlanmış attachment, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Her (kaldır)işaretlemede spam/ham öğrenimi: harici komut (`rspamc`, `sa-learn`) veya dovecot-antispam klasörüne IMAP kopyası. | `message.spam` |
| `wkd-keys` | Alıcıların PGP anahtarlarının otomatik keşfi: **WKD** (advanced + direct), **Autocrypt** başlık toplama, opsiyonel `keys.openpgp.org` yedeği - yazarken içe aktarım önerilir. | `app.data`, `message.view` |
| `seasonal-decorations` | Tarihe göre banner'larda sade monokrom manzara (Yılbaşı, Paskalya, 1 Nisan, Cadılar Bayramı, Noel): `scenes.json` ({svg, pos, size}) üzerinden SVG nesne kütüphanesi `mask-image` katmanlarına yeniden birleştirilir, otomatik kontrast renk - ayarlanabilir pencereler, oturum dışı opsiyonel, animasyon yok. | `app.head` |
| `link-confirmation` | Mail gövdesindeki bağlantıyı açmadan önce onay modalı (anti-phishing): gösterilen gerçek hedef, şüpheli bağlantıların işaretlenmesi (metin ≠ URL, literal IP, punycode, URL'de kimlik bilgisi), güvenilen etki alanları ve "bu etki alanı için bir daha sorma". | `app.head` |
| `always-cc-bcc` | Gönderimde sistematik Cc veya Bcc alıcıları (uygunluk arşivi), hesap başına veya tümü - Bcc görünmez kalır. | `message.before-send` |
| `report-abuse` | "Şikayet et" düğmesi (eylem çubuğu): işaretli mailleri yönetilen abuse adresine `.eml` ekleri olarak iletir, opsiyonel spam işaretleme. | `app.data` |
| `vcf-viewer` | Okuyucuda `.vcf` ek önizlemesi + tek tıkla deftere içe aktarım (`features.vcf_viewer` ile frontend'e entegre düğme). | `app.data` |
| `send-rate-limit` | Hesap başına gönderim kotası (mesaj/saat, mesaj/gün, alıcı/gün) - üzerinde 429 ret. Yalnızca webmail. | `message.before-send` |
| `login-notify` | Bilinmeyen IP'den girişte hesaba uyarı maili (Gmail modeli) - gelen kutusuna okunmamış bırakılır, kullanıcı başına opt-out. | `auth.post-login`, `app.data` |
| `sso-header` | Kimlik doğrulama proxy'siyle SSO (güvenilen kullanıcı başlığı) - bildirilmiş proxy IP'leri ve IMAP master parola gerektirir. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Özel IMAP kutusunun DMARC (rua) raporlarını birleştiren admin sayfası - XML/gzip ayrıştırma, etki alanı ve kaynak IP'ye göre toplamlar. | `admin.mounts` |

## 2. Eklenti yapılandırması

Admin'de (*Uzantılar* > ⚙ düğmesi) yapılandırma eklentinin
`config.schema.json` dosyasından üretilir. Desteklenen tipler: `string`,
`text`, `integer`, `number`, `password`, `bool`, `enum`, `color`, `list` -
`color` doğal seçici + `#RRGGBB` alanı işler (boş değer kabul: alan
eklentinin varsayılanına düşer). Değerler `plugins` tablosunda saklanır
(`config` sütunu, JSON).

Dikkat çeken yapılandırma detayları:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (varsayılan `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimlik), sonra sürücünün kendi
  parametreleri (`poppassd_host/port`, `:email :oldpass :newpass :domain
  :username :login_name` yer tutuculu `pdo_dsn`/`pdo_sql`,
  `ldap_uri`/`ldap_user_dn_format`,
  `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (`%u` yer tutucu), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (`%q` yer tutucu), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (korunan etki alanı
  alt-etki-alanlarını da kapsar, örn. `accountprotection.microsoft.com`),
  `brands` (vurgular ve confusables normalizasyonundan sonra görünen ad
  içinde algılanır - "AppIe TV" "Apple TV" olarak tanınır) ve anahtarlar
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  SPF/DKIM/DMARC sonuçları yalnızca `From` etki alanıyla hizalıyken dikkate
  alınır: taşıyıcı imzasındaki `dkim=fail` veya röleli (SRS-)mailfrom'daki
  `spf=fail` alarmı tetiklemez.
- **`two-factor-auth`** - `issuer` (varsayılan `app_name`), `label` (`email`
  = tam adres, `local` = `@` öncesi kısım; `local` TOTP uygulamalarının
  etki alanından marka ikonu çıkarmasını önler, örn. Yahoo) ve `icon_url`
  (standart dışı `image=` parametresi, Aegis/andOTP/FreeOTP tarafından
  gösterilir - Authy yoksayar; boş = örneğin genel favicon'u). Eklenti
  yerel admin hesabının 2FA'sını da sağlar: `config.php`'de
  `admin_totp_secret` gizli anahtarı (admin API'si
  `/api/admin/plugin/two-factor-auth/*` üzerinden), oturumda beklemede,
  admin girişinde `admin.post-login` hook'uyla zorlanır. Eklentiyi
  devre dışı bırakmak admin zorlamasını kaldırır.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  boşluk, virgül veya noktalı virgülle ayrılmış değerler), `exceptions`,
  `auth_error` (403 "yetkisiz hesap" > genel 401). Miras eklentiden
  devralınan semantiğe dikkat: black-list için `exceptions` = **izinli
  kalmaya devam eden** kara liste e-postaları; white-list için `exceptions`
  boşsa beyaz liste dışındaki her e-posta reddedilir, ama doluysa **yalnızca
  istisnaların e-postaları reddedilir** ve geri kalan geçer - "ek olarak
  izinli" adresleri oraya koymayın (beklenen etkinin tersi).

- **`link-confirmation`** - `mode` (`all` = her dış bağlantı, `suspicious`
  = yalnızca şüpheli bağlantılar), `whitelist` (güvenilen etki alanları,
  satır başına bir veya boşluk/virgül/noktalı virgülle ayrılmış -
  alt-etki-alanları dahil), `warn_mailto` (`mailto:`/`tel:`'i de onayla,
  varsayılan kapalı) ve `remember` ("bu etki alanı için bir daha sorma"
  onay kutusu - localStorage ile tarayıcıda hatırlanır, sunucu tarafında
  değil).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (hedefler, boş =
  etkisiz), `senders` (gönderici wildcard'ları, `*` = tümü).
- **`report-abuse`** - `abuse_address` (gerekli, boş = etkisiz),
  `subject_prefix`, `mark_spam` (ayrıca `message.spam` ile spam işaretler -
  öğrenmeyi besler).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = limitsiz), `exempt` (muaf hesap wildcard'ları). Yalnızca webmail'i
  sınırlar: doğrudan IMAP/SMTP istemcisi kotayı atlar.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (kullanıcı başına opt-out Ayarlar sekmesi).
- **`sso-header`** - `header` (`X-Forwarded-User` veya `REMOTE_USER`),
  `trusted_ips` (**gerekli, boş = eklenti etkisiz**), `master_pass`
  (gerekli; *same* modu = passdb `master=yes`), `master_user` (opsiyonel:
  `user*master` giriş), `allowed_domains`, `hide_form`. Örnek proxy'yi
  atlayarak erişilebilir olmamalı (başlık taklit edilebilir olurdu).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (raporların
  IMAP kutusu), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Eklentinin kendi IMAP bağlantısı (etki alanı
  seçenekleri uygulanmaz).

Bazı eklentilerin admin şeması yoktur: yapılandırmaları **kullanıcı
başınadır** (örn. `carddav-contacts`, `color-tags`, hesap durumu için
`two-factor-auth`).

## 3. Eklenti oluşturma

### Minimal yapı

```
app/plugins/benim-eklentim/
├── plugin.json          # manifesto
├── BenimEklentim.php    # ana sınıf
├── config.schema.json   # (opsiyonel) admin yapılandırma şeması
├── langs/<lang>.php     # sözlükler (plugin.<name>. önekli anahtarlar)
└── js/ veya assets/     # (opsiyonel) /assets/plugin/<name>/<file> ile sunulan assetler
```

### `plugin.json`

```json
{
	"name": "benim-eklentim",
	"label": "plugin.benim-eklentim.label",
	"class": "BenimEklentim",
	"main": "BenimEklentim.php",
	"version": "0.1.0",
	"description": "plugin.benim-eklentim.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (opsiyonel) Admin > Uzantılar'da eklentinin görünen adı (önerilen
i18n anahtarı `plugin.<name>.label`, literal metin kabul; teknik `name`
yanında gösterilir ve tanımlayıcı görevi görür).

`min_app`/`max_app` (opsiyonel, semver `x.y.z`) `APP_VERSION` ile
uyumluluğu sınırlar - aralık dışında kurulum veya güncelleme reddedilir ve
katalog girdiyi *uyumsuz* işaretler.

### Ana sınıf

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class BenimEklentim extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Kullanılabilir hook'lar

- **Çekirdek**: `app.routes`, `app.head` (`app.php`, `login.php` ve
  `maintenance.php`'nin `<head>`'ine enjekte edilen HTML - `admin*.php`
  görünümleri yaymaz), `app.data`, `auth.login.mapping`,
  `auth.login.mapped`, `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > admin panelinde `pluginMount`
  tarafından işlenen `{js, plugin, section, tab?, label?}` tanımlayıcısı -
  `tab` yoksa: `section` bölgesinde satır içi blok, örn. `security-account`;
  `tab` + `label` ile: `section` sayfasının özel alt-sekmesi, örn.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, yerel admin hesabı girişinde yayınlanır -
  `TotpRequiredException` fırlatmak ikinci faktör ister),
  `admin.totp-required` (`first` > boolean: admin giriş sayfasında TOTP
  alanını gösterir).

Her eklenti `hooks`'ta dinlediği hook'ları bildirir. Yalnızca `admin.*`
hook'ları bildiren manifesto eklentiyi salt-admin olarak işaretler -
Uzantılar sekmesinin *Kullanıcı erişimi* sütunu onun için anahtar
göstermez. Çekirdek `HookRegistry::run('hook', …)` çağırır; eklenti aynı
adlı metodu (veya çekirdek kuralına göre `on<Hook>`) uygular.

### Genel API

Her eklenti `/api/plugin/<name>/<action>` (GET/POST) ile çağrılabilir >
`api<Action>(Request $r, ?array $user)` metodu. CSRF belirteci tüm
yöntemlerde gerekir (`api.js` istemcisi sistematik gönderir) -
`X-CSRF-Token` başlıksız doğrudan çağrılar 403'te reddedilir.

Admin tarafında, `/api/admin/plugin/<name>/<action>` (GET/POST) >
`apiAdmin<Action>(Request $r)` metodu: admin oturumu (`$_SESSION['admin']`)
+ CSRF gerekir; `$user` yok (yerel admin hesabı `users` satırı değil).
Dikkat: `requireAdmin` oturumu yazıma kapatır - oraya durum saklamak için
(örn. bekleyen gizli anahtar) `$this->app->startSession()` ile yeniden
açın, sonra `session_write_close()`.

### Kullanıcı sekmesi

`#/@settings/…`'te sekme eklemek için `/assets/plugin/<name>/<file>`
üzerinden sunulan JS sağlayın ve sekmeyi bildirin (örnek olarak
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth`
eklentilerine bakın).

### Çeviriler

`langs/<lang>.php`'de `plugin.<name>.` önekli anahtarlar; manifestoların ve
şemaların `description`/`label`/`help`'i bu anahtarları kabul eder
(aksi halde pass-through). PHP tarafı: `$this->t('anahtar')`, JS tarafı:
`mount(element, ctx)`'te `ctx.t('anahtar')`.
