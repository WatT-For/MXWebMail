# Administration

Le panneau d'administration est accessible sur `/{admin_path}` (défaut `/admin`). Il s'agit
d'une administration **globale** (multi-domaines) ; il n'y a pas de rôles fins.

## 1. Authentification admin

Deux modes, selon la configuration :

1. **Compte local** (`admin_user` + `admin_password_hash` dans `config.php`) : login/mot de
   passe, éventuellement TOTP (`admin_totp_secret`). Le TOTP est provisionné et appliqué
   par le plugin `two-factor-auth` (sous-onglet « Double authentification » de
   *Sécurité*, avec QR code) — plugin désactivé = non exigé au login.
2. **Fallback IMAP** (si pas de compte local) : un utilisateur authentifié via IMAP dont le
   champ `is_admin = 1` ouvre l'admin. Ce droit est revalidé à chaque requête (un compte
   rétrogradé/bloqué/supprimé est immédiatement rejeté).

Le rate-limit de connexion s'applique aussi à l'admin, ainsi que `admin_ip_allowlist` s'il
est renseigné. Un admin IMAP ne peut pas se rétrograder, se bloquer ni se supprimer
lui-même.

## 2. Sections du panneau

Le panneau se compose d'onglets (hash `#/<page>[/<sous-onglet>]`).

### Général

`app_name`, `admin_path`, `language` (liste des langues installées + `auto`),
`session_lifetime`, `maintenance`, `update.channel`. Gère aussi le **favicon** (upload
≤ 512 Ko : ico/png/svg/jpg/webp, contenu validé). Le mode **maintenance** affiche une
page dédiée (503) sur `/` : les utilisateurs connectés y sont basculés à leur
prochaine requête API (session conservée) et elle se recharge seule à la fin ;
l'admin (`/{admin_path}` + `/api/admin/*`) reste pleinement accessible.

### Base de données

Pilote SQLite ou MariaDB/MySQL, hôte/port/nom/utilisateur/mot de passe. Un test
(`/api/admin/test-db`) est obligatoire avant écriture : le serveur valide la connexion et
crée la base si besoin. Voir [base-de-donnees.md](base-de-donnees.md).

### Fonctionnalités

Sous-onglets *Fonctionnalités* et *Défauts* :

- proxy d'images + allowlist, tailles des pièces jointes ;
- `contacts_enable` / `contacts_suggestions_limit` ;
- `allow_additional_accounts` / `allow_additional_identities`, `popup_identity`,
  `dangerous_actions` ;
- tous les réglages `user_defaults.*` (défauts utilisateurs).

### Sécurité

Sous-onglets *Compte*, *Double authentification* (si le plugin `two-factor-auth` est
activé), *Accès*, *En-têtes*, *TLS* :

- compte admin local (`admin_user`) et **changement du mot de passe** admin —
  renseigner les deux champs puis *Enregistrer* : le mot de passe n'est modifié
  que s'ils sont remplis et identiques ;
- **TOTP** admin (sous-onglet du plugin `two-factor-auth`) : génération du secret,
  QR code à scanner, code de confirmation obligatoire avant activation (le secret n'est
  jamais affiché après enregistrement) ;
- `admin_ip_allowlist` (bouton « ajouter mon IP »), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies` ;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow` ;
- section `ssl.*` complète (certificats sortants).

### Journaux

Sous-onglets *Visionneuse* et *Paramètres* :

- visionneuse des journaux `app.log`, `mail.log` et du journal d'auth (200 dernières
  lignes) ;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domaines

Configuration des serveurs mail par domaine — voir [domaines.md](domaines.md).
L'onglet *Paramètres* regroupe la résolution (`default_domain`,
`restrict_domains`, `autodetect.*`). Pour les domaines « compte secondaire
uniquement + provider OAuth » (Gmail, Outlook, Yahoo), l'admin n'autorise que
le provider (option `oauth` de la modale domaine) : les identifiants
d'application OAuth2 sont enregistrés par chaque utilisateur
(*Réglages → Applications OAuth2*).

### Utilisateurs

Liste + recherche d'utilisateurs, avec bouton d'actualisation (icône) pour recharger la liste.
Par utilisateur (modale, onglets *Profil*, *Réglages*,
*Comptes/Identités*) :

- `display_name`, `is_admin`, `disabled` ;
- réglages utilisateur effectifs (édition + reset — le reset restaure les
  `user_defaults` mais **préserve** les clés internes `plugin.*` et
  `user_folders`, ex. secret 2FA, provenance des dossiers) ;
- comptes IMAP secondaires (suppression, sauf le compte par défaut) ;
- identités (création/édition/suppression, identité par défaut).

La suppression d'un utilisateur est en cascade et supprime aussi sa base contacts
(`storage/db/users/<id>.sqlite`).

### Extensions (plugins)

Activation/désactivation de chaque plugin + modale de **configuration** générée depuis
`config.schema.json` (types `string`, `text`, `integer`, `number`, `password`, `bool`,
`enum`, `list`). La colonne **Utilisable** restreint un plugin activé à la surface admin
(page `/{admin_path}`, `/api/admin/*`, sessions admin) : il devient inerte pour les
utilisateurs — voir [plugins.md](plugins.md).

**Installation / mise à jour depuis GitHub** : les plugins ne sont plus livrés dans le
zip de release — chaque release transporte un asset `plugin-<name>-<ver>.zip` (+ `.sha256`)
et un index `plugins.index.json` (même dépôt verrouillé que l'updater cœur). Le catalogue
est chargé à l'ouverture de l'onglet (cache partagé avec le check cœur) et actualisable
via **Vérifier** :

- badge `vX dispo` + bouton **Mettre à jour** sur les plugins installés quand une
  version plus récente est publiée (l'ancien dossier est sauvegardé dans
  `storage/update/plugins/backup-<name>-<ts>` — un seul conservé, rollback automatique
  en cas d'échec) ;
- bouton **Installer** sur les entrées du catalogue non présentes — un plugin nouvellement
  installé est **désactivé** par défaut ;
- bouton **Désinstaller** : suppression **définitive** du dossier et de la configuration
  (ligne `plugins` purgée), sans backup ;
- `min_app`/`max_app` du `plugin.json` distant : une entrée incompatible avec
  `APP_VERSION` est marquée *incompatible* et son installation refusée.

### Thèmes

- thème **par défaut** (écrit `user_defaults.theme`) ;
- **autorisation** par thème (`allowed`) : un utilisateur ne peut choisir qu'un thème
  autorisé (le défaut reste toujours sélectionnable) ;
- modale de **configuration** du thème (mêmes types + `color`) ;
- affichage de la chaîne d'héritage (`extends`).

Voir [themes.md](themes.md).

### À propos

Version (`APP_VERSION`), PHP/SAPI/limites, pilote et taille de la base, chemins,
extensions chargées. Gère aussi les **mises à jour** via GitHub Releases :

- *Vérifier* → *Télécharger* → *Appliquer* ; états conservés dans `storage/update/state.json` ;
- sauvegarde `storage/update/backup-<ts>` pour revenir en arrière, action *Réinitialiser*.

> Le dépôt officiel est verrouillé dans `GitHubClient::REPO` : la vérification est
> désactivée tant qu'il n'est pas renseigné. `update.channel` reste modifiable ici ;
> `update.repo`/`PAT GitHub` se règlent manuellement dans `config.php`.

## 3. Écriture de `config.php`

L'admin écrit `config.php` **par section**, de façon atomique (fichier temporaire +
`rename`, `chmod 0600`), en préservant les clés non fournies. Si `app/config/` n'est pas
inscriptible, l'interface l'indique (`saved_readonly`) et les changements sont affichés
mais non persistés.

## 4. Tester la connexion

Dans *Domaines*, **Tester la connexion** valide le protocole de l'onglet actif de la
modale (IMAP, SMTP ou Sieve — les autres onglets testent IMAP), et **Détecter** remplit
les champs sans sauvegarder. La détection (`DomainDetector`) fonctionne indépendamment
de `autodetect.enabled`. Enregistrer manuellement un domaine efface le marqueur
« auto-détecté » (`options.detected`).