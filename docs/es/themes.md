# Temas

*Traducido del francés - la versión francesa es la que hace fe.*

Los temas (plantillas de interfaz) viven en `app/templates/<name>/`. Se
entregan tres: `default`, `dark` y `midnight` (los dos últimos heredan de
`default`).

- **`default`** - interfaz clara/oscura según el SO (`dark_mode: auto`), acento
  azul Bootstrap.
- **`dark`** - misma estructura que `default` forzada en oscuro: barra en
  degradado, carpeta activa teñida con borde lateral, densidad de lista
  reducida.
- **`midnight`** - identidad "noche": acento teal (`#0d9488`/`#2dd4bf`),
  paleta navy teñida, radios suavizados, carpetas activas en píldoras, borde
  teal bajo la barra superior, iconos finos (`regular`) y página de conexión
  en degradado.

## 1. Estructura de un tema

```
app/templates/<name>/
├── theme.json          # manifiesto
├── config.schema.json  # (opcional) ajustes del tema
├── pages/              # vistas PHP (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # diccionarios (claves con prefijo theme.<name>.)
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

- `label` puede ser una clave de traducción (pass-through en caso contrario);
- `extends` define el tema padre (opcional).

### `config.schema.json`

Formato plano `{clave: {label, type, default, options?, help?}}` con tipos
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Ejemplo
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

Los valores se almacenan en la tabla `themes` (columna `config`, JSON), se
editan en el admin y se exponen mediante variables CSS `--mxwm-*`, un atributo
`<html data-dark-mode>` y un script inline (resolución de `auto` vía
`prefers-color-scheme`).

Las variables `--mxwm-*` de las claves `X_color` se emiten bajo `:root` -
nombre en kebab-case (`folder_active_color` > `--mxwm-folder-active`),
declinadas en:
- `-rgb` (triplete `r, g, b` para los `rgba()`);
- `-text`/`-text-rgb` (texto legible sobre ese fondo, contraste calculado en
  el servidor);
- `-hover`/`-active`/`-active-border` (tonos −15 %/−20 %/−25 %, equivalente
  PHP del `shade-color()` Sass de Bootstrap);
- `-active-rgb` (tono −20 % en triplete rgb);
- `-tint` (+50 %, bordes de foco) y `-focus-rgb` (+15 %, halos de botones).

El CSS de la plantilla redeclara los componentes Bootstrap - compilados con
colores literales - hacia estas variables: botones
`btn-primary`/`btn-outline-primary`, casillas `form-check-input`, foco de
`form-control`/`form-select`/`btn-close`, hover de enlaces y `link-primary`,
`progress-bar` siguen `primary_color`; los elementos "seleccionados"
(`.dropdown-item.active`, `.list-group-item.active`, `.nav-pills`/`.pagination`
activos) siguen `folder_active_color`.

El color claro se aplica en ambos modos: cada clave `X_color_dark` reemite su
variante (tonos incluidos) bajo `[data-bs-theme=dark]` **solo si hay un valor
definido** (guardado o por defecto del campo) - si no, el color claro persiste
en oscuro. El selector sigue `data-bs-theme`, por lo que la variante se aplica
tanto si `dark_mode` es `auto` como `dark`.
Un campo `color` dejado vacío recae en el por defecto del campo; para una
clave `*_dark` sin por defecto, vacío = mismo color que en modo claro.

## 2. Herencia

La cadena de resolución es `name > extends > … > default` (implícita si
ausente). `default` es siempre el último recurso. Anti-ciclo y profundidad
máxima 8.

- Todo archivo (página, asset, esquema) se resuelve en el **primer existente**
  de la cadena.
- `theme.json` y `langs/` **no** se heredan (cada tema tiene los suyos).
- Vistas renderizadas por la aplicación: `app`, `login`, `admin`,
  `admin-login`, `maintenance` (todas provistas por `default`, por tanto
  heredables).
- Una vista personalizada debe escapar `<?= $pluginHead ?>` en su `<head>`
  (después de `$themeScript`) para que las inyecciones `app.head` de los
  plugins funcionen allí - cf. `default/pages/app.php`. Sin él, el HTML de
  los plugins que escuchan `app.head` se recoge y luego se ignora
  silenciosamente en esa página.

Ejemplo: `dark` y `midnight` solo proveen `assets/app.css` y su
`theme.json`/esquema; todo lo demás (páginas, diccionarios) se toma de
`default`.

## 3. Crear un tema

1. Cree `app/templates/<name>/theme.json` (eventualmente con `extends`).
2. Añada las vistas faltantes en `pages/` (si no, heredadas) y
   `assets/app.css`.
3. Añada `config.schema.json` para los ajustes y `langs/<lang>.php` para las
   etiquetas (claves `theme.<name>.`; `label`/`help`/opciones del esquema
   aceptan estas claves).

## 4. Administración de temas

En el admin (*Temas*):

- definir el **tema por defecto** (escribe `user_defaults.theme`);
- **autorizar** un tema para los usuarios (`allowed`) - el tema por defecto
  permanece siempre seleccionable;
- **configurar** cada tema (modal desde `config.schema.json`).

El usuario elige su tema mediante el ajuste `theme`; el CSS se aplica en
caliente.
