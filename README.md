# MXWebMail

Webmail auto-hébergé en **PHP 8.4**, inspiré de **SnappyMail**, **RainLoop** et
**Roundcube**, autour d'une architecture MVC claire. Frontend **Knockout + Bootstrap 5.3** (sans jQuery), bibliothèque
**MailSo** vendorée pour IMAP/SMTP/Sieve, stockage **SQLite** ou **MariaDB**.

## Fonctionnalités

- **Messagerie complète** : dossiers IMAP, recherche avancée, tri côté serveur (SORT),
  sélection multiple, suivi, spam, quota, ACL de dossiers.
- **Composition** : éditeur HTML (TinyMCE) ou texte, identités multiples, signatures,
  brouillons, pièces jointes, comptes IMAP secondaires.
- **Chiffrement OpenPGP** entièrement côté navigateur (OpenPGP.js) - les clés privées ne
  quittent jamais le poste.
- **Carnet de contacts** avec autocomplétion, synchronisation CardDAV et annuaire LDAP.
- **Filtres Sieve** : assistant visuel de règles (modèles dont anti-spam) ou édition
  de scripts, activation, suppression.
- **Panneau d'administration** : domaines (IMAP/SMTP/Sieve), utilisateurs, extensions,
  thèmes, logs, mises à jour, paramètres de sécurité.
- **15 plugins** installables depuis l'admin (catalogue lu dans le dépôt GitHub :
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
voir **[docs/installation.md](docs/installation.md)**.

## Documentation

| Sujet | Fichier |
|---|---|
| Installation et déploiement | [docs/installation.md](docs/installation.md) |
| Référence de configuration (`config.php`) | [docs/configuration.md](docs/configuration.md) |
| Panneau d'administration | [docs/administration.md](docs/administration.md) |
| Configuration des domaines / serveurs mail | [docs/domaines.md](docs/domaines.md) |
| Plugins (catalogue GitHub + développement) | [docs/plugins.md](docs/plugins.md) |
| Thèmes | [docs/themes.md](docs/themes.md) |
| Traduction / langues | [docs/localisation.md](docs/localisation.md) |
| Base de données | [docs/base-de-donnees.md](docs/base-de-donnees.md) |
| Sécurité | [docs/securite.md](docs/securite.md) |
| Développement (architecture, hooks) | [docs/developpement.md](docs/developpement.md) |
| Dépannage / FAQ | [docs/depannage.md](docs/depannage.md) |

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
sabre/vobject (vCard) · MariaDB 10.11 / SQLite · Apache ou Nginx.

## Licence

**GNU AGPL-3.0** (`AGPL-3.0-only`) - voir [LICENSE](LICENSE).
Copyright (C) 2025-2026 MXWebMail.

Les bibliothèques vendorées conservent leurs licences d'origine
(MailSo : MIT, Sabre : BSD-3-Clause, TinyMCE : GPL-2.0+).

Contribuer : voir [CONTRIBUTING.md](CONTRIBUTING.md) - toute participation est soumise
au [code de conduite](CODE_OF_CONDUCT.md).