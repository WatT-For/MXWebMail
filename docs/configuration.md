# Référence de configuration

Le fichier `app/config/config.php` ne définit que les clés qui **écrasent** les valeurs
par défaut (`app/src/MXWebMail/Config/Config.php`). Copiez `config.example.php` en
`config.php` puis adaptez. Le panneau d'administration édite la plupart de ces clés par
section ; seules certaines (`update.*` surtout) exigent une édition manuelle.

Les tableaux associatifs sont fusionnés en profondeur ; les listes sont remplacées.

## Stockage

| Clé | Défaut | Rôle |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (→ `storage/db/webmail.sqlite`) ou DSN PDO complet (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Utilisateur PDO (MariaDB). |
| `db_pass` | `''` | Mot de passe PDO (MariaDB). |

## Application

| Clé | Défaut | Rôle |
|---|---|---|
| `app_name` | `MXWebMail` | Nom affiché (titre, PWA, TOTP). |
| `favicon` | `''` | Fichier favicon uploadé dans `storage/branding`, servi via `/favicon.ico` ; vide = icône par défaut. |
| `debug` | `false` | Renvoie les erreurs internes aux clients API (jamais en production). |
| `language` | `auto` | `auto` = `Accept-Language` du navigateur ; sinon code d'une langue installée (`fr`, `en`…). Repli final `en`. |

## Domaines

| Clé | Défaut | Rôle |
|---|---|---|
| `default_domain` | `auto` | Suffixe d'un login saisi sans `@domaine` : `auto` = hôte HTTP (sans `www.` ni port), `''` = aucun, ou un domaine fixe. |
| `restrict_domains` | `true` | Seuls les domaines nommés de la table `domains` sont acceptés au login (ni `*` ni la détection ne s'appliquent). |
| `autodetect.enabled` | `false` | Détection automatique de la config d'un domaine inconnu au login. |
| `autodetect.use_ispdb` | `false` | Repli sur la base Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Sondage de ports `imap.`/`mail.`/`smtp.`/`sieve.<domaine>`. |

Les serveurs IMAP/SMTP/Sieve se configurent **par domaine** dans l'admin (voir
[domaines.md](domaines.md)). Le domaine `*` sert de paramètres catch-all.

## Sécurité

| Clé | Défaut | Rôle |
|---|---|---|
| `admin_path` | `admin` | Segment d'URL du panneau admin. |
| `admin_user` | `''` | Compte admin local ; vide = fallback sur un utilisateur `is_admin` vérifié via IMAP. |
| `admin_password_hash` | `''` | `password_hash('motdepasse', PASSWORD_DEFAULT)` du compte admin local. |
| `admin_totp_secret` | `''` | Secret TOTP (base32) du compte admin local, écrit par le plugin `two-factor-auth` ; vide = désactivé. Non exigé si le plugin est désactivé. |
| `login_max_attempts` | `5` | Tentatives de connexion avant blocage temporaire. |
| `login_lock_seconds` | `300` | Durée du blocage (secondes). |
| `admin_ip_allowlist` | `''` | IP/CIDR (CSV) autorisées sur l'admin ; vide = toutes. |
| `session_lifetime` | `0` | Durée de session en heures ; `0` = cookie de session. |
| `maintenance` | `false` | Mode maintenance : `/` sert une page dédiée en 503 (tous publics, session conservée) et `/api/*` utilisateur répond 503 — `/api/admin/*` et `/api/ping` restent actifs. Les utilisateurs connectés basculent sur la page maintenance à leur prochaine requête API ; celle-ci se recharge seule à la fin. |
| `force_https` | `false` | Redirige HTTP → HTTPS (301). |
| `hsts` | `false` | Émet `Strict-Transport-Security` quand la requête est HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (défaut, requis pour le retour OAuth2) / `Strict` (casse le flux OAuth2) / `None` (exige HTTPS). |
| `csp_extra` | `''` | Directives CSP additionnelles (ex. `img-src https:`). |
| `trusted_proxies` | `''` | IP/CIDR (CSV) des reverse proxies de confiance. Obligatoire pour fiabiliser `X-Forwarded-*` (rate-limit, allowlist, logs). |
| `secfetch_allow` | `''` | Règles `Sec-Fetch-*` additionnelles (ex. `site=same-origin;mode=cors`). |

## Mises à jour

Le dépôt officiel est verrouillé dans `GitHubClient::REPO` : la vérification est
désactivée tant qu'il n'est pas renseigné. Cette section — partagée par l'updater cœur
et le catalogue plugins (même release : `mxwebmail-*.zip`, `plugin-*.zip`,
`plugins.index.json`) — n'est pas éditable via l'admin (sauf `channel`).

| Clé | Défaut | Rôle |
|---|---|---|
| `update.repo` | `''` | Fallback dev si `GitHubClient::REPO` est vide (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (seul réglage exposé dans l'admin). |
| `PAT GitHub` | `''` | PAT GitHub optionnel (rate-limit / dépôt privé) — aussi utilisé pour le catalogue plugins. |
| `update.manifest_url` | `''` | Dev : manifeste JSON au format release, contourne l'API GitHub. |

## Fonctionnalités

| Clé | Défaut | Rôle |
|---|---|---|
| `attachment_max_size` | `25` | Mo maximum par fichier. Doit être ≤ `attachments_max_total` (au-delà, la limite est sans effet : l'upload borne déjà au `min` des deux — l'admin refuse la sauvegarde incohérente). |
| `attachments_max_total` | `25` | Mo cumulés par message. |
| `allow_additional_accounts` | `true` | L'utilisateur peut ajouter/gérer des comptes IMAP secondaires (Réglages → Comptes). |
| `allow_additional_identities` | `true` | L'utilisateur peut créer des identités additionnelles. |
| `popup_identity` | `false` | Ouvre le réglage d'identité au premier login si le nom est vide. |
| `dangerous_actions` | `true` | Autorise vider/supprimer un dossier, supprimer des messages (dont le déplacement vers la corbeille). |
| `contacts_enable` | `true` | Carnet d'adresses + autocomplétion. |
| `contacts_suggestions_limit` | `20` | Nombre max de suggestions d'autocomplétion (1-100). |

## `user_defaults` — défauts des réglages utilisateur

Écrasables par l'utilisateur. Les clés autorisées en écriture via `/api/settings` sont
celles-ci + celles déclarées par les plugins (hook `settings.allowed`).

| Clé | Défaut | Rôle |
|---|---|---|
| `messages_per_page` | `50` | Messages par page (5-200). |
| `list_sort` | `REVERSE DATE` | Tri de la liste (critère IMAP SORT whitelisté). |
| `view_images` | `ask` | Images externes : `always` / `ask` / `match` (expéditeur connu). |
| `msg_default_action` | `reply` | Bouton répondre par défaut : `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Secondes avant de marquer lu un message ouvert (0 = immédiat). |
| `autologout` | `30` | Minutes d'inactivité avant déconnexion (0 = jamais). |
| `list_refresh` | `5` | Minutes entre réactualisations auto (0 = jamais). |
| `notify_new_mail` | `false` | Notification navigateur à l'arrivée d'un mail. |
| `contacts_autosave` | `true` | Auto-enregistrer le destinataire dans les contacts. |
| `folder_pane_width` | `240` | Largeur colonne dossiers (px, 140-600). |
| `message_pane_width` | `340` | Largeur colonne liste des messages (px, 200-800). |
| `layout` | `horizontal` | Disposition de lecture : `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Hauteur liste des messages en mode vertical (px, 120-1200). |
| `compose_modal_width` | `0` | Largeur modale composition (px, 0 = défaut Bootstrap). |
| `compose_modal_height` | `0` | Hauteur modale composition (px, 0 = auto). |
| `theme` | `default` | Template d'interface (dossier `app/templates/<name>`). |

## TLS (connexions sortantes IMAP/SMTP/Sieve)

| Clé | Défaut | Rôle |
|---|---|---|
| `ssl.verify_certificate` | `true` | Vérifier le certificat (`false` = cert auto-signé). |
| `ssl.allow_self_signed` | `false` | Accepter les certificats auto-signés. |
| `ssl.cafile` / `ssl.capath` | `''` | CA custom (chemin absolu / dossier). |
| `ssl.disable_compression` | `true` | Désactive la compression TLS. |
| `ssl.security_level` | `1` | Niveau de sécurité OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Certificat client éventuel. |

## Proxy d'images externes

| Clé | Défaut | Rôle |
|---|---|---|
| `image_proxy` | `true` | Servir les images distantes via `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Domaines autorisés (CSV, `*.domaine` ok) ; vide = tous (IP privées toujours bloquées). |

## HTTP — compression & cache navigateur

Éditables dans l'admin, section *Performance → HTTP*.

| Clé | Défaut | Rôle |
|---|---|---|
| `http.gzip` | `true` | Compresse les réponses texte/JSON en PHP (gzip) — fonctionne aussi sans `mod_deflate`. Ignoré si `zlib.output_compression` est actif. |
| `http.gzip_level` | `6` | Niveau de compression 1-9. |
| `http.gzip_min_size` | `1024` | Taille minimale du corps (octets) pour compresser. |
| `http.etag` | `true` | ETag/Last-Modified + réponses 304 sur les assets servis par PHP (thème, plugins, favicon). |
| `http.assets_ttl` | `86400` | `max-age` des assets thème/plugin (`/assets/theme|plugin/*`). `0` = revalidation systématique. |
| `http.favicon_ttl` | `3600` | `max-age` de `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (privé) des images via `/api/proxy`. |

Les réponses privées/API ne reçoivent jamais de cache public ; le HTML est `no-cache`.

## Cache applicatif (fichier)

Éditables dans l'admin, section *Performance → Cache serveur*.

| Clé | Défaut | Rôle |
|---|---|---|
| `cache.enabled` | `true` | Active le cache fichier `app/storage/cache` (DNS, détection de domaine, vérification de mise à jour). N'affecte ni l'autoloader (`classmap.php`) ni `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL du cache négatif de détection IMAP/SMTP (s). |
| `cache.update_ttl` | `3600` | TTL du résultat de vérification de mise à jour (s). |
| `cache.gc_hours` | `168` | Intervalle de purge des fichiers de cache expirés (h) ; `0` = jamais. |

## Logs MailSo (protocoles IMAP/SMTP/Sieve)

| Clé | Défaut | Rôle |
|---|---|---|
| `logs.enabled` | `true` | Écrit `storage/logs/mail.log` (secrets masqués). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` pour tracer le protocole. |
| `logs.syslog` | `false` | Doublon vers `syslog()` si le log fichier est inactif. |
| `logs.time_zone` | `''` | Fuseau des horodatages (ex. `Europe/Paris`) ; vide = serveur. |

## Journal d'authentification (type fail2ban)

| Clé | Défaut | Rôle |
|---|---|---|
| `auth_logging` | `false` | Active le journal d'authentification dédié. |
| `auth_logging_filename` | `auth.log` | Fichier dans `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholders : `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

## Labs / compatibilité

| Clé | Défaut | Rôle |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Afficher les erreurs SMTP brutes au client (diagnostic, peut révéler des détails internes). |