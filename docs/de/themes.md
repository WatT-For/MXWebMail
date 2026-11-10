# Themes

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Themes (Oberflächen-Templates) liegen in `app/templates/<name>/`. Drei werden
mitgeliefert: `default`, `dark` und `midnight` (die letzten beiden erben von
`default`).

- **`default`** - helle/dunkle Oberfläche je nach OS (`dark_mode: auto`), blauer
  Bootstrap-Akzent.
- **`dark`** - gleiche Struktur wie `default`, auf dunkel erzwungen:
  Farbverlaufsleiste, eingefärbter aktiver Ordner mit seitlichem Rand,
  reduzierte Listendichte.
- **`midnight`** - "Nacht"-Identität: teal-Akzent (`#0d9488`/`#2dd4bf`),
  getönte Navy-Palette, abgerundete Ecken, pillenförmige aktive Ordner,
  teal-Rand unter der oberen Leiste, dünne (`regular`) Icons und
  Login-Seite mit Farbverlauf.

## 1. Aufbau eines Themes

```
app/templates/<name>/
├── theme.json          # Manifest
├── config.schema.json  # (optional) Theme-Einstellungen
├── pages/              # PHP-Views (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # Wörterbücher (Schlüssel mit Präfix theme.<name>.)
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

- `label` kann ein Übersetzungsschlüssel sein (sonst Pass-through);
- `extends` definiert das Eltern-Theme (optional).

### `config.schema.json`

Flaches Format `{key: {label, type, default, options?, help?}}` mit den Typen
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Beispiel
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

Die Werte werden in der Tabelle `themes` gespeichert (Spalte `config`, JSON),
im Admin bearbeitet und über CSS-Variablen `--mxwm-*`, ein
`<html data-dark-mode>`-Attribut und ein Inline-Skript bereitgestellt
(Auflösung von `auto` über `prefers-color-scheme`).

Die `--mxwm-*`-Variablen der `X_color`-Schlüssel werden unter `:root` emittiert
- Name in Kebab-Case (`folder_active_color` > `--mxwm-folder-active`), abgeleitet
in:
- `-rgb` (`r, g, b`-Triplett für `rgba()`);
- `-text`/`-text-rgb` (lesbarer Text auf diesem Hintergrund, Kontrast
  serverseitig berechnet);
- `-hover`/`-active`/`-active-border` (−15 %/−20 %/−25 % Schattierungen,
  PHP-Äquivalent des Sass-`shade-color()` von Bootstrap);
- `-active-rgb` (−20 %-Schattierung als rgb-Triplett);
- `-tint` (+50 %, Fokus-Ränder) und `-focus-rgb` (+15 %, Button-Halos).

Das Template-CSS deklariert die Bootstrap-Komponenten - mit literarischen
Farben kompiliert - auf diese Variablen um: `btn-primary`/`btn-outline-primary`-Buttons,
`form-check-input`-Checkboxen, Fokus von
`form-control`/`form-select`/`btn-close`, Link-Hover und `link-primary`,
`progress-bar` folgen `primary_color`; "ausgewählte" Elemente
(`.dropdown-item.active`, `.list-group-item.active`, aktive
`.nav-pills`/`.pagination`) folgen `folder_active_color`.

Die helle Farbe gilt in beiden Modi: jeder `X_color_dark`-Schlüssel emittiert
seine Variante (Schattierungen inbegriffen) unter `[data-bs-theme=dark]` **nur
wenn ein Wert gesetzt ist** (gespeichert oder Feldstandard) - sonst bleibt die
helle Farbe im Dunkeln bestehen. Der Selektor folgt `data-bs-theme`, die
Variante gilt also, ob `dark_mode` nun `auto` oder `dark` ist.
Ein leer gelassenes `color`-Feld fällt auf den Feldstandard zurück; für einen
`*_dark`-Schlüssel ohne Standard bedeutet leer = gleiche Farbe wie im
Hellmodus.

## 2. Vererbung

Die Auflösungskette ist `name > extends > … > default` (implizit, falls
fehlend). `default` ist immer die letzte Rückfallebene. Zyklusschutz und
maximale Tiefe 8.

- Jede Datei (Seite, Asset, Schema) wird am **ersten vorhandenen** Eintrag der
  Kette aufgelöst.
- `theme.json` und `langs/` werden **nicht** vererbt (jedes Theme hat eigene).
- Von der Anwendung gerenderte Views: `app`, `login`, `admin`, `admin-login`,
  `maintenance` (alle von `default` geliefert, also vererbbar).
- Eine eigene View muss `<?= $pluginHead ?>` in ihrem `<head>` ausgeben (nach
  `$themeScript`), damit die `app.head`-Injektionen der Plugins dort wirken -
  siehe `default/pages/app.php`. Ohne sie wird das HTML der auf `app.head`
  hörenden Plugins gesammelt und dann auf dieser Seite still ignoriert.

Beispiel: `dark` und `midnight` liefern nur `assets/app.css` und ihr
`theme.json`/Schema; alles andere (Seiten, Wörterbücher) wird von `default`
übernommen.

## 3. Ein Theme erstellen

1. `app/templates/<name>/theme.json` anlegen (ggf. mit `extends`).
2. Fehlende Views in `pages/` ergänzen (sonst vererbt) und `assets/app.css`.
3. `config.schema.json` für Einstellungen und `langs/<lang>.php` für Labels
   hinzufügen (`theme.<name>.`-Schlüssel; `label`/`help`/Optionen des Schemas
   akzeptieren diese Schlüssel).

## 4. Theme-Verwaltung

Im Admin (*Themes*):

- das **Standard-Theme** festlegen (schreibt `user_defaults.theme`);
- ein Theme für Benutzer **freigeben** (`allowed`) - das Standard-Theme bleibt
  immer wählbar;
- jedes Theme **konfigurieren** (Modale aus `config.schema.json` generiert).

Der Benutzer wählt sein Theme über die Einstellung `theme`; das CSS wird im
laufenden Betrieb angewendet.
