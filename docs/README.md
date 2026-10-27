# Documentation MXWebMail

Documentation destinée aux **administrateurs** qui installent, déploient et exploitent
MXWebMail, ainsi qu'aux **développeurs** qui étendent le projet (plugins, thèmes, cœur).

> Le `README.md` à la racine sert uniquement de **vitrine GitHub**. Toute la documentation
> technique vit ici, dans `docs/`.

## Parcours conseillé

- **Installer** : [installation.md](installation.md)
- **Configurer** : [configuration.md](configuration.md)
- **Administrer au quotidien** : [administration.md](administration.md)
- **Brancher les serveurs mail** : [domaines.md](domaines.md)
- **Dépanner** : [depannage.md](depannage.md)

## Sommaire

| Fichier | Contenu |
|---|---|
| [installation.md](installation.md) | Prérequis, assistant `/setup/`, installation manuelle, déploiement Apache/Nginx, étapes post-installation. |
| [configuration.md](configuration.md) | Référence complète des clés de `app/config/config.php` (valeurs par défaut et rôle). |
| [administration.md](administration.md) | Guide du panneau d'administration : sections, utilisateurs, logs, mises à jour. |
| [domaines.md](domaines.md) | Configuration des serveurs IMAP/SMTP/Sieve par domaine, domaine `*`, autodétection. |
| [plugins.md](plugins.md) | Les 15 plugins livrés (rôle, configuration) + création d'un plugin (hooks, API, schéma). |
| [themes.md](themes.md) | Système de thèmes (`app/templates/`), héritage, création d'un thème. |
| [localisation.md](localisation.md) | Langues disponibles, ajout d'une langue, dictionnaires plugins/thèmes. |
| [base-de-donnees.md](base-de-donnees.md) | Schéma, migrations, SQLite ↔ MariaDB, `app.key`, bases par utilisateur. |
| [securite.md](securite.md) | Modèle de sécurité et recommandations de durcissement. |
| [fail2ban.md](fail2ban.md) | Intégration fail2ban : journal d'authentification, filtre, jail, logrotate. |
| [developpement.md](developpement.md) | Architecture, autoloader, hooks du cœur. |
| [depannage.md](depannage.md) | Erreurs courantes, journaux, FAQ. |

## Conventions

- Les chemins sont relatifs à la racine du projet.
- Les valeurs par défaut citées correspondent à `app/config/config.example.php` et au code
  (`app/src/MXWebMail/…`), version `0.40.0`.
- Cette documentation décrit la **réalité du code** : si un fichier diverge, corrigez-le.