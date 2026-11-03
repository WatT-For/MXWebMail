# Traduction / langues

## 1. Fonctionnement

La localisation repose sur des dictionnaires PHP `clé => texte` :

- cœur : `app/src/MXWebMail/Locale/<lang>.php` ;
- plugins : `app/plugins/<name>/langs/<lang>.php` (clés préfixées `plugin.<name>.`) ;
- thèmes : `app/templates/<name>/langs/<lang>.php` (clés préfixées `theme.<name>.`).

Le traducteur fusionne ces sources (le cœur d'abord, puis plugins, puis thèmes - ces
derniers écrasent en cas de collision), avec l'anglais (`en`) comme **langue de base**
(fallback) puis la langue active. `t('cle', $params)` fait l'interpolation `{param}` ;
une clé inconnue est renvoyée telle quelle.

## 2. Langues installées

10 langues sont disponibles : **de, en, es, fr, it, nl, pl, pt, ru, tr**. La langue de
base (fallback) est **l'anglais** : `app/src/MXWebMail/Locale/en.php` doit rester exhaustif.

## 3. Choix de la langue

`language` dans `config.php` (ou admin > *Général*) :

- `auto` (défaut) : en-tête `Accept-Language` du navigateur ;
- sinon le code d'une langue installée (`fr`, `en`…).

Repli final : `en`. Le frontend reçoit le dictionnaire complet dans `window.MXWM.i18n`
(l'utilise via `i18n.js`).

## 4. Ajouter une langue

1. Créez `app/src/MXWebMail/Locale/<code>.php` : `return ['cle' => 'texte', …]`.
2. (Recommandé) ajoutez les dictionnaires des plugins et thèmes :
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. La langue apparaît automatiquement dans le sélecteur admin (liste = fichiers présents
   dans `Locale/`).

Aucun enregistrement central à modifier : la présence du fichier suffit.

## 5. Côté PHP et JS

- PHP : `$this->t('cle')` dans les contrôleurs/plugins ; `t('cle')` dans les templates.
- JS : `ctx.t('cle')` dans `mount(element, ctx)` des plugins ; `t('cle')` via `i18n.js`.

Les `label`/`help`/`description` des manifestes (`plugin.json`, `theme.json`) et des
schémas (`config.schema.json`) acceptent des clés de traduction (pass-through si ce n'est
pas une clé connue).

## 6. Parité des dictionnaires

L'anglais est la langue de base : toute nouvelle clé doit d'abord être ajoutée dans
`en.php`, et chaque dictionnaire doit rester à parité avec `en`.