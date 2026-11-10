# Thèmes

Les thèmes (templates d'interface) vivent dans `app/templates/<name>/`. Trois sont livrés :
`default`, `dark` et `midnight` (les deux derniers héritent de `default`).

- **`default`** - interface claire/sombre selon l'OS (`dark_mode: auto`), accent bleu Bootstrap.
- **`dark`** - même structure que `default` forcée en sombre : barre en dégradé, dossier actif
  teinté avec liseré latéral, densité de liste réduite.
- **`midnight`** - identité "nuit" : accent teal (`#0d9488`/`#2dd4bf`), palette navy teintée,
  rayons adoucis, dossiers actifs en pilules, liseré teal sous la barre supérieure,
  icônes fines (`regular`) et page de connexion en dégradé.

## 1. Structure d'un thème

```
app/templates/<name>/
├── theme.json          # manifeste
├── config.schema.json  # (optionnel) réglages du thème
├── pages/              # vues PHP (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # dictionnaires (clés préfixées theme.<name>.)
```

### `theme.json`

```json
{
	"name": "dark",
	"label": "Sombre",
	"version": "0.13.0",
	"extends": "default"
}
```

- `label` peut être une clé de traduction (pass-through sinon) ;
- `extends` définit le thème parent (facultatif).

### `config.schema.json`

Format plat `{clé: {label, type, default, options?, help?}}` avec types `string`, `text`,
`integer`, `number`, `bool`, `enum`, `color`. Exemple (`default`) :

```json
{
	"dark_mode": { "type": "enum", "options": {"auto": "…", "light": "…", "dark": "…"}, "default": "auto" },
	"primary_color": { "type": "color", "default": "#0d6efd" },
	"link_color": { "type": "color", "default": "#0d6efd" },
	"header_color": { "type": "color", "default": "#0d6efd" },
	"folder_active_color": { "type": "color", "default": "#0d6efd" },
	"primary_color_dark": { "type": "color", "default": "" },
	"link_color_dark": { "type": "color", "default": "" },
	"header_color_dark": { "type": "color", "default": "" },
	"folder_active_color_dark": { "type": "color", "default": "" }
}
```

Les valeurs sont stockées en table `themes` (colonne `config`, JSON), éditées dans l'admin
et exposées via des variables CSS `--mxwm-*`, un attribut `<html data-dark-mode>`
et un script inline (résolution de `auto` via `prefers-color-scheme`).

Les variables `--mxwm-*` des clés `X_color` sont émises sous `:root` - nom en
kebab-case (`folder_active_color` > `--mxwm-folder-active`), déclinées en :
- `-rgb` (triplet `r, g, b` pour les `rgba()`) ;
- `-text`/`-text-rgb` (texte lisible sur ce fond, contraste calculé côté serveur) ;
- `-hover`/`-active`/`-active-border` (teintes −15 %/−20 %/−25 %, équivalent PHP
  du `shade-color()` Sass de Bootstrap) ;
- `-active-rgb` (teinte −20 % en triplet rgb) ;
- `-tint` (+50 %, bordures de focus) et `-focus-rgb` (+15 %, halos de boutons).

Le CSS du template redéclare les composants Bootstrap - compilés avec des
couleurs littérales - vers ces variables : boutons `btn-primary`/`btn-outline-primary`,
cases `form-check-input`, focus de `form-control`/`form-select`/`btn-close`,
survol des liens et `link-primary`, `progress-bar` suivent `primary_color` ;
les éléments "sélectionnés" (`.dropdown-item.active`, `.list-group-item.active`,
`.nav-pills`/`.pagination` actives) suivent `folder_active_color`.

La couleur claire s'applique dans les deux modes : chaque clé
`X_color_dark` ré-émet sa variante (teintes comprises) sous `[data-bs-theme=dark]`
**uniquement si une valeur est définie** (enregistrée ou défaut du champ) - sinon
la couleur claire persiste en sombre. Le sélecteur suit `data-bs-theme`, donc la
variante s'applique que `dark_mode` soit `auto` ou `dark`.
Un champ `color` laissé vide retombe sur le défaut du champ ; pour une clé
`*_dark` sans défaut, vide = même couleur qu'en mode clair.

## 2. Héritage

La chaîne de résolution est `name > extends > … > default` (implicite si absent).
`default` est toujours le dernier recours. Anti-cycle et profondeur max 8.

- Tout fichier (page, asset, schéma) est résolu au **premier existant** de la chaîne.
- `theme.json` et `langs/` ne sont **pas** hérités (chaque thème a les siens).
- Vues rendues par l'application : `app`, `login`, `admin`, `admin-login`,
  `maintenance` (toutes fournies par `default`, donc héritables).
- Une vue personnalisée doit échapper `<?= $pluginHead ?>` dans son `<head>`
  (après `$themeScript`) pour que les injections `app.head` des plugins y
  fonctionnent - cf. `default/pages/app.php`. Sans lui, le HTML des plugins
  écoutant `app.head` est collecté puis silencieusement ignoré sur cette page.

Exemple : `dark` et `midnight` ne fournissent que `assets/app.css` et leur
`theme.json`/schéma ; tout le reste (pages, dictionnaires) est repris de `default`.

## 3. Créer un thème

1. Créez `app/templates/<name>/theme.json` (avec éventuellement `extends`).
2. Ajoutez les vues manquantes dans `pages/` (sinon héritées) et `assets/app.css`.
3. Ajoutez `config.schema.json` pour les réglages et `langs/<lang>.php` pour les labels
   (clés `theme.<name>.` ; `label`/`help`/options du schéma acceptent ces clés).

## 4. Administration des thèmes

Dans l'admin (*Thèmes*) :

- définir le **thème par défaut** (écrit `user_defaults.theme`) ;
- **autoriser** un thème pour les utilisateurs (`allowed`) - le thème par défaut reste
  toujours sélectionnable ;
- **configurer** chaque thème (modale depuis `config.schema.json`).

L'utilisateur choisit son thème via le réglage `theme` ; le CSS est appliqué à chaud.

