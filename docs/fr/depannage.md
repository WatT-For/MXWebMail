# Dépannage / FAQ

## Installation

**Toutes les URL redirigent vers `/setup/`**
Normal : le verrou `app/storage/INSTALLED` est absent. Terminez l'installation, ou créez le
fichier manuellement après avoir posé `config.php`.

**L'assistant affiche "config/ non inscriptible"**
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

**"Signaler comme spam" ne filtre pas les mails suivants**
Le bouton déplace le message vers le dossier Courrier indésirable (ou pose le
keyword IMAP `$Junk` si le serveur n'a pas de dossier Junk) - il n'apprend rien
au serveur. Les filtres Sieve ne s'appliquent qu'à la livraison : le modèle
*Anti-spam* de l'assistant (Paramètres > Filtres) classe les mails marqués
`X-Spam-Flag: YES` par le MTA. L'apprentissage automatique (spam/ham) exige une
configuration côté serveur - Dovecot IMAPSieve (script report-spam/ham déclenché
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
(*Journaux*). Intégration fail2ban : [fail2ban.md](fail2ban.md).

**Passer à MariaDB a vidé les données**
Comportement attendu : le changement de `db_dsn` repart sur un schéma vierge (les fichiers
contacts par utilisateur sont conservés). Il n'y a pas d'outil de migration de données.

## Mises à jour

**"Échec : Introuvable sur GitHub … dépôt privé ou ressource absente"**
Le dépôt officiel est verrouillé (`GitHubClient::REPO` = `WatT-For/MXWebMail`)
et **public** : aucun token n'est requis. GitHub renvoie 404 - pas 403 - pour une
ressource absente ou un accès insuffisant ; le message indique le endpoint fautif
(`releases/latest`, `commits/…` ou un chemin `raw` du catalogue plugins). Un 404
persistant signifie en général une ressource absente (aucune release publiée,
`update.plugins_ref` inconnu). Le cas "dépôt privé" ne concerne qu'un fork
configuré via `update.repo` + PAT dans `config.php`.

**HTTP 404 sur `…/releases/download/…` (catalogue plugins ou téléchargement de mise à jour)**
Sur le dépôt officiel public, `browser_download_url` fonctionne. Le cas n'a de
sens que pour un fork privé via `update.repo` : `browser_download_url` y renvoie
404 même avec un token valide - corrigé à partir de la 0.36.1, les assets sont
alors téléchargés via l'endpoint API `releases/assets/<id>` quand un PAT est
configuré dans `config.php`.

**Le site répond 503 "update" / page maintenance pendant une mise à jour**
Normal : le flag `app/storage/update/applying` coupe l'API utilisateur pendant la
copie des fichiers (jamais de mix ancien/nouveau code). Il est retiré à la fin ;
s'il reste après un plantage, il expire tout seul au bout de 15 min - ou
supprimez `app/storage/update/applying` manuellement.

**"Migrations en attente" après une mise à jour**
La finalisation (`/api/admin/update/finalize`) n'a pas tourné : bouton
*Finaliser* dans *À propos*, ou en CLI `php app/bin/post-update.php`. Sans
action, les migrations s'appliquent quand même à la prochaine requête
(migration paresseuse), mais il est préférable de les contrôler.

**Revenir en arrière après une mise à jour (rollback)**
Bouton *Restaurer* dans *À propos* : restaure les fichiers de
`app/storage/update/backup-<ts>` (les 3 derniers backups sont conservés),
supprime les fichiers créés et recopie les bases SQLite sauvegardées.
Sous **MariaDB**, les migrations ne sont pas réversibles : restaurez d'abord la
base depuis votre `mysqldump`, puis relancez le rollback (la confirmation forcée
est proposée). S'il faut repartir de zéro : réinstaller le paquet d'origine et
restaurer `app/storage/` (dont `app.key` et `db/`).

**Bandeau "schéma de la base plus récent que le code"**
`meta.schema_ahead` : le code installé est plus ancien que le schéma (rollback
fichiers sans restauration de la base, ou paquet plus ancien déployé). Restaurez
la base ou réappliquez la version correspondante ; les migrations restent
suspendues tant que l'écart existe.

## Divers

**"Renvoyer tel quel" (bounce) : le destinataire voit-il le mail comme spam ?**
Possible : la réexpédition casse SPF/DMARC côté final (le serveur d'envoi ne
correspond plus à l'expéditeur d'origine) - comportement normal des bounce,
les en-têtes `Resent-*` documentent la retransmission. Pour garder
l'authentification, préférer le transfert classique.

**Sous Nginx, les dossiers privés sont accessibles ?**
Non, si le `root` pointe sur `public/` et que le bloc `location ~ /\.` + `^/(app|dist)`
est présent. Ne pointez jamais le `root` sur la racine du projet.

**Erreur 403 "cross_site" sur une requête légitime**
La protection `Sec-Fetch-*` a rejeté la requête. Si vous intégrez une API externe
légitime, ajoutez une règle dans `secfetch_allow`.

**Déboguer une erreur interne (500)**
Activez temporairement `debug = true` (jamais en production) pour voir le message, puis
consultez `app.log`.