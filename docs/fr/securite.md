# Sécurité

## 1. Modèle de sécurité intégré

### "Rester connecté" (jetons persistants)

La case "Rester connecté" émet un cookie `mxwm_remember` (`selector:validator`,
HttpOnly, Secure, SameSite=Lax, durée `remember_days` - 30 jours par défaut). La
table `auth_tokens` ne stocke que le
**hash SHA-256** du validator ; celui-ci est **tourné à chaque usage** (un cookie
volé déjà présenté est détectable et invalidé). La restauration re-déchiffre le mot
de passe IMAP (`CredentialCrypt`) et re-vérifie les identifiants - un échec supprime
le jeton. Chaque jeton enregistre l'**IP à l'émission** (`ip`), la **dernière IP**
connue (`last_ip`, à chaque restauration) et le User-Agent - visibles dans
Paramètres > Sessions pour repérer une session inconnue. Les jetons y sont listés
et révocables, et le jeton courant est révoqué à la déconnexion **manuelle**
(la déconnexion d'inactivité `autologout` le préserve : la session est alors
restaurée silencieusement à la visite suivante).

### CSRF

Tout POST exige un jeton CSRF de session (`X-CSRF-Token`, champ de formulaire ou clé JSON) ;
les API des plugins (`/api/plugin/…`) l'exigent quelle que soit la méthode HTTP.
Une seconde couche, basée sur les en-têtes **`Sec-Fetch-*`**, rejette les requêtes
cross-site sauf navigation GET vers un document - y compris sans activation
utilisateur, car le navigateur retire `Sec-Fetch-User` après chaque redirection
(portails, liens réécrits) (`secfetch_allow` permet d'étendre ces règles).

### En-têtes HTTP

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, HSTS optionnel, et une **CSP** avec
nonce par requête (directive `script-src` inclut `unsafe-eval` requis par Knockout).
Les pièces jointes servies en ligne reçoivent `Content-Security-Policy: sandbox`.

### Chiffrement des credentials

Les mots de passe IMAP (et les secrets de plugins) sont chiffrés **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) avec la clé maîtresse `app/storage/app.key`,
en session et en base. Voir
[base-de-donnees.md](base-de-donnees.md).

### Comptes secondaires OAuth2

Les domaines publics (Gmail, Outlook, Yahoo - voir [domaines.md](domaines.md))
s'ajoutent par **authorization code + PKCE** : l'anti-CSRF `state` et le
verifier sont tenus en session, le `redirect_uri` est reconstruit côté serveur
(hôte filtré comme `force_https`). Seul le *refresh token* est persisté, chiffré
comme un mot de passe ; l'*access token* éphémère n'existe qu'en mémoire le
temps de la connexion (XOAUTH2/OAUTHBEARER). La rotation de refresh token
(Microsoft, Yahoo) est réappliquée en base à chaque échange. L'email de
l'`id_token` sert de contrôle de cohérence ; la preuve d'appartenance reste la
connexion IMAP OAuth elle-même. Le callback (`/api/oauth/callback`) ne renvoie
qu'une mini-page `postMessage`>`window.close()` pour la popup de consentement.

Les identifiants d'application OAuth2 sont **par utilisateur** (table
`oauth_apps`, saisis au premier ajout de compte ou dans les réglages) : le
`secret` est chiffré avec la même clé maîtresse et n'est jamais renvoyé à
l'interface. Une app supprimée ou éditée invalide les comptes qui en dépendent
(le refresh token reste lié au client_id d'origine).

### Rate-limit du login

`login_max_attempts` (5) puis blocage `login_lock_seconds` (300 s). Le compteur est
persisté dans `storage/cache/login_attempts.json` (clé `sha256(IP|login)`, purge des
entrées inactives > 1 h, écriture sous verrou `flock`). Un blocage actif n'est ni
prolongé ni réenregistré par les tentatives rejetées (429). S'applique aussi au login
admin. Un journal d'authentification type fail2ban est disponible (`auth_logging*`,
placeholders `{request:ip}`, `{imap:login}`, `{result}` ; CR/LF neutralisés) : il couvre
les logins webmail et admin (login préfixé `admin:`) ainsi que les échecs de code
TOTP. Intégration complète : [fail2ban.md](fail2ban.md).

### Sanitisation du HTML des messages

Le corps des messages est assaini dans le navigateur (DOMParser inerte + allowlist) :
balises et attributs restreints (pas de `script`/`iframe`/`object`/`on*`…), blocage des
schémas `javascript:`/`vbscript:`/`data:`, neutralisation des styles piégeux
(`position:fixed`, contenu masqué), préfixage des classes. La CSP est la seconde couche.

### Envoi de messages

- **From** : whitelist stricte - l'adresse du compte ou une identité déclarée
  (IDN accepté sous ses deux formes) ; toute autre valeur est refusée (403).
- **Destinataires** : les champs To/Cc/Bcc passent par un parse strict - un
  segment invalide n'est pas silencieusement écarté, l'envoi est refusé (400)
  avec la liste des adresses incomprises.
- **CR/LF** : neutralisés dans le sujet, les en-têtes de threading
  (In-Reply-To, References), les noms de dossiers référencés et les paramètres
  MIME des pièces jointes (`filename=`).
- **Bcc** : l'en-tête est retiré des données envoyées au serveur SMTP mais
  conservé dans la copie "Éléments envoyés" (visible par l'expéditeur
  uniquement).
- **Éditeur** : l'iframe TinyMCE porte une `Content-Security-Policy` bloquant
  tout chargement distant - le contenu d'un message transféré/collé ne peut
  pas exfiltrer d'accusé de lecture à l'ouverture.
- **Pièces jointes temporaires** : tokens scopés à l'utilisateur, nom
  assaini (`basename` + caractères de contrôle supprimés), purge à 24 h.
- **Erreurs SMTP** : le client reçoit un message générique ; les réponses
  brutes du serveur ne sont exposées que si `labs.smtp_show_server_errors`
  est activé (diagnostic, peut révéler des détails internes).

### OpenPGP

Le chiffrement/déchiffrement se fait **entièrement dans le navigateur** (OpenPGP.js). Le
serveur ne stocke que le keyring armored ; les clés privées sont protégées par leur
passphrase et ne sont jamais déchiffrées côté serveur. **Seul le corps du message est
chiffré** : l'envoi de pièces jointes est refusé quand le chiffrement est activé (elles
partiraient en clair).

### Proxy d'images

Les images distantes passent par `/api/proxy` : uniquement `http(s)`, allowlist optionnelle
(wildcards), **anti-SSRF** (refus des IP privées/réservées, revalidé à **chaque**
redirection - 3 maximum), timeout, taille bornée. Le MIME réel du corps est vérifié contre
une **allowlist bitmap** (png, jpeg, gif, webp, avif, bmp, ico) - SVG et tout type actif
sont refusés - et la réponse porte `Content-Security-Policy: sandbox` +
`nosniff`. Les journaux ne conservent que scheme+hôte+chemin (jamais la query, qui peut
contenir des tokens).

### Gestion des IP et proxys

`X-Forwarded-For`/`X-Forwarded-Proto` ne sont pris en compte que si `REMOTE_ADDR` figure
dans `trusted_proxies` (CIDR). La détection HTTPS (`isHttps`) suit la même règle.

## 2. Recommandations de durcissement

- **Document root = `public/`** uniquement ; ne jamais pointer sur la racine du projet.
  Les dossiers privés (`app/`, `config/`, `storage/`…) ont leurs `.htaccess` de protection,
  mais ce n'est qu'un filet de sécurité.
- **HTTPS obligatoire** : activez `force_https` + `hsts` en production.
- **Derrière un reverse proxy** (Nginx, HAProxy, Cloudflare…) : renseignez
  `trusted_proxies` pour fiabiliser rate-limit, allowlist et logs ; sans cela, tout
  `X-Forwarded-*` est ignoré.
- **Restreignez l'admin** : `admin_ip_allowlist` (IP/CIDR CSV) et un chemin `admin_path`
  non trivial ; activez le **TOTP admin** via le plugin `two-factor-auth` (le secret
  `admin_totp_secret` n'est exigé que tant que le plugin est activé).
- **Compte admin local** : préférez `admin_user` + `admin_password_hash` (mot de passe
  fort) plutôt que le fallback IMAP pour l'administration.
- **Sauvegardez `app.key`** hors du webroot ; ne le versionnez jamais.
- **`debug = false`** en production (ne pas exposer les erreurs internes).
- **L'assistant `/setup`** est intégré au cœur : il répond 404 une fois le
  verrou `app/storage/INSTALLED` posé - plus aucun fichier d'installation à
  supprimer dans le webroot.
- Vérifiez `ssl.verify_certificate` : `false` n'est acceptable que pour des certificats
  auto-signés maîtrisés.
- En mode maintenance, `maintenance = true` sert une page maintenance dédiée en 503
  sur `/` (utilisateurs connectés basculés à leur prochaine requête API, session
  conservée) et coupe `/api/*` utilisateur (l'admin et `/api/ping` restent actifs).

## 3. Limites connues

- Le dépôt `GitHubClient::REPO` est verrouillé sur `WatT-For/MXWebMail` : les
  paquets installés (zip cœur de la release, fichiers plugins lus dans le dépôt au
  ref `update.plugins_ref` via l'API git + `raw.githubusercontent.com`, chacun
  vérifié par sha1 de blob git) ne proviennent que de ce dépôt - aucune URL
  arbitraire configurable. Le paquet cœur est vérifié en deux niveaux : somme
  sha256 de l'asset (fichier `<zip>.sha256` de la release) puis `files.sha256`
  (manifeste par fichier embarqué, contrôlé après extraction) ; les constantes
  `APP_REQ_PHP`/`APP_REQ_FROM` du `App.php` du paquet déclarent les prérequis
  (version PHP, version source minimale) appliqués avant copie. Le dépôt officiel
  est **public** : l'API, les assets et les `raw` fonctionnent sans token (le quota
  anonyme de l'API GitHub est couvert par le cache `cache.update_ttl`). Un PAT GitHub
  optionnel (accès `Contents: Read` au dépôt) peut être configuré dans `config.php`
  pour relever le quota ou servir un fork privé via `update.repo` - les assets
  passent alors par l'endpoint `releases/assets/<id>` (`browser_download_url`
  renvoie 404 sur dépôt privé même authentifié). `update.plugins_local`
  et `update.manifest_url` (manifeste de release alternatif) sont réservés au
  dev/tests - modifiables uniquement via `config.php`, jamais via l'API admin.
  Pendant la copie des fichiers, le flag `storage/update/applying` coupe l'API
  utilisateur (503, expire après 15 min).
- Le changement SQLite > MariaDB ne migre pas les données (schéma vierge).
- Les clés privées OpenPGP sont stockées armored côté serveur (la passphrase protège la
  clé elle-même, pas d'encapsulation supplémentaire).