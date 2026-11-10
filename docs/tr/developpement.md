# Geliştirme

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

Mimarinin anlaşılması ve projenin genişletilmesi için belgeler (çekirdek,
eklentiler, temalar).

## 1. Mimari

```
public/                ön denetleyici (index.php) + asset'ler
app/src/MXWebMail/     uygulama kodu:
  Application/App.php  servis kapsayıcısı + dispatch + rotalar
  API.php, Utils.php   vendored kod için uyumluluk cepheleri (MailSo/Lib)
  Compat/              MailSo uyumluluk köprüleri (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              yapılandırma yükleme/kaydetme
  Controller/          PageController, ApiController, AdminController,
                       SetupController (/setup sihirbazı,
                       storage/INSTALLED olmadığı sürece sunulur - sonra 404) ;
    Api/               etki alanı başına API denetleyicileri (Messages,
                       Folders, Contacts, Compose, Accounts, Settings,
                       Filters, Pgp, Poll)
    Admin/             sekme başına admin denetleyicileri (System, Domains,
                       Users, Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + sözlükler
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          veri erişimi (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (ortak HTTP Releases), UpdateService (çekirdek),
                       PluginPackageService (eklenti katalog/kurulum/kaldırma),
                       kişi içe aktarımları (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - filtre kuralı üretimi
  Theme/               ThemeManager
app/src/MailSo/        vendored posta kütüphanesi (değiştirilmemiş)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           MXWebMailLib yardımcıları (classmap)
app/src/bootstrap.php  özel otomatik yükleyici
```

- **Frontend**: Knockout + Bootstrap 5.3, `public/assets/js/` içinde ES
  modülleri (app.js, viewmodels/, models/, bindings/, ui/).
- **Composer yok**: özel otomatik yükleyici (bkz. §2).

## 2. Otomatik yükleyici (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` >
  `app/src/Sabre`.
- **Büyük/küçük harf duyarsız classmap**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (miras kod, küçük harf dosyalar, çoklu
  sınıf). `token_get_all` taramasıyla üretilir,
  `app/storage/cache/classmap.php`'de önbelleklenir, ıskada otomatik
  yeniden inşa edilir (istek başına bir kez).
- sabre/uri ve sabre/xml'in `functions.php`'sini yükler ("files" autoload).

## 3. Çekirdek hook'ları

Eklentiler şunlara kaydolur: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Admin panelinde: `admin.mounts` (collect) bootstrap'in `adminMounts`'unu
besler - `{js, plugin, section, tab?, label?}` tanımlayıcısı `pluginMount`
binding'iyle monte edilir (`admin: true` seçeneği > `ctx.admin`, modül
çağrıları `Plugin::apiAdmin<Action>`'a `/admin/plugin/…` öneklenir). `tab`
yoksa: `section` bölgesinde satır içi blok (örn. `security-account`); `tab` +
`label` ile: `section` sayfasının kendi alt-sekmesi (örn. `security` >
`#/security/<tab>`), yalnızca eklenti aktifse işlenir. Özel `'page'`
bölümü (+ opsiyonel `icon`, Font Awesome sınıfı) yan menüde **üst düzey
sayfa** oluşturur (`#/<tab>`). `admin.post-login` yerel admin hesabı
girişinde yayınlanır; orada `TotpRequiredException` fırlatmak erişimi
engeller (örn. admin TOTP).

Eklenti oluşturmak için bkz. [plugins.md](plugins.md).

## 3bis. Eklenti dağıtımı (GitHubClient + PluginPackageService)

- `GitHubClient`, `UpdateService` (`coreAsset` aracılığıyla `mxwebmail-*.zip`
  çekirdek paketi) ve `PluginPackageService` (`update.plugins_ref` ref'inde
  repo okuması: `resolveCommit()` > `GET /commits/<ref>` sha + tree sha verir,
  `tree()` > `GET /git/trees/<sha>?recursive=1` `app/plugins/**` bloblarını
  eşler, `rawUrl()` > commit sha'sına sabitlenmiş `raw.githubusercontent.com`)
  için GitHub erişimini ortaklar. Repo `REPO` sabitiyle kilitli, ortak
  release önbelleği `storage/cache/update-check.json`. Her kesilmiş asset bir
  `download_url` taşır, `pickUrl()` ile çözümlenir: `config.php`'de PAT
  ayarlıysa `releases/assets/<id>` API uç noktası
  (`Accept: application/octet-stream`) - `browser_download_url`'in 404
  döndürdüğü özel repoda zorunlu - aksi halde `browser_download_url`.
  Eklenti önbellekleri: `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` ve `plugin-manifest-<name>-<sha>.json`
  (değişmez).
- `PluginPackageService`: `catalog()`, `install()` (her dosyayı raw ile
  sahneye koyar > boyut + `blobId()` sha1 git doğrulaması > `plugin.json`
  doğrulaması + `min_app`/`max_app` > eklenti başına tekil yedekle takas >
  classmap/opcache geçersizleştirme), `remove()` (rmrf +
  `PluginRepository::delete()` ile `plugins` satırının temizlenmesi).
  `UpdateService` ile ortak `storage/update/update.lock` kilidi.
  `storage/update/plugins/` içinde sahneleme. Alternatif kaynak
  `update.plugins_local` (disk, dev/testler, uzak doğrulama yok).
- Release zip paketlemesi `app/plugins/*`'ı çekirdek zipinden hariç tutar -
  hiçbir eklenti asseti yayınlanmaz: `plugin.json`'da `version` bump'ı +
  izlenen dala push = yayınlama. Zip ayrıca repo meta dosyalarını da hariç
  tutar (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` dağıtılmaya devam
  eder.

## 4. Önbellek & sıkıştırma

Her şey yapılandırılabilir (admin > *Performans*, `http.*` / `cache.*`
bölümleri `config.php`'de):

- **Sıkıştırma**: `App::sendResponse()`, `Response::gzip()`'i tek gönderim
  noktasında çağırır - `php -S`, Apache ve Nginx'i kapsar. Zaten kodlanmış
  veya sıkıştırılamaz yanıtlar atlanır; tip sıkıştırılabilir olduğu anda
  `Vary: Accept-Encoding` konur. `zlib.output_compression` aktif > no-op.
- **Statik assetler** (`public/assets/img|js|vendor`): sürümlenmiş URL
  `/assets/v<APP_VERSION>/…` altında web sunucusu tarafından sunulur
  (`public/.htaccess` rewrite, installation.md'de belgelenen `location`
  nginx bloğu). Apache altında 1 yıllık immutable TTL; dev-server
  `no-cache` sunar.
- **PHP assetleri** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`,
  favicon): `PageController::serveAsset()` `Cache-Control`'ü
  `http.assets_ttl` / `http.favicon_ttl`'den ayarlar (`?v=` varsa +
  `immutable`) ve `http.etag` ile `ETag`/`Last-Modified` > `If-None-Match`'te
  `304`. `?v=`'ler `theme.json`/`plugin.json`'dan gelir
  (`ThemeManager::version`, `PluginBase::version`) - hedefli bileşen
  bump'ı dolayısıyla kendi önbelleğini bozar.
- **Sunucu önbelleği**: `ActionsBridge::Cacher()`, `cache.enabled=false` iken
  sürücüsüz istemci döndürür (Get/Set no-op). Periyodik GC (`cache.gc_hours`,
  işaretçi `storage/cache/_gc.ts`) - `classmap.php` dahil klasörün tüm
  dosyalarını temizler (sonraki istekte yeniden inşa). Kendi TTL'leri:
  `cache.domain_detect_ttl` (algılama), `cache.update_ttl` (release).
- **HTML**: `no-cache` - kabuk her zaman mevcut sürümün assetlerine başvurur.
  API/özel yanıtlar asla public TTL almaz.

## 5. Proje kuralları

- **Bootstrap**: eleman başına bir JS bileşeni (`Data`) - asla
  `data-bs-toggle` elemanında `tooltip:` (ve bileşen yaratan başka binding);
  alt ikona koyun.
- **Sürümleme** semver `0.MINOR.PATCH`: `PATCH` = düzeltme/refactor,
  `MINOR` = özellik veya kırıcı değişiklik. Hedefli bump: `APP_VERSION`
  (çekirdek) veya `plugin.json`/`theme.json` (değişen bileşen). Her
  commit'te global bump yok.
