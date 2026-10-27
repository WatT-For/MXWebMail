# Changelog

Toutes les modifications notables du projet sont listées ici — source unique dont
découlent les notes de release GitHub et le corps des commits de version.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) —
semver `0.MINOR.PATCH` en développement.
Catégories, dans l'ordre : `Sécurité`, `Ajouté`, `Modifié`, `Corrigé`, `Obsolète`, `Supprimé`.

## [Unreleased]

### Ajouté

- Plugin `snappymail-import` : import des données SnappyMail (domaines, utilisateurs, contacts, identités)

## [0.37.4] — 2026-10-26

### Ajouté

- Catalogue des plugins dans le tableau d'administration
- Signatures et identités dans la composition

### Modifié

- Autologout désarmable dans les réglages

### Corrigé

- Focus TinyMCE perdu en fenêtre modale
- z-index du menu des dossiers

## [0.36.1] — 2026-10-24

### Corrigé

- 404 sur les assets de release quand le dépôt est privé

## [0.36.0] — 2026-10-24

### Ajouté

- Catalogue des plugins via GitHub Releases
- Compte IMAP par défaut
- Couleurs de thème extensibles

## [0.32.0] — 2026-10-22

### Modifié

- Performances HTTP
- Licence AGPL-3.0

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
