# Entwicklung

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Dokumentation zum Verständnis der Architektur und zur Erweiterung des Projekts
(Kern, Plugins, Themes).

## 1. Architektur

```
public/                Front Controller (index.php) + Assets
app/src/MXWebMail/     Anwendungscode:
  Application/App.php  Service-Container + Dispatch + Routen
  API.php, Utils.php   Kompatibilitätsfassaden für vendorten Code (MailSo/Lib)
  Compat/              MailSo-Kompatibilitätsbrücken (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              Laden/Schreiben der Konfiguration
  Controller/          PageController, ApiController, AdminController,
                       SetupController (/setup-Assistent, ausgeliefert solange
                       storage/INSTALLED fehlt - danach 404) ;
    Api/               API-Controller nach Domäne (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             Admin-Controller nach Tab (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + Wörterbücher
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          Datenzugriff (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (geteilter Releases-HTTP), UpdateService (Kern),
                       PluginPackageService (Plugin-Katalog/Install/Remove),
                       Kontakt-Imports (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - Erzeugung der Filterregeln
  Theme/               ThemeManager
app/src/MailSo/        vendorte Mail-Bibliothek (unverändert)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           MXWebMailLib-Hilfsprogramme (Classmap)
app/src/bootstrap.php  hausgemachter Autoloader
```

- **Frontend**: Knockout + Bootstrap 5.3, ES-Module in `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Kein Composer**: hausgemachter Autoloader (siehe §2).

## 2. Autoloader (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Groß-/Kleinschreibung-unempfindliche Classmap**: `MailSo\` >
  `app/src/MailSo`, `MXWebMailLib\` > `app/src/Lib` (Altcode, Dateinamen in
  Kleinbuchstaben, Multi-Klasse). Erzeugt per `token_get_all`-Scan, gecacht in
  `app/storage/cache/classmap.php`, bei Miss automatisch neu erzeugt (einmal
  pro Request).
- Lädt die `functions.php` von sabre/uri und sabre/xml
  ("files"-Autoload).

## 3. Kern-Hooks

Plugins registrieren sich auf: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Auf dem Admin-Panel: `admin.mounts` (collect) speist `adminMounts` des
Bootstraps - `{js, plugin, section, tab?, label?}`-Deskriptor, gemountet über
das `pluginMount`-Binding (opt `admin: true` > `ctx.admin`, die Aufrufe des
Moduls werden mit `/admin/plugin/…` Richtung `Plugin::apiAdmin<Action>`
präfixiert). Ohne `tab`: Inline-Block in der `section`-Zone
(z. B. `security-account`); mit `tab` + `label`: eigener Sub-Tab der
`section`-Seite (z. B. `security` > `#/security/<tab>`), nur gerendert wenn
das Plugin aktiviert ist. Die spezielle Sektion `'page'` (+ optionales `icon`,
Font-Awesome-Klasse) erzeugt eine **Seite auf oberster Ebene** im
Seitenmenü (`#/<tab>`). `admin.post-login` wird beim Login des lokalen
Admin-Kontos emittiert; ein `TotpRequiredException` dort sperrt den Zugang
(z. B. Admin-TOTP).

Siehe [plugins.md](plugins.md), um ein Plugin zu erstellen.

## 3bis. Plugin-Verteilung (GitHubClient + PluginPackageService)

- `GitHubClient` bündelt den GitHub-Zugriff für `UpdateService` (Kernpaket
  `mxwebmail-*.zip` via `coreAsset`) und `PluginPackageService` (Lesen des
  Repos am Ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` liefert sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mappt die `app/plugins/**`-Blobs,
  `rawUrl()` > `raw.githubusercontent.com`, auf den Commit-sha gepinnt).
  Repository durch die Konstante `REPO` verriegelt, geteilter Release-Cache
  `storage/cache/update-check.json`. Jedes abgeschnittene Asset trägt
  `download_url`, aufgelöst von `pickUrl()`: API-Endpunkt
  `releases/assets/<id>` (`Accept: application/octet-stream`), wenn ein PAT in
  `config.php` konfiguriert ist - obligatorisch auf einem privaten Fork, wo
  `browser_download_url` 404 liefert, sonst optional (API-Quote) - sonst
  `browser_download_url` (das offizielle Repo ist öffentlich).
  Plugin-Caches: `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` und `plugin-manifest-<name>-<sha>.json`
  (unveränderlich).
- `PluginPackageService`: `catalog()`, `install()` (stagt jede Datei via raw >
  Größen- + `blobId()`-git-sha1-Prüfung > `plugin.json`-Validierung +
  `min_app`/`max_app` > Swap mit einzigem Backup pro Plugin >
  Classmap/opcache-Invalidierung), `remove()` (rmrf + Purge der `plugins`-Zeile
  via `PluginRepository::delete()`). Geteiltes Lock
  `storage/update/update.lock` mit `UpdateService`. Staging in
  `storage/update/plugins/`. Alternative Quelle `update.plugins_local`
  (Festplatte, Dev/Tests, keine Fernprüfung).
- Das Packaging des Release-Zips schließt `app/plugins/*` aus dem Kern-Zip aus -
  kein Plugin-Asset wird veröffentlicht: `version` in `plugin.json` bumpen +
  Push auf den verfolgten Branch = Veröffentlichung. Der Zip schließt auch die
  Repo-Meta-Dateien aus (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`,
  `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` bleibt
  verteilt.

## 4. Cache & Kompression

Alles konfigurierbar (Admin > *Performance*, Abschnitte `http.*` / `cache.*`
von `config.php`):

- **Kompression**: `App::sendResponse()` ruft `Response::gzip()` am einzigen
  Sendepunkt auf - deckt `php -S`, Apache und Nginx ab. Bereits kodierte
  Antworten oder nicht komprimierbare Typen werden ignoriert;
  `Vary: Accept-Encoding` wird gesetzt, sobald der Typ komprimierbar ist.
  `zlib.output_compression` aktiv > No-op.
- **Statische Assets** (`public/assets/img|js|vendor`): vom Webserver unter
  der versionierten URL `/assets/v<APP_VERSION>/…` ausgeliefert
  (`public/.htaccess`-Rewrite, nginx-`location`-Block in installation.md
  dokumentiert). 1 Jahr immutable TTL unter Apache; der Dev-Server liefert
  `no-cache`.
- **PHP-Assets** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, Favicon):
  `PageController::serveAsset()` setzt `Cache-Control` aus `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` bei `?v=`) und, wenn `http.etag`,
  `ETag`/`Last-Modified` > `304` auf `If-None-Match`. Die `?v=` stammen aus
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  ein gezielter Komponenten-Bump bustet also seinen eigenen Cache.
- **Server-Cache**: `ActionsBridge::Cacher()` liefert einen Client ohne Treiber,
  wenn `cache.enabled=false` (Get/Set No-op). Periodisches GC
  (`cache.gc_hours`, Marker `storage/cache/_gc.ts`) - löscht alle Dateien des
  Ordners, einschließlich `classmap.php` (beim nächsten Request neu erzeugt).
  Eigene TTLs: `cache.domain_detect_ttl` (Detektion), `cache.update_ttl`
  (Releases).
- **HTML**: `no-cache` - die Shell referenziert immer die Assets der aktuellen
  Version. API-/private Antworten erhalten nie ein öffentliches TTL.

## 5. Projektkonventionen

- **Bootstrap**: eine einzige JS-Komponente pro Element (`Data`) - nie
  `tooltip:` (oder ein anderes Binding) auf einem `data-bs-toggle`-Element; auf
  das Kind-Icon legen.
- **Versionierung** semver `0.MINOR.PATCH`: `PATCH` = Fix/Refactor, `MINOR` =
  Feature oder Breaking Change. Gezielter Bump: `APP_VERSION` (Kern) oder
  `plugin.json`/`theme.json` (geänderte Komponente). Kein globaler Bump bei
  jedem Commit.
