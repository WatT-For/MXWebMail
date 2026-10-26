# Plugins

Les plugins sont des extensions bornées dans `app/plugins/<name>/`, chargées si activées
(depuis l'admin, onglet *Extensions*). Ils s'enregistrent sur des **hooks** du cœur.

## 0. Distribution via GitHub Releases

Les plugins ne sont **pas inclus** dans le zip de release `mxwebmail-<ver>.zip` : chaque
release transporte un asset par plugin `plugin-<name>-<ver>.zip` (+ `.sha256`) et un
index `plugins.index.json` (`{name, version, sha256, size, description, min_app?, max_app?}`),
téléchargés depuis l'admin, onglet *Extensions* (`PluginPackageService`) — les entrées du
catalogue non installées figurent en fin du tableau des plugins.

- **Installer** : télécharge le zip, vérifie la somme sha256 (index ou asset `.sha256`),
  extrait et valide `plugin.json` (nom concordant, version semver, `main` sain,
  compatibilité `APP_VERSION`), remplace le dossier — un plugin installé est désactivé
  par défaut ;
- **Mettre à jour** : même flux sur un plugin présent — l'ancien dossier part dans
  `storage/update/plugins/backup-<name>-<ts>` (un seul conservé, rollback auto) ;
- **Désinstaller** : suppression définitive du dossier + purge de la ligne `plugins`
  (configuration perdue), sans backup.

Le dépôt est verrouillé dans `GitHubClient::REPO` (même constante que l'updater cœur) —
aucune URL arbitraire n'est acceptée. `update.channel`, `PAT GitHub` et le cache
release (`cache.update_ttl`) sont partagés avec la vérification de mise à jour du cœur.
Le `check` statique exige `version` semver dans `plugin.json` : c'est cette version qui
nomme l'asset `plugin-<name>-<ver>.zip` au packaging.

Un plugin activé peut aussi être **réservé à la surface admin** (colonne *Utilisable* de
l'onglet *Extensions*, flag `user_allowed` en table `plugins`) : il n'est alors chargé que
sur la page `/{admin_path}`, les routes `/api/admin/*` et les sessions admin. Ailleurs il
est totalement inerte (aucun hook, `/api/plugin/<name>/*` → 404) — y compris pour les
utilisateurs `is_admin` non connectés au panneau. Les hooks de login admin
(`admin.post-login`, `admin.totp-required`, `admin.mounts`) continuent de fonctionner.

## 1. Plugins du catalogue officiel

| Plugin | Rôle | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Ajoute `X-Originating-IP` (IP de l'expéditeur) aux envois. | `message.before-send` |
| `anti-spoofing` | Détection d'usurpation dans le lecteur : Return-Path/Reply-To/Sender incohérents, display-name imitant une marque, domaine leurre (homoglyphes). | `imap.message-headers`, `message.view` |
| `avatars` | Avatar de l'expéditeur : icônes de services connues, Gravatar, favicon du domaine, initiales. | `app.data` |
| `black-list` | Liste noire de connexion (wildcards + exceptions). | `auth.login.mapping` |
| `white-list` | Liste blanche de connexion (wildcards + exceptions). | `auth.login.mapping` |
| `carddav-contacts` | Synchronisation des contacts depuis un carnet CardDAV (Nextcloud, Radicale, iCloud…) — **HTTPS + hôte public exigés**. | `app.data` |
| `change-password` | Changement de mot de passe (drivers poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Étiquettes de couleur sur les messages (manuelles + règles automatiques). | `app.data`, `message.meta` |
| `ics-viewer` | Aperçu des invitations/pièces jointes `.ics` dans le lecteur. | `app.data` |
| `imap-contacts-suggestions` | Suggestions de contacts depuis les expéditeurs d'un dossier IMAP. | `contacts.provider` |
| `ldap-contacts` | Suggestions de contacts depuis un annuaire LDAP (autocomplétion). | `contacts.provider` |
| `ldap-login` | Résolution LDAP : uid/alias → adresse IMAP réelle avant le login. | `auth.login.mapping` |
| `mailbox-detect` | Détection des dossiers système et création des manquants. | `app.data` |
| `pwa` | Rend le webmail installable (manifest + service worker minimal, icônes). | `app.head`, `app.routes` |
| `two-factor-auth` | TOTP à deux facteurs (Google Authenticator, Aegis…) — comptes utilisateurs **et** compte admin local (provisioning QR dans un sous-onglet dédié de Admin → Sécurité ; exigé au login admin tant que le plugin est activé). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |

## 2. Configuration d'un plugin

Dans l'admin (*Extensions* → bouton ⚙), la configuration est générée depuis le fichier
`config.schema.json` du plugin. Types supportés : `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `list`. Les valeurs sont stockées en table `plugins` (colonne
`config`, JSON).

Détails de configuration notables :

- **`change-password`** — `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`), `allowed_emails`
  (défaut `*`), `pass_min_length` (10), `pass_min_strength` (70), `check_hibp`
  (k-anonymité), puis les paramètres propres au driver (`poppassd_host/port`, `pdo_dsn`/
  `pdo_sql` avec placeholders `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** — `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`, `base_dn`,
  `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** — `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`, `base_dn`,
  `filter` (placeholder `%q`), `name_attr`, `mail_attr`, `max_results`.
- **`anti-spoofing`** — `protected_domains`, `brands`, et les commutateurs
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`.
- **`two-factor-auth`** — `issuer` (défaut `app_name`), `label` (`email` = adresse
  complète, `local` = partie avant `@` ; `local` évite que les applis TOTP déduisent
  une icône de marque du domaine, ex. Yahoo) et `icon_url` (paramètre `image=` non
  standard, affiché par Aegis/andOTP/FreeOTP — ignoré par Authy ; vide = favicon
  publique de l'instance). Le plugin provisionne aussi la
  2FA du compte admin local : secret `admin_totp_secret` en `config.php` (via l'API admin
  `/api/admin/plugin/two-factor-auth/*`), pending en session, exigé au login admin via
  le hook `admin.post-login`. Désactiver le plugin désactive l'enforcement admin.

Certains plugins n'ont pas de schéma admin : leur configuration est **par utilisateur**
(ex. `carddav-contacts`, `color-tags`, `two-factor-auth` pour l'état du compte).

## 3. Créer un plugin

### Structure minimale

```
app/plugins/mon-plugin/
├── plugin.json          # manifeste
├── MonPlugin.php        # classe principale
├── config.schema.json   # (optionnel) schéma de configuration admin
├── langs/<lang>.php     # dictionnaires (clés préfixées plugin.<name>.)
└── js/ ou assets/       # (optionnel) assets servis via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "mon-plugin",
	"class": "MonPlugin",
	"main": "MonPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mon-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`min_app`/`max_app` (optionnels, semver `x.y.z`) bornent la compatibilité avec
`APP_VERSION` — l'installation ou la mise à jour est refusée hors plage, et le
catalogue marque l'entrée *incompatible*.

### Classe principale

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MonPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Hooks disponibles

- **Cœur** : `app.routes`, `app.head`, `app.data`, `auth.login.mapping`, `auth.provider`,
  `auth.post-login`, `contacts.provider`, `identities.provider`, `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`, `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin** : `admin.mounts` (`collect` → descripteur `{js, plugin, section, tab?, label?}`
  rendu par `pluginMount` dans le panneau admin — sans `tab` : bloc inline dans la zone
  nommée `section`, ex. `security-account` ; avec `tab` + `label` : sous-onglet dédié de
  la page nommée `section`, ex. `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, émis au login du compte admin local — lever
  `TotpRequiredException` exige un second facteur), `admin.totp-required`
  (`first` → booléen : affiche le champ TOTP sur la page de login admin).

Chaque plugin déclare dans `hooks` les hooks qu'il écoute. Le cœur appelle
`HookRegistry::run('hook', …)` ; le plugin implémente une méthode du même nom (ou un
`on<Hook>` selon la convention du cœur).

### API générique

Tout plugin est appelable via `/api/plugin/<name>/<action>` (GET/POST) → méthode
`api<Action>(Request $r, ?array $user)`. Le jeton CSRF est exigé sur toutes les
méthodes (le client `api.js` l'envoie systématiquement) — les appels directs
sans en-tête `X-CSRF-Token` sont rejetés en 403.

Côté admin, `/api/admin/plugin/<name>/<action>` (GET/POST) → méthode
`apiAdmin<Action>(Request $r)` : session admin (`$_SESSION['admin']`) + CSRF requis ;
il n'y a pas de `$user` (le compte admin local n'est pas une ligne `users`).
Attention : `requireAdmin` ferme la session en écriture — pour y stocker un état
(ex. secret en attente), la ré-ouvrir via `$this->app->startSession()` puis
`session_write_close()`.

### Onglet utilisateur

Pour ajouter un onglet dans `#/@settings/…`, fournissez du JS servi via
`/assets/plugin/<name>/<file>` et déclarez l'onglet (voir les plugins `change-password`,
`carddav-contacts`, `color-tags`, `two-factor-auth` comme exemples).

### Traductions

Clés préfixées `plugin.<name>.` dans `langs/<lang>.php` ; `description`/`label`/`help` des
manifestes et schémas acceptent ces clés (sinon pass-through). Côté PHP : `$this->t('cle')`,
côté JS : `ctx.t('cle')` dans `mount(element, ctx)`.

