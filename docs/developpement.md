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
  Service/             AuthService, MailService, DomainDetector, OAuthService, UpdateService
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

## 4. Conventions du projet

- **Bootstrap** : un seul composant JS par élément (`Data`) — jamais `tooltip:` (ou autre
  binding) sur un élément `data-bs-toggle` ; le placer sur l'icône enfant.
- **Versioning** semver `0.MINOR.PATCH` : `PATCH` = correctif/refactor, `MINOR` = feature
  ou changement cassant. Bump ciblé : `APP_VERSION` (cœur) ou `plugin.json`/`theme.json`
  (composant modifié). Pas de bump global à chaque commit.