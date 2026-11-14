# Changelog

Toutes les modifications notables du projet sont listées ici - source unique dont
découlent les notes de release GitHub et le corps des commits de version.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) -
semver `0.MINOR.PATCH` en développement.
Catégories, dans l'ordre : `Sécurité`, `Ajouté`, `Modifié`, `Corrigé`, `Obsolète`, `Supprimé`.

## [0.54.4] - 2026-11-14

### Corrigé

- Le webmail se rouvrait toujours sur le compte par défaut après une reconnexion (session expirée pendant la nuit, login ou "Rester connecté") : le dernier compte consulté est désormais mémorisé à chaque bascule et restauré à l'établissement de la session, avec repli sur le compte par défaut - le `last_folder` du bon compte est du coup correctement restauré aussi.
- Changement de mot de passe alors que la session est positionnée sur un compte secondaire : le credential IMAP de session du secondaire était écrasé par le nouveau mot de passe du principal (connexions IMAP en échec jusqu'au prochain login) - la session n'est plus touchée dans ce cas et la ligne `accounts` du compte principal est mise à jour.

## [0.54.3] - 2026-11-12

### Corrigé

- Suppression/déplacement de mails pendant une recherche : la liste n'était pas resynchronisée après une action groupée réussie - la page gardait un trou, le compteur et le paging restaient sur l'ancien total et les messages remontés dans la fenêtre courante étaient définitivement sautés (page suivante vide ou résultats manquants). La page est désormais rechargée silencieusement après chaque retrait confirmé, la page courante est recalée automatiquement quand le total la fait sortir des limites, et la recherche multi-dossiers pagine réellement (le paramètre page était ignoré).
- Marquage "lu" différé (`mark_read_delay`) : un rechargement de la liste survenu pendant le délai (suppression, poll) empêchait le flag `\Seen` d'être posé - le message affiché restait non lu.

## [0.54.2] - 2026-11-11

### Corrigé

- Le dernier dossier consulté n'était mémorisé que lors d'un clic dans la colonne des dossiers : une ouverture directe par lien `#/<dossier>` ou la bascule de compte (retour forcé sur `#/INBOX`) laissaient une valeur périmée qui était restaurée à la place du dossier réellement consulté - le dossier affiché au démarrage est désormais mémorisé dès qu'il diffère.

## [0.54.1] - 2026-11-11

### Corrigé

- Filtres Sieve : les refus du serveur ManageSieve (NO/BAD sur `CHECKSCRIPT`, `PUTSCRIPT`, `GETSCRIPT`, `SETACTIVE`, `DELETESCRIPT`) retournaient une 500 masquée "Erreur interne" - ils affichent désormais le motif du serveur en 400 (404 `NONEXISTENT`, 401 login refusé, 503 hôte injoignable), et les extensions `require` non annoncées sont refusées localement avant envoi.
- Migrations bloquées sur MySQL vanilla : `CREATE INDEX IF NOT EXISTS` (schéma v13) est une syntaxe MariaDB/SQLite que MySQL rejette (erreur 1064), ce qui figeait `schema_version` et rendait l'application inutilisable. La syntaxe est désormais dispatchée par driver et les re-runs sur driver `mysql` (MySQL comme MariaDB) sautent via `information_schema` les colonnes et index déjà créés - un échec à mi-migration ne bloque plus la reprise.

## [0.54.0] - 2026-11-10

### Ajouté

- Documentation `docs/` traduite dans les 9 autres langues du webmail (en, de, es, it, nl, pl, pt, ru, tr) - chaque langue a son dossier `docs/<lang>/`, le français (`docs/fr/`) reste la version de référence, sélecteur de langue en tête de chaque `README.md`.

### Corrigé

- `README.md` : affirmations corrigées pour refléter le code (clés privées OpenPGP stockées chiffrées par passphrase côté serveur, éditeur HTML TinyMCE sans mode texte, CardDAV/LDAP via plugins, MySQL reconnu aux côtés de MariaDB, paquets Sabre complets) - ajout des badges et du lien vers le site officiel.
- Zone "mise à jour" de la carte "À propos" refondue sur une seule ligne : le numéro de version n'apparaît plus jusqu'à trois fois (badge actuel + badge `latest` + nom de release), les boutons sont strictement contextuels à l'état (paquet en attente affiché via `v{state.version}`), et le bouton "Télécharger" ne persiste plus à côté d'"Appliquer" après un téléchargement réussi.

## [0.53.0] - 2026-11-09

### Ajouté

- Nouveau plugin `always-cc-bcc` : destinataires Cc ou Bcc systématiques à l'envoi (archive de conformité, adresse de suivi), par compte ou tous - le Bcc reste invisible des autres destinataires.
- Nouveau plugin `report-abuse` : bouton "Signaler" dans la barre d'actions - transfère les mails cochés en pièces jointes `.eml` vers une adresse abuse administrée, avec marquage spam optionnel (alimente l'apprentissage).
- Nouveau plugin `vcf-viewer` : aperçu des pièces jointes `.vcf` dans le lecteur et import en un clic dans le carnet d'adresses.
- Nouveau plugin `send-rate-limit` : quota d'envoi par compte (messages/heure, messages/jour, destinataires/jour) - amortit les dégâts d'un compte compromis.
- Nouveau plugin `login-notify` : mail d'alerte au compte lors d'une connexion depuis une adresse IP inconnue (modèle Gmail), délivré non lu en boîte - opt-out par utilisateur.
- Nouveau plugin `sso-header` : connexion automatique via proxy d'authentification (Authelia, authentik, oauth2-proxy) par en-tête de confiance - requiert un mot de passe maître IMAP et des IPs de proxy déclarées.
- Nouveau plugin `dmarc-reports` : page admin agrégeant les rapports DMARC (rua) relevés dans une boîte IMAP dédiée - parsing XML/gzip, agrégats par domaine et par IP source.

### Modifié

- Le webmail rouvre sur le dernier dossier consulté (mémorisé par compte) au lieu de retomber systématiquement sur INBOX - les liens `#/<dossier>` restent prioritaires ; un dossier disparu retombe sur INBOX proprement.

### Corrigé

- Dossiers spéciaux absents du serveur IMAP : la copie "Éléments envoyés" était perdue silencieusement, l'enregistrement de brouillon échouait en erreur et la suppression détruisait définitivement sans corbeille - les dossiers Sent/Drafts/Trash/Junk sont désormais créés automatiquement au premier usage (réponse IMAP `TRYCREATE`/`NONEXISTENT`).
- Plugin `dmarc-reports` : boucle de récupération infinie quand `delete_processed` est désactivé (recherche `ALL` relivrant le même lot, le bouton "Relever" ne s'arrêtait jamais) - recherche `UNSEEN`, marquage `\Seen` garanti même en échec, borne côté JS.
- Plugin `dmarc-reports` : rapports XML portant `xmlns="urn:ietf:params:xml:ns:dmarc"` ignorés silencieusement (XPath non préfixés) - requêtes en `local-name()`, rapport non reconnu n'est plus marqué traité.
- Plugin `sso-header` : boucle de rechargement infinie de la page de connexion quand le compte SSO exige la 2FA - bloc de saisie TOTP affiché, flux SSO rejoué avec nouveau ticket + code au lieu d'un `location.reload()`.
- Traductions : les 8 langues hors fr/en avaient ~105 clés restées en anglais (raccourcis clavier, actions post-suppression, signatures OpenPGP, plugins `dmarc-reports`, `login-notify`, `sso-header`, `send-rate-limit`, `vcf-viewer`) - désormais traduites.
- Textes en dur i18n : libellé "Renvoyer" affiché en français à tous les non-français (clé de base anglaise erronée), noms de touches "Suppr/Échap/Entrée" de l'aide `?`, erreur de chargement des librairies, libellé "Connexion manuelle" SSO et une quinzaine de messages d'erreur admin/API - passés par les dictionnaires.
- Listes de messages : la pagination serveur (IMAP ESORT, RFC 5267) n'était jamais exécutée - chaque affichage retombait sur le tri complet du dossier et un warning remplissait le log ; expansion des plages du résultat normalisée selon la RFC.
- Anti-usurpation : les motifs "marque-mot" ("apple-security.xyz") n'étaient plus signalés en danger (seulement en avertissement) depuis la borne de longueur des labels - les motifs exacts sont désormais évalués sans borne, le contrôle flou reste limité aux longueurs proches.
- Listes de messages : un serveur annonçant `ESORT` mais rejetant `RETURN (PARTIAL ...)` (réponse `BAD`, ex. Zimbra) provoquait un aller-retour IMAP perdu et deux warnings à chaque affichage - le refus est désormais mémorisé par compte et le tri classique utilisé directement.
- Admin > Extensions : la désinstallation d'une extension rapportait un succès alors que son dossier n'avait pas pu être supprimé (droits insuffisants) - l'opération échoue désormais proprement comme l'installation.


## [0.52.0] - 2026-11-06

### Ajouté

- "Copier vers..." : copie d'un message ou de la sélection vers un dossier (menu du lecteur + barre d'actions groupée) - les originaux restent en place.
- "Renvoyer tel quel" : réexpédition du message brut vers un autre destinataire avec en-têtes `Resent-*` (bounce).
- "Créer un filtre" dans le menu du message : ouvre l'assistant Sieve pré-rempli sur l'expéditeur.
- Transfert groupé : la sélection cochée peut être jointe en `.eml` dans une seule composition (bouton dédié, 20 messages max) ; à l'envoi, les messages transférés sont marqués `$Forwarded` (icône flèche dans la liste, comme "répondu").
- Composition : `Ctrl`+`Entrée` envoie le message (documenté dans l'aide `?`).
- Contacts : la fiche édite désormais téléphone, organisation, surnom, site web, anniversaire et note (conservés à l'import/export vCard) ; recherche dans l'onglet et bouton "Écrire" par contact.
- Admin > Utilisateurs : onglet Sessions - jetons "Rester connecté" listés et révoquables, bouton "Forcer la déconnexion" (jetons + sessions actives).
- Admin > Base de données : téléchargement d'une sauvegarde zip (config.php, clé de chiffrement, bases SQLite, branding).
- Schémas de configuration des extensions : type de champ `color` (sélecteur de couleur natif + saisie hexadécimale, vide = repli du plugin) - appliqué aux couleurs du plugin PWA.
- API : endpoint `GET /api/poll` regroupant dossiers, derniers messages des dossiers surveillés, page de liste et quota en une seule requête.
- API : endpoint `POST /api/messages/flags` groupant les changements de drapeaux (lu, suivi) de la sélection en une seule requête.
- API : endpoint `POST /api/messages/spam` groupant les marquages spam/non-spam de la sélection en une seule requête.

### Modifié

- Favicon : nouvelle icône par défaut (logo mxrelay).
- Plugin PWA : les icônes par défaut reprennent le logo mxrelay (centré, fond transparent).
- Réponse : la citation du message d'origine n'est plus préfixée de chevrons `>` dans le corps - elle s'affiche en bloc encadré ; les chevrons restent générés dans la partie texte brut du mail envoyé.
- Plugin PWA : champ "Couleur du thème" vide > `theme-color` suit la couleur d'en-tête du thème affiché (clair/sombre selon l'OS ou le réglage du thème) au lieu du bleu fixe ; le manifest reçoit la couleur d'en-tête du thème actif.
- Actualisation automatique et chargement initial mutualisés via `/api/poll` : une connexion IMAP par tick et par boot au lieu de plusieurs ; le polling est suspendu quand l'onglet est masqué.
- Paquet de release : les sourcemaps JS (`*.map`) ne sont plus incluses.
- Performances : la liste de messages est réconciliée en place au lieu d'être reconstruite à chaque actualisation (DOM intact quand rien n'a changé, sélection et fils dépliés préservés).
- Performances : actions groupées lu/suivi et spam parallélisées ou regroupées en une seule requête (un UID STORE par dossier au lieu d'un par message).
- Performances : requêtes SQL réduites par appel (config des plugins chargée avec les états, domaines et réglages utilisateur mémoïsés par requête, lecture du répondeur d'absence sans double téléchargement du script Sieve).
- Performances : session PHP et chargement des extensions évités pour les assets publics (favicon, dictionnaires, fichiers de thème et d'extensions).
- Performances : le cache des dossiers n'est réécrit que lorsque son contenu change.
- Performances : la liste de messages pagine désormais côté serveur IMAP quand il le permet (ESORT `RETURN COUNT PARTIAL`) - plus besoin de trier et rapatrier tous les UID du dossier ni d'invalider le cache à chaque changement, recherche incluse.
- Performances : l'affichage d'un message ne fait plus qu'un aller-retour IMAP au lieu de deux pour la quasi-totalité des structures de mail.
- Performances : les pièces jointes ne re-fetchent plus les en-têtes MIME déjà connues et leurs métadonnées sont mises en cache - un aller-retour de moins par pièce jointe, mutualisé entre téléchargement, aperçu et archive zip.
- Paquet : les fontes FontAwesome `.ttf` (~700 Ko, jamais téléchargées car `woff2` est prioritaire) ne sont plus livrées.

### Corrigé

- Anti-usurpation : le bouclier ne mentionnait que les mécanismes écrits dans `Authentication-Results` - les verdicts SPF/DKIM/DMARC publiés dans `Received-SPF`, `X-Spam-Status` (SpamAssassin) et `X-Spamd-Result` (rspamd) sont désormais comptés quand le MTA ne les écrit pas dans `Authentication-Results`, et `header.i=@domaine` est reconnu pour l'alignement DKIM.
- Désabonnement : le one-click RFC 8058 échouait systématiquement sur les hébergements sans `allow_url_fopen` (mutualisés) - il passe désormais par le client HTTP interne (cURL ou socket) avec prise en compte de `ssl.cafile`. Tout échec est journalisé et le bouton retombe sur le lien https ou un mailto complet (sujet et corps conservés).
- Accusé de réception (MDN) et vérification de signature S/MIME : les deux actions retournaient une erreur serveur sur tout message (le dossier n'était jamais transmis à la couche IMAP).
- "Rester connecté" et bascule de compte en échec permanent après un changement de mot de passe de la boîte : le mot de passe chiffré stocké par compte n'était jamais rafraîchi au login.
- Le cache des dossiers (résolution des rôles corbeille/spam/archives) n'était jamais persisté : il est désormais stocké en fichier par compte au lieu de la session.
- Export .eml/.zip : warning PHP "headers already sent" dans les logs à chaque export.
- Réglage des dossiers spéciaux : une requête malformée effaçait toutes les assignations personnalisées.
- API de réglages : la valeur texte "false" était acceptée comme vraie pour les options booléennes.
- Déplacement de messages : la cible est désormais validée (erreur 400 propre au lieu d'un échec IMAP brut).
- Mise à jour : un `apply` de plus de 15 minutes pouvait débloquer l'API en pleine copie de fichiers.
- Logout admin sans vérification CSRF (cohérent avec le reste de l'API admin).
- Composition : les suggestions de destinataires cassaient le rendu Knockout dès qu'un contact sans groupe était proposé (exception `group is not defined` à chaque item) - les champs to/cc/bcc sont corrigés.
- Lecture : certains mails (ex. notifications eBay imbriquant un avis Mondial Relay) n'affichaient plus leur contenu central à cause d'une balise `<style>` malformée qui avalait le corps - le sanitizer déballe désormais les balises inconnues et les éléments custom au lieu de les supprimer avec leur contenu.
- Premier login : erreur serveur possible sur connexions simultanées d'un nouvel utilisateur.
- ETag des assets : correspondance par élément de la liste `If-None-Match` (conforme RFC) ; prépare les codes de langue type `pt_BR` pour l'URL i18n.
- Migrations sous MariaDB : chaque version était appliquée puis la migration échouait ("no active transaction") - le DDL auto-commit invalide la transaction PDO ; la transaction n'est désormais ouverte que sous SQLite, donc installation et mises à jour de schéma refonctionnent sur MariaDB.
- Envoi SMTP sans authentification : l'option `smtp.useAuth` d'un domaine était ignorée (une commande `AUTH` était tentée systématiquement, donc échec sur serveur sans AUTH) - elle est désormais respectée.

- "Renvoyer tel quel" : le message réexpédié arrivait sans son contenu (seuls les en-têtes `Resent-*` étaient envoyés).
- Démarrage : un échec de `/api/poll` figeait l'initialisation de l'application (liste et dossiers vides, pas de raccourcis ni d'actualisation) - repli sur les endpoints unitaires.
- "Copier vers..." acceptait le dossier source comme destination (duplication possible).
- Caches `folders-*.json` laissés orphelins par les sessions expirées (purge > 30 j).
- Références `.ttf` orphelines dans le CSS FontAwesome (fichiers retirés du paquet).

## [0.51.0] - 2026-11-04

### Ajouté

- Raccourcis clavier de la vue mail : navigation (↑/↓/j/k), suppression, réponse, transfert, archivage, spam, suivi, non-lu, déplacement, composition, recherche - aide via la touche `?`, désactivables dans Paramètres (`keyboard_shortcuts`).
- Sélection multiple dans la liste : Ctrl+clic pour basculer une ligne, Shift+clic pour une plage.
- Lecture : boutons message précédent/suivant et réglage `after_delete` pour ouvrir le voisin après suppression/déplacement au lieu de fermer le lecteur.
- Composition : glisser-déposer de fichiers sur la fenêtre, de mails sur le bouton "Nouveau message" (joints en .eml) et collage de fichiers non-images dans le corps.
- Signature OpenPGP sans chiffrement (option "Signer") + vérification des messages signés en clair à la lecture.
- "Modifier comme nouveau" dans le menu du message : réédition fidèle (destinataires, sujet, corps, pièces jointes).
- Gestionnaire `mailto:` : enregistrement du webmail comme client mailto: du navigateur/OS avec composition pré-remplie.
- Contacts : saisir le nom d'un groupe (catégorie) dans un champ destinataire propose le groupe et l'expanse en membres.
- Paramètres > Identités : la taille de la modale d'édition est mémorisée après un redimensionnement à la poignée (réglages `identity_modal_width`/`identity_modal_height`) - double-clic sur la poignée = retour à la taille par défaut.
- Admin > Extensions : les plugins sont listés sous un nom d'affichage traduit (nouveau champ `label` du manifeste, convention `theme.json`) - le nom technique reste affiché à côté et sert toujours d'identifiant (dossier, API, logs).
- Paramètres > Dossiers : interrupteur "Affichage" par dossier personnel pour le masquer de la colonne latérale (effet immédiat, mémorisé par compte - les dossiers natifs comme INBOX ou Corbeille ne sont pas masquables).
- Icône "répondu" (flèche) sur les mails de la liste ayant reçu une réponse : le flag IMAP `\Answered` est posé sur le message d'origine à l'envoi d'une réponse, et persisté via `X-Draft-Info` pour les brouillons de réponse envoyés plus tard.
- Admin > Général : réglage `remember_days` pour la durée de vie des jetons "Rester connecté" (1-365 jours, 30 par défaut).

### Modifié

- Admin > À propos : réorganisation de l'onglet - la vérification et le workflow de mise à jour sont intégrés à la carte d'en-tête en version simplifiée (bouton GitHub retiré, lien présent dans la navbar), "Chemins" prend la place de l'ancienne carte "Mise à jour" et "Utilisation du stockage" rejoint "Extensions PHP" sur la même ligne (grille 2x2 à largeurs égales).
- La déconnexion automatique d'inactivité (`autologout`) détruit la session sans révoquer le jeton "Rester connecté" - la session est restaurée silencieusement à la visite suivante ; la déconnexion manuelle révoque toujours le jeton.

### Corrigé

- Le voile "déposer pour joindre" de la composition ne se masquait plus après un survol sans dépôt (compteur gonflé par `dragover` - comptage désormais sur `dragenter`).
- La vérification OpenPGP n'était jamais atteinte : clé privée/passphrase exigés avant même pour un message signé en clair, puis bloc introuvable (restructuration de `decryptPgp`).
- Après une suppression avec le réglage "ouvrir le précédent", un message non capturé dans la liste pouvait ouvrir le premier message au lieu de fermer le lecteur.
- La confirmation "Vider" d'un dossier affiche désormais le nom traduit des dossiers natifs (ex. "Corbeille") au lieu du nom IMAP brut (ex. "Trash").
- Paramètres > Identités : le marqueur "position du curseur" de la signature s'affichait avec un glyphe de police de secours incorrect dans l'éditeur et l'aide - il est désormais dessiné en CSS (ruban signet) et l'aide affiche l'icône du bouton.
- La touche `!` était inerte dans le dossier des indésirables (cibles déjà marquées sautées) - elle inverse désormais le marquage comme le bouton.
- Après une action groupée (suppression/déplacement/spam) incluant le mail affiché, l'ouverture automatique du voisin (`after_delete`) pouvait sauter des messages.
- Le gestionnaire `mailto:` n'était plus jamais reproposé si l'enregistrement navigateur échouait une première fois.
- Le glisser-déposer d'un mail pour le joindre en `.eml` se fait sur le bouton "Nouveau message" (la fenêtre de composition modale ne pouvait pas recevoir de mail glissé depuis la liste).

### Supprimé

- Plugins `announcement` et `seasonal-decorations` : la case "Activé(e)" de la modale de configuration est supprimée - l'activation se fait uniquement par le switch du tableau Extensions.

## [0.50.0] - 2026-11-03

### Ajouté

- Composition : les champs À/Cc/Cci acceptent désormais plusieurs destinataires sous forme de pastilles (validation par `,`/`;`, Entrée ou suggestion) - autocomplétion des contacts pilotable au clavier (↑/↓, Entrée, Échap), dédoublonnage des adresses déjà saisies, suppression d'une pastille par `×` ou Retour arrière ; les fragments invalides sont affichés en rouge.
- Paramètres > Contacts : import des carnets RainLoop/SnappyMail (`AddressBook.sqlite`) - même déduplication sur les emails que l'import `.vcf`/`.csv` ; dans un carnet multi-utilisateurs, seuls les contacts du compte sont repris.
- Plugin anti-spoofing : nouvelles détections de domaines leurres - domaine protégé placé en sous-domaine d'un domaine hostile (`microsoft.com.evil.com`), typosquat sur le label registrable quel que soit le TLD (`apple-security.xyz`, `paypa1.net`) et marque réemployée dans un domaine étranger (`microsoft.evil.com`, `securemicrosoft.com`, en avertissement).
- Plugin anti-spoofing : alerte d'information quand une signature DKIM tierce (transporteur, relais) est invalide - sans fausser le verdict d'authentification de l'expéditeur.
- Colonne des dossiers : repli/extension des sous-dossiers par chevron devant chaque dossier parent - l'état est mémorisé par compte, et naviguer vers un dossier masqué (lien direct, notification) étend automatiquement ses parents.
- Nouveau thème "Minuit" (`midnight`) : accent teal, palette "nuit" bleutée, dossiers actifs en pilules, rayons adoucis et page de connexion en dégradé - sélectionnable comme les autres thèmes dans les réglages.
- Glisser-déposer des messages vers un dossier de la colonne latérale : la ligne saisie déplace toute la sélection cochée si elle en fait partie, sinon le seul message saisi - la cible est surlignée au survol (en rouge pour la corbeille) et les dossiers repliés s'étendent automatiquement pour atteindre leurs sous-dossiers.

### Modifié

- Le dictionnaire de traduction n'est plus injecté en ligne dans chaque page : il est servi comme un fichier JavaScript séparé (`/assets/i18n/{lang}.js`) versionné par signature de contenu et mis en cache navigateur - les rechargements complets ne retéléchargent plus ~80 Ko de traductions à chaque fois.
- Téléchargement d'une pièce jointe : le corps n'est plus chargé intégralement en mémoire - il est streamé depuis le cache disque ou depuis un fichier temporaire alimenté par l'IMAP, ce qui supprime le pic mémoire sur les grosses pièces jointes et ajoute `Content-Length` (progression de téléchargement).
- JavaScript applicatif servi en bundles minifiés (`js/dist/`) au lieu d'une cascade de modules ES bruts - au démarrage, ~17 requêtes de modules remplacées par 1 à 3 fichiers minifiés (app.js : 243 Ko de sources > ~107 Ko).
- Refactor interne : `ApiController` (~2900 l.) et `AdminController` (~1500 l.) éclatés en contrôleurs par domaine (`Controller/Api/*`, `Controller/Admin/*`) - les ~75+45 endpoints gardent leurs routes et méthodes inchangées, les helpers partagés sont mutualisés dans des classes de base. Aucun changement fonctionnel.
- Lecture d'un message : "Se désabonner" devient un bouton dédié de la barre d'actions (entre "Supprimer" et "⋯"), retiré du menu "Plus d'actions".
- Paramètres > Contacts : le formulaire d'ajout/édition s'ouvre désormais dans une modale (au lieu d'une carte en bas de liste) et le tableau est paginé (25 contacts par page).
- Colonne des dossiers : le dossier de rôle archive (Archives, All Mail) est désormais rangé dans la section des dossiers utilisateur au lieu du groupe des dossiers de base - il reste protégé (pas de renommage ni suppression).
- Après un déplacement, une suppression ou un marquage (non) spam, l'application ne sélectionne plus automatiquement un autre mail - le lecteur ne se ferme que si le message affiché était parmi ceux retirés, sinon il reste ouvert.
- Menu des comptes : le compte actif est désormais mis en évidence par un fond coloré sur toute la ligne, au lieu d'une icône de coche devant son adresse.

### Corrigé

- Un sous-dossier portant un nom usuel ("Envoyé", "Corbeille"…) n'hérite plus de l'icône, du label traduit ni du rôle du dossier spécial correspondant - la détection par nom ne s'applique qu'à la racine ou sous `INBOX` ; un tel dossier redevient renommable et supprimable.
- Détection des noms usuels Outlook en français ("Éléments envoyés", "Éléments supprimés") pour les dossiers spéciaux.
- Panneau d'administration : erreur "Call to undefined method" à l'ouverture de l'onglet Réglages - deux helpers partagés (`userSettingsSchema`, `proxyUrlError`/`hostAllowed`) étaient rangés dans un seul contrôleur au lieu de la classe de base commune.
- Les indisponibilités ou coupures du serveur de messagerie (IMAP/SMTP/Sieve) affichent désormais un message explicite ("Serveur IMAP injoignable : hôte", "Connexion au serveur de messagerie interrompue"…) au lieu d'"Erreur interne".
- Envoi SMTP : les échecs affichaient "Erreur interne" au lieu du message "Échec de l'envoi" prévu - leur statut 502 était masqué par le gestionnaire d'erreurs générique.

- Import de carnet RainLoop/SnappyMail : un carnet multi-utilisateurs dont tous les contacts appartiennent à un seul compte n'est plus refusé - le test mono-utilisateur porte désormais sur les propriétaires réels des contacts.
- Plugin anti-spoofing : les sous-domaines des domaines protégés ne sont plus accusés d'usurpation (`accountprotection.microsoft.com` est légitime) - fin du bouclier rouge sur les mails Microsoft/Google/etc. authentiques.
- Plugin anti-spoofing : les usurpations de nom d'affichage par caractères confusables sont détectées ("AppIe TV" avec un `I` majuscule est reconnu comme "Apple TV") et signalées explicitement ; la comparaison des marques se fait désormais à borne de mot (`pineapple` ne déclenche plus l'alerte "apple") et tolère les accents (`Impôts`).
- Thème sombre (`dark`) : sa feuille de style, qui dérivait de celle du thème par défaut, est resynchronisée - le voile de chargement de la liste des messages et le tooltip multi-lignes du verdict anti-spoofing s'affichent à nouveau correctement.
- Plugin anti-spoofing : SPF/DKIM/DMARC ne sont évalués qu'en alignement avec le domaine du `From` - un `dkim=fail` sur la signature du transporteur ou un `spf=fail` sur un mailfrom relayé (SRS) ne lève plus d'alerte.
- Plugin anti-spoofing : l'indicateur de confiance n'affiche plus "SPF, DKIM et DMARC vérifiés" quand un seul mécanisme était présent - il liste les mécanismes réellement vérifiés ("Authentification vérifiée : DKIM").
- Plugin anti-spoofing : un domaine dont le nom est une variante trop longue d'une marque protégée n'est plus signalé en alerte critique (`*.onmicrosoft.com`, domaine Office 365, n'est plus accusé d'imiter `microsoft.com`) - la comparaison des labels est bornée aux longueurs proches et la recherche de marque limitée aux préfixes/suffixes.
- Barre d'actions de la liste des messages : les boutons `.btn-icon` ont désormais tous la même largeur (31px, carrés) quelle que soit la largeur du glyphe - `aspect-ratio` ignoré dans les `btn-group` est remplacé par une largeur fixe dérivée des variables Bootstrap.
- Messages d'erreur GitHub explicites : dépôt privé ou ressource absente, token invalide, quota de l'API épuisé (avec heure de reset) ou GitHub indisponible - au lieu d'un brut "HTTP 404 - URL" dans la vérification des mises à jour et le catalogue d'extensions.
- Panneau d'administration : un échec de chargement du catalogue des extensions à l'ouverture de l'onglet, ou de la configuration d'un plugin/thème, affiche désormais un toast d'erreur au lieu d'échouer silencieusement.
- Contacts auto-enregistrés : le nom d'un destinataire sans display-name (adresse saisie manuellement) vaut désormais son adresse mail au lieu d'être vide, et se met à jour quand un vrai nom apparaît ensuite ; les suggestions n'affichent plus "x@y - x@y".
- Envoi d'un message avec pièce jointe après un enregistrement automatique du brouillon : erreur "Pièce jointe introuvable" - la sauvegarde du brouillon supprimait les fichiers temporaires des PJ que la composition référençait encore (et les brouillons cessaient de se mettre à jour après la première sauvegarde automatique).
- Réactualisation de la liste : la liste des messages du dossier courant n'était jamais rafraîchie par le minuteur dès qu'un message était affiché (le mail visionné étant automatiquement coché, le garde-fou "sélection en cours" sautait chaque tick) - seuls les badges des dossiers et les notifications étaient actualisés ; les coches et la sélection sont désormais préservées au rechargement et le panneau des dossiers est mis à jour en place (scroll et repli conservés).
- Thèmes : la couleur primaire configurée dans l'administration s'applique désormais aux boutons ("Nouveau message", "Enregistrer", "Envoyer"…), aux cases à cocher, interrupteurs, anneaux de focus et barres de progression - Bootstrap compile ses composants avec des couleurs littérales, désormais redéclarées vers les variables du thème ; les éléments "sélectionnés" (item actif des menus déroulants, menus latéraux des réglages et de l'administration, suggestions) utilisent la couleur "dossier actif".
- Thèmes, mode sombre : les variantes sombres pré-remplies de "Couleur principale" et "Couleur des liens" (`#6ea8fe`/`#2dd4bf` par défaut) masquaient la couleur claire configurée - boutons, badges, barres de progression et liens restaient bleus en mode sombre. Toutes les variantes "(sombre)" sont désormais vides par défaut : la couleur claire s'applique aux deux modes, une variante sombre reste configurable explicitement.
- Changement de dossier après un aperçu de pièce jointe `.eml` : une erreur "Cannot read properties of null (reading 'uid')" s'affichait en toast et le dossier cliqué n'était pas sélectionné au premier clic - les liens des pièces jointes imbriquées sont désormais figés sur le message propriétaire de l'`.eml` au lieu du message couramment affiché.

### Supprimé

- Paramètres : suppression de l'avertissement rouge "notifications bloquées par le navigateur" affiché sous l'option de notification des nouveaux messages.
- Réglage `auto_advance` ("Après suppression ou déplacement") retiré - plus aucune ouverture automatique du message voisin après une action sur la liste.
- Option "Icônes" des thèmes (plein/fin) retirée : la police Font Awesome Free "regular" ne contient qu'environ 160 icônes sur ~1400 - la plupart des icônes s'affichaient en carrés barrés, notamment sous le thème Minuit qui l'activait par défaut.

## [0.49.0] - 2026-11-01

### Ajouté

- Réglages > Comptes : l'ordre des comptes secondaires est désormais persistant et modifiable par glisser-déposer - un nouveau compte s'ajoute en fin de liste (auparavant le tri était alphabétique).
- Aperçu des pièces jointes `.eml` (message transféré, message/rfc822) : le bouton loupe affiche dans la modale les en-têtes (expéditeur, destinataires, date), le corps sanitisé comme un message normal (images `cid:` et distantes selon le réglage d'affichage) et les pièces jointes du message joint, téléchargeables individuellement - y compris les `.eml` imbriqués en cascade.
- Plugin `link-confirmation` : modale de confirmation avant l'ouverture d'un lien contenu dans un mail (anti-hameçonnage) - destination réelle affichée, signalement des liens suspects (texte différent de l'URL cible, IP littérale, punycode, identifiants dans l'URL), domaines de confiance et option "ne plus demander pour ce domaine" (réglages dans Extensions > ⚙).
- Réglages > Dossiers : nouvelle colonne "Taille" affichant la taille totale des messages de chaque dossier - nécessite l'extension IMAP `STATUS=SIZE` (RFC 8438), la colonne est masquée si le serveur ne l'annonce pas.
- Écran de chargement plein page affiché jusqu'à l'initialisation complète de l'interface (fin du premier chargement de la liste de messages) - supprime le flash de mise en page brute au rechargement ou au changement de compte, avec fondu de sortie et durée d'affichage minimale.
- Indicateur de chargement animé centré sur la colonne de la liste des messages (voile estompé + spinner) pendant les rechargements déclenchés par l'utilisateur - le rafraîchissement automatique en arrière-plan reste signalé par la seule icône.

### Modifié

- Onglet "Pièces jointes" du lecteur de message : les fichiers sont présentés en tableau (nom, taille, actions d'aperçu et de téléchargement) au lieu d'une suite de boutons.
- Le sélecteur de comptes de la barre supérieure devient un bouton visible (auparavant un texte semi-transparent peu lisible).
- Changement de compte : l'interface revient désormais systématiquement sur la boîte de réception, quel que soit le dossier consulté (auparavant le dossier courant était reporté via le hash et conservé si un dossier du même nom existait sur le compte cible).
- Robustesse : le cache disque des pièces jointes est purgé automatiquement (7 jours) et l'aperçu d'une pièce jointe `.eml` borne la lecture à 25 Mo avant mise en mémoire (évite une croissance disque non maîtrisée et un pic mémoire sur les messages volumineux).
- Performances : le cache fichier (`cache.enabled`) est désormais activé par défaut - il cache les listes de messages IMAP (indexées sur l'ETag du dossier), les résolutions DNS, la détection de domaine et la vérification de mise à jour, et borne le dossier `storage/cache` via un GC périodique.
- Performances : les assets servis par PHP (CSS de thème, assets de plugins, favicon) sont désormais mis en cache navigateur - `http.etag`, `http.assets_ttl` (24 h) et `http.favicon_ttl` (1 h) activés par défaut, avec `?v=` basé sur le mtime du fichier servi (bust garanti à chaque édition).
- Actions des dossiers : le menu `⋯` en face de chaque dossier est supprimé - "Renommer", "ACL" et "Supprimer" sont déplacés dans Réglages > Dossiers, dont l'onglet "Dossiers surveillés" devient "Dossiers" (premier sous-onglet) avec un tableau regroupant surveillance et gestion ; "Vider" et "Tout marquer comme lu" deviennent des boutons de l'en-tête de la colonne agissant sur le dossier affiché.
- Performances : "Signaler comme spam" / "Non-spam" retire désormais le message immédiatement de la liste (action en arrière-plan, resynchronisation en cas d'échec), et la résolution des dossiers spéciaux (indésirables, corbeille, envoyés…) est mise en cache de session au lieu d'un listage complet des dossiers IMAP à chaque action - suppression, déplacement et vidage à la déconnexion en bénéficient aussi.
- Performances : la suppression et le déplacement d'une sélection multiple n'envoient plus qu'un seul appel API groupé (une commande IMAP `UID MOVE`/`EXPUNGE` par dossier) au lieu d'une requête par message - la sélection disparaît instantanément au lieu de s'effacer ligne par ligne.
- Ajout d'un compte secondaire : email et mot de passe sont réunis en une seule modale et les erreurs s'affichent en place avec la saisie conservée (auparavant deux boîtes de dialogue successives puis une alerte séparée).
- Réglages > Dossiers : la colonne "Surveillance" est resserrée sur son en-tête avec les cases à cocher centrées, et les lignes du tableau ont une hauteur uniforme avec ou sans boutons d'action.
- Réglages > Étiquettes (plugin color-tags) : la palette et les règles automatiques sont réparties sur deux sous-onglets, avec deep-link `#/@settings/color-tags/rules`.
- Lecteur de message : le verdict anti-spoofing s'affiche désormais sous forme d'icône bouclier colorée en bas à droite de l'en-tête, sous les boutons d'action (couleur selon le niveau, détail au survol) - le bandeau d'alerte et la ligne verte sous l'en-tête sont supprimés.

### Corrigé

- La liste des messages est rafraîchie quand le champ de recherche est vidé via l'icône "×" native du navigateur - auparavant les résultats de la recherche restaient affichés.
- Proxy d'images : erreur 500 systématique (`MimeType::fromString` appelé avec une expression alors que son premier paramètre était passé par référence) - les images distantes des mails ne s'affichaient plus.
- Plugin anti-spoofing : le bandeau "Attention - usurpation possible" s'affichait sur quasiment tous les mails car le succès SPF/DKIM/DMARC y était compté comme une alerte - il devient un indicateur vert discret, le bandeau n'apparaît plus qu'en cas de vraie alerte.
- Traductions de/es/it/nl/pl/pt/ru/tr complétées : plusieurs fonctionnalités récentes restaient en anglais (répondeur d'absence, réponses types, import .eml, désabonnement, réglages HTTP/cache admin, plugins announcement, filelink, spam-learn, wkd-keys…).
- Aperçu d'une pièce jointe .ics : les libellés affichaient les clés de traduction brutes (ex. `plugin.ics-viewer.start`) dans toutes les langues.
- Aide du réglage `admin_path` en français : le placeholder `{path}` s'affichait littéralement.
- Aperçu, téléchargement et export ZIP des pièces jointes : erreur 500 systématique - régression du FETCH `BODYSTRUCTURE` dédié (UID passé en `int` sous `strict_types`, dossier IMAP non sélectionné, champ `INTERNALDATE` exigé par la reconstruction du message non demandé).
- Export ZIP "Tout télécharger" des pièces jointes : erreur 500 - variable `$mail` non initialisée dans la boucle de lecture (le fichier téléchargé contenait la réponse d'erreur au lieu des PJ).
- Export ZIP "Tout télécharger" : le fichier téléchargé était un gzip contenant le zip (double compression) - un en-tête Content-Encoding vide empêchait le navigateur de décoder la compression serveur.
- Plugin avatars : les refus de favicon pour domaine inexistant (NXDOMAIN - cas banal des expéditeurs sans site web) remplissaient le journal en WARNING ; ils passent en INFO, seuls les refus vers IP privée/réservée restent en WARNING, et les avatars introuvables sont mémorisés 7 jours (cache négatif) au lieu de retenter DNS + HTTP à chaque affichage.
- La sélection du dossier courant reste synchronisée avec la liste des mails affichée (rechargement F5, navigation précédent/suivant, lien direct par hash).
- "Vider" sur le dossier affiché ne rafraîchissait pas la liste : les messages supprimés restaient visibles, la pagination gardait l'ancien total et le lecteur pouvait encore afficher un message inexistant - la liste est rechargée et le lecteur fermé si son message venait du dossier vidé.
- Ajout d'un compte secondaire : message d'erreur explicite ("Identifiants invalides", "Serveur IMAP injoignable") au lieu d'un "Erreur interne" générique - y compris pour l'ajout OAuth et les champs obligatoires.
- Vue "Conversations" : l'item du menu de tri était inerte (mauvais contexte de binding Knockout) - le clic bascule désormais le mode et la coche reflète l'état actif.
- Vue "Conversations" activée dans les réglages : la liste des messages n'était pas rechargée après la sauvegarde - le basculement est désormais appliqué immédiatement.
- Vue "Conversations" : une note indique désormais dans les réglages quand le serveur IMAP ne supporte pas l'extension `THREAD` - auparavant le réglage restait activable sans aucun effet ni explication ; les sous-messages d'un fil déplié sont aussi retirés de la liste avec leur racine.

### Supprimé

- Le bouton "Basculer sur ce compte" est retiré de Réglages > Comptes - la bascule reste dans le sélecteur de comptes de la barre supérieure.

## [0.47.0] - 2026-10-30

### Sécurité

- Le verrouillage anti-force-brute du login pouvait être contourné en variant la casse ou les espaces de l'email saisi : la clé de comptage est désormais normalisée (minuscules, sans espaces) au point unique de hachage.
- La connexion au panneau admin via le compte local (`admin_user`/`admin_password_hash`) régénère désormais l'identifiant de session à l'élévation de privilèges (fixation de session possible auparavant).
- Le compteur anti-force-brute était multiplié par les variantes de saisie du même compte (`user` vs `user@domaine` quand `default_domain` est défini) : le suffixe de domaine est désormais reproduit dans la clé de hachage.

### Ajouté

- Paramètres > Sessions : nouvelle colonne "IP" et bouton d'information ouvrant une modale de détails par session - appareil (navigateur/OS), User-Agent complet, IP de création et dernière IP connue, dates de création / dernière utilisation / expiration, révocation.
- Panneau admin, onglet "À propos" : carte "Utilisation du stockage" - taille occupée par chaque sous-dossier de `storage/` (logs, cache, base…).
- Panneau admin, onglet "Utilisateurs" : ligne de synthèse en en-tête - nombre total de comptes, actifs sur les 30 derniers jours, désactivés.
- Nouveau plugin `seasonal-decorations` : paysage monochrome discret affiché selon la date (Nouvel An, Pâques, 1er avril, Halloween, Noël) - silhouettes SVG détaillées appliquées en couches `mask-image` dans le bandeau de l'app et en haut des pages de connexion/maintenance, bibliothèque d'objets recomposable via `scenes.json`, couleur contrastée avec le fond automatique, sans animation ; événements et fenêtres réglables.

### Modifié

- L'assistant d'installation est intégré au cœur (`SetupController`) : `/setup` répond 404 une fois le verrou posé - la page "déjà installé" qui exposait le chemin du panneau admin disparaît, et il n'y a plus de fichier `public/setup/` à supprimer ni à redéployer par les mises à jour.
- Panneau admin, onglet Extensions : la description s'affiche sous le nom du plugin (police réduite) et la colonne "Utilisable" devient "Accès utilisateurs".
- Panneau admin, onglet Extensions : l'accès utilisateurs d'un plugin est désormais refusé par défaut (à autoriser explicitement) et le switch disparaît pour les plugins purement admin (hooks `admin.*` uniquement - admin-stats, webmail-migration). Les valeurs déjà en base sont conservées.
- Performance : une seule connexion IMAP par requête (auparavant jusqu'à 3-4, ×2 par image inline dans un message), listes de messages et threads mis en cache sur disque (listes d'UIDs indexées sur l'état du dossier - SORT/SEARCH/THREAD serveur évités tant qu'il n'a pas changé), pièces jointes mises en cache disque avec `Cache-Control: immutable` (plus de re-téléchargement à la réouverture d'un message), manifestes des plugins et dictionnaires de langue en cache, requêtes SQL et fichiers de configuration des thèmes mémoïsées par requête.
- Performance : les requêtes API en lecture seule libèrent le verrou de session PHP au lieu de le garder pendant les appels IMAP - le rafraîchissement automatique, les images inline et les actions ne se bloquent plus mutuellement.
- Performance : `openpgp.js` (~540 K) et `tinymce` (~500 K) ne sont plus chargés au démarrage mais à la première utilisation ; les dossiers surveillés sont désormais inclus dans `/api/appdata` et la liste des identités est mutualisée (jusqu'à 2 allers-retours en moins au boot).
- Proxy d'images externes : mise en cache disque des images téléchargées (TTL `http.proxy_ttl`, défaut 24 h).
- Plugin imap-contacts-suggestions : les suggestions sont mises en cache 5 minutes (auparavant une connexion IMAP + recherche à chaque frappe).
- SQLite : `PRAGMA busy_timeout=5000` ajouté aux connexions (tolérance aux écritures concurrentes).
- Les caches configurables sont désormais désactivés par défaut : cache fichier `cache.enabled` (DNS, détection de domaine, vérification de mise à jour, listes de messages) et cache navigateur `http.etag` avec TTL `assets_ttl`/`favicon_ttl`/`proxy_ttl` à 0 (le cache disque du proxy d'images est coupé par la même clé) - réactivables dans admin > Performance.

### Corrigé

- Compatibilité MariaDB/MySQL : la colonne `key` (mot réservé) des tables `meta` et `user_settings` est désormais citée partout - l'installation échouait dès les migrations sur ces drivers.
- Le domaine stocké en session au login est désormais celui résolu après le hook `auth.login.mapping` (auparavant la forme pré-mapping, divergente pour les plugins de mapping).
- Une erreur de lecture IMAP en téléchargement de pièce jointe ou de source .eml ne produit plus un fichier téléchargé contenant la page d'erreur (en-têtes d'attachment purgés avant la 404).
- `/api/messages/{uid}` renvoie désormais `isSeen:true` quand le flag `\Seen` vient d'être posé (la réponse annonçait toujours "non lu").
- Un nom de dossier passé brut (non encodé base64url, ex. `INBOX`) dans les routes `/api/folders/{name}/…` n'est plus décodé en binaire : vérification round-trip stricte avec repli sur le nom brut.
- Un filtre Sieve utilisant l'opérateur "regex" ne pouvait pas être enregistré sur les serveurs stricts (Dovecot) : la directive `require ["regex"]` manquait dans le script généré.
- Une session laissée inactive plus de 24 minutes pouvait être détruite par le ramasse-miettes PHP (gc_maxlifetime par défaut à 1440 s quand `session_lifetime = 0`) : plancher de 8 h désormais appliqué.
- Les réponses 404 des téléchargements de pièces jointes et de sources .eml ne perdaient plus les en-têtes de sécurité globaux (purge limitée aux en-têtes posés localement).
- L'import mbox au-delà de 2000 messages comptait un seul échec : les messages non importés sont désormais comptés et rapportés (`skipped`).
- Les plages CIDR IPv4 supérieures à /32 saisies dans les listes d'IP admin (`admin_ip_allowlist`, `trusted_proxies`) étaient acceptées mais jamais appliquées : elles sont désormais rejetées en validation.
- Une pièce jointe sans nom ne produit plus une entrée au nom vide dans le zip "télécharger toutes les pièces jointes".
- Paramètres > Étiquettes (plugin color-tags) : le sélecteur de champ des règles automatiques affichait des clés de traduction brutes au lieu des libellés localisés.
- L'aperçu calendrier (ics-viewer) ne s'affichait plus pour les pièces jointes `*.ics` servies avec un type MIME générique (`application/octet-stream`, cas Outlook) : le test d'extension était un regex sur-échappé qui ne pouvait jamais correspondre.
- Le téléchargement ".eml en zip" d'un dossier devenu invalide produisait une archive vide ou tronquée au lieu d'une erreur propre ; les messages ou pièces jointes illisibles pendant le téléchargement zip sont désormais listés dans une entrée `ERREURS.txt` de l'archive (au lieu de manquer en silence).
- Le zip "toutes les pièces jointes" pouvait contenir deux entrées au même nom quand une pièce portait déjà un nom du type "a (2).txt" : la déduplication tient désormais compte des noms qu'elle génère.
- Corrections internes : variable non initialisée dans l'archiveur TAR (code mort, warning PHP), champ "version made by" non conforme dans l'en-tête des zips générés, encodage du chemin admin dans la page de connexion admin, documentation du repli UUID v4.
- Plugin `spam-learn` : la commande d'apprentissage ne risque plus d'interblocage quand elle produit une sortie volumineuse (stdout/stderr redirigés vers des fichiers temporaires au lieu de pipes non drainés) ; un échec de lecture IMAP du message est désormais signalé au lieu de piper un flux vide.
- Plugin `attachments-clamav` : un fichier temporaire illisible n'est plus accepté comme un scan propre (`stream: OK` factice) - l'échec de lecture suit désormais la politique `on_error` (allow/deny).
- Plugin `filelink` : un fichier disparu entre la vérification et l'ouverture (GC concurrente, suppression manuelle) renvoie désormais 410 au lieu d'une erreur 500.
- Plugin `webmail-migration` : l'extraction du zip téléversé est désormais bornée (nombre de fichiers et taille totale - protection zip bomb) et le TTL des extractions mesure le dernier usage au lieu de la date d'upload.
- Plugin `carddav-contacts` : l'effacement du mot de passe CardDAV utilise un paramètre dédié (un mot de passe littéral "reset" ne peut plus être confondu avec une demande d'effacement) ; bouton "Effacer le mot de passe" ajouté à l'onglet du plugin.
- Mise à jour manuelle (`post-update.php`) : la suppression du reliquat `public/setup/` est désormais récursive (fichiers cachés et sous-dossiers pris en compte).
- Assistant d'installation : la session `mxwm_setup` pose désormais les mêmes attributs de cookie que la session applicative (HttpOnly, SameSite=Lax, Secure en HTTPS).
- Le HTML des plugins écoutant `app.head` était collecté puis ignoré sur les pages de connexion et de maintenance (`$pluginHead` jamais échappé dans leurs vues) : il est désormais émis - le réglage `show_on_login` du plugin `announcement` devient effectif.
- Une adresse portant un domaine punycode invalide (`xn--` malformé) ne provoque plus d'erreur 500 : la forme brute est conservée lorsque la conversion IDN échoue.
- Le toast de fin d'import .eml/.mbox annonce désormais aussi les messages ignorés par le plafond serveur (`skipped`) et ne compte plus un message fantôme quand un .mbox importe 0 message.
- Les erreurs d'enregistrement dans les paramètres n'étaient pas affichées (rejet silencieux) : un gestionnaire global affiche désormais un toast pour toute promesse API non traitée.
- Le décodage d'une réponse HTTP compressée gzip tient compte des en-têtes étendus (FEXTRA/FNAME/FCOMMENT/FHCRC) au lieu d'un décalage fixe, et du trailer complet (8 octets).
- Le transfert "en pièce jointe" d'un message dont le sujet ne contient que des caractères filtrés produit désormais un fichier `message.eml` au lieu de `.eml`.
- Plugin `webmail-migration` : la borne de taille d'extraction limite désormais l'écriture elle-même (un fichier unique dépassant la borne n'est plus extrait intégralement avant abandon).
- Traductions manquantes complétées (`Effacer le mot de passe` CardDAV, échec de lecture ClamAV) dans de/es/it/nl/pl/pt/ru/tr.

### Supprimé

- `public/setup/` n'est plus livré (assistant intégré au cœur) : les reliquats des installations existantes sont effacés par la mise à jour, et exclu de la copie si un ancien paquet le contient.
- Plugin `admin-stats` : sa page "Statistiques" doublait les onglets "À propos", "Extensions" et "Utilisateurs", et sa détection d'orphelins était rendue inutile par les clés étrangères `ON DELETE CASCADE` - ses deux informations utiles (répartition de `storage/`, compteurs utilisateurs) sont reportées dans le cœur. Toute ligne d'état résiduelle est purgée en base (migration).

## [0.44.0] - 2026-10-29

### Sécurité

- La liste blanche (`white-list`) pouvait être contournée lorsqu'un plugin de mapping (ex. `ldap-login`) résolvait l'identifiant : les listes de contrôle de connexion sont désormais évaluées sur l'email résolu via le nouveau hook `auth.login.mapped`.
- Validation anti-SSRF mutualisée dans `App::isPublicHost()` (résolution A+AAAA complète, refus dès qu'une IP est privée ou réservée) - le plugin `avatars` ne se limitait auparavant qu'aux adresses IPv4.

### Ajouté

- Hook cœur `auth.login.mapped` (type `run`) : émis après `auth.login.mapping` avec l'email résolu, destiné aux contrôles post-mapping des plugins (black-list, white-list).
- Hooks cœur `message.spam` (à chaque marquage/démarquage spam, avant le déplacement) et `attachment.upload` (après chaque upload de pièce jointe et import de fichier, avant exploitation).
- Plugin `anti-spoofing` : le bandeau de sécurité affiche désormais les verdicts SPF/DKIM/DMARC déjà fournis par le MTA (`Authentication-Results`), avec un indicateur positif quand tout passe.
- Plugin `ics-viewer` : réponse iTIP aux invitations (`METHOD:REQUEST`) - boutons Accepter / Peut-être / Refuser envoyant un `REPLY` calendrier à l'organisateur par SMTP.
- Nouveau plugin `announcement` : bannière d'annonce administrateur (app, optionnellement page de login).
- Nouveau plugin `spam-learn` : apprentissage spam/ham vers `rspamc`/`sa-learn` ou dossier dovecot-antispam.
- Nouveau plugin `attachments-clamav` : scan antiviral ClamAV des pièces jointes à l'upload.
- Nouveau plugin `filelink` : pièces jointes trop volumineuses envoyées via un lien de téléchargement expirable.
- Nouveau plugin `admin-stats` : page d'administration de statistiques (stockage, utilisateurs, tables, orphelins, plugins).
- Nouveau plugin `wkd-keys` : découverte automatique des clés PGP (WKD, Autocrypt, keyserver optionnel).
- Compteur de multi-sélection : lorsque plusieurs messages sont cochés, la zone de lecture affiche "N messages sélectionnés" à la place du contenu.

### Modifié

- Changement de dossier : la zone de lecture est réinitialisée à l'état par défaut ("Sélectionnez un message") au lieu de conserver le message du dossier précédent.
- Paramètres webmail et panneau admin : les icônes d'aide `?` sont désormais alignées en colonne sur le bord droit des libellés, juste avant le champ (auparavant collées à la fin du texte du libellé - position variable selon sa longueur).

### Corrigé

- Actions groupées en recherche "Tous les dossiers" : marquage lu/suivi, déplacement, suppression, spam et téléchargement `.eml` ciblaient le dossier courant au lieu du dossier réel de chaque message (les UID IMAP étant par dossier, un autre message pouvait être affecté) ; le zip est désormais généré par dossier source.
- Codes de secours 2FA impossibles à saisir à la connexion : le champ TOTP était limité à 6 caractères alors qu'un code de secours fait 9 (`xxxx-xxxx`).
- Suppression/déplacement depuis le lecteur : la sélection du message suivant et le badge non lus pouvaient viser la mauvaise ligne en recherche multi-dossiers (correspondance par UID seul).
- Clic sur un brouillon dont la récupération échoue : erreur désormais affichée au lieu d'un silence.
- Suggestions de destinataires : une requête par frappe remplacée par une requête après 250 ms de pause.
- Les menus déroulants du compte (barre supérieure) et d'un dossier sont désormais atteignables au clavier.
- Plugins `ldap-login` et `ldap-contacts` : le filtre LDAP réellement appliqué tant que la configuration n'était pas enregistrée différait du défaut affiché dans l'administration - les fallbacks du code sont alignés sur les défauts du schéma.
- Plugin `avatars` : les images mises en cache localement n'expiraient jamais - ajout d'un TTL de 30 jours (rafraîchissement automatique et borne sur `storage/avatars`).
- Plugin `ics-viewer` : les dates de l'aperçu étaient figées au format français pour toutes les langues - elles sont désormais formatées selon la langue de l'interface.

## [0.43.0] - 2026-10-28

### Ajouté

- Rotation automatique des journaux au-delà de `logs.max_size` Mio (`app.log`, `mail.log`, journal d'authentification ; `logs.keep` rotations conservées).
- Enregistrement automatique du brouillon pendant la composition (réglage `compose_autosave`, secondes ; indicateur de dernier enregistrement dans la modale).
- Aperçu des pièces jointes (icône loupe dans l'onglet Pièces jointes) : images bitmap, PDF (iframe - sandbox CSP levée pour ce type seul) et texte brut.
- Plugin two-factor-auth : codes de secours à usage unique (8 codes `xxxx-xxxx` générés à l'activation, régénérables dans l'onglet, hashés en base - saisissables dans le champ TOTP à la connexion).
- Import de contacts au format CSV (Paramètres > Contacts) en plus du vCard : en-tête reconnue ou heuristique de colonnes, séparateur `,` `;` ou tabulation.
- Import de boîtes au format mbox (Paramètres > Dossiers > Importer) en plus du `.eml` : dates `From ` préservées en INTERNALDATE, plafond `import_max_size` (100 Mo, 2000 messages).
- Groupes de contacts : champ "Groupes" (catégories vCard `CATEGORIES` en import/export), badges dans la liste, recherche étendue aux catégories.
- Recherche multi-dossiers : portée "Tous les dossiers" dans la recherche avancée (résultats fusionnés, badge du dossier sur chaque ligne).
- Accusés de réception (RFC 3798) : demande dans les options de composition, bandeau "Envoyer/Ignorer" à la lecture (réglage `mdn_mode`), flag `$MDNSent` posé après envoi.
- Vérification S/MIME : badge cliquable sur les messages signés - signature cryptographique vérifiée via `openssl_pkcs7_verify` (confiance CA si `smime_ca_path` configuré), signataire affiché.
- "Rester connecté" à la connexion (jetons selector:validator persistants 30 j, rotation à l'usage) + onglet "Sessions" dans les Paramètres (appareils, révocation).
- Admin > Logs : bouton "Vider" pour tronquer le fichier de log sélectionné (app/mail/auth), avec confirmation.
- "Tout marquer comme lu" dans le menu d'un dossier (visible s'il reste des non lus).
- Bouton "Archiver" (barre d'actions groupées + lecteur de message) : déplacement direct vers le dossier de rôle archive quand il existe.
- Compteur de non lus de la boîte de réception dans le titre de l'onglet ("(3) MXWebMail").
- Réglage "Après suppression ou déplacement" (Paramètres > Général) : ouvrir le message suivant / précédent ou fermer le lecteur.
- Téléchargement groupé des messages cochés en `.eml` dans un zip (bouton de la barre d'actions groupées, 100 messages maximum).
- Rappel "pièce jointe oubliée" avant envoi quand le corps ou l'objet annonce une pièce jointe absente (citations de réponse ignorées, désactivé si chiffrement OpenPGP).
- Option "Priorité haute" dans le menu Options de la composition (en-tête `X-Priority: 1 (Highest)`).
- Mise à jour robuste : vérification d'intégrité par fichier (`files.sha256` embarqué dans le paquet), contrôle des prérequis (constantes `APP_REQ_PHP`/`APP_REQ_FROM` de `App.php` : version PHP, version source minimale), et détection des fichiers modifiés localement signalée avant application.
- Sauvegarde de la base avant chaque mise à jour : `webmail.sqlite` et les bases contacts sont copiées après checkpoint WAL dans `backup-<ts>/db/` (sous MariaDB, un avertissement rappelle de faire un `mysqldump` - la sauvegarde externe reste requise).
- Finalisation des migrations de base après application d'une mise à jour (bouton/étape "Finaliser" + commande de secours `php app/bin/post-update.php`) ; les bases contacts des utilisateurs sont migrées dans la foulée.
- Rollback assisté depuis "À propos" : restauration des fichiers du dernier backup, suppression des fichiers créés par l'update et recopie des bases SQLite (sous MariaDB, restaurer le dump d'abord puis confirmer).
- Verrou applicatif pendant la copie d'une mise à jour : l'API utilisateur répond 503 et le web sert la page maintenance jusqu'à la fin (expiration automatique après 15 min en cas d'échec).
- Bandeau "schéma de la base plus récent que le code" dans "À propos" quand un rollback fichiers n'a pas été suivi d'une restauration de la base.

### Modifié

- Barre d'actions du lecteur de message allégée : les actions secondaires (transférer en pièce jointe, se désabonner, déplacer, archiver, marquer non lu, imprimer, télécharger .eml, voir le source) sont regroupées dans un menu "…".
- Le zip de release n'embarque plus les fichiers méta du dépôt (`.github`, `docs`, `README.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`) - `LICENSE` reste distribué.
- Paramètres > Filtres : sous-onglets "Filtres" (scripts Sieve) et "Répondeur d'absence", titres internes redondants supprimés ; le champ "Adresses" du répondeur devient une liste à cocher des adresses du compte.

### Corrigé

- `mail.log` : un refus protocolaire du serveur (mot de passe IMAP/SMTP/Sieve erroné, quota absent…) n'écrit plus la pile d'appel complète - une seule ligne `WARNING` avec le message du serveur.
- Admin > Logs : sélections rapides ou "Vider" pendant un chargement ne laissent plus une réponse tardive écraser l'affichage (contenu obsolète ressuscité) ; les erreurs de chargement sont désormais signalées.

## [0.42.0] - 2026-10-27

### Ajouté

- Bouton "Se désabonner" dans le lecteur de message (List-Unsubscribe, one-click RFC 8058, ouverture https/mailto).
- Import de fichiers `.eml` dans un dossier depuis Paramètres > Dossiers > "Importer des .eml" (IMAP APPEND).
- "Transférer en pièce jointe" : le message original est joint en `.eml` (message/rfc822) depuis le menu du bouton Transférer.
- Bouton "Tout télécharger (.zip)" sur l'onglet Pièces jointes d'un message.
- Réglages "Vider la corbeille / le spam à la déconnexion" (Paramètres > Général, avec défauts administrables).
- Répondeur d'absence Sieve (vacation) : carte dédiée dans Paramètres > Filtres (activation, dates, intervalle, sujet, message, adresses) compilée dans le script géré actif.
- Vue conversations : regroupement IMAP THREAD dans la liste des messages (réglage "Vue conversations", expansion des fils en sous-lignes, badge du nombre de messages).
- Réponses types : nouvel onglet Paramètres (CRUD avec éditeur TinyMCE) et insertion dans le compose via le sous-menu "Réponses types" du menu Options.

## [0.41.0] - 2026-10-27

### Ajouté

- Lien vers le dépôt GitHub dans le panel d'administration (navbar + onglet
  "À propos")
- Documentation de la sémantique des `exceptions` des plugins listes
  noire/blanche (`docs/fr/plugins.md`)

## [0.40.0] - 2026-10-27

### Ajouté

- Documentation d'intégration fail2ban (`docs/fr/fail2ban.md`) : filtre `filter.d`,
  jail, rotation logrotate, pièges fuseau horaire et `trusted_proxies`

### Corrigé

- Le journal d'authentification (`auth_logging*`) couvre désormais le login
  admin - succès et échecs, login préfixé `admin:` - et les mauvais codes TOTP
  (`AUTH FAILED (totp)`) : ces tentatives sont visibles par fail2ban

## [0.39.1] - 2026-10-27

### Corrigé

- Erreur 500 sur l'affichage quand un thème sans `version` était sélectionné
- Hôte de base de données validé avant construction du DSN MariaDB
- Noms de pièces jointes contenant des caractères de contrôle neutralisés dans
  l'en-tête de téléchargement
- Guillemets correctement échappés dans la valeur compilée du filtre Sieve
  "commence par"
- Cache négatif de détection de domaine écrit sous verrou
- Emails invalides ignorés à l'enregistrement d'un contact

## [0.39.0] - 2026-10-27

### Modifié

- Catalogue et installation des plugins lus directement dans le dépôt GitHub au
  ref `update.plugins_ref` (`release` = tag de la dernière release par défaut,
  `main` en dev - un push de `plugin.json` publie la mise à jour sans release) ;
  chaque fichier téléchargé via `raw.githubusercontent.com` est vérifié par
  sha1 de blob git (arbre `git/trees` du commit)
- Nouvelle clé `update.plugins_local` (dev/tests) : dossier local de plugins -
  catalogue et installation hors réseau depuis le disque

### Supprimé

- Assets `plugin-<name>-<ver>.zip` (+ `.sha256`) et `plugins.index.json` des
  releases GitHub - le packaging ne produit plus que le zip cœur

## [0.38.0] - 2026-10-27

### Ajouté

- Import/export vCard des contacts (Paramètres > Contacts) : fichiers `.vcf`
  multi-fiches, fusion sur adresse existante, types d'emails préservés
- Plugin `webmail-migration` : migration depuis un dossier `data` RainLoop ou
  SnappyMail (chemin serveur ou upload ZIP) - domaines, carnets d'adresses, identités,
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

- Erreurs de chargement des dossiers IMAP désormais affichées - aucun
  élément ne les présentait, elles étaient silencieuses

## [0.37.4] - 2026-10-26

### Ajouté

- Catalogue des plugins intégré au tableau de l'onglet Extensions (entrées non
  installées en fin de liste, rafraîchissement en haut à droite) - anciennes
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

## [0.36.1] - 2026-10-24

### Corrigé

- 404 sur les assets de release quand le dépôt est privé

## [0.36.0] - 2026-10-24

### Ajouté

- Catalogue des plugins via GitHub Releases : les plugins ne sont plus livrés dans
  le zip cœur - chacun est un asset `plugin-<name>-<ver>.zip` installable, mettable
  à jour et désinstallable depuis l'admin > Extensions (sha256 vérifié, backup à la
  mise à jour, compatibilité `min_app`/`max_app`)
- Compte IMAP par défaut configurable et synchronisation des identifiants de session
- Couleurs de thème extensibles via `config.schema.json` (variantes sombres incluses)

## [0.32.0] - 2026-10-22

### Ajouté

- Compression gzip configurable des réponses (niveau, taille minimale) - fonctionne
  même sans `mod_deflate`
- ETag/Last-Modified et réponses 304 sur les assets thème/plugin et le favicon ;
  TTL `Cache-Control` configurables
- Assets versionnés `/assets/vX.Y.Z/` : cache navigateur d'un an, invalidé
  automatiquement à chaque release
- Cache applicatif fichier (`app/storage/cache`) : DNS, détection de domaine,
  vérification de mise à jour, avec GC
- En-têtes de cache Apache et `mod_deflate` préconfigurés dans `public/.htaccess`
- Onglets "HTTP" et "Cache serveur" dans l'administration

### Modifié

- Licence du projet : GNU AGPL-3.0 (les bibliothèques vendorées conservent leurs
  licences d'origine)

## [0.31.0] - 2026-10-22

Première release publiée de MXWebMail - webmail auto-hébergé en PHP 8.4, installable via l'assistant `/setup/`.

### Ajouté

- Messagerie complète : dossiers IMAP, recherche avancée, tri côté serveur, suivi, quota, ACL de dossiers
- Composition : éditeur HTML (TinyMCE) ou texte, identités multiples, signatures, pièces jointes, comptes IMAP secondaires
- Chiffrement OpenPGP entièrement côté navigateur - les clés privées ne quittent jamais le poste
- Carnet de contacts avec autocomplétion, synchronisation CardDAV et annuaire LDAP
- Filtres Sieve : assistant visuel de règles (modèles dont anti-spam) ou édition de scripts
- Panneau d'administration : domaines (IMAP/SMTP/Sieve), utilisateurs, extensions, thèmes, logs, mises à jour, sécurité
- 15 plugins : 2FA (TOTP), anti-spoofing, avatars, listes noire/blanche, changement de mot de passe, PWA, étiquettes de couleur…
- Multilingue (10 langues) et thèmes extensibles
- Mise à jour en un clic depuis l'onglet "À propos" (GitHub Releases, vérification sha256, backup et rollback automatiques)

### Sécurité

- CSRF sur tous les POST, en-têtes CSP avec nonce, rate-limit du login
- Credentials IMAP chiffrés (XSalsa20-Poly1305, libsodium) en session et en base
- Corps des messages sanitisés ; images externes via proxy anti-SSRF

[Unreleased]: https://github.com/WatT-For/MXWebMail/compare/v0.54.0...HEAD
[0.54.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.54.0
[0.53.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.53.0
[0.52.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.52.0
[0.51.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.51.0
[0.50.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.50.0
[0.49.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.49.0
[0.47.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.47.0
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
