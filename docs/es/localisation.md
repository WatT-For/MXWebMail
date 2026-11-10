# Traducción / idiomas

*Traducido del francés - la versión francesa es la que hace fe.*

## 1. Funcionamiento

La localización se apoya en diccionarios PHP `clave => texto`:

- núcleo: `app/src/MXWebMail/Locale/<lang>.php`;
- plugins: `app/plugins/<name>/langs/<lang>.php` (claves con prefijo
  `plugin.<name>.`);
- temas: `app/templates/<name>/langs/<lang>.php` (claves con prefijo
  `theme.<name>.`).

El traductor fusiona estas fuentes (primero el núcleo, luego plugins, luego
temas - estos últimos ganan en caso de colisión), con el inglés (`en`) como
**idioma base** (fallback) seguido del idioma activo. `t('clave', $params)`
realiza la interpolación `{param}`; una clave desconocida se devuelve tal cual.

## 2. Idiomas instalados

Hay 10 idiomas disponibles: **de, en, es, fr, it, nl, pl, pt, ru, tr**. El
idioma base (fallback) es el **inglés**: `app/src/MXWebMail/Locale/en.php`
debe permanecer exhaustivo.

## 3. Elección del idioma

`language` en `config.php` (o admin > *General*):

- `auto` (por defecto): cabecera `Accept-Language` del navegador;
- si no, el código de un idioma instalado (`fr`, `en`…).

Fallback final: `en`. El frontend recibe el diccionario completo en
`window.MXWM.i18n` (lo usa vía `i18n.js`).

## 4. Añadir un idioma

1. Cree `app/src/MXWebMail/Locale/<code>.php`:
   `return ['clave' => 'texto', …]`.
2. (Recomendado) añada los diccionarios de plugins y temas:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. El idioma aparece automáticamente en el selector del admin (lista = archivos
   presentes en `Locale/`).

Ningún registro central que modificar: basta la presencia del archivo.

## 5. Lado PHP y JS

- PHP: `$this->t('clave')` en controladores/plugins; `t('clave')` en
  plantillas.
- JS: `ctx.t('clave')` en `mount(element, ctx)` de los plugins; `t('clave')`
  vía `i18n.js`.

Los `label`/`help`/`description` de los manifiestos (`plugin.json`,
`theme.json`) y de los esquemas (`config.schema.json`) aceptan claves de
traducción (pass-through si no es una clave conocida).

## 6. Paridad de diccionarios

El inglés es el idioma base: toda nueva clave debe añadirse primero en
`en.php`, y cada diccionario debe permanecer a la par con `en`.

## 7. Documentación traducida

Cada idioma del webmail tiene su directorio `docs/<lang>/` (`fr`, `de`, `en`,
`es`, `it`, `nl`, `pl`, `pt`, `ru`, `tr`) con los mismos nombres de archivo. El
francés de `docs/fr/` es la versión de referencia: toda modificación de un
documento canónico debe repercutirse en las traducciones, y todo artículo nuevo
debe crearse en cada idioma.
