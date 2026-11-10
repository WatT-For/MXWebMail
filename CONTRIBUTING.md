# Contribuer à MXWebMail

*English: contributions are welcome via fork + pull request against `main`.
Discussions may be held in French or English. Essentials: keep PRs focused,
update `docs/` when behavior changes, never commit secrets or credentials.
All contributions are licensed under the AGPL-3.0 - see below for details.*

Merci de l'intérêt porté au projet ! Ce document décrit comment contribuer
sereinement.

## Ce qui est bienvenu

- **Correctifs** de bugs (cœur, plugins, thèmes) - directement en PR ;
- **Plugins et thèmes** : le cœur est conçu pour être étendu sans modification -
  voir [docs/fr/plugins.md](docs/fr/plugins.md) et [docs/fr/themes.md](docs/fr/themes.md) ;
- **Traductions** - voir [docs/fr/localisation.md](docs/fr/localisation.md) ;
- **Documentation** (`docs/`, README) ;
- **Fonctionnalités du cœur** : ouvrez d'abord une **issue** pour discuter de
  l'approche - cela évite le travail perdu sur une direction non retenue.

## Processus

1. Forkez le dépôt, créez une branche depuis `main`
   (`feature/…`, `fix/…` ou `docs/…`).
2. Développez des changements **focalisés** : une PR = un sujet.
3. Testez localement sur une instance réelle - l'installation standard suffit
   ([docs/fr/installation.md](docs/fr/installation.md)) : le dépôt est complet tel
   quel, aucune dépendance à installer.
4. Ouvrez la PR vers `main` et remplissez le template proposé.
5. Le mainteneur effectue la revue, les vérifications statiques et les tests
   avant merge ; les retours éventuels sont discutés dans la PR.

## Conventions

- **Stack** : PHP 8.4, Knockout.js, Bootstrap 5.3 - **pas de jQuery** ; MariaDB
  et SQLite doivent rester tous deux supportés.
- **Bootstrap** : un seul composant JS Bootstrap par élément - ne pas ajouter de
  second composant (tooltip, popover…) sur un élément portant déjà
  `data-bs-toggle` ; le mettre sur un élément enfant.
- **Plugins** : `plugin.json` avec `version` semver et compatibilité
  `min_app`/`max_app` - voir [docs/fr/plugins.md](docs/fr/plugins.md).
- **Docs synchronisées** : tout changement de comportement, de config ou de
  rendu doit se refléter dans `docs/` dans la même PR.
- **Changelog** : l'entrée `CHANGELOG.md` est rédigée par le mainteneur au
  moment du merge ; vous pouvez suggérer une puce dans la description de la PR.
- **Jamais de secrets** : aucun credential, token, `config.php` réel ou donnée
  d'`app/storage/` dans le dépôt.

## Sécurité

Ne signalez **jamais** une vulnérabilité dans une issue ou une PR publique :
suivez [SECURITY.md](SECURITY.md).

## Licence

Toute contribution est publiée sous **GNU AGPL-3.0** ([LICENSE](LICENSE)),
comme le reste du projet. En soumettant une PR, vous acceptez ce principe et
déclarez avoir le droit de publier le code soumis.
