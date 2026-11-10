# Motywy

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Motywy (szablony interfejsu) żyją w `app/templates/<name>/`. Trzy są
dostarczone: `default`, `dark` i `midnight` (dwa ostatnie dziedziczą z
`default`).

- **`default`** - jasny/ciemny interfejs według OS (`dark_mode: auto`),
  niebieski akcent Bootstrap.
- **`dark`** - ta sama struktura co `default` wymuszona na ciemny: pasek w
  gradient, aktywny folder zabarwiony z bocznym obrzeżem, zmniejszona
  gęstość listy.
- **`midnight`** - tożsamość "nocna": akcent teal (`#0d9488`/`#2dd4bf`),
  zabarwiona paleta navy, złagodzone promienie, aktywne foldery jako
  pigułki, obrzeże teal pod górnym paskiem, cienkie ikony (`regular`) i
  strona logowania w gradient.

## 1. Struktura motywu

```
app/templates/<name>/
├── theme.json          # manifest
├── config.schema.json  # (opcjonalnie) ustawienia motywu
├── pages/              # widoki PHP (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # słowniki (klucze z prefiksem theme.<name>.)
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

- `label` może być kluczem tłumaczenia (pass-through w przeciwnym razie);
- `extends` definiuje motyw rodzicielski (opcjonalnie).

### `config.schema.json`

Płaski format `{klucz: {label, type, default, options?, help?}}` z typami
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Przykład
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

Wartości są przechowywane w tabeli `themes` (kolumna `config`, JSON),
edytowane w adminie i eksponowane przez zmienne CSS `--mxwm-*`, atrybut
`<html data-dark-mode>` i skrypt inline (rozdzielczość `auto` przez
`prefers-color-scheme`).

Zmienne `--mxwm-*` kluczy `X_color` są emitowane pod `:root` - nazwa w
kebab-case (`folder_active_color` > `--mxwm-folder-active`), zdeklarowane
w:
- `-rgb` (trójka `r, g, b` dla `rgba()`);
- `-text`/`-text-rgb` (czytelny tekst na tym tle, kontrast obliczony po
  stronie serwera);
- `-hover`/`-active`/`-active-border` (odcienie −15 %/−20 %/−25 %,
  ekwiwalent PHP `shade-color()` Sass Bootstrap);
- `-active-rgb` (odcień −20 % w trójce rgb);
- `-tint` (+50 %, krawędzie fokusu) i `-focus-rgb` (+15 %, poświata
  przycisków).

CSS szablonu redeklaruje komponenty Bootstrap - skompilowane z kolorami
literałowymi - na te zmienne: przyciski `btn-primary`/`btn-outline-primary`,
checkboxy `form-check-input`, fokus `form-control`/`form-select`/`btn-close`,
hover linków i `link-primary`, `progress-bar` idą za `primary_color`;
elementy "zaznaczone" (`.dropdown-item.active`, `.list-group-item.active`,
aktywne `.nav-pills`/`.pagination`) idą za `folder_active_color`.

Jasny kolor stosuje się w obu trybach: każdy klucz `X_color_dark` emituje
ponownie swój wariant (odcienie włącznie) pod `[data-bs-theme=dark]` **tylko
jeśli wartość jest ustawiona** (zapisana lub domyślna pola) - inaczej jasny
kolor trwa w ciemnym. Selektor idzie za `data-bs-theme`, więc wariant
stosuje się czy `dark_mode` jest `auto` czy `dark`.
Puste pole `color` spada na domyślną pola; dla klucza `*_dark` bez
domyślnej, puste = ten sam kolor co w trybie jasnym.

## 2. Dziedziczenie

Łańcuch rozdzielczości to `name > extends > … > default` (niejawny jeśli
brak). `default` jest zawsze ostatnim deskiem ratunku. Anty-cykl i
maksymalna głębokość 8.

- Każdy plik (strona, asset, schemat) rozwiązuje się do **pierwszego
  istniejącego** w łańcuchu.
- `theme.json` i `langs/` **nie** są dziedziczone (każdy motyw ma swoje).
- Widoki renderowane przez aplikację: `app`, `login`, `admin`,
  `admin-login`, `maintenance` (wszystkie dostarczone przez `default`, więc
  dziedziczone).
- Niestandardowy widok musi echować `<?= $pluginHead ?>` w swoim `<head>`
  (po `$themeScript`) by wstrzyknięcia `app.head` wtyczek tam działały -
  zob. `default/pages/app.php`. Bez tego HTML wtyczek nasłuchujących
  `app.head` jest zebrany, a potem cicho ignorowany na tej stronie.

Przykład: `dark` i `midnight` dostarczają tylko `assets/app.css` i swój
`theme.json`/schemat; wszystko inne (strony, słowniki) bierze się z
`default`.

## 3. Tworzenie motywu

1. Utwórz `app/templates/<name>/theme.json` (ewentualnie z `extends`).
2. Dodaj brakujące widoki w `pages/` (inaczej dziedziczone) i
   `assets/app.css`.
3. Dodaj `config.schema.json` dla ustawień i `langs/<lang>.php` dla
   etykiet (klucze `theme.<name>.`; `label`/`help`/opcje schematu akceptują
   te klucze).

## 4. Administracja motywami

W adminie (*Motywy*):

- ustawić **motyw domyślny** (pisze `user_defaults.theme`);
- **autoryzować** motyw dla użytkowników (`allowed`) - motyw domyślny
  pozostaje zawsze wybieralny;
- **skonfigurować** każdy motyw (modal generowany z `config.schema.json`).

Użytkownik wybiera swój motyw przez ustawienie `theme`; CSS stosuje się na
gorąco.
