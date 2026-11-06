# Plugins

Les plugins sont des extensions bornées dans `app/plugins/<name>/`, chargées si activées
(depuis l'admin, onglet *Extensions*). Ils s'enregistrent sur des **hooks** du cœur.

> **Session en lecture seule** : les endpoints GET ferment la session PHP tôt
> (`session_write_close`) pour libérer le verrou. Les hooks appelés depuis un
> GET (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **ne doivent jamais écrire
> `$_SESSION`** - ces écritures seraient silencieusement perdues.

## 0. Distribution via le dépôt GitHub

Les plugins ne sont **pas inclus** dans le zip de release `mxwebmail-<ver>.zip` : ils
sont lus directement dans le dépôt à un **ref configurable** (`update.plugins_ref` -
`'release'` = tag de la dernière release par défaut, `'main'` ou tout ref git).
Le catalogue (`PluginPackageService`, onglet *Extensions* - les entrées non installées
figurent en fin du tableau) est construit depuis l'API git :

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` liste les fichiers sous
  `app/plugins/` avec leur **sha1 de blob git** et leur taille ;
- le `plugin.json` de chaque plugin est lu via `raw.githubusercontent.com` (pinné sur
  le sha du commit) - version, description, `min_app`/`max_app` ;
- **Installer / mettre à jour** : chaque fichier du plugin est téléchargé via `raw`,
  vérifié (taille + sha1 de blob du tree), puis `plugin.json` validé (nom concordant,
  version semver, `main` sain, compatibilité `APP_VERSION`) avant de remplacer le
  dossier - un plugin installé est **désactivé** par défaut ; l'ancien dossier part
  dans `storage/update/plugins/backup-<name>-<ts>` (un seul conservé, rollback auto) ;
- **Désinstaller** : suppression définitive du dossier + purge de la ligne `plugins`
  (configuration perdue), sans backup.

Publier une version de plugin = bumper `version` dans son `plugin.json` et pousser sur
la branche suivie par `update.plugins_ref` - aucune release ni asset n'est requis.

Le dépôt est verrouillé dans `GitHubClient::REPO` (même constante que l'updater cœur) -
aucune URL arbitraire n'est acceptée. `update.channel`, `PAT GitHub` et le cache
release (`cache.update_ttl`) sont partagés avec la vérification de mise à jour du cœur ;
le ref>commit est caché à ce TTL, le tree et les manifestes sont adressés par leur sha
(immutables). Le `check` statique exige `version` semver dans `plugin.json`.

Pour le dev/tests hors réseau, `update.plugins_local` (chemin absolu d'un dossier de
plugins dans `config.php`) sert de source : catalogue et installation par copie disque,
sans vérification distante.

Un plugin activé peut aussi être **réservé à la surface admin** (colonne *Accès utilisateurs* de
l'onglet *Extensions*, flag `user_allowed` en table `plugins`, **décoché par défaut**) : il
n'est alors chargé que
sur la page `/{admin_path}`, les routes `/api/admin/*` et les sessions admin. Ailleurs il
est totalement inerte (aucun hook, `/api/plugin/<name>/*` > 404) - y compris pour les
utilisateurs `is_admin` non connectés au panneau. Les hooks de login admin
(`admin.post-login`, `admin.totp-required`, `admin.mounts`) continuent de fonctionner.
Un plugin dont tous les hooks déclarés au manifeste sont des hooks `admin.*` n'a aucune
surface utilisateur : la colonne n'affiche alors pas de switch pour lui (`-`).

## 1. Plugins du catalogue officiel

| Plugin | Rôle | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Ajoute `X-Originating-IP` (IP de l'expéditeur) aux envois. | `message.before-send` |
| `anti-spoofing` | Détection d'usurpation dans le lecteur : Return-Path/Reply-To/Sender incohérents, display-name imitant une marque (homoglyphes et confusables détectés), domaine leurre (sous-domaine piégé, typosquat, trans-TLD), SPF/DKIM/DMARC évalués en alignement avec le domaine de l'expéditeur (verdicts lus dans `Authentication-Results`, `Received-SPF` ou les en-têtes SpamAssassin/rspamd) - les sous-domaines des domaines protégés sont légitimes. | `imap.message-headers`, `message.view` |
| `avatars` | Avatar de l'expéditeur : icônes de services connues, Gravatar, favicon du domaine, initiales. | `app.data` |
| `black-list` | Liste noire de connexion (wildcards + exceptions) - vérifiée sur l'email **résolu** (post-mapping). | `auth.login.mapped` |
| `white-list` | Liste blanche de connexion (wildcards + exceptions) - vérifiée sur l'email **résolu** (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Synchronisation des contacts depuis un carnet CardDAV (Nextcloud, Radicale, iCloud…) - **HTTPS + hôte public exigés**. | `app.data` |
| `change-password` | Changement de mot de passe (drivers poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Étiquettes de couleur sur les messages (manuelles + règles automatiques). | `app.data`, `message.meta` |
| `ics-viewer` | Aperçu des invitations/pièces jointes `.ics` dans le lecteur **+ réponse iTIP** (Accepter/Peut-être/Refuser sur `METHOD:REQUEST`, envoyée par SMTP à l'organisateur). | `app.data` |
| `imap-contacts-suggestions` | Suggestions de contacts depuis les expéditeurs d'un dossier IMAP. | `contacts.provider` |
| `ldap-contacts` | Suggestions de contacts depuis un annuaire LDAP (autocomplétion). | `contacts.provider` |
| `ldap-login` | Résolution LDAP : uid/alias > adresse IMAP réelle avant le login. | `auth.login.mapping` |
| `mailbox-detect` | Détection des dossiers système et création des manquants. | `app.data` |
| `pwa` | Rend le webmail installable (manifest + service worker minimal, icônes). | `app.head`, `app.routes` |
| `webmail-migration` | Page admin d'import depuis un dossier `data` **RainLoop ou SnappyMail** (chemin serveur ou ZIP) : domaines, carnets `AddressBook.sqlite`/PDO, identités, réglages, dossiers système, clés PGP - scan dry-run puis import idempotent, comptes inconnus ignorés. | `admin.mounts` |
| `two-factor-auth` | TOTP à deux facteurs (Google Authenticator, Aegis…) - comptes utilisateurs **et** compte admin local (provisioning QR dans un sous-onglet dédié de Admin > Sécurité ; exigé au login admin tant que le plugin est activé). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Bannière d'annonce administrateur (page app, optionnellement page de login - maintenance). Texte en clair uniquement. | `app.head` |
| `attachments-clamav` | Scan ClamAV (`clamd`, `zINSTREAM`) des pièces jointes uploadées et des imports - rejet sur détection, politique `on_error` configurable. | `attachment.upload` |
| `filelink` | Pièces jointes trop grosses pour le SMTP envoyées sous forme de **lien de téléchargement expirable** (`/filelink/<token>` - attachment forcé, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Apprentissage spam/ham à chaque (dé)marquage : commande externe (`rspamc`, `sa-learn`) ou copie IMAP vers un dossier dovecot-antispam. | `message.spam` |
| `wkd-keys` | Découverte automatique des clés PGP des destinataires : **WKD** (advanced + direct), récolte des en-têtes **Autocrypt**, secours `keys.openpgp.org` optionnel - import proposé au compose. | `app.data`, `message.view` |
| `seasonal-decorations` | Paysage monochrome discret dans les bandeaux selon la date (Nouvel An, Pâques, 1er avril, Halloween, Noël) : bibliothèque d'objets SVG recomposée en couches `mask-image` via `scenes.json` ({svg, pos, size}), couleur contrastée automatique - fenêtres réglables, optionnel hors session, sans animation. | `app.head` |
| `link-confirmation` | Modale de confirmation avant l'ouverture d'un lien du corps d'un mail (anti-hameçonnage) : destination réelle affichée, signalement des liens suspects (texte ≠ URL, IP littérale, punycode, identifiants dans l'URL), domaines de confiance et "ne plus demander pour ce domaine". | `app.head` |

## 2. Configuration d'un plugin

Dans l'admin (*Extensions* > bouton ⚙), la configuration est générée depuis le fichier
`config.schema.json` du plugin. Types supportés : `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list` - `color` rend un sélecteur natif + un champ
`#RRGGBB` (la valeur vide est acceptée : le champ retombe sur le repli du plugin).
Les valeurs sont stockées en table `plugins` (colonne `config`, JSON).

Détails de configuration notables :

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`), `allowed_emails`
  (défaut `*`), `pass_min_length` (10), `pass_min_strength` (70), `check_hibp`
  (k-anonymité), puis les paramètres propres au driver (`poppassd_host/port`, `pdo_dsn`/
  `pdo_sql` avec placeholders `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`, `base_dn`,
  `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`, `base_dn`,
  `filter` (placeholder `%q`), `name_attr`, `mail_attr`, `max_results`.
- **`anti-spoofing`** - `protected_domains` (un domaine protégé couvre aussi ses
  sous-domaines, ex. `accountprotection.microsoft.com`), `brands` (détectés dans le
  nom d'affichage après normalisation des accents et des caractères confusables -
  "AppIe TV" est reconnu comme "Apple TV"), et les commutateurs
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  Les résultats SPF/DKIM/DMARC ne sont pris en compte que lorsqu'ils sont alignés
  avec le domaine du `From` : un `dkim=fail` sur la signature du transporteur ou
  un `spf=fail` sur un mailfrom relayé (SRS) ne déclenche pas d'alerte.
- **`two-factor-auth`** - `issuer` (défaut `app_name`), `label` (`email` = adresse
  complète, `local` = partie avant `@` ; `local` évite que les applis TOTP déduisent
  une icône de marque du domaine, ex. Yahoo) et `icon_url` (paramètre `image=` non
  standard, affiché par Aegis/andOTP/FreeOTP - ignoré par Authy ; vide = favicon
  publique de l'instance). Le plugin provisionne aussi la
  2FA du compte admin local : secret `admin_totp_secret` en `config.php` (via l'API admin
  `/api/admin/plugin/two-factor-auth/*`), pending en session, exigé au login admin via
  le hook `admin.post-login`. Désactiver le plugin désactive l'enforcement admin.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards ;
  valeurs séparées par espaces, virgules ou point-virgules), `exceptions`,
  `auth_error` (403 "compte non autorisé" > 401 générique). Attention à
  la sémantique héritée du plugin legacy : pour black-list, `exceptions`
  = emails de la liste noire qui **restent autorisés** ; pour white-list,
  si `exceptions` est vide tout email hors liste blanche est rejeté, mais
  si elle est renseignée **seuls les emails des exceptions sont rejetés**
  et tout le reste passe - ne pas y mettre des adresses "autorisées en
  plus" (effet inverse attendu).

- **`link-confirmation`** - `mode` (`all` = tout lien externe, `suspicious` =
  uniquement les liens suspects), `whitelist` (domaines de confiance, un par
  ligne ou séparés par espace/virgule/point-virgule - sous-domaines inclus),
  `warn_mailto` (confirmer aussi `mailto:`/`tel:`, désactivé par défaut) et
  `remember` (case "ne plus demander pour ce domaine" - mémorisé dans le
  navigateur via localStorage, pas côté serveur).

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
	"label": "plugin.mon-plugin.label",
	"class": "MonPlugin",
	"main": "MonPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mon-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (optionnel) est le nom d'affichage du plugin dans Admin > Extensions
(clé i18n `plugin.<name>.label` recommandée, texte littéral accepté ; le nom
technique `name` reste affiché à côté et sert d'identifiant).

`min_app`/`max_app` (optionnels, semver `x.y.z`) bornent la compatibilité avec
`APP_VERSION` - l'installation ou la mise à jour est refusée hors plage, et le
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

- **Cœur** : `app.routes`, `app.head` (HTML injecté dans le `<head>` de
  `app.php`, `login.php` et `maintenance.php` - les vues `admin*.php` ne
  l'émettent pas), `app.data`, `auth.login.mapping`, `auth.login.mapped`, `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`, `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`, `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin** : `admin.mounts` (`collect` > descripteur `{js, plugin, section, tab?, label?}`
  rendu par `pluginMount` dans le panneau admin - sans `tab` : bloc inline dans la zone
  nommée `section`, ex. `security-account` ; avec `tab` + `label` : sous-onglet dédié de
  la page nommée `section`, ex. `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, émis au login du compte admin local - lever
  `TotpRequiredException` exige un second facteur), `admin.totp-required`
  (`first` > booléen : affiche le champ TOTP sur la page de login admin).

Chaque plugin déclare dans `hooks` les hooks qu'il écoute. Un manifeste ne déclarant
que des hooks `admin.*` marque le plugin comme purement admin - la colonne
*Accès utilisateurs* de l'onglet Extensions n'affiche pas de switch pour lui. Le cœur appelle
`HookRegistry::run('hook', …)` ; le plugin implémente une méthode du même nom (ou un
`on<Hook>` selon la convention du cœur).

### API générique

Tout plugin est appelable via `/api/plugin/<name>/<action>` (GET/POST) > méthode
`api<Action>(Request $r, ?array $user)`. Le jeton CSRF est exigé sur toutes les
méthodes (le client `api.js` l'envoie systématiquement) - les appels directs
sans en-tête `X-CSRF-Token` sont rejetés en 403.

Côté admin, `/api/admin/plugin/<name>/<action>` (GET/POST) > méthode
`apiAdmin<Action>(Request $r)` : session admin (`$_SESSION['admin']`) + CSRF requis ;
il n'y a pas de `$user` (le compte admin local n'est pas une ligne `users`).
Attention : `requireAdmin` ferme la session en écriture - pour y stocker un état
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

