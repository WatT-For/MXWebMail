# Sviluppo

*Tradotto dal francese - la versione francese fa fede.*

Documentazione per comprendere l'architettura ed estendere il progetto (core,
plugin, temi).

## 1. Architettura

```
public/                front controller (index.php) + asset
app/src/MXWebMail/     codice applicativo:
  Application/App.php  container di servizi + dispatch + rotte
  API.php, Utils.php   facade di compatibilità per il codice vendored (MailSo/Lib)
  Compat/              ponti di compatibilità MailSo (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              caricamento/scrittura della configurazione
  Controller/          PageController, ApiController, AdminController,
                       SetupController (procedura /setup, servita finché
                       storage/INSTALLED è assente - poi 404) ;
    Api/               controller API per dominio (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             controller admin per scheda (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + dizionari
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          accesso ai dati (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (HTTP Releases condiviso), UpdateService (core),
                       PluginPackageService (catalogo/install/remove plugin),
                       import contatti (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - generazione delle regole di filtri
  Theme/               ThemeManager
app/src/MailSo/        libreria mail vendored (non modificata)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           utility MXWebMailLib (classmap)
app/src/bootstrap.php  autoloader interno
```

- **Frontend**: Knockout + Bootstrap 5.3, moduli ES in `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Niente Composer**: autoloader interno (vedi §2).

## 2. Autoloader (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Classmap case-insensitive**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (codice legacy, file in minuscolo,
  multi-classe). Generata via scansione `token_get_all`, cacheata in
  `app/storage/cache/classmap.php`, ricostruita automaticamente su miss (una
  volta per richiesta).
- Carica i `functions.php` di sabre/uri e sabre/xml (autoload "files").

## 3. Hook del core

I plugin si registrano su: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Sul pannello admin: `admin.mounts` (collect) alimenta `adminMounts` del
bootstrap - descrittore `{js, plugin, section, tab?, label?}` montato via il
binding `pluginMount` (opt `admin: true` > `ctx.admin`, le chiamate del modulo
sono prefissate `/admin/plugin/…` verso `Plugin::apiAdmin<Action>`). Senza
`tab`: blocco inline nella zona `section` (es. `security-account`); con `tab`
+ `label`: sotto-scheda dedicata della pagina `section` (es. `security` >
`#/security/<tab>`), resa solo se il plugin è attivo. La sezione speciale
`'page'` (+ `icon` opzionale, classe Font Awesome) crea una **pagina di
livello superiore** nel menu laterale (`#/<tab>`). `admin.post-login` è
emesso al login dell'account admin locale; lanciare `TotpRequiredException`
lì blocca l'accesso (es. TOTP admin).

Vedi [plugins.md](plugins.md) per creare un plugin.

## 3bis. Distribuzione dei plugin (GitHubClient + PluginPackageService)

- `GitHubClient` mutualizza l'accesso GitHub per `UpdateService` (pacchetto
  core `mxwebmail-*.zip` via `coreAsset`) e `PluginPackageService` (lettura
  del repo al ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` dà sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mappa i blob `app/plugins/**`,
  `rawUrl()` > `raw.githubusercontent.com` pinnato sullo sha del commit).
  Repository bloccato dalla costante `REPO`, cache release condivisa
  `storage/cache/update-check.json`. Ogni asset troncato porta
  `download_url`, risolto da `pickUrl()`: endpoint API `releases/assets/<id>`
  (`Accept: application/octet-stream`) se un PAT è configurato in
  `config.php` - obbligatorio su un fork privato dove `browser_download_url`
  restituisce 404, opzionale altrimenti (quota API) - altrimenti
  `browser_download_url` (il repo ufficiale è pubblico). Cache plugin:
  `plugins-ref.json` (TTL `update_ttl`), `plugins-tree-<sha>.json` e
  `plugin-manifest-<name>-<sha>.json` (immutabili).
- `PluginPackageService`: `catalog()`, `install()` (stagia ogni file via raw >
  verifica dimensione + `blobId()` sha1 git > validazione `plugin.json` +
  `min_app`/`max_app` > swap con backup unico per plugin > invalidazione
  classmap/opcache), `remove()` (rmrf + purge della riga `plugins` via
  `PluginRepository::delete()`). Lock condiviso `storage/update/update.lock`
  con `UpdateService`. Staging in `storage/update/plugins/`. Fonte
  alternativa `update.plugins_local` (disco, dev/test, nessuna verifica
  remota).
- Il packaging dello zip di release esclude `app/plugins/*` dallo zip core -
  nessun asset plugin è pubblicato: bump `version` in `plugin.json` + push
  sul branch seguito = pubblicazione. Lo zip esclude anche i file meta del
  repo (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` resta distribuito.

## 4. Cache & compressione

Tutto è configurabile (admin > *Prestazioni*, sezioni `http.*` / `cache.*` di
`config.php`):

- **Compressione**: `App::sendResponse()` chiama `Response::gzip()` nel punto
  di invio unico - copre `php -S`, Apache e Nginx. Le risposte già codificate
  o di tipo non comprimibile sono ignorate; `Vary: Accept-Encoding` è posto
  appena il tipo è comprimibile. `zlib.output_compression` attivo > no-op.
- **Asset statici** (`public/assets/img|js|vendor`): serviti dal web server
  sotto l'URL versionata `/assets/v<APP_VERSION>/…` (rewrite
  `public/.htaccess`, blocco `location` nginx documentato in installation.md).
  TTL 1 anno immutable sotto Apache; il dev server serve in `no-cache`.
- **Asset PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon):
  `PageController::serveAsset()` pone `Cache-Control` da `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` se `?v=` presente) e, quando `http.etag`,
  `ETag`/`Last-Modified` > `304` su `If-None-Match`. I `?v=` provengono da
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  il bump mirato di un componente invalida quindi la propria cache.
- **Cache server**: `ActionsBridge::Cacher()` restituisce un client senza
  driver quando `cache.enabled=false` (Get/Set no-op). GC periodico
  (`cache.gc_hours`, marker `storage/cache/_gc.ts`) - purga tutti i file
  della cartella, compreso `classmap.php` (ricostruito alla richiesta
  successiva). TTL dedicati: `cache.domain_detect_ttl` (rilevamento),
  `cache.update_ttl` (release).
- **HTML**: `no-cache` - la shell referenzia sempre gli asset della versione
  corrente. Le risposte API/private non ricevono mai TTL pubblico.

## 5. Convenzioni del progetto

- **Bootstrap**: un solo componente JS per elemento (`Data`) - mai
  `tooltip:` (o altro binding) su un elemento `data-bs-toggle`; metterlo
  sull'icona figlia.
- **Versioning** semver `0.MINOR.PATCH`: `PATCH` = fix/refactor, `MINOR` =
  feature o breaking change. Bump mirato: `APP_VERSION` (core) o
  `plugin.json`/`theme.json` (componente modificato). Nessun bump globale a
  ogni commit.
