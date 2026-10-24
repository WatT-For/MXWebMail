# Développement

Documentation pour comprendre l'architecture et étendre le projet (cœur, plugins, thèmes).

## 1. Architecture

```
public/                front controller (index.php) + assets + assistant /setup/
app/src/MXWebMail/     code applicatif :
  Application/App.php  conteneur de services + dispatch + routes
  API.php, Utils.php   façades de compatibilité pour le code vendoré (MailSo/Lib)
  Compat/              ponts de compatibilité MailSo (Actions, Config, Plugins)
  Config/              chargement/écriture de la configuration
  Controller/          PageController, ApiController, AdminController
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + dictionnaires
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          accès données (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (HTTP Releases partagé), UpdateService (cœur),
                       PluginPackageService (catalogue/install/remove plugins)
  Sieve/               ScriptBuilder — génération des règles de filtres
  Theme/               ThemeManager
app/src/MailSo/        bibliothèque mail vendorée (non modifiée)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           utilitaires MXWebMailLib (classmap)
app/src/bootstrap.php  autoloader maison
```

- **Frontend** : Knockout + Bootstrap 5.3, modules ES dans `public/assets/js/` (app.js,
  viewmodels/, models/, bindings/, ui/).
- **Pas de Composer** : autoloader maison (voir §2).

## 2. Autoloader (`app/src/bootstrap.php`)

- **PSR-4** : `MXWebMail\` → `app/src/MXWebMail`, `Sabre\` → `app/src/Sabre`.
- **Classmap insensible à la casse** : `MailSo\` → `app/src/MailSo`, `MXWebMailLib\` →
  `app/src/Lib` (code hérité, fichiers en minuscules, multi-classes). Générée par scan
  `token_get_all`, mise en cache dans `app/storage/cache/classmap.php`, reconstruite
  automatiquement sur miss (une fois par requête).
- Charge les `functions.php` de sabre/uri et sabre/xml (autoload « files »).

## 3. Hooks du cœur

Les plugins s'enregistrent sur : `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.provider`, `auth.post-login`, `contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`, `imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`, `message.meta`,
`message.view`, `message.before-send`, `admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Côté panneau admin : `admin.mounts` (collect) alimente `adminMounts` du bootstrap —
descripteur `{js, plugin, section, tab?, label?}` monté via le binding `pluginMount`
(opt `admin: true` → `ctx.admin`, les appels du module sont préfixés `/admin/plugin/…`
vers `Plugin::apiAdmin<Action>`). Sans `tab` : bloc inline dans la zone `section`
(ex. `security-account`) ; avec `tab` + `label` : sous-onglet dédié de la page `section`
(ex. `security` → `#/security/<tab>`), rendu uniquement si le plugin est activé. `admin.post-login` est émis au login du compte admin local ;
lever `TotpRequiredException` y verrouille l'accès (ex. TOTP admin).

Voir [plugins.md](plugins.md) pour créer un plugin.

## 3bis. Distribution des plugins (GitHubClient + PluginPackageService)

- `GitHubClient` mutualise l'accès aux GitHub Releases pour `UpdateService` (paquet
  cœur `mxwebmail-*.zip` via `coreAsset`) et `PluginPackageService` (assets
  `plugin-<name>-<ver>.zip` via `pluginAssets` + `plugins_index`). Dépôt verrouillé
  par la constante `REPO`, cache release partagé `storage/cache/update-check.json`.
- `PluginPackageService` : `catalog()`, `install()` (download → sha256 → extraction
  anti-traversal → validation `plugin.json` + `min_app`/`max_app` → swap avec backup
  unique par plugin → invalidation classmap/opcache), `remove()` (rmrf + purge de la
  ligne `plugins` via `PluginRepository::delete()`). Verrou partagé
  `storage/update/update.lock` avec `UpdateService`. Staging dans
  `storage/update/plugins/`.
- Le packaging (`tools.ps1 package`) exclut `app/plugins/*` du zip cœur et produit
  les `plugin-*.zip(.sha256)` + `plugins.index.json` à attacher à la release.

## 4. Cache & compression

Tout est paramétrable (admin → *Performance*, sections `http.*` / `cache.*` de
`config.php`) :

- **Compression** : `App::sendResponse()` appelle `Response::gzip()` au point
  d'envoi unique — couvre `php -S`, Apache et Nginx. Les réponses déjà encodées
  ou de type non compressible sont ignorées ; `Vary: Accept-Encoding` est posé
  dès que le type est compressible. `zlib.output_compression` actif → no-op.
- **Assets statiques** (`public/assets/img|js|vendor`) : servis par le serveur
  web sous l'URL versionnée `/assets/v<APP_VERSION>/…` (rewrite `public/.htaccess`,
  bloc `location` nginx documenté dans installation.md). TTL 1 an immutable sous
  Apache ; le serveur de dev sert en `no-cache`.
- **Assets PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon) :
  `PageController::serveAsset()` pose `Cache-Control` depuis `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` si `?v=` présent) et, quand `http.etag`,
  `ETag`/`Last-Modified` → `304` sur `If-None-Match`. Les `?v=` proviennent de
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) —
  le bump ciblé d'un composant bust donc son propre cache.
- **Cache serveur** : `ActionsBridge::Cacher()` retourne un client sans driver
  quand `cache.enabled=false` (Get/Set no-op). GC périodique (`cache.gc_hours`,
  marqueur `storage/cache/_gc.ts`) — purge tous les fichiers du dossier, y
  compris `classmap.php` (reconstruit à la requête suivante). TTL propres :
  `cache.domain_detect_ttl` (détection), `cache.update_ttl` (releases).
- **HTML** : `no-cache` — le shell référence toujours les assets de la version
  courante. Les réponses API/privées ne reçoivent jamais de TTL public.

## 5. Conventions du projet

- **Bootstrap** : un seul composant JS par élément (`Data`) — jamais `tooltip:` (ou autre
  binding) sur un élément `data-bs-toggle` ; le placer sur l'icône enfant.
- **Versioning** semver `0.MINOR.PATCH` : `PATCH` = correctif/refactor, `MINOR` = feature
  ou changement cassant. Bump ciblé : `APP_VERSION` (cœur) ou `plugin.json`/`theme.json`
  (composant modifié). Pas de bump global à chaque commit.