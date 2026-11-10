# Translation / languages

*Translated from French - the French version is authoritative.*

## 1. How it works

Localization relies on PHP `key => text` dictionaries:

- core: `app/src/MXWebMail/Locale/<lang>.php`;
- plugins: `app/plugins/<name>/langs/<lang>.php` (keys prefixed `plugin.<name>.`);
- themes: `app/templates/<name>/langs/<lang>.php` (keys prefixed `theme.<name>.`).

The translator merges these sources (core first, then plugins, then themes - the
latter win on collision), with English (`en`) as the **base language**
(fallback) followed by the active language. `t('key', $params)` performs
`{param}` interpolation; an unknown key is returned as is.

## 2. Installed languages

10 languages are available: **de, en, es, fr, it, nl, pl, pt, ru, tr**. The base
language (fallback) is **English**: `app/src/MXWebMail/Locale/en.php` must stay
exhaustive.

## 3. Choosing the language

`language` in `config.php` (or admin > *General*):

- `auto` (default): the browser's `Accept-Language` header;
- otherwise the code of an installed language (`fr`, `en`…).

Final fallback: `en`. The frontend receives the full dictionary in
`window.MXWM.i18n` (used via `i18n.js`).

## 4. Adding a language

1. Create `app/src/MXWebMail/Locale/<code>.php`: `return ['key' => 'text', …]`.
2. (Recommended) add the plugin and theme dictionaries:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. The language automatically appears in the admin selector (list = files present
   in `Locale/`).

No central registry to edit: the file's presence is enough.

## 5. PHP and JS sides

- PHP: `$this->t('key')` in controllers/plugins; `t('key')` in templates.
- JS: `ctx.t('key')` in `mount(element, ctx)` of plugins; `t('key')` via `i18n.js`.

The `label`/`help`/`description` of manifests (`plugin.json`, `theme.json`) and
schemas (`config.schema.json`) accept translation keys (pass-through when it is
not a known key).

## 6. Dictionary parity

English is the base language: every new key must first be added to
`en.php`, and every dictionary must stay at parity with `en`.

## 7. Translated documentation

Each webmail language has its own `docs/<lang>/` directory (`fr`, `de`, `en`,
`es`, `it`, `nl`, `pl`, `pt`, `ru`, `tr`) with the same filenames. The French
under `docs/fr/` is the reference version: any change to a canonical document
must be propagated to the translations, and every new article must be created in
each language.
