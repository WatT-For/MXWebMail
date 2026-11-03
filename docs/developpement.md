# Développement

Documentation pour comprendre l'architecture et étendre le projet (cœur, plugins, thèmes).

## 1. Architecture

```
public/                front controller (index.php) + assets
app/src/MXWebMail/     code applicatif :
  Application/App.php  conteneur de services + dispatch + routes
  API.php, Utils.php   façades de compatibilité pour le code vendoré (MailSo/Lib)
  Compat/              ponts de compatibilité MailSo (Actions, Config, Plugins)
  Config/              chargement/écriture de la configuration
  Controller/          PageController, ApiController, AdminController,
                       SetupController (assistant /setup, servi tant que
                       storage/INSTALLED est absent - 404 ensuite)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + dictionnaires
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          accès données (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (HTTP Releases partagé), UpdateService (cœur),
                       PluginPackageService (catalogue/install/remove plugins)
  Sieve/               ScriptBuilder - génération des règles de filtres
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

- **PSR-4** : `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Classmap insensible à la casse** : `MailSo\` > `app/src/MailSo`, `MXWebMailLib\` >
  `app/src/Lib` (code hérité, fichiers en minuscules, multi-classes). Générée par scan
  `token_get_all`, mise en cache dans `app/storage/cache/classmap.php`, reconstruite
  automatiquement sur miss (une fois par requête).
- Charge les `functions.php` de sabre/uri et sabre/xml (autoload "files").

## 3. Hooks du cœur

Les plugins s'enregistrent sur : `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`, `imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`, `message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

Côté panneau admin : `admin.mounts` (collect) alimente `adminMounts` du bootstrap -
descripteur `{js, plugin, section, tab?, label?}` monté via le binding `pluginMount`
(opt `admin: true` > `ctx.admin`, les appels du module sont préfixés `/admin/plugin/…`
vers `Plugin::apiAdmin<Action>`). Sans `tab` : bloc inline dans la zone `section`
(ex. `security-account`) ; avec `tab` + `label` : sous-onglet dédié de la page `section`
(ex. `security` > `#/security/<tab>`), rendu uniquement si le plugin est activé.
La section spéciale `'page'` (+ `icon` optionnel, classe Font Awesome) crée une
**page de niveau supérieur** dans le menu latéral (`#/<tab>`). `admin.post-login` est émis au login du compte admin local ;
lever `TotpRequiredException` y verrouille l'accès (ex. TOTP admin).

Voir [plugins.md](plugins.md) pour créer un plugin.

## 3bis. Distribution des plugins (GitHubClient + PluginPackageService)

- `GitHubClient` mutualise l'accès GitHub pour `UpdateService` (paquet cœur
  `mxwebmail-*.zip` via `coreAsset`) et `PluginPackageService` (lecture du dépôt
  au ref `update.plugins_ref` : `resolveCommit()` > `GET /commits/<ref>` donne
  sha + tree sha, `tree()` > `GET /git/trees/<sha>?recursive=1` mappe les blobs
  `app/plugins/**`, `rawUrl()` > `raw.githubusercontent.com` pinné sur le sha du
  commit). Dépôt verrouillé par la constante `REPO`, cache release partagé
  `storage/cache/update-check.json`. Chaque asset tronqué porte `download_url`,
  résolu par `pickUrl()` : endpoint API `releases/assets/<id>`
  (`Accept: application/octet-stream`) si `PAT GitHub` est configuré -
  obligatoire sur dépôt privé où `browser_download_url` renvoie 404 - sinon
  `browser_download_url`. Caches plugins : `plugins-ref.json` (TTL `update_ttl`),
  `plugins-tree-<sha>.json` et `plugin-manifest-<name>-<sha>.json` (immutables).
- `PluginPackageService` : `catalog()`, `install()` (stage chaque fichier via raw >
  vérif taille + `blobId()` sha1 git > validation `plugin.json` +
  `min_app`/`max_app` > swap avec backup unique par plugin > invalidation
  classmap/opcache), `remove()` (rmrf + purge de la ligne `plugins` via
  `PluginRepository::delete()`). Verrou partagé `storage/update/update.lock` avec
  `UpdateService`. Staging dans `storage/update/plugins/`. Source alternative
  `update.plugins_local` (disque, dev/tests, sans vérification distante).
- Le packaging (`tools.ps1 package`) exclut `app/plugins/*` du zip cœur - aucun
  asset plugin n'est publié : bump `version` dans `plugin.json` + push sur la
  branche suivie = publication. Le zip exclut aussi les fichiers méta du dépôt
  (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`) ; `LICENSE` reste distribué.

## 4. Cache & compression

Tout est paramétrable (admin > *Performance*, sections `http.*` / `cache.*` de
`config.php`) :

- **Compression** : `App::sendResponse()` appelle `Response::gzip()` au point
  d'envoi unique - couvre `php -S`, Apache et Nginx. Les réponses déjà encodées
  ou de type non compressible sont ignorées ; `Vary: Accept-Encoding` est posé
  dès que le type est compressible. `zlib.output_compression` actif > no-op.
- **Assets statiques** (`public/assets/img|js|vendor`) : servis par le serveur
  web sous l'URL versionnée `/assets/v<APP_VERSION>/…` (rewrite `public/.htaccess`,
  bloc `location` nginx documenté dans installation.md). TTL 1 an immutable sous
  Apache ; le serveur de dev sert en `no-cache`.
- **Assets PHP** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon) :
  `PageController::serveAsset()` pose `Cache-Control` depuis `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` si `?v=` présent) et, quand `http.etag`,
  `ETag`/`Last-Modified` > `304` sur `If-None-Match`. Les `?v=` proviennent de
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  le bump ciblé d'un composant bust donc son propre cache.
- **Cache serveur** : `ActionsBridge::Cacher()` retourne un client sans driver
  quand `cache.enabled=false` (Get/Set no-op). GC périodique (`cache.gc_hours`,
  marqueur `storage/cache/_gc.ts`) - purge tous les fichiers du dossier, y
  compris `classmap.php` (reconstruit à la requête suivante). TTL propres :
  `cache.domain_detect_ttl` (détection), `cache.update_ttl` (releases).
- **HTML** : `no-cache` - le shell référence toujours les assets de la version
  courante. Les réponses API/privées ne reçoivent jamais de TTL public.

## 5. Conventions du projet

- **Bootstrap** : un seul composant JS par élément (`Data`) - jamais `tooltip:` (ou autre
  binding) sur un élément `data-bs-toggle` ; le placer sur l'icône enfant.
- **Versioning** semver `0.MINOR.PATCH` : `PATCH` = correctif/refactor, `MINOR` = feature
  ou changement cassant. Bump ciblé : `APP_VERSION` (cœur) ou `plugin.json`/`theme.json`
  (composant modifié). Pas de bump global à chaque commit.