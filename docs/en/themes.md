# Themes

*Translated from French - the French version is authoritative.*

Themes (interface templates) live in `app/templates/<name>/`. Three are bundled:
`default`, `dark` and `midnight` (the latter two inherit from `default`).

- **`default`** - light/dark interface following the OS (`dark_mode: auto`),
  Bootstrap blue accent.
- **`dark`** - same structure as `default` forced to dark: gradient bar,
  tinted active folder with a side border, reduced list density.
- **`midnight`** - "night" identity: teal accent (`#0d9488`/`#2dd4bf`), tinted
  navy palette, softened radii, pill-shaped active folders, teal border under
  the top bar, thin (`regular`) icons and a gradient login page.

## 1. Theme structure

```
app/templates/<name>/
├── theme.json          # manifest
├── config.schema.json  # (optional) theme settings
├── pages/              # PHP views (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # dictionaries (keys prefixed theme.<name>.)
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

- `label` may be a translation key (pass-through otherwise);
- `extends` defines the parent theme (optional).

### `config.schema.json`

Flat format `{key: {label, type, default, options?, help?}}` with types
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Example
(`default`):

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

Values are stored in the `themes` table (`config` column, JSON), edited in the
admin and exposed via CSS variables `--mxwm-*`, a `<html data-dark-mode>`
attribute and an inline script (resolving `auto` through
`prefers-color-scheme`).

The `--mxwm-*` variables of `X_color` keys are emitted under `:root` - kebab-case
name (`folder_active_color` > `--mxwm-folder-active`), declined into:
- `-rgb` (`r, g, b` triplet for `rgba()`);
- `-text`/`-text-rgb` (legible text on that background, contrast computed
  server-side);
- `-hover`/`-active`/`-active-border` (−15 %/−20 %/−25 % shades, PHP equivalent
  of Bootstrap's Sass `shade-color()`);
- `-active-rgb` (−20 % shade as rgb triplet);
- `-tint` (+50 %, focus borders) and `-focus-rgb` (+15 %, button halos).

The template CSS redeclares Bootstrap components - compiled with literal
colors - toward these variables: `btn-primary`/`btn-outline-primary` buttons,
`form-check-input` checkboxes, `form-control`/`form-select`/`btn-close` focus,
link hover and `link-primary`, `progress-bar` follow `primary_color`;
"selected" elements (`.dropdown-item.active`, `.list-group-item.active`,
active `.nav-pills`/`.pagination`) follow `folder_active_color`.

The light color applies in both modes: each `X_color_dark` key re-emits its
variant (shades included) under `[data-bs-theme=dark]` **only if a value is
set** (saved or field default) - otherwise the light color persists in dark
mode. The selector follows `data-bs-theme`, so the variant applies whether
`dark_mode` is `auto` or `dark`.
A `color` field left empty falls back to the field default; for a `*_dark` key
without a default, empty = same color as in light mode.

## 2. Inheritance

The resolution chain is `name > extends > … > default` (implicit if absent).
`default` is always the last resort. Cycle protection and max depth of 8.

- Every file (page, asset, schema) resolves at the **first existing** entry of
  the chain.
- `theme.json` and `langs/` are **not** inherited (each theme has its own).
- Views rendered by the application: `app`, `login`, `admin`, `admin-login`,
  `maintenance` (all provided by `default`, hence inheritable).
- A custom view must echo `<?= $pluginHead ?>` in its `<head>` (after
  `$themeScript`) for plugin `app.head` injections to work there - see
  `default/pages/app.php`. Without it, HTML from plugins listening on
  `app.head` is collected then silently ignored on that page.

Example: `dark` and `midnight` only provide `assets/app.css` and their
`theme.json`/schema; everything else (pages, dictionaries) is taken from
`default`.

## 3. Creating a theme

1. Create `app/templates/<name>/theme.json` (optionally with `extends`).
2. Add missing views in `pages/` (otherwise inherited) and `assets/app.css`.
3. Add `config.schema.json` for settings and `langs/<lang>.php` for labels
   (`theme.<name>.` keys; schema `label`/`help`/options accept these keys).

## 4. Theme administration

In the admin (*Themes*):

- set the **default theme** (writes `user_defaults.theme`);
- **allow** a theme for users (`allowed`) - the default theme always stays
  selectable;
- **configure** each theme (modal generated from `config.schema.json`).

The user picks their theme via the `theme` setting; the CSS is applied hot.
