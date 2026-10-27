# Changelog

Toutes les modifications notables du projet sont listées ici — source unique dont
découlent les notes de release GitHub et le corps des commits de version.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) —
semver `0.MINOR.PATCH` en développement.
Catégories, dans l'ordre : `Sécurité`, `Ajouté`, `Modifié`, `Corrigé`, `Obsolète`, `Supprimé`.

## [Unreleased]

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

[Unreleased]: https://github.com/WatT-For/MXWebMail/compare/v0.37.4...HEAD
[0.37.4]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.37.4
[0.36.1]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.36.1
[0.36.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.36.0
[0.32.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.32.0
[0.31.0]: https://github.com/WatT-For/MXWebMail/releases/tag/v0.31.0
