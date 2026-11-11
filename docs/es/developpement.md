# Desarrollo

*Traducido del francés - la versión francesa es la que hace fe.*

Documentación para comprender la arquitectura y ampliar el proyecto (núcleo,
plugins, temas).

## 1. Arquitectura

```
public/                front controller (index.php) + assets
app/src/MXWebMail/     código de la aplicación:
  Application/App.php  contenedor de servicios + dispatch + rutas
  API.php, Utils.php   fachadas de compatibilidad para el código vendorado (MailSo/Lib)
  Compat/              puentes de compatibilidad MailSo (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              carga/escritura de la configuración
  Controller/          PageController, ApiController, AdminController,
                       SetupController (asistente /setup, servido mientras
                       storage/INSTALLED esté ausente - 404 después) ;
    Api/               controladores API por dominio (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             controladores admin por pestaña (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + diccionarios
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          acceso a datos (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (HTTP Releases compartido), UpdateService (núcleo),
                       PluginPackageService (catálogo/install/remove de plugins),
                       importaciones de contactos (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - generación de reglas de filtros
  Theme/               ThemeManager
app/src/MailSo/        biblioteca de correo vendorada (sin modificar)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           utilidades MXWebMailLib (classmap)
app/src/bootstrap.php  autocargador propio
```

- **Frontend**: Knockout + Bootstrap 5.3, módulos ES en `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Sin Composer**: autocargador propio (ver §2).

## 2. Autocargador (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Classmap insensible a mayúsculas**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (código heredado, archivos en minúsculas,
  multi-clase). Generada por escaneo `token_get_all`, cacheada en
  `app/storage/cache/classmap.php`, reconstruida automáticamente en fallo
  (una vez por petición).
- Carga los `functions.php` de sabre/uri y sabre/xml (autoload "files").

## 3. Hooks del núcleo

Los plugins se registran en: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

En el panel admin: `admin.mounts` (collect) alimenta `adminMounts` del
bootstrap - descriptor `{js, plugin, section, tab?, label?}` montado vía el
binding `pluginMount` (opt `admin: true` > `ctx.admin`, las llamadas del
módulo se prefijan `/admin/plugin/…` hacia `Plugin::apiAdmin<Action>`). Sin
`tab`: bloque inline en la zona `section` (ej. `security-account`); con `tab`
+ `label`: sub-pestaña dedicada de la página `section` (ej. `security` >
`#/security/<tab>`), renderizada solo si el plugin está activado. La sección
especial `'page'` (+ `icon` opcional, clase Font Awesome) crea una **página
de nivel superior** en el menú lateral (`#/<tab>`). `admin.post-login` se
emite en el login de la cuenta admin local; lanzar `TotpRequiredException`
allí bloquea el acceso (ej. TOTP admin).

Ver [plugins.md](plugins.md) para crear un plugin.

## 3bis. Distribución de plugins (GitHubClient + PluginPackageService)

- `GitHubClient` mutualiza el acceso GitHub para `UpdateService` (paquete
  núcleo `mxwebmail-*.zip` vía `coreAsset`) y `PluginPackageService` (lectura
  del repo en el ref `update.plugins_ref`: `resolveCommit()` >
  `GET /commits/<ref>` da sha + tree sha, `tree()` >
  `GET /git/trees/<sha>?recursive=1` mapea los blobs `app/plugins/**`,
  `rawUrl()` > `raw.githubusercontent.com` fijado al sha del commit).
  Repositorio bloqueado por la constante `REPO`, caché de release compartida
  `storage/cache/update-check.json`. Cada asset truncado lleva
  `download_url`, resuelto por `pickUrl()`: endpoint API
  `releases/assets/<id>` (`Accept: application/octet-stream`) si un PAT está
  configurado en `config.php` - obligatorio en un fork privado donde
  `browser_download_url` devuelve 404, opcional si no (cuota API) - si no,
  `browser_download_url` (el repo oficial es público).
  Cachés de plugins: `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` y `plugin-manifest-<name>-<sha>.json`
  (inmutables).
- `PluginPackageService`: `catalog()`, `install()` (staguea cada archivo vía
  raw > verif. tamaño + `blobId()` sha1 git > validación `plugin.json` +
  `min_app`/`max_app` > swap con backup único por plugin > invalidación
  classmap/opcache), `remove()` (rmrf + purga de la fila `plugins` vía
  `PluginRepository::delete()`). Lock compartido `storage/update/update.lock`
  con `UpdateService`. Staging en `storage/update/plugins/`. Fuente
  alternativa `update.plugins_local` (disco, dev/tests, sin verificación
  remota).
- El empaquetado del zip de release excluye `app/plugins/*` del zip núcleo -
  ningún asset de plugin se publica: bump `version` en `plugin.json` + push
  sobre la rama seguida = publicación. El zip excluye también los archivos
  meta del repo (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`,
  `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` sigue
  distribuyéndose.

## 4. Caché y compresión

Todo es configurable (admin > *Rendimiento*, secciones `http.*` / `cache.*` de
`config.php`):

- **Compresión**: `App::sendResponse()` llama `Response::gzip()` en el punto
  de envío único - cubre `php -S`, Apache y Nginx. Las respuestas ya codificadas
  o de tipo no compresible se ignoran; `Vary: Accept-Encoding` se pone en
  cuanto el tipo es compresible. `zlib.output_compression` activo > no-op.
- **Assets estáticos** (`public/assets/img|js|vendor`): servidos por el
  servidor web bajo la URL versionada `/assets/v<APP_VERSION>/…` (rewrite
  `public/.htaccess`, bloque `location` nginx documentado en installation.md).
  TTL de 1 año immutable bajo Apache; el servidor de dev sirve en `no-cache`.
- **Assets PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon):
  `PageController::serveAsset()` pone `Cache-Control` desde `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` si `?v=` presente) y, cuando `http.etag`,
  `ETag`/`Last-Modified` > `304` en `If-None-Match`. Los `?v=` provienen de
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  el bump dirigido de un componente basta su propio caché.
- **Caché de servidor**: `ActionsBridge::Cacher()` devuelve un cliente sin
  driver cuando `cache.enabled=false` (Get/Set no-op). GC periódico
  (`cache.gc_hours`, marcador `storage/cache/_gc.ts`) - purga todos los
  archivos de la carpeta, incluido `classmap.php` (reconstruido en la petición
  siguiente). TTL propios: `cache.domain_detect_ttl` (detección),
  `cache.update_ttl` (releases).
- **HTML**: `no-cache` - el shell siempre referencia los assets de la versión
  actual. Las respuestas API/privadas nunca reciben TTL público.

## 5. Convenciones del proyecto

- **Bootstrap**: un solo componente JS por elemento (`Data`) - nunca
  `tooltip:` (ni otro binding) en un elemento `data-bs-toggle`; ponerlo en el
  icono hijo.
- **Versionado** semver `0.MINOR.PATCH`: `PATCH` = corrección/refactor,
  `MINOR` = feature o cambio rompedor. Bump dirigido: `APP_VERSION` (núcleo)
  o `plugin.json`/`theme.json` (componente modificado). Sin bump global en
  cada commit.
