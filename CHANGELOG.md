# Changelog

Toutes les modifications notables du projet sont listées ici — source unique dont
découlent les notes de release GitHub et le corps des commits de version.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) —
semver `0.MINOR.PATCH` en développement.
Catégories, dans l'ordre : `Sécurité`, `Ajouté`, `Modifié`, `Corrigé`, `Obsolète`, `Supprimé`.

## [Unreleased]

## [0.44.0] — 2026-10-29

### Sécurité

- La liste blanche (`white-list`) pouvait être contournée lorsqu'un plugin de mapping (ex. `ldap-login`) résolvait l'identifiant : les listes de contrôle de connexion sont désormais évaluées sur l'email résolu via le nouveau hook `auth.login.mapped`.
- Validation anti-SSRF mutualisée dans `App::isPublicHost()` (résolution A+AAAA complète, refus dès qu'une IP est privée ou réservée) — le plugin `avatars` ne se limitait auparavant qu'aux adresses IPv4.

### Ajouté

- Hook cœur `auth.login.mapped` (type `run`) : émis après `auth.login.mapping` avec l'email résolu, destiné aux contrôles post-mapping des plugins (black-list, white-list).
- Hooks cœur `message.spam` (à chaque marquage/démarquage spam, avant le déplacement) et `attachment.upload` (après chaque upload de pièce jointe et import de fichier, avant exploitation).
- Plugin `anti-spoofing` : le bandeau de sécurité affiche désormais les verdicts SPF/DKIM/DMARC déjà fournis par le MTA (`Authentication-Results`), avec un indicateur positif quand tout passe.
- Plugin `ics-viewer` : réponse iTIP aux invitations (`METHOD:REQUEST`) — boutons Accepter / Peut-être / Refuser envoyant un `REPLY` calendrier à l'organisateur par SMTP.
- Nouveau plugin `announcement` : bannière d'annonce administrateur (app, optionnellement page de login).
- Nouveau plugin `spam-learn` : apprentissage spam/ham vers `rspamc`/`sa-learn` ou dossier dovecot-antispam.
- Nouveau plugin `attachments-clamav` : scan antiviral ClamAV des pièces jointes à l'upload.
- Nouveau plugin `filelink` : pièces jointes trop volumineuses envoyées via un lien de téléchargement expirable.
- Nouveau plugin `admin-stats` : page d'administration de statistiques (stockage, utilisateurs, tables, orphelins, plugins).
- Nouveau plugin `wkd-keys` : découverte automatique des clés PGP (WKD, Autocrypt, keyserver optionnel).
- Compteur de multi-sélection : lorsque plusieurs messages sont cochés, la zone de lecture affiche « N messages sélectionnés » à la place du contenu.

### Modifié

- Changement de dossier : la zone de lecture est réinitialisée à l'état par défaut (« Sélectionnez un message ») au lieu de conserver le message du dossier précédent.
- Paramètres webmail et panneau admin : les icônes d'aide `?` sont désormais alignées en colonne sur le bord droit des libellés, juste avant le champ (auparavant collées à la fin du texte du libellé — position variable selon sa longueur).

### Corrigé

- Actions groupées en recherche « Tous les dossiers » : marquage lu/suivi, déplacement, suppression, spam et téléchargement `.eml` ciblaient le dossier courant au lieu du dossier réel de chaque message (les UID IMAP étant par dossier, un autre message pouvait être affecté) ; le zip est désormais généré par dossier source.
- Codes de secours 2FA impossibles à saisir à la connexion : le champ TOTP était limité à 6 caractères alors qu'un code de secours fait 9 (`xxxx-xxxx`).
- Suppression/déplacement depuis le lecteur : la sélection du message suivant et le badge non lus pouvaient viser la mauvaise ligne en recherche multi-dossiers (correspondance par UID seul).
- Clic sur un brouillon dont la récupération échoue : erreur désormais affichée au lieu d'un silence.
- Suggestions de destinataires : une requête par frappe remplacée par une requête après 250 ms de pause.
- Les menus déroulants du compte (barre supérieure) et d'un dossier sont désormais atteignables au clavier.
- Plugins `ldap-login` et `ldap-contacts` : le filtre LDAP réellement appliqué tant que la configuration n'était pas enregistrée différait du défaut affiché dans l'administration — les fallbacks du code sont alignés sur les défauts du schéma.
- Plugin `avatars` : les images mises en cache localement n'expiraient jamais — ajout d'un TTL de 30 jours (rafraîchissement automatique et borne sur `storage/avatars`).
- Plugin `ics-viewer` : les dates de l'aperçu étaient figées au format français pour toutes les langues — elles sont désormais formatées selon la langue de l'interface.

## [0.43.0] — 2026-10-28

### Ajouté

- Rotation automatique des journaux au-delà de `logs.max_size` Mio (`app.log`, `mail.log`, journal d'authentification ; `logs.keep` rotations conservées).
- Enregistrement automatique du brouillon pendant la composition (réglage `compose_autosave`, secondes ; indicateur de dernier enregistrement dans la modale).
- Aperçu des pièces jointes (icône loupe dans l'onglet Pièces jointes) : images bitmap, PDF (iframe — sandbox CSP levée pour ce type seul) et texte brut.
- Plugin two-factor-auth : codes de secours à usage unique (8 codes `xxxx-xxxx` générés à l'activation, régénérables dans l'onglet, hashés en base — saisissables dans le champ TOTP à la connexion).
- Import de contacts au format CSV (Paramètres → Contacts) en plus du vCard : en-tête reconnue ou heuristique de colonnes, séparateur `,` `;` ou tabulation.
- Import de boîtes au format mbox (Paramètres → Dossiers → Importer) en plus du `.eml` : dates `From ` préservées en INTERNALDATE, plafond `import_max_size` (100 Mo, 2000 messages).
- Groupes de contacts : champ « Groupes » (catégories vCard `CATEGORIES` en import/export), badges dans la liste, recherche étendue aux catégories.
- Recherche multi-dossiers : portée « Tous les dossiers » dans la recherche avancée (résultats fusionnés, badge du dossier sur chaque ligne).
- Accusés de réception (RFC 3798) : demande dans les options de composition, bandeau « Envoyer/Ignorer » à la lecture (réglage `mdn_mode`), flag `$MDNSent` posé après envoi.
- Vérification S/MIME : badge cliquable sur les messages signés — signature cryptographique vérifiée via `openssl_pkcs7_verify` (confiance CA si `smime_ca_path` configuré), signataire affiché.
- « Rester connecté » à la connexion (jetons selector:validator persistants 30 j, rotation à l'usage) + onglet « Sessions » dans les Paramètres (appareils, révocation).
- Admin → Logs : bouton « Vider » pour tronquer le fichier de log sélectionné (app/mail/auth), avec confirmation.
- « Tout marquer comme lu » dans le menu d'un dossier (visible s'il reste des non lus).
- Bouton « Archiver » (barre d'actions groupées + lecteur de message) : déplacement direct vers le dossier de rôle archive quand il existe.
- Compteur de non lus de la boîte de réception dans le titre de l'onglet (« (3) MXWebMail »).
- Réglage « Après suppression ou déplacement » (Paramètres → Général) : ouvrir le message suivant / précédent ou fermer le lecteur.
- Téléchargement groupé des messages cochés en `.eml` dans un zip (bouton de la barre d'actions groupées, 100 messages maximum).
- Rappel « pièce jointe oubliée » avant envoi quand le corps ou l'objet annonce une pièce jointe absente (citations de réponse ignorées, désactivé si chiffrement OpenPGP).
- Option « Priorité haute » dans le menu Options de la composition (en-tête `X-Priority: 1 (Highest)`).
- Mise à jour robuste : vérification d'intégrité par fichier (`files.sha256` embarqué dans le paquet), contrôle des prérequis (constantes `APP_REQ_PHP`/`APP_REQ_FROM` de `App.php` : version PHP, version source minimale), et détection des fichiers modifiés localement signalée avant application.
- Sauvegarde de la base avant chaque mise à jour : `webmail.sqlite` et les bases contacts sont copiées après checkpoint WAL dans `backup-<ts>/db/` (sous MariaDB, un avertissement rappelle de faire un `mysqldump` — la sauvegarde externe reste requise).
- Finalisation des migrations de base après application d'une mise à jour (bouton/étape « Finaliser » + commande de secours `php app/bin/post-update.php`) ; les bases contacts des utilisateurs sont migrées dans la foulée.
- Rollback assisté depuis « À propos » : restauration des fichiers du dernier backup, suppression des fichiers créés par l'update et recopie des bases SQLite (sous MariaDB, restaurer le dump d'abord puis confirmer).
- Verrou applicatif pendant la copie d'une mise à jour : l'API utilisateur répond 503 et le web sert la page maintenance jusqu'à la fin (expiration automatique après 15 min en cas d'échec).
- Bandeau « schéma de la base plus récent que le code » dans « À propos » quand un rollback fichiers n'a pas été suivi d'une restauration de la base.

### Modifié

- Barre d'actions du lecteur de message allégée : les actions secondaires (transférer en pièce jointe, se désabonner, déplacer, archiver, marquer non lu, imprimer, télécharger .eml, voir le source) sont regroupées dans un menu « … ».
- Le zip de release n'embarque plus les fichiers méta du dépôt (`.github`, `docs`, `README.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`) — `LICENSE` reste distribué.
- Paramètres → Filtres : sous-onglets « Filtres » (scripts Sieve) et « Répondeur d'absence », titres internes redondants supprimés ; le champ « Adresses » du répondeur devient une liste à cocher des adresses du compte.

### Corrigé

- `mail.log` : un refus protocolaire du serveur (mot de passe IMAP/SMTP/Sieve erroné, quota absent…) n'écrit plus la pile d'appel complète — une seule ligne `WARNING` avec le message du serveur.
- Admin → Logs : sélections rapides ou « Vider » pendant un chargement ne laissent plus une réponse tardive écraser l'affichage (contenu obsolète ressuscité) ; les erreurs de chargement sont désormais signalées.

## [0.42.0] — 2026-10-27

### Ajouté

- Bouton « Se désabonner » dans le lecteur de message (List-Unsubscribe, one-click RFC 8058, ouverture https/mailto).
- Import de fichiers `.eml` dans un dossier depuis Paramètres → Dossiers → « Importer des .eml » (IMAP APPEND).
- « Transférer en pièce jointe » : le message original est joint en `.eml` (message/rfc822) depuis le menu du bouton Transférer.
- Bouton « Tout télécharger (.zip) » sur l'onglet Pièces jointes d'un message.
- Réglages « Vider la corbeille / le spam à la déconnexion » (Paramètres → Général, avec défauts administrables).
- Répondeur d'absence Sieve (vacation) : carte dédiée dans Paramètres → Filtres (activation, dates, intervalle, sujet, message, adresses) compilée dans le script géré actif.
- Vue conversations : regroupement IMAP THREAD dans la liste des messages (réglage « Vue conversations », expansion des fils en sous-lignes, badge du nombre de messages).
- Réponses types : nouvel onglet Paramètres (CRUD avec éditeur TinyMCE) et insertion dans le compose via le sous-menu « Réponses types » du menu Options.

## [0.41.0] — 2026-10-27

### Ajouté

- Lien vers le dépôt GitHub dans le panel d'administration (navbar + onglet
  « À propos »)
- Documentation de la sémantique des `exceptions` des plugins listes
  noire/blanche (`docs/plugins.md`)

## [0.40.0] — 2026-10-27

### Ajouté

- Documentation d'intégration fail2ban (`docs/fail2ban.md`) : filtre `filter.d`,
  jail, rotation logrotate, pièges fuseau horaire et `trusted_proxies`

### Corrigé

- Le journal d'authentification (`auth_logging*`) couvre désormais le login
  admin — succès et échecs, login préfixé `admin:` — et les mauvais codes TOTP
  (`AUTH FAILED (totp)`) : ces tentatives sont visibles par fail2ban

## [0.39.1] — 2026-10-27

### Corrigé

- Erreur 500 sur l'affichage quand un thème sans `version` était sélectionné
- Hôte de base de données validé avant construction du DSN MariaDB
- Noms de pièces jointes contenant des caractères de contrôle neutralisés dans
  l'en-tête de téléchargement
- Guillemets correctement échappés dans la valeur compilée du filtre Sieve
  « commence par »
- Cache négatif de détection de domaine écrit sous verrou
- Emails invalides ignorés à l'enregistrement d'un contact

## [0.39.0] — 2026-10-27

### Modifié

- Catalogue et installation des plugins lus directement dans le dépôt GitHub au
  ref `update.plugins_ref` (`release` = tag de la dernière release par défaut,
  `main` en dev — un push de `plugin.json` publie la mise à jour sans release) ;
  chaque fichier téléchargé via `raw.githubusercontent.com` est vérifié par
  sha1 de blob git (arbre `git/trees` du commit)
- Nouvelle clé `update.plugins_local` (dev/tests) : dossier local de plugins —
  catalogue et installation hors réseau depuis le disque

### Supprimé

- Assets `plugin-<name>-<ver>.zip` (+ `.sha256`) et `plugins.index.json` des
  releases GitHub — le packaging ne produit plus que le zip cœur

## [0.38.0] — 2026-10-27

### Ajouté

- Import/export vCard des contacts (Paramètres → Contacts) : fichiers `.vcf`
  multi-fiches, fusion sur adresse existante, types d'emails préservés
- Plugin `webmail-migration` : migration depuis un dossier `data` RainLoop ou
  SnappyMail (chemin serveur ou upload ZIP) — domaines, carnets d'adresses, identités,
  réglages, dossiers système et clés PGP ; analyse préalable puis import
  idempotent, comptes inconnus ignorés et listés
- Pages de niveau supérieur déclarables par plugin dans l'admin
  (`admin.mounts`, `section: 'page'`)

### Modifié

- `contacts.syncFrom` accepte les emails typés `{email, type}`
  (types CardDAV désormais préservés)
- Notifications transitoires (erreurs, confirmations de succès) affichées en
  toasts dans le webmail et l'admin au lieu d'alertes inline ou de modales ;
  les bandeaux d'état persistants (maintenance, sécurité du message,
  indisponibilités) restent affichés inline
- Les erreurs survenant dans les modales (composition, édition de domaine
  ou d'utilisateur, configuration de plugin/thème, éditeur de filtres)
  remontent également en toast

### Corrigé

- Erreurs de chargement des dossiers IMAP désormais affichées — aucun
  élément ne les présentait, elles étaient silencieuses

## [0.37.4] — 2026-10-26

### Ajouté

- Catalogue des plugins intégré au tableau de l'onglet Extensions (entrées non
  installées en fin de liste, rafraîchissement en haut à droite) — anciennes
  sous-sections dédiées retirées
- Signatures et identités dans la composition

### Modifié

- Déconnexion après inactivité désarmable et ré-armée à la sauvegarde des réglages
- Nombre de messages par page appliqué immédiatement

### Corrigé

- Dialogues TinyMCE (lien, image, code source) non saisissables dans les modales
  Bootstrap
- Ligne vide et `nbsp` parasite dans les signatures ; identités du compose
  resynchronisées après édition
- Menu contextuel des dossiers masqué derrière le fond du dossier actif

## [0.36.1] — 2026-10-24

### Corrigé

- 404 sur les assets de release quand le dépôt est privé

## [0.36.0] — 2026-10-24

### Ajouté

- Catalogue des plugins via GitHub Releases : les plugins ne sont plus livrés dans
  le zip cœur — chacun est un asset `plugin-<name>-<ver>.zip` installable, mettable
  à jour et désinstallable depuis l'admin → Extensions (sha256 vérifié, backup à la
  mise à jour, compatibilité `min_app`/`max_app`)
- Compte IMAP par défaut configurable et synchronisation des identifiants de session
- Couleurs de thème extensibles via `config.schema.json` (variantes sombres incluses)

## [0.32.0] — 2026-10-22

### Ajouté

- Compression gzip configurable des réponses (niveau, taille minimale) — fonctionne
  même sans `mod_deflate`
- ETag/Last-Modified et réponses 304 sur les assets thème/plugin et le favicon ;
  TTL `Cache-Control` configurables
- Assets versionnés `/assets/vX.Y.Z/` : cache navigateur d'un an, invalidé
  automatiquement à chaque release
- Cache applicatif fichier (`app/storage/cache`) : DNS, détection de domaine,
  vérification de mise à jour, avec GC
- En-têtes de cache Apache et `mod_deflate` préconfigurés dans `public/.htaccess`
- Onglets « HTTP » et « Cache serveur » dans l'administration

### Modifié

- Licence du projet : GNU AGPL-3.0 (les bibliothèques vendorées conservent leurs
  licences d'origine)

## [0.31.0] — 2026-10-22

Première release publiée de MXWebMail — webmail auto-hébergé en PHP 8.4, installable via l'assistant `/setup/`.

### Ajouté

- Messagerie complète : dossiers IMAP, recherche avancée, tri côté serveur, suivi, quota, ACL de dossiers
- Composition : éditeur HTML (TinyMCE) ou texte, identités multiples, signatures, pièces jointes, comptes IMAP secondaires
- Chiffrement OpenPGP entièrement côté navigateur — les clés privées ne quittent jamais le poste
- Carnet de contacts avec autocomplétion, synchronisation CardDAV et annuaire LDAP
- Filtres Sieve : assistant visuel de règles (modèles dont anti-spam) ou édition de scripts
- Panneau d'administration : domaines (IMAP/SMTP/Sieve), utilisateurs, extensions, thèmes, logs, mises à jour, sécurité
- 15 plugins : 2FA (TOTP), anti-spoofing, avatars, listes noire/blanche, changement de mot de passe, PWA, étiquettes de couleur…
- Multilingue (10 langues) et thèmes extensibles
- Mise à jour en un clic depuis l'onglet « À propos » (GitHub Releases, vérification sha256, backup et rollback automatiques)

### Sécurité

- CSRF sur tous les POST, en-têtes CSP avec nonce, rate-limit du login
- Credentials IMAP chiffrés (XSalsa20-Poly1305, libsodium) en session et en base
- Corps des messages sanitisés ; images externes via proxy anti-SSRF

[Unreleased]: https://github.com/WatT-For/MXWebMail/compare/v0.44.0...HEAD
[0.44.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.44.0
[0.43.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.43.0
[0.42.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.42.0
[0.41.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.41.0
[0.40.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.40.0
[0.39.1]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.39.1
[0.39.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.39.0
[0.38.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.38.0
[0.37.4]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.37.4
[0.36.1]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.36.1
[0.36.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.36.0
[0.32.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.32.0
[0.31.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.31.0
