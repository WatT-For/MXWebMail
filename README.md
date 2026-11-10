# MXWebMail

[![Licence AGPL-3.0](https://img.shields.io/badge/licence-AGPL--3.0-blue.svg)](LICENSE)
[![PHP >= 8.4](https://img.shields.io/badge/PHP-%3E%3D8.4-777BB4)](docs/fr/installation.md)
[![GitHub release](https://img.shields.io/github/v/release/WatT-For/MXWebMail)](https://github.com/WatT-For/MXWebMail/releases)

Webmail auto-hébergé en **PHP 8.4**, inspiré de **SnappyMail**, **RainLoop** et
**Roundcube**, autour d'une architecture MVC claire. Frontend **Knockout + Bootstrap 5.3** (sans jQuery), bibliothèque
**MailSo** vendorée pour IMAP/SMTP/Sieve, stockage **SQLite**, **MariaDB** ou **MySQL**.
Site officiel : [mxrelay.fr/webmail](https://mxrelay.fr/webmail/).

## Fonctionnalités

- **Messagerie complète** : dossiers IMAP, recherche avancée, tri côté serveur (SORT),
  sélection multiple, suivi, spam, quota, ACL de dossiers.
- **Composition** : éditeur HTML TinyMCE (partie texte générée automatiquement),
  identités multiples, signatures, brouillons, pièces jointes, comptes IMAP secondaires.
- **Chiffrement OpenPGP** entièrement côté navigateur (OpenPGP.js) - les clés privées
  sont stockées protégées par passphrase et jamais déchiffrées côté serveur.
- **Carnet de contacts** avec autocomplétion - synchronisation CardDAV et annuaire
  LDAP via plugins.
- **Filtres Sieve** : assistant visuel de règles (modèles dont anti-spam) ou édition
  de scripts, activation, suppression.
- **Panneau d'administration** : domaines (IMAP/SMTP/Sieve), utilisateurs, extensions,
  thèmes, logs, mises à jour, réglages de performance et de sécurité.
- **30 plugins** installables depuis l'admin (catalogue lu dans le dépôt GitHub :
  installation, mise à jour, désinstallation) : 2FA (TOTP), anti-spoofing, avatars, listes
  noire/blanche, changement de mot de passe (poppassd/PDO/LDAP/Mailcow), PWA,
  étiquettes de couleur, etc.
- **Multilingue** (de, en, es, fr, it, nl, pl, pt, ru, tr) et thèmes extensibles.

## Prérequis

- PHP ≥ 8.4 - extensions : `mbstring`, `intl`, `sodium`, `openssl`, `ctype`, `json`,
  `iconv`, `zlib`, `xml`, `dom`, et `pdo_sqlite` (ou `pdo_mysql`). `ldap` optionnel.
- Un serveur **IMAP** (+ SMTP, et ManageSieve en option).
- **Aucune dépendance à installer** : le dépôt est complet (autoloader maison, libs vendorées).

## Installation rapide

1. Pointez le document root du vhost sur `public/`.
2. Ouvrez `https://webmail.example.com/` : l'assistant `/setup/` se lance (tant que
   `app/storage/INSTALLED` n'existe pas), vérifie les prérequis, écrit la configuration,
   crée le schéma de base et pose le verrou - `/setup` répond ensuite 404.

Détails (Apache/Nginx, installation manuelle, sortie de `app/` hors racine web) :
voir **[docs/fr/installation.md](docs/fr/installation.md)**.

## Documentation

| Sujet | Fichier |
|---|---|
| Installation et déploiement | [docs/fr/installation.md](docs/fr/installation.md) |
| Référence de configuration (`config.php`) | [docs/fr/configuration.md](docs/fr/configuration.md) |
| Panneau d'administration | [docs/fr/administration.md](docs/fr/administration.md) |
| Configuration des domaines / serveurs mail | [docs/fr/domaines.md](docs/fr/domaines.md) |
| Plugins (catalogue GitHub + développement) | [docs/fr/plugins.md](docs/fr/plugins.md) |
| Thèmes | [docs/fr/themes.md](docs/fr/themes.md) |
| Traduction / langues | [docs/fr/localisation.md](docs/fr/localisation.md) |
| Base de données | [docs/fr/base-de-donnees.md](docs/fr/base-de-donnees.md) |
| Sécurité | [docs/fr/securite.md](docs/fr/securite.md) |
| Fail2ban (journal auth, filtre, jail) | [docs/fr/fail2ban.md](docs/fr/fail2ban.md) |
| Développement (architecture, hooks) | [docs/fr/developpement.md](docs/fr/developpement.md) |
| Dépannage / FAQ | [docs/fr/depannage.md](docs/fr/depannage.md) |

Index complet : [docs/README.md](docs/README.md).

## Sécurité

- CSRF sur tous les POST, en-têtes CSP (nonce) + `Sec-Fetch-*`, rate-limit du login.
- Credentials IMAP chiffrés (XSalsa20-Poly1305, libsodium secretbox) en session et en base.
- Corps des messages sanitisés (allowlist) et rendus inline ; images externes via proxy
  avec protection anti-SSRF.
- Durcissement serveur : document root = `public/`, dossiers privés protégés par `.htaccess`.

Signalement de vulnérabilités : **jamais en issue publique** - voir
[SECURITY.md](SECURITY.md).

## Stack technique

PHP 8.4 · Knockout.js · Bootstrap 5.3 · TinyMCE · OpenPGP.js · MailSo (vendoré) ·
Sabre (vobject, xml, uri) · MariaDB / MySQL ou SQLite · Apache ou Nginx.

## Licence

**GNU AGPL-3.0** (`AGPL-3.0-only`) - voir [LICENSE](LICENSE).
Copyright (C) 2025-2026 MXWebMail.

Les bibliothèques vendorées conservent leurs licences d'origine
(MailSo : MIT, Sabre : BSD-3-Clause, TinyMCE : GPL-2.0+).

Contribuer : voir [CONTRIBUTING.md](CONTRIBUTING.md) - toute participation est soumise
au [code de conduite](CODE_OF_CONDUCT.md).