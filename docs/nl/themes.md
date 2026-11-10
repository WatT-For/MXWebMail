# Thema's

*Vertaald uit het Frans - de Franse versie is leidend.*

Thema's (interface-templates) leven in `app/templates/<name>/`. Drie worden
meegeleverd: `default`, `dark` en `midnight` (de laatste twee erven van
`default`).

- **`default`** - lichte/donkere interface volgens het OS (`dark_mode: auto`),
  blauw Bootstrap-accent.
- **`dark`** - zelfde structuur als `default` maar geforceerd donker:
  verloopbalk, getinte actieve map met zijrand, verlaagde lijstdichtheid.
- **`midnight`** - "nacht"-identiteit: teal-accent (`#0d9488`/`#2dd4bf`),
  getint navy-palet, verzachte radii, pilvormige actieve mappen, teal-rand
  onder de bovenbalk, dunne (`regular`) iconen en loginpagina met verloop.

## 1. Opbouw van een thema

```
app/templates/<name>/
├── theme.json          # manifest
├── config.schema.json  # (optioneel) thema-instellingen
├── pages/              # PHP-views (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # woordenboeken (sleutels met prefix theme.<name>.)
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

- `label` kan een vertaalsleutel zijn (anders pass-through);
- `extends` definieert het ouder-thema (optioneel).

### `config.schema.json`

Plat formaat `{sleutel: {label, type, default, options?, help?}}` met types
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Voorbeeld
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

De waarden worden opgeslagen in de tabel `themes` (kolom `config`, JSON),
bewerkt in de admin en blootgesteld via CSS-variabelen `--mxwm-*`, een
`<html data-dark-mode>`-attribuut en een inline script (resolutie van `auto`
via `prefers-color-scheme`).

De `--mxwm-*`-variabelen van `X_color`-sleutels worden uitgegeven onder
`:root` - naam in kebab-case (`folder_active_color` > `--mxwm-folder-active`),
afgeleid in:
- `-rgb` (`r, g, b`-triplet voor `rgba()`);
- `-text`/`-text-rgb` (leesbare tekst op die achtergrond, contrast
  server-side berekend);
- `-hover`/`-active`/`-active-border` (−15 %/−20 %/−25 % tinten, PHP-equivalent
  van Bootstrap's Sass `shade-color()`);
- `-active-rgb` (−20 % tint als rgb-triplet);
- `-tint` (+50 %, focus-randen) en `-focus-rgb` (+15 %, knop-halo's).

De template-CSS verklaart de Bootstrap-componenten - gecompileerd met
letterlijke kleuren - opnieuw naar deze variabelen:
`btn-primary`/`btn-outline-primary`-knoppen, `form-check-input`-checkboxes,
focus van `form-control`/`form-select`/`btn-close`, link-hover en
`link-primary`, `progress-bar` volgen `primary_color`; "geselecteerde"
elementen (`.dropdown-item.active`, `.list-group-item.active`, actieve
`.nav-pills`/`.pagination`) volgen `folder_active_color`.

De lichte kleur geldt in beide modi: elke `X_color_dark`-sleutel geeft zijn
variant (tinten inbegrepen) opnieuw uit onder `[data-bs-theme=dark]` **alleen
als er een waarde is ingesteld** (opgeslagen of veld-standaard) - anders
blijft de lichte kleur in het donker bestaan. De selector volgt
`data-bs-theme`, dus de variant geldt of `dark_mode` nu `auto` of `dark` is.
Een leeg gelaten `color`-veld valt terug op de veld-standaard; voor een
`*_dark`-sleutel zonder standaard betekent leeg = zelfde kleur als in lichte
modus.

## 2. Overerving

De resolutieketen is `name > extends > … > default` (impliciet indien
afwezig). `default` is altijd het laatste redmiddel. Anti-cyclus en maximale
diepte 8.

- Elk bestand (pagina, asset, schema) wordt opgelost bij de **eerst
  bestaande** van de keten.
- `theme.json` en `langs/` worden **niet** geërfd (elk thema heeft de zijne).
- Door de applicatie gerenderde views: `app`, `login`, `admin`,
  `admin-login`, `maintenance` (alle geleverd door `default`, dus erfbaar).
- Een aangepaste view moet `<?= $pluginHead ?>` in zijn `<head>` echoën (na
  `$themeScript`) opdat de `app.head`-injecties van plugins daar werken -
  zie `default/pages/app.php`. Zonder dit wordt de HTML van de op `app.head`
  luisterende plugins verzameld en vervolgens stil genegeerd op die pagina.

Voorbeeld: `dark` en `midnight` leveren alleen `assets/app.css` en hun
`theme.json`/schema; al het andere (pagina's, woordenboeken) wordt van
`default` overgenomen.

## 3. Een thema maken

1. Maak `app/templates/<name>/theme.json` (eventueel met `extends`).
2. Voeg ontbrekende views toe in `pages/` (anders geërfd) en `assets/app.css`.
3. Voeg `config.schema.json` toe voor instellingen en `langs/<lang>.php` voor
   labels (`theme.<name>.`-sleutels; `label`/`help`/opties van het schema
   accepteren deze sleutels).

## 4. Thema-beheer

In de admin (*Thema's*):

- het **standaardthema** instellen (schrijft `user_defaults.theme`);
- een thema voor gebruikers **toestaan** (`allowed`) - het standaardthema
  blijft altijd selecteerbaar;
- elk thema **configureren** (modaal gegenereerd uit `config.schema.json`).

De gebruiker kiest zijn thema via de instelling `theme`; de CSS wordt direct
toegepast.
