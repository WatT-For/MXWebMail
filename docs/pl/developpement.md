# Rozwój

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Dokumentacja do zrozumienia architektury i rozszerzania projektu (rdzeń,
wtyczki, motywy).

## 1. Architektura

```
public/                front controller (index.php) + assety
app/src/MXWebMail/     kod aplikacji:
  Application/App.php  kontener usług + dispatch + trasy
  API.php, Utils.php   fasady kompatybilności dla kodu vendored (MailSo/Lib)
  Compat/              mosty kompatybilności MailSo (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              ładowanie/zapis konfiguracji
  Controller/          PageController, ApiController, AdminController,
                       SetupController (kreator /setup, serwowany póki
                       storage/INSTALLED nie istnieje - potem 404) ;
    Api/               kontrolery API per domena (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             kontrolery admina per zakładka (System, Domains,
                       Users, Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + słowniki
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          dostęp do danych (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (współdzielone HTTP Releases), UpdateService (rdzeń),
                       PluginPackageService (katalog/install/remove wtyczek),
                       importy kontaktów (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - generacja reguł filtrów
  Theme/               ThemeManager
app/src/MailSo/        vendored biblioteka poczty (niemodyfikowana)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           narzędzia MXWebMailLib (classmap)
app/src/bootstrap.php  własny autoloader
```

- **Frontend**: Knockout + Bootstrap 5.3, moduły ES w `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Bez Composera**: własny autoloader (zob. §2).

## 2. Autoloader (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Classmap nieczuły na wielkość liter**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (kod legacy, pliki małymi literami,
  multi-klasa). Generowany przez skan `token_get_all`, cache'owany w
  `app/storage/cache/classmap.php`, przebudowywany automatycznie przy
  miss (raz na żądanie).
- Ładuje `functions.php` sabre/uri i sabre/xml (autoload "files").

## 3. Hooki rdzenia

Wtyczki rejestrują się na: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Na panelu admina: `admin.mounts` (collect) zasila `adminMounts` bootstrapa -
deskryptor `{js, plugin, section, tab?, label?}` montowany przez binding
`pluginMount` (opcja `admin: true` > `ctx.admin`, wywołania modułu są
prefiksowane `/admin/plugin/…` w stronę `Plugin::apiAdmin<Action>`). Bez
`tab`: blok inline w strefie `section` (np. `security-account`); z `tab` +
`label`: własna pod-zakładka strony `section` (np. `security` >
`#/security/<tab>`), renderowana tylko gdy wtyczka jest aktywna. Specjalna
sekcja `'page'` (+ opcjonalny `icon`, klasa Font Awesome) tworzy **stronę
najwyższego poziomu** w menu bocznym (`#/<tab>`). `admin.post-login` jest
emitowany przy logowaniu lokalnego konta admina; rzucenie tam
`TotpRequiredException` blokuje dostęp (np. TOTP admina).

Zob. [plugins.md](plugins.md) by stworzyć wtyczkę.

## 3bis. Dystrybucja wtyczek (GitHubClient + PluginPackageService)

- `GitHubClient` wzajemnia dostęp GitHub dla `UpdateService` (pakiet rdzenia
  `mxwebmail-*.zip` przez `coreAsset`) i `PluginPackageService` (czytanie
  repo na ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` daje sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mapuje bloby `app/plugins/**`,
  `rawUrl()` > `raw.githubusercontent.com` przypięte na sha commita).
  Repozytorium zaryglowane przez stałą `REPO`, współdzielony cache release
  `storage/cache/update-check.json`. Każdy obcięty asset niesie
  `download_url`, rozwiązywany przez `pickUrl()`: endpoint API
  `releases/assets/<id>` (`Accept: application/octet-stream`) jeśli PAT jest
  skonfigurowany w `config.php` - obowiązkowy na prywatnym repo, gdzie
  `browser_download_url` zwraca 404 - inaczej `browser_download_url`.
  Cache wtyczek: `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` i `plugin-manifest-<name>-<sha>.json`
  (niezmienne).
- `PluginPackageService`: `catalog()`, `install()` (stageuje każdy plik przez
  raw > weryf. rozmiar + `blobId()` sha1 git > walidacja `plugin.json` +
  `min_app`/`max_app` > swap z unikalnym backupem per wtyczka >
  unieważnienie classmap/opcache), `remove()` (rmrf + purge wiersza
  `plugins` przez `PluginRepository::delete()`). Współdzielony lock
  `storage/update/update.lock` z `UpdateService`. Staging w
  `storage/update/plugins/`. Alternatywne źródło `update.plugins_local`
  (dysk, dev/testy, brak weryfikacji zdalnej).
- Pakowanie zipa release wyklucza `app/plugins/*` z zipa rdzenia - żaden
  asset wtyczki nie jest publikowany: bump `version` w `plugin.json` + push
  na śledzoną gałąź = publikacja. Zip wyklucza też pliki meta repo
  (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` pozostaje
  dystrybuowana.

## 4. Cache & kompresja

Wszystko jest konfigurowalne (admin > *Wydajność*, sekcje `http.*` /
`cache.*` z `config.php`):

- **Kompresja**: `App::sendResponse()` wywołuje `Response::gzip()` w
  jedynym punkcie wysyłki - pokrywa `php -S`, Apache i Nginx. Już
  zakodowane lub niekompresowalne odpowiedzi są pomijane;
  `Vary: Accept-Encoding` jest ustawiane gdy tylko typ jest
  kompresowalny. `zlib.output_compression` aktywne > no-op.
- **Statyczne assety** (`public/assets/img|js|vendor`): serwowane przez
  serwer web pod wersjonowanym URL `/assets/v<APP_VERSION>/…` (rewrite
  `public/.htaccess`, blok `location` nginx udokumentowany w
  installation.md). TTL 1 rok immutable pod Apache; dev-server serwuje
  `no-cache`.
- **Assety PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`,
  favicon): `PageController::serveAsset()` ustawia `Cache-Control` z
  `http.assets_ttl` / `http.favicon_ttl` (+ `immutable` jeśli `?v=`
  obecne) i, gdy `http.etag`, `ETag`/`Last-Modified` > `304` na
  `If-None-Match`. `?v=` pochodzą z `theme.json`/`plugin.json`
  (`ThemeManager::version`, `PluginBase::version`) - ukierunkowany bump
  komponentu bustuje więc własny cache.
- **Cache serwera**: `ActionsBridge::Cacher()` zwraca klienta bez
  sterownika gdy `cache.enabled=false` (Get/Set no-op). Okresowy GC
  (`cache.gc_hours`, marker `storage/cache/_gc.ts`) - czyści wszystkie
  pliki folderu, w tym `classmap.php` (przebudowany przy kolejnym
  żądaniu). Własne TTL: `cache.domain_detect_ttl` (detekcja),
  `cache.update_ttl` (release).
- **HTML**: `no-cache` - shell zawsze referencjuje assety bieżącej
  wersji. Odpowiedzi API/prywatne nigdy nie dostają publicznego TTL.

## 5. Konwencje projektu

- **Bootstrap**: jeden komponent JS per element (`Data`) - nigdy
  `tooltip:` (ani innego bindingu) na elemencie `data-bs-toggle`; umieść
  go na dziecięcej ikonie.
- **Wersjonowanie** semver `0.MINOR.PATCH`: `PATCH` = poprawka/refactor,
  `MINOR` = funkcja lub breaking change. Ukierunkowany bump: `APP_VERSION`
  (rdzeń) lub `plugin.json`/`theme.json` (zmieniony komponent). Bez
  globalnego bumpa przy każdym commicie.
