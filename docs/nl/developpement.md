# Ontwikkeling

*Vertaald uit het Frans - de Franse versie is leidend.*

Documentatie om de architectuur te begrijpen en het project uit te breiden
(kern, plugins, thema's).

## 1. Architectuur

```
public/                front controller (index.php) + assets
app/src/MXWebMail/     applicatiecode:
  Application/App.php  servicecontainer + dispatch + routes
  API.php, Utils.php   compatibiliteitsfacades voor gevendoreerde code (MailSo/Lib)
  Compat/              MailSo-compatibiliteitsbruggen (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              laden/schrijven van de configuratie
  Controller/          PageController, ApiController, AdminController,
                       SetupController (/setup-wizard, geserveerd zolang
                       storage/INSTALLED afwezig is - daarna 404) ;
    Api/               API-controllers per domein (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             admin-controllers per tab (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + woordenboeken
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          datatoegang (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (gedeelde Releases-HTTP), UpdateService (kern),
                       PluginPackageService (plugin-catalogus/install/remove),
                       contact-imports (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - generatie van filterregels
  Theme/               ThemeManager
app/src/MailSo/        gevendoreerde mailbibliotheek (ongewijzigd)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           MXWebMailLib-hulpprogramma's (classmap)
app/src/bootstrap.php  zelfgemaakte autoloader
```

- **Frontend**: Knockout + Bootstrap 5.3, ES-modules in `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Geen Composer**: zelfgemaakte autoloader (zie §2).

## 2. Autoloader (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Hoofdletterongevoelige classmap**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (legacy-code, bestandsnamen in kleine
  letters, multi-klasse). Gegenereerd via `token_get_all`-scan, gecached in
  `app/storage/cache/classmap.php`, automatisch herbouwd bij miss (eenmaal
  per request).
- Laadt de `functions.php` van sabre/uri en sabre/xml ("files"-autoload).

## 3. Kern-hooks

Plugins registreren zich op: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Op het admin-paneel: `admin.mounts` (collect) voedt `adminMounts` van de
bootstrap - `{js, plugin, section, tab?, label?}`-descriptor gemount via de
`pluginMount`-binding (opt `admin: true` > `ctx.admin`, de aanroepen van de
module worden geprefixed `/admin/plugin/…` richting
`Plugin::apiAdmin<Action>`). Zonder `tab`: inline-blok in de `section`-zone
(bijv. `security-account`); met `tab` + `label`: eigen sub-tab van de
`section`-pagina (bijv. `security` > `#/security/<tab>`), alleen gerenderd
als de plugin geactiveerd is. De speciale sectie `'page'` (+ optioneel
`icon`, Font Awesome-klasse) maakt een **toplevel-pagina** in het zijmenu
(`#/<tab>`). `admin.post-login` wordt uitgegeven bij de login van het lokale
admin-account; een `TotpRequiredException` daar blokkeert de toegang
(bijv. admin-TOTP).

Zie [plugins.md](plugins.md) om een plugin te bouwen.

## 3bis. Plugin-distributie (GitHubClient + PluginPackageService)

- `GitHubClient` bundelt de GitHub-toegang voor `UpdateService` (kernpakket
  `mxwebmail-*.zip` via `coreAsset`) en `PluginPackageService` (lezen van de
  repo op ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` geeft sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mapt de `app/plugins/**`-blobs,
  `rawUrl()` > `raw.githubusercontent.com` gepind op de commit-sha).
  Repository vergrendeld door de constante `REPO`, gedeelde release-cache
  `storage/cache/update-check.json`. Elk afgekapt asset draagt
  `download_url`, opgelost door `pickUrl()`: API-endpoint
  `releases/assets/<id>` (`Accept: application/octet-stream`) als een PAT is
  geconfigureerd in `config.php` - verplicht op een private repo waar
  `browser_download_url` 404 teruggeeft - anders `browser_download_url`.
  Plugin-caches: `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` en `plugin-manifest-<name>-<sha>.json`
  (onveranderlijk).
- `PluginPackageService`: `catalog()`, `install()` (staget elk bestand via
  raw > grootte- + `blobId()`-git-sha1-controle > `plugin.json`-validatie +
  `min_app`/`max_app` > swap met unieke backup per plugin >
  classmap/opcache-invalidatie), `remove()` (rmrf + purge van de
  `plugins`-rij via `PluginRepository::delete()`). Gedeeld slot
  `storage/update/update.lock` met `UpdateService`. Staging in
  `storage/update/plugins/`. Alternatieve bron `update.plugins_local`
  (schijf, dev/tests, geen verificatie op afstand).
- De packaging van de release-zip sluit `app/plugins/*` uit van de kern-zip -
  geen plugin-asset wordt gepubliceerd: `version` in `plugin.json` bumpen +
  push naar de gevolgde branch = publicatie. De zip sluit ook de
  repo-meta-bestanden uit (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`,
  `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` blijft
  gedistribueerd.

## 4. Cache & compressie

Alles is configureerbaar (admin > *Performance*, secties `http.*` / `cache.*`
van `config.php`):

- **Compressie**: `App::sendResponse()` roept `Response::gzip()` aan op het
  enige zendpunt - dekt `php -S`, Apache en Nginx. Al gecodeerde antwoorden
  of niet-comprimeerbare types worden overgeslagen; `Vary: Accept-Encoding`
  wordt gezet zodra het type comprimeerbaar is. `zlib.output_compression`
  actief > no-op.
- **Statische assets** (`public/assets/img|js|vendor`): geserveerd door de
  webserver onder de geversioeerde URL `/assets/v<APP_VERSION>/…`
  (`public/.htaccess`-rewrite, nginx `location`-blok gedocumenteerd in
  installation.md). TTL van 1 jaar immutable onder Apache; de dev-server
  serveert `no-cache`.
- **PHP-assets** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon):
  `PageController::serveAsset()` zet `Cache-Control` uit `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` bij `?v=`) en, bij `http.etag`,
  `ETag`/`Last-Modified` > `304` op `If-None-Match`. De `?v=` komen van
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  een gerichte componenten-bump bust dus zijn eigen cache.
- **Server-cache**: `ActionsBridge::Cacher()` levert een client zonder driver
  als `cache.enabled=false` (Get/Set no-op). Periodieke GC (`cache.gc_hours`,
  marker `storage/cache/_gc.ts`) - zuivert alle bestanden van de map,
  inclusief `classmap.php` (herbouwd bij de volgende request). Eigen TTL's:
  `cache.domain_detect_ttl` (detectie), `cache.update_ttl` (releases).
- **HTML**: `no-cache` - de shell refereert altijd de assets van de huidige
  versie. API-/prive-antwoorden krijgen nooit een publieke TTL.

## 5. Projectconventies

- **Bootstrap**: een enkele JS-component per element (`Data`) - nooit
  `tooltip:` (of een andere binding) op een `data-bs-toggle`-element; op het
  kind-icoon zetten.
- **Versionering** semver `0.MINOR.PATCH`: `PATCH` = fix/refactor, `MINOR` =
  feature of breaking change. Gerichte bump: `APP_VERSION` (kern) of
  `plugin.json`/`theme.json` (gewijzigde component). Geen globale bump bij
  elke commit.
