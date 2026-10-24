# Base de données

MXWebMail fonctionne avec **SQLite** (défaut) ou **MariaDB/MySQL**. Le pilote est choisi
par `db_dsn` dans `config.php`.

## 1. Connexion

- `db_dsn = 'sqlite'` → `app/storage/db/webmail.sqlite` (mode WAL + `foreign_keys`).
- Sinon un DSN PDO complet (ex. `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`)
  avec `db_user`/`db_pass`.

Options PDO : `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migrations

Le schéma est **portable** SQLite/MariaDB (clés primaires auto-incrément adaptées au
driver). Les migrations sont versionnées (`meta.schema_version`) et appliquées
automatiquement au premier accès à la base (`App::db()`), y compris pendant l'installation.

## 3. Tables globales

| Table | Contenu |
|---|---|
| `meta` | paires clé/valeur (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (unique), `display_name`, `is_admin`, `disabled`, `created_at`, `last_login_at`. |
| `accounts` | comptes IMAP des utilisateurs : `email`, `imap_user`, `imap_pass` (chiffré), `is_default` (compte principal, modifiable dans Réglages → Comptes), `auth` (`password`, `google`, `microsoft`, `yahoo` — OAuth2 : `imap_pass` stocke alors le *refresh token* chiffré). |
| `oauth_apps` | applications OAuth2 par utilisateur : `provider`, `client_id`, `secret` (chiffré), `tenant` — une app par `(user_id, provider)` (voir [domaines.md](domaines.md) §8). |
| `user_settings` | réglages utilisateur (clé/valeur), PK `(user_id, key)`. |
| `identities` | identités d'envoi : `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | serveurs mail par domaine (voir [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (réservé admin si 0), `config` (JSON) par plugin. |
| `themes` | `config` (JSON) et `allowed` par thème. |
| `pgp_keys` | keyring OpenPGP (`key_data` armored, `is_private`). |
| `auth_tokens` | (héritage) jetons d'authentification. |

Les anciennes tables `contacts` / `contact_emails` ont été déplacées hors de la base
globale (voir §5).

## 4. `app.key`

`app/storage/app.key` est une clé maîtresse de 32 octets (hex 64), générée au premier
chiffrement de credential. Elle chiffre (XSalsa20-Poly1305, libsodium secretbox) :

- les mots de passe IMAP en session et dans `accounts.imap_pass` (et les
  *refresh tokens* OAuth2 des comptes `auth != 'password'`) ;
- les secrets d'applications OAuth2 des utilisateurs (`oauth_apps.secret`) ;
- les secrets de plugins (ex. mot de passe CardDAV).

**Sauvegardez-la et ne la perdez jamais** : sa perte invalide tous les credentials stockés
(les utilisateurs doivent se reconnecter). Elle ne doit jamais être servie ni versionnée.

## 5. Bases par utilisateur (contacts)

Les contacts sont isolés dans des bases SQLite individuelles :
`app/storage/db/users/<id>.sqlite` (tables `contacts`, `contact_emails`). Cela vaut **aussi
sous MariaDB** : les données personnelles restent en fichiers SQLite.

La migration `contacts_moved` copie une seule fois les contacts de la base globale vers ces
fichiers (transaction + `INSERT OR IGNORE`, auto-réparante), puis supprime les tables
globales.

## 6. Passer de SQLite à MariaDB

1. Dans l'admin (*Base de données*), renseignez le DSN/hôte/port/nom/utilisateur/mot de
   passe MariaDB. Le test de connexion est **obligatoire** et crée la base (`utf8mb4`,
   collation `utf8mb4_unicode_ci`) si elle n'existe pas.
2. Les migrations rejouent le schéma sur la nouvelle base.

> **Important** : le changement de `db_dsn` repart sur un **schéma vierge** — les données
> (utilisateurs, domaines, réglages) **ne sont pas migrées**. Il n'existe pas d'outil
> d'export. Les fichiers contacts par utilisateur, eux, restent inchangés.

## 7. Sauvegarde / restauration

- SQLite : copiez `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) et `app.key`.
- MariaDB : sauvegardez la base avec `mysqldump`, plus `app.key` et `db/users/*.sqlite`.

