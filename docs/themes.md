# Thèmes

Les thèmes (templates d'interface) vivent dans `app/templates/<name>/`. Deux sont livrés :
`default` et `dark` (qui hérite de `default`).

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
	"version": "0.11.0",
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
	"icon_style": { "type": "enum", "options": {"solid": "…", "regular": "…"}, "default": "solid" },
	"primary_color": { "type": "color", "default": "#0d6efd" },
	"link_color": { "type": "color", "default": "#0d6efd" },
	"primary_color_dark": { "type": "color", "default": "" },
	"link_color_dark": { "type": "color", "default": "" }
}
```

Les valeurs sont stockées en table `themes` (colonne `config`, JSON), éditées dans l'admin
et exposées via des variables CSS `--mxwm-*`, des attributs `<html data-dark-mode
data-icon-style>` et un script inline (résolution de `auto` via `prefers-color-scheme`).

Les variables `--mxwm-*` des clés `*_color` sont émises sous `:root` ; chaque clé
`*_color_dark` produit sa variante sous `[data-bs-theme=dark]` (le sélecteur suit
`data-bs-theme`, donc la variante s'applique que `dark_mode` soit `auto` ou `dark`).
Un champ `color` laissé vide retombe sur le défaut clair ; pour les `*_dark`, vide
= teinte éclaircie automatique (40 % de blanc, comme `tint-color()` de Bootstrap —
ex. `#0d6efd` → `#6ea8fe`).

## 2. Héritage

La chaîne de résolution est `name → extends → … → default` (implicite si absent).
`default` est toujours le dernier recours. Anti-cycle et profondeur max 8.

- Tout fichier (page, asset, schéma) est résolu au **premier existant** de la chaîne.
- `theme.json` et `langs/` ne sont **pas** hérités (chaque thème a les siens).
- Vues rendues par l'application : `app`, `login`, `admin`, `admin-login`,
  `maintenance` (toutes fournies par `default`, donc héritables).

Exemple : `dark` ne fournit que `assets/app.css` et son `theme.json`/schéma ; tout le reste
est repris de `default`.

## 3. Créer un thème

1. Créez `app/templates/<name>/theme.json` (avec éventuellement `extends`).
2. Ajoutez les vues manquantes dans `pages/` (sinon héritées) et `assets/app.css`.
3. Ajoutez `config.schema.json` pour les réglages et `langs/<lang>.php` pour les labels
   (clés `theme.<name>.` ; `label`/`help`/options du schéma acceptent ces clés).

## 4. Administration des thèmes

Dans l'admin (*Thèmes*) :

- définir le **thème par défaut** (écrit `user_defaults.theme`) ;
- **autoriser** un thème pour les utilisateurs (`allowed`) — le thème par défaut reste
  toujours sélectionnable ;
- **configurer** chaque thème (modale depuis `config.schema.json`).

L'utilisateur choisit son thème via le réglage `theme` ; le CSS est appliqué à chaud.

