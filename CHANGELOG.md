# Changelog

Toutes les modifications notables du projet sont listées ici — source unique dont
découlent les notes de release GitHub et le corps des commits de version.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) —
semver `0.MINOR.PATCH` en développement.
Catégories, dans l'ordre : `Sécurité`, `Ajouté`, `Modifié`, `Corrigé`, `Obsolète`, `Supprimé`.

## [0.49.0] — 2026-11-01

### Ajouté

- Réglages → Comptes : l'ordre des comptes secondaires est désormais persistant et modifiable par glisser-déposer — un nouveau compte s'ajoute en fin de liste (auparavant le tri était alphabétique).
- Aperçu des pièces jointes `.eml` (message transféré, message/rfc822) : le bouton loupe affiche dans la modale les en-têtes (expéditeur, destinataires, date), le corps sanitisé comme un message normal (images `cid:` et distantes selon le réglage d'affichage) et les pièces jointes du message joint, téléchargeables individuellement — y compris les `.eml` imbriqués en cascade.
- Plugin `link-confirmation` : modale de confirmation avant l'ouverture d'un lien contenu dans un mail (anti-hameçonnage) — destination réelle affichée, signalement des liens suspects (texte différent de l'URL cible, IP littérale, punycode, identifiants dans l'URL), domaines de confiance et option « ne plus demander pour ce domaine » (réglages dans Extensions → ⚙).
- Réglages → Dossiers : nouvelle colonne « Taille » affichant la taille totale des messages de chaque dossier — nécessite l'extension IMAP `STATUS=SIZE` (RFC 8438), la colonne est masquée si le serveur ne l'annonce pas.
- Écran de chargement plein page affiché jusqu'à l'initialisation complète de l'interface (fin du premier chargement de la liste de messages) — supprime le flash de mise en page brute au rechargement ou au changement de compte, avec fondu de sortie et durée d'affichage minimale.
- Indicateur de chargement animé centré sur la colonne de la liste des messages (voile estompé + spinner) pendant les rechargements déclenchés par l'utilisateur — le rafraîchissement automatique en arrière-plan reste signalé par la seule icône.

### Modifié

- Onglet « Pièces jointes » du lecteur de message : les fichiers sont présentés en tableau (nom, taille, actions d'aperçu et de téléchargement) au lieu d'une suite de boutons.
- Le sélecteur de comptes de la barre supérieure devient un bouton visible (auparavant un texte semi-transparent peu lisible).
- Changement de compte : l'interface revient désormais systématiquement sur la boîte de réception, quel que soit le dossier consulté (auparavant le dossier courant était reporté via le hash et conservé si un dossier du même nom existait sur le compte cible).
- Robustesse : le cache disque des pièces jointes est purgé automatiquement (7 jours) et l'aperçu d'une pièce jointe `.eml` borne la lecture à 25 Mo avant mise en mémoire (évite une croissance disque non maîtrisée et un pic mémoire sur les messages volumineux).
- Performances : le cache fichier (`cache.enabled`) est désormais activé par défaut — il cache les listes de messages IMAP (indexées sur l'ETag du dossier), les résolutions DNS, la détection de domaine et la vérification de mise à jour, et borne le dossier `storage/cache` via un GC périodique.
- Performances : les assets servis par PHP (CSS de thème, assets de plugins, favicon) sont désormais mis en cache navigateur — `http.etag`, `http.assets_ttl` (24 h) et `http.favicon_ttl` (1 h) activés par défaut, avec `?v=` basé sur le mtime du fichier servi (bust garanti à chaque édition).
- Actions des dossiers : le menu `⋯` en face de chaque dossier est supprimé — « Renommer », « ACL » et « Supprimer » sont déplacés dans Réglages → Dossiers, dont l'onglet « Dossiers surveillés » devient « Dossiers » (premier sous-onglet) avec un tableau regroupant surveillance et gestion ; « Vider » et « Tout marquer comme lu » deviennent des boutons de l'en-tête de la colonne agissant sur le dossier affiché.
- Performances : « Signaler comme spam » / « Non-spam » retire désormais le message immédiatement de la liste (action en arrière-plan, resynchronisation en cas d'échec), et la résolution des dossiers spéciaux (indésirables, corbeille, envoyés…) est mise en cache de session au lieu d'un listage complet des dossiers IMAP à chaque action — suppression, déplacement et vidage à la déconnexion en bénéficient aussi.
- Performances : la suppression et le déplacement d'une sélection multiple n'envoient plus qu'un seul appel API groupé (une commande IMAP `UID MOVE`/`EXPUNGE` par dossier) au lieu d'une requête par message — la sélection disparaît instantanément au lieu de s'effacer ligne par ligne.
- Ajout d'un compte secondaire : email et mot de passe sont réunis en une seule modale et les erreurs s'affichent en place avec la saisie conservée (auparavant deux boîtes de dialogue successives puis une alerte séparée).
- Réglages → Dossiers : la colonne « Surveillance » est resserrée sur son en-tête avec les cases à cocher centrées, et les lignes du tableau ont une hauteur uniforme avec ou sans boutons d'action.
- Réglages → Étiquettes (plugin color-tags) : la palette et les règles automatiques sont réparties sur deux sous-onglets, avec deep-link `#/@settings/color-tags/rules`.
- Lecteur de message : le verdict anti-spoofing s'affiche désormais sous forme d'icône bouclier colorée en bas à droite de l'en-tête, sous les boutons d'action (couleur selon le niveau, détail au survol) — le bandeau d'alerte et la ligne verte sous l'en-tête sont supprimés.

### Corrigé

- La liste des messages est rafraîchie quand le champ de recherche est vidé via l'icône « × » native du navigateur — auparavant les résultats de la recherche restaient affichés.
- Proxy d'images : erreur 500 systématique (`MimeType::fromString` appelé avec une expression alors que son premier paramètre était passé par référence) — les images distantes des mails ne s'affichaient plus.
- Plugin anti-spoofing : le bandeau « Attention — usurpation possible » s'affichait sur quasiment tous les mails car le succès SPF/DKIM/DMARC y était compté comme une alerte — il devient un indicateur vert discret, le bandeau n'apparaît plus qu'en cas de vraie alerte.
- Traductions de/es/it/nl/pl/pt/ru/tr complétées : plusieurs fonctionnalités récentes restaient en anglais (répondeur d'absence, réponses types, import .eml, désabonnement, réglages HTTP/cache admin, plugins announcement, filelink, spam-learn, wkd-keys…).
- Aperçu d'une pièce jointe .ics : les libellés affichaient les clés de traduction brutes (ex. `plugin.ics-viewer.start`) dans toutes les langues.
- Aide du réglage `admin_path` en français : le placeholder `{path}` s'affichait littéralement.
- Aperçu, téléchargement et export ZIP des pièces jointes : erreur 500 systématique — régression du FETCH `BODYSTRUCTURE` dédié (UID passé en `int` sous `strict_types`, dossier IMAP non sélectionné, champ `INTERNALDATE` exigé par la reconstruction du message non demandé).
- Export ZIP « Tout télécharger » des pièces jointes : erreur 500 — variable `$mail` non initialisée dans la boucle de lecture (le fichier téléchargé contenait la réponse d'erreur au lieu des PJ).
- Export ZIP « Tout télécharger » : le fichier téléchargé était un gzip contenant le zip (double compression) — un en-tête Content-Encoding vide empêchait le navigateur de décoder la compression serveur.
- Plugin avatars : les refus de favicon pour domaine inexistant (NXDOMAIN — cas banal des expéditeurs sans site web) remplissaient le journal en WARNING ; ils passent en INFO, seuls les refus vers IP privée/réservée restent en WARNING, et les avatars introuvables sont mémorisés 7 jours (cache négatif) au lieu de retenter DNS + HTTP à chaque affichage.
- La sélection du dossier courant reste synchronisée avec la liste des mails affichée (rechargement F5, navigation précédent/suivant, lien direct par hash).
- « Vider » sur le dossier affiché ne rafraîchissait pas la liste : les messages supprimés restaient visibles, la pagination gardait l'ancien total et le lecteur pouvait encore afficher un message inexistant — la liste est rechargée et le lecteur fermé si son message venait du dossier vidé.
- Ajout d'un compte secondaire : message d'erreur explicite (« Identifiants invalides », « Serveur IMAP injoignable ») au lieu d'un « Erreur interne » générique — y compris pour l'ajout OAuth et les champs obligatoires.
- Vue « Conversations » : l'item du menu de tri était inerte (mauvais contexte de binding Knockout) — le clic bascule désormais le mode et la coche reflète l'état actif.
- Vue « Conversations » activée dans les réglages : la liste des messages n'était pas rechargée après la sauvegarde — le basculement est désormais appliqué immédiatement.
- Vue « Conversations » : une note indique désormais dans les réglages quand le serveur IMAP ne supporte pas l'extension `THREAD` — auparavant le réglage restait activable sans aucun effet ni explication ; les sous-messages d'un fil déplié sont aussi retirés de la liste avec leur racine.

### Supprimé

- Le bouton « Basculer sur ce compte » est retiré de Réglages → Comptes — la bascule reste dans le sélecteur de comptes de la barre supérieure.

## [0.47.0] — 2026-10-30

### Sécurité

- Le verrouillage anti-force-brute du login pouvait être contourné en variant la casse ou les espaces de l'email saisi : la clé de comptage est désormais normalisée (minuscules, sans espaces) au point unique de hachage.
- La connexion au panneau admin via le compte local (`admin_user`/`admin_password_hash`) régénère désormais l'identifiant de session à l'élévation de privilèges (fixation de session possible auparavant).
- Le compteur anti-force-brute était multiplié par les variantes de saisie du même compte (`user` vs `user@domaine` quand `default_domain` est défini) : le suffixe de domaine est désormais reproduit dans la clé de hachage.

### Ajouté

- Paramètres → Sessions : nouvelle colonne « IP » et bouton d'information ouvrant une modale de détails par session — appareil (navigateur/OS), User-Agent complet, IP de création et dernière IP connue, dates de création / dernière utilisation / expiration, révocation.
- Panneau admin, onglet « À propos » : carte « Utilisation du stockage » — taille occupée par chaque sous-dossier de `storage/` (logs, cache, base…).
- Panneau admin, onglet « Utilisateurs » : ligne de synthèse en en-tête — nombre total de comptes, actifs sur les 30 derniers jours, désactivés.
- Nouveau plugin `seasonal-decorations` : paysage monochrome discret affiché selon la date (Nouvel An, Pâques, 1er avril, Halloween, Noël) — silhouettes SVG détaillées appliquées en couches `mask-image` dans le bandeau de l'app et en haut des pages de connexion/maintenance, bibliothèque d'objets recomposable via `scenes.json`, couleur contrastée avec le fond automatique, sans animation ; événements et fenêtres réglables.

### Modifié

- L'assistant d'installation est intégré au cœur (`SetupController`) : `/setup` répond 404 une fois le verrou posé — la page « déjà installé » qui exposait le chemin du panneau admin disparaît, et il n'y a plus de fichier `public/setup/` à supprimer ni à redéployer par les mises à jour.
- Panneau admin, onglet Extensions : la description s'affiche sous le nom du plugin (police réduite) et la colonne « Utilisable » devient « Accès utilisateurs ».
- Panneau admin, onglet Extensions : l'accès utilisateurs d'un plugin est désormais refusé par défaut (à autoriser explicitement) et le switch disparaît pour les plugins purement admin (hooks `admin.*` uniquement — admin-stats, webmail-migration). Les valeurs déjà en base sont conservées.
- Performance : une seule connexion IMAP par requête (auparavant jusqu'à 3-4, ×2 par image inline dans un message), listes de messages et threads mis en cache sur disque (listes d'UIDs indexées sur l'état du dossier — SORT/SEARCH/THREAD serveur évités tant qu'il n'a pas changé), pièces jointes mises en cache disque avec `Cache-Control: immutable` (plus de re-téléchargement à la réouverture d'un message), manifestes des plugins et dictionnaires de langue en cache, requêtes SQL et fichiers de configuration des thèmes mémoïsées par requête.
- Performance : les requêtes API en lecture seule libèrent le verrou de session PHP au lieu de le garder pendant les appels IMAP — le rafraîchissement automatique, les images inline et les actions ne se bloquent plus mutuellement.
- Performance : `openpgp.js` (~540 K) et `tinymce` (~500 K) ne sont plus chargés au démarrage mais à la première utilisation ; les dossiers surveillés sont désormais inclus dans `/api/appdata` et la liste des identités est mutualisée (jusqu'à 2 allers-retours en moins au boot).
- Proxy d'images externes : mise en cache disque des images téléchargées (TTL `http.proxy_ttl`, défaut 24 h).
- Plugin imap-contacts-suggestions : les suggestions sont mises en cache 5 minutes (auparavant une connexion IMAP + recherche à chaque frappe).
- SQLite : `PRAGMA busy_timeout=5000` ajouté aux connexions (tolérance aux écritures concurrentes).
- Les caches configurables sont désormais désactivés par défaut : cache fichier `cache.enabled` (DNS, détection de domaine, vérification de mise à jour, listes de messages) et cache navigateur `http.etag` avec TTL `assets_ttl`/`favicon_ttl`/`proxy_ttl` à 0 (le cache disque du proxy d'images est coupé par la même clé) — réactivables dans admin → Performance.

### Corrigé

- Compatibilité MariaDB/MySQL : la colonne `key` (mot réservé) des tables `meta` et `user_settings` est désormais citée partout — l'installation échouait dès les migrations sur ces drivers.
- Le domaine stocké en session au login est désormais celui résolu après le hook `auth.login.mapping` (auparavant la forme pré-mapping, divergente pour les plugins de mapping).
- Une erreur de lecture IMAP en téléchargement de pièce jointe ou de source .eml ne produit plus un fichier téléchargé contenant la page d'erreur (en-têtes d'attachment purgés avant la 404).
- `/api/messages/{uid}` renvoie désormais `isSeen:true` quand le flag `\Seen` vient d'être posé (la réponse annonçait toujours « non lu »).
- Un nom de dossier passé brut (non encodé base64url, ex. `INBOX`) dans les routes `/api/folders/{name}/…` n'est plus décodé en binaire : vérification round-trip stricte avec repli sur le nom brut.
- Un filtre Sieve utilisant l'opérateur « regex » ne pouvait pas être enregistré sur les serveurs stricts (Dovecot) : la directive `require ["regex"]` manquait dans le script généré.
- Une session laissée inactive plus de 24 minutes pouvait être détruite par le ramasse-miettes PHP (gc_maxlifetime par défaut à 1440 s quand `session_lifetime = 0`) : plancher de 8 h désormais appliqué.
- Les réponses 404 des téléchargements de pièces jointes et de sources .eml ne perdaient plus les en-têtes de sécurité globaux (purge limitée aux en-têtes posés localement).
- L'import mbox au-delà de 2000 messages comptait un seul échec : les messages non importés sont désormais comptés et rapportés (`skipped`).
- Les plages CIDR IPv4 supérieures à /32 saisies dans les listes d'IP admin (`admin_ip_allowlist`, `trusted_proxies`) étaient acceptées mais jamais appliquées : elles sont désormais rejetées en validation.
- Une pièce jointe sans nom ne produit plus une entrée au nom vide dans le zip « télécharger toutes les pièces jointes ».
- Paramètres → Étiquettes (plugin color-tags) : le sélecteur de champ des règles automatiques affichait des clés de traduction brutes au lieu des libellés localisés.
- L'aperçu calendrier (ics-viewer) ne s'affichait plus pour les pièces jointes `*.ics` servies avec un type MIME générique (`application/octet-stream`, cas Outlook) : le test d'extension était un regex sur-échappé qui ne pouvait jamais correspondre.
- Le téléchargement « .eml en zip » d'un dossier devenu invalide produisait une archive vide ou tronquée au lieu d'une erreur propre ; les messages ou pièces jointes illisibles pendant le téléchargement zip sont désormais listés dans une entrée `ERREURS.txt` de l'archive (au lieu de manquer en silence).
- Le zip « toutes les pièces jointes » pouvait contenir deux entrées au même nom quand une pièce portait déjà un nom du type « a (2).txt » : la déduplication tient désormais compte des noms qu'elle génère.
- Corrections internes : variable non initialisée dans l'archiveur TAR (code mort, warning PHP), champ « version made by » non conforme dans l'en-tête des zips générés, encodage du chemin admin dans la page de connexion admin, documentation du repli UUID v4.
- Plugin `spam-learn` : la commande d'apprentissage ne risque plus d'interblocage quand elle produit une sortie volumineuse (stdout/stderr redirigés vers des fichiers temporaires au lieu de pipes non drainés) ; un échec de lecture IMAP du message est désormais signalé au lieu de piper un flux vide.
- Plugin `attachments-clamav` : un fichier temporaire illisible n'est plus accepté comme un scan propre (`stream: OK` factice) — l'échec de lecture suit désormais la politique `on_error` (allow/deny).
- Plugin `filelink` : un fichier disparu entre la vérification et l'ouverture (GC concurrente, suppression manuelle) renvoie désormais 410 au lieu d'une erreur 500.
- Plugin `webmail-migration` : l'extraction du zip téléversé est désormais bornée (nombre de fichiers et taille totale — protection zip bomb) et le TTL des extractions mesure le dernier usage au lieu de la date d'upload.
- Plugin `carddav-contacts` : l'effacement du mot de passe CardDAV utilise un paramètre dédié (un mot de passe littéral « reset » ne peut plus être confondu avec une demande d'effacement) ; bouton « Effacer le mot de passe » ajouté à l'onglet du plugin.
- Mise à jour manuelle (`post-update.php`) : la suppression du reliquat `public/setup/` est désormais récursive (fichiers cachés et sous-dossiers pris en compte).
- Assistant d'installation : la session `mxwm_setup` pose désormais les mêmes attributs de cookie que la session applicative (HttpOnly, SameSite=Lax, Secure en HTTPS).
- Le HTML des plugins écoutant `app.head` était collecté puis ignoré sur les pages de connexion et de maintenance (`$pluginHead` jamais échappé dans leurs vues) : il est désormais émis — le réglage `show_on_login` du plugin `announcement` devient effectif.
- Une adresse portant un domaine punycode invalide (`xn--` malformé) ne provoque plus d'erreur 500 : la forme brute est conservée lorsque la conversion IDN échoue.
- Le toast de fin d'import .eml/.mbox annonce désormais aussi les messages ignorés par le plafond serveur (`skipped`) et ne compte plus un message fantôme quand un .mbox importe 0 message.
- Les erreurs d'enregistrement dans les paramètres n'étaient pas affichées (rejet silencieux) : un gestionnaire global affiche désormais un toast pour toute promesse API non traitée.
- Le décodage d'une réponse HTTP compressée gzip tient compte des en-têtes étendus (FEXTRA/FNAME/FCOMMENT/FHCRC) au lieu d'un décalage fixe, et du trailer complet (8 octets).
- Le transfert « en pièce jointe » d'un message dont le sujet ne contient que des caractères filtrés produit désormais un fichier `message.eml` au lieu de `.eml`.
- Plugin `webmail-migration` : la borne de taille d'extraction limite désormais l'écriture elle-même (un fichier unique dépassant la borne n'est plus extrait intégralement avant abandon).
- Traductions manquantes complétées (`Effacer le mot de passe` CardDAV, échec de lecture ClamAV) dans de/es/it/nl/pl/pt/ru/tr.

### Supprimé

- `public/setup/` n'est plus livré (assistant intégré au cœur) : les reliquats des installations existantes sont effacés par la mise à jour, et exclu de la copie si un ancien paquet le contient.
- Plugin `admin-stats` : sa page « Statistiques » doublait les onglets « À propos », « Extensions » et « Utilisateurs », et sa détection d'orphelins était rendue inutile par les clés étrangères `ON DELETE CASCADE` — ses deux informations utiles (répartition de `storage/`, compteurs utilisateurs) sont reportées dans le cœur. Toute ligne d'état résiduelle est purgée en base (migration).

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
