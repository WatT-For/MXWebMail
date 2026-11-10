# Temi

*Tradotto dal francese - la versione francese fa fede.*

I temi (template di interfaccia) vivono in `app/templates/<name>/`. Tre sono
forniti: `default`, `dark` e `midnight` (gli ultimi due ereditano da
`default`).

- **`default`** - interfaccia chiara/scura secondo l'OS (`dark_mode: auto`),
  accento blu Bootstrap.
- **`dark`** - stessa struttura di `default` forzata in scuro: barra in
  gradiente, cartella attiva tinta con bordo laterale, densità di lista
  ridotta.
- **`midnight`** - identità "notte": accento teal (`#0d9488`/`#2dd4bf`),
  palette navy tinta, raggi ammorbiditi, cartelle attive a pillole, bordo teal
  sotto la barra superiore, icone sottili (`regular`) e pagina di login in
  gradiente.

## 1. Struttura di un tema

```
app/templates/<name>/
├── theme.json          # manifest
├── config.schema.json  # (opzionale) impostazioni del tema
├── pages/              # viste PHP (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # dizionari (chiavi con prefisso theme.<name>.)
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

- `label` può essere una chiave di traduzione (pass-through altrimenti);
- `extends` definisce il tema genitore (opzionale).

### `config.schema.json`

Formato piatto `{chiave: {label, type, default, options?, help?}}` con tipi
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Esempio
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

I valori sono memorizzati nella tabella `themes` (colonna `config`, JSON),
modificati nell'admin ed esposti via variabili CSS `--mxwm-*`, un attributo
`<html data-dark-mode>` e uno script inline (risoluzione di `auto` via
`prefers-color-scheme`).

Le variabili `--mxwm-*` delle chiavi `X_color` sono emesse sotto `:root` -
nome in kebab-case (`folder_active_color` > `--mxwm-folder-active`),
declinate in:
- `-rgb` (tripletta `r, g, b` per i `rgba()`);
- `-text`/`-text-rgb` (testo leggibile su quello sfondo, contrasto calcolato
  lato server);
- `-hover`/`-active`/`-active-border` (tinte −15 %/−20 %/−25 %, equivalente
  PHP del `shade-color()` Sass di Bootstrap);
- `-active-rgb` (tinta −20 % in tripletta rgb);
- `-tint` (+50 %, bordi di focus) e `-focus-rgb` (+15 %, aloni dei bottoni).

Il CSS del template ridichiara i componenti Bootstrap - compilati con colori
letterali - verso queste variabili: bottoni `btn-primary`/`btn-outline-primary`,
checkbox `form-check-input`, focus di `form-control`/`form-select`/`btn-close`,
hover dei link e `link-primary`, `progress-bar` seguono `primary_color`; gli
elementi "selezionati" (`.dropdown-item.active`, `.list-group-item.active`,
`.nav-pills`/`.pagination` attivi) seguono `folder_active_color`.

Il colore chiaro si applica in entrambi i modi: ogni chiave `X_color_dark`
riemette la sua variante (tinte incluse) sotto `[data-bs-theme=dark]` **solo
se un valore è impostato** (salvato o predefinito del campo) - altrimenti il
colore chiaro persiste in scuro. Il selettore segue `data-bs-theme`, quindi
la variante si applica sia che `dark_mode` sia `auto` che `dark`.
Un campo `color` lasciato vuoto ricade sul predefinito del campo; per una
chiave `*_dark` senza predefinito, vuoto = stesso colore del modo chiaro.

## 2. Ereditarietà

La catena di risoluzione è `name > extends > … > default` (implicita se
assente). `default` è sempre l'ultima risorsa. Anti-ciclo e profondità massima
8.

- Ogni file (pagina, asset, schema) si risolve al **primo esistente** della
  catena.
- `theme.json` e `langs/` **non** sono ereditati (ogni tema ha i suoi).
- Viste rese dall'applicazione: `app`, `login`, `admin`, `admin-login`,
  `maintenance` (tutte fornite da `default`, quindi ereditabili).
- Una vista personalizzata deve fare echo di `<?= $pluginHead ?>` nel suo
  `<head>` (dopo `$themeScript`) affinché le iniezioni `app.head` dei plugin
  vi funzionino - cfr. `default/pages/app.php`. Senza, l'HTML dei plugin in
  ascolto su `app.head` viene raccolto poi silenziosamente ignorato su quella
  pagina.

Esempio: `dark` e `midnight` forniscono solo `assets/app.css` e il loro
`theme.json`/schema; tutto il resto (pagine, dizionari) è ripreso da
`default`.

## 3. Creare un tema

1. Create `app/templates/<name>/theme.json` (eventualmente con `extends`).
2. Aggiungete le viste mancanti in `pages/` (altrimenti ereditate) e
   `assets/app.css`.
3. Aggiungete `config.schema.json` per le impostazioni e `langs/<lang>.php`
   per le etichette (chiavi `theme.<name>.`; `label`/`help`/opzioni dello
   schema accettano queste chiavi).

## 4. Amministrazione dei temi

Nell'admin (*Temi*):

- definire il **tema predefinito** (scrive `user_defaults.theme`);
- **autorizzare** un tema per gli utenti (`allowed`) - il tema predefinito
  resta sempre selezionabile;
- **configurare** ogni tema (modale generata da `config.schema.json`).

L'utente sceglie il suo tema tramite l'impostazione `theme`; il CSS viene
applicato a caldo.
