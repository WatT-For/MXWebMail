# Dépannage / FAQ

## Installation

**Toutes les URL redirigent vers `/setup/`**
Normal : le verrou `app/storage/INSTALLED` est absent. Terminez l'installation, ou créez le
fichier manuellement après avoir posé `config.php`.

**L'assistant affiche « config/ non inscriptible »**
Avertissement non bloquant : copiez le contenu affiché dans `app/config/config.php` à la
main, puis relancez l'installation. Vérifiez aussi que `app/storage/` est inscriptible
(bloquant).

**Le test de connexion MariaDB échoue**
Vérifiez hôte/port/identifiants, et que l'utilisateur a les droits de créer la base
(`CREATE DATABASE`). Le charset doit être `utf8mb4`.

## Connexion / login

**Aucun utilisateur ne peut se connecter après l'installation**
Avec `restrict_domains = true` (défaut), il faut **au moins un domaine nommé** en table
(le wildcard `*` est ignoré pour cette vérification). L'installeur tente de le détecter ;
sinon créez-le dans *Domaines*.

**Login refusé sans raison (compte bloqué)**
- Trop de tentatives : attendre `login_lock_seconds` (défaut 300 s), ou purger
  `app/storage/cache/login_attempts.json`.
- Les plugins `black-list`/`white-list` peuvent refuser le login (404/403 ou erreur
  générique selon `auth_error`).
- Un utilisateur `disabled` est rejeté même avec une session valide.

**Le login admin ne fonctionne pas**
- Compte local (`admin_user`) : vérifiez `admin_password_hash` et le TOTP éventuel.
- Fallback IMAP : l'utilisateur doit avoir `is_admin = 1`.
- `admin_ip_allowlist` peut bloquer votre IP.

## Serveurs mail

**Impossible de se connecter au serveur IMAP/SMTP**
Utilisez le bouton **Tester la connexion** dans *Domaines* (résultat par protocole). Causes
fréquentes : port/chiffrement incohérent (993/ssl vs 143/tls), certificat auto-signé (voir
`ssl.verify_certificate`/`ssl.allow_self_signed`), ou pare-feu.

**Certificat auto-signé rejeté**
`ssl.verify_certificate = false` ou `ssl.allow_self_signed = true` (à n'utiliser qu'en
connaissance de cause).

**Le domaine n'est pas détecté automatiquement**
`autodetect.enabled` est désactivé par défaut. Activez-le, ou utilisez le bouton
**Détecter** de la page *Domaines*.

**« Signaler comme spam » ne filtre pas les mails suivants**
Le bouton déplace le message vers le dossier Courrier indésirable (ou pose le
keyword IMAP `$Junk` si le serveur n'a pas de dossier Junk) — il n'apprend rien
au serveur. Les filtres Sieve ne s'appliquent qu'à la livraison : le modèle
*Anti-spam* de l'assistant (Paramètres → Filtres) classe les mails marqués
`X-Spam-Flag: YES` par le MTA. L'apprentissage automatique (spam/ham) exige une
configuration côté serveur — Dovecot IMAPSieve (script report-spam/ham déclenché
par la copie vers/depuis Junk, que le MOVE de l'app déclenche) ou
rspamd/sa-learn.

## Sécurité / HTTPS

**Le site reste en HTTP derrière un reverse proxy**
Renseignez `trusted_proxies` (IP du proxy) pour que `X-Forwarded-Proto` soit honoré, puis
activez `force_https` / `hsts`.

**Le rate-limit ou l'allowlist ne voit pas la vraie IP**
Même cause : sans `trusted_proxies`, `REMOTE_ADDR` est utilisé tel quel.

**Le favicon ne s'affiche pas**
Le favicon uploadé doit être ≤ 512 Ko (ico/png/svg/jpg/webp) ; sinon il reste l'icône par
défaut.

## Données / journaux

**Perte de `app.key`**
Tous les credentials stockés deviennent indéchiffrables : les utilisateurs doivent se
reconnecter. Sauvegardez toujours `app.key`.

**Où sont les journaux ?**
`app/storage/logs/app.log` (applicatif), `mail.log` (protocoles IMAP/SMTP/Sieve, si
`logs.enabled`), et le journal d'auth (`auth_logging`). Visionnables dans l'admin
(*Journaux*).

**Passer à MariaDB a vidé les données**
Comportement attendu : le changement de `db_dsn` repart sur un schéma vierge (les fichiers
contacts par utilisateur sont conservés). Il n'y a pas d'outil de migration de données.

## Mises à jour

**Le bouton de vérification des mises à jour ne fait rien / HTTP 404 sur `releases/latest`**
Le dépôt officiel est verrouillé (`GitHubClient::REPO` = `WatT-For/MXWebMail`) et
**privé** : `PAT GitHub` (PAT avec accès `Contents: Read` au dépôt) est requis
dans `config.php`. GitHub renvoie 404 — pas 403 — quand le token n'a pas accès
au dépôt. `update.repo`/`PAT GitHub` se règlent manuellement dans `config.php`.

**HTTP 404 sur `…/releases/download/…` (catalogue plugins ou téléchargement de mise à jour)**
Sur dépôt privé, le `browser_download_url` des assets renvoie 404 même avec un
token valide. Corrigé à partir de la 0.36.1 : les assets sont téléchargés via
l'endpoint API `releases/assets/<id>` quand `PAT GitHub` est configuré.

## Divers

**Sous Nginx, les dossiers privés sont accessibles ?**
Non, si le `root` pointe sur `public/` et que le bloc `location ~ /\.` + `^/(app|dist)`
est présent. Ne pointez jamais le `root` sur la racine du projet.

**Erreur 403 « cross_site » sur une requête légitime**
La protection `Sec-Fetch-*` a rejeté la requête. Si vous intégrez une API externe
légitime, ajoutez une règle dans `secfetch_allow`.

**Déboguer une erreur interne (500)**
Activez temporairement `debug = true` (jamais en production) pour voir le message, puis
consultez `app.log`.