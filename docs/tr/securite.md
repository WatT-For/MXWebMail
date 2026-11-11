# Güvenlik

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

## 1. Yerleşik güvenlik modeli

### "Oturum açık kalsın" (kalıcı belirteçler)

"Oturum açık kalsın" onay kutusu `mxwm_remember` cookie'si yayınlar
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, `remember_days` süresi
- varsayılan 30 gün). `auth_tokens` tablosu yalnızca doğrulayıcının
**SHA-256 hash'ini** saklar; doğrulayıcı **her kullanımda döner** (zaten
sunulmuş çalınmış cookie tespit edilir ve geçersiz kılınır). Geri yükleme
IMAP parolasını yeniden çözer (`CredentialCrypt`) ve kimlik bilgilerini
yeniden doğrular - başarısızlık belirteci siler. Her belirteç **verilişteki
IP'yi** (`ip`), **son bilinen IP'yi** (`last_ip`, her geri yüklemede) ve
User-Agent'ı kaydeder - bilinmeyen oturumu tespit için Ayarlar > Oturumlar'da
görünür. Belirteçler orada listelenir ve iptal edilebilir; mevcut belirteç
**manuel** çıkışta iptal edilir (`autologout` hareketsizlik çıkışı onu
korur: oturum sonraki ziyarette sessizce geri yüklenir).

### CSRF

Her POST oturum CSRF belirteci gerektirir (`X-CSRF-Token`, form alanı veya
JSON anahtarı); eklenti API'leri (`/api/plugin/…`) HTTP yönteminden bağımsız
gerektirir. **`Sec-Fetch-*`** başlıklarına dayalı ikinci katman, belgeye
GET navigasyonu dışında cross-site istekleri reddeder - kullanıcı
etkinleştirmesi olmadan bile, çünkü tarayıcı her yönlendirmede
`Sec-Fetch-User`'ı kaldırır (portallar, yeniden yazılmış bağlantılar)
(`secfetch_allow` bu kuralları genişletir).

### HTTP başlıkları

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, opsiyonel HSTS ve istek
başına nonce'lu **CSP** (`script-src` yönergesi Knockout'un gerektirdiği
`unsafe-eval` içerir). Satır içi sunulan ekler
`Content-Security-Policy: sandbox` alır.

### Kimlik bilgisi şifreleme

IMAP parolaları (ve eklenti gizli anahtarları) **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) ile `app/storage/app.key` ana
anahtarıyla, oturumda ve tabanda şifrelenir. Bkz.
[base-de-donnees.md](base-de-donnees.md).

### Ek OAuth2 hesapları

Genel etki alanları (Gmail, Outlook, Yahoo - bkz. [domaines.md](domaines.md))
**authorization code + PKCE** ile eklenir: `state` anti-CSRF ve verifier
oturumda tutulur, `redirect_uri` sunucu tarafında yeniden kurulur (host
`force_https` gibi filtrelenir). Yalnızca *refresh token* parola gibi
şifrelenerek kalıcılaşır; geçici *access token* yalnızca bağlantı süresince
bellekte yaşar (XOAUTH2/OAUTHBEARER). Refresh token rotasyonu (Microsoft,
Yahoo) her değişimde tabanda yeniden uygulanır. `id_token`'un email'i
tutarlılık denetimi görevi görür; sahiplik kanıtı OAuth IMAP bağlantısının
kendisi kalır. Callback (`/api/oauth/callback`) onay popup'ı için yalnızca
mini `postMessage`>`window.close()` sayfası döndürür.

OAuth2 uygulama kimlik bilgileri **kullanıcı başınadır** (`oauth_apps`
tablosu, ilk hesap eklemede veya ayarlarda girilir): `secret` aynı ana
anahtarla şifrelenir ve arayüze asla döndürülmez. Silinen veya değiştirilen
uygulama bağlı hesapları geçersiz kılar (refresh token orijinal
client_id'ye bağlı kalır).

### Oturum açma rate-limit

`login_max_attempts` (5) sonra `login_lock_seconds` (300 s) engellemesi.
Sayaç `storage/cache/login_attempts.json`'da kalıcıdır (`sha256(IP|login)`
anahtarı, 1 saatten eski hareketsiz girdiler temizlenir, `flock` altında
yazım). Aktif engel reddedilen denemelerle ne uzatılır ne yeniden
günlüklenir (429). Admin girişine de uygulanır. fail2ban tarzı kimlik
doğrulama günlüğü mevcuttur (`auth_logging*`, `{request:ip}`,
`{imap:login}`, `{result}` yer tutucuları; CR/LF nötralize): webmail ve
admin girişlerini (`admin:` önekli giriş) ve TOTP kodu hatalarını kapsar.
Tam entegrasyon: [fail2ban.md](fail2ban.md).

### HTML mesaj temizleme

Mesaj gövdesi tarayıcıda temizlenir (inert DOMParser + allowlist): sınırlı
etiket ve öznitelikler (`script`/`iframe`/`object`/`on*` yok…),
`javascript:`/`vbscript:`/`data:` şema engelleme, kurnaz stillerin
nötralizasyonu (`position:fixed`, gizli içerik), sınıf önekleme. CSP ikinci
katmandır.

### Mesaj gönderme

- **From**: sıkı whitelist - hesap adresi veya beyan edilmiş kimlik (IDN
  iki biçimde de kabul); herhangi başka değer reddedilir (403).
- **Alıcılar**: To/Cc/Bcc alanları sıkı ayrıştırmadan geçer - geçersiz
  segment sessizce düşürülmez, gönderim anlaşılmayan adresler listesiyle
  reddedilir (400).
- **CR/LF**: konuda, threading başlıklarında (In-Reply-To, References),
  atıflı klasör adlarında ve ek MIME parametrelerinde (`filename=`)
  nötralize.
- **Bcc**: başlık SMTP sunucusuna gönderilen veriden çıkarılır ama
  "Gönderilenler" kopyasında saklanır (yalnızca gönderici görür).
- **Editör**: TinyMCE iframe'i her uzak yüklemeyi engelleyen
  `Content-Security-Policy` taşır - iletilen/yapıştırılan mesaj içeriği
  açılışta okundu teyidi sızdıramaz.
- **Geçici ekler**: kullanıcı kapsamlı belirteçler, ad temizlenir
  (`basename` + kontrol karakterleri kaldırılır), 24 saat sonra temizlik.
- **SMTP hataları**: istemci genel mesaj alır; ham sunucu yanıtları yalnızca
  `labs.smtp_show_server_errors` açıksa gösterilir (teşhis, iç detayları
  açığa çıkarabilir).

### OpenPGP

Şifreleme/çözme **tamamen tarayıcıda** gerçekleşir (OpenPGP.js). Sunucu
yalnızca armored anahtarlığı saklar; özel anahtarlar parola ifadeleriyle
korunur ve sunucu tarafında asla çözülmez. **Yalnızca mesaj gövdesi
şifrelenir**: ek gönderimi şifreleme açıkken reddedilir (açık metin
giderlerdi).

### Görüntü proxy'si

Uzak görüntüler `/api/proxy`'den geçer: yalnızca `http(s)`, opsiyonel
allowlist (wildcards), **anti-SSRF** (özel/rezerve IP'lerin reddi,
**her** yönlendirmede yeniden doğrulanır - maks. 3), zaman aşımı, sınırlı
boyut. Gövdenin gerçek MIME'ı **bitmap allowlist**'e karşı doğrulanır
(png, jpeg, gif, webp, avif, bmp, ico) - SVG ve her aktif tip reddedilir -
ve yanıt `Content-Security-Policy: sandbox` + `nosniff` taşır. Günlükler
yalnızca scheme+host+yol saklar (belirteç içerebilecek query asla).

### IP ve proxy yönetimi

`X-Forwarded-For`/`X-Forwarded-Proto` yalnızca `REMOTE_ADDR`
`trusted_proxies`'te (CIDR) ise dikkate alınır. HTTPS algılama (`isHttps`)
aynı kuralı izler.

## 2. Sağlamlaştırma önerileri

- **Document root = `public/`** yalnızca; asla proje köküne yöneltmeyin.
  Özel klasörlerin (`app/`, `config/`, `storage/`…) koruyucu `.htaccess`'leri
  var ama yalnızca güvenlik ağı.
- **HTTPS zorunlu**: üretimde `force_https` + `hsts` etkinleştirin.
- **Ters proxy arkasında** (Nginx, HAProxy, Cloudflare…): rate-limit,
  allowlist ve günlükleri güvenilir kılmak için `trusted_proxies`'i doldurun;
  onsuz her `X-Forwarded-*` yoksayılır.
- **Admin'i sınırlayın**: `admin_ip_allowlist` (IP/CIDR CSV) ve sıradışı
  `admin_path`; `two-factor-auth` eklentisiyle **admin TOTP** etkinleştirin
  (`admin_totp_secret` gizli anahtarı yalnızca eklenti aktifken zorlanır).
- **Yerel admin hesabı**: yönetim için IMAP fallback yerine `admin_user` +
  `admin_password_hash` (güçlü parola) tercih edin.
- **`app.key` yedeği** webroot dışında; asla sürümlemeyin.
- **`debug = false`** üretimde (iç hataları açığa çıkarmayın).
- **`/setup` sihirbazı** çekirdeğe gömülü: `app/storage/INSTALLED` kilidi
  konulunca 404 yanıtlar - webroot'ta silinecek kurulum dosyası yok.
- `ssl.verify_certificate`'i kontrol edin: `false` yalnızca kontrollü
  self-signed sertifikalar için kabul edilebilir.
- Bakım modunda `maintenance = true` `/`'da 503'te özel bakım sayfası
  sunar (bağlı kullanıcılar sonraki API isteğinde oraya aktarılır, oturum
  korunur) ve kullanıcı `/api/*`'sini keser (admin ve `/api/ping` aktif
  kalır).

## 3. Bilinen sınırlar

- `GitHubClient::REPO` reposu `WatT-For/MXWebMail`'e kilitli: kurulan
  paketler (release çekirdek zip'i, git-API + `raw.githubusercontent.com`
  üzerinden `update.plugins_ref` ref'inde repoda okunan eklenti dosyaları,
  her biri sha1 git-blob ile doğrulanır) yalnızca bu repodan gelir - keyfi
  URL yapılandırılamaz. Çekirdek paketi iki düzeyde doğrulanır: asset'in
  sha256 toplamı (release'in `<zip>.sha256` dosyası) sonra `files.sha256`
  (gömülü dosya başına manifesto, açmadan sonra denetlenir); paketin
  `APP_REQ_PHP`/`APP_REQ_FROM` `App.php` sabitleri gereksinimleri
  (PHP sürümü, minimum kaynak sürüm) beyan eder, kopyalamadan önce
  uygulanır. Resmi repo **genel**: API, assetler ve `raw` tokensız çalışır
  (GitHub'un anonim API kotası `cache.update_ttl` önbelleğiyle karşılanır).
  `config.php`'de yapılandırılmış isteğe bağlı bir GitHub PAT (repoya
  `Contents: Read` erişimi) kotayı yükseltebilir veya `update.repo` üzerinden
  özel bir fork'a hizmet verebilir - assetler o zaman `releases/assets/<id>`
  uç noktasından sunulur (`browser_download_url` özel repoda doğrulanmış olsa
  bile 404 döndürür).
  `update.plugins_local` ve `update.manifest_url` (alternatif release
  manifestosu) dev/testlere ayrılmış - yalnızca `config.php` üzerinden
  değişir, asla admin API'si ile. Dosya kopyalama sırasında
  `storage/update/applying` bayrağı kullanıcı API'sini keser (503, 15 dk
  sonra sürer).
- SQLite > MariaDB geçişi veri taşımaz (boş şema).
- OpenPGP özel anahtarları sunucu tarafında armored saklanır (parola
  ifadesi anahtarın kendisini korur, ek kapsülleme yok).
