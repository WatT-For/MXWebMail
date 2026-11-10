# Übersetzung / Sprachen

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

## 1. Funktionsweise

Die Lokalisierung beruht auf PHP-Wörterbüchern `Schlüssel => Text`:

- Kern: `app/src/MXWebMail/Locale/<lang>.php`;
- Plugins: `app/plugins/<name>/langs/<lang>.php` (Schlüssel mit Präfix
  `plugin.<name>.`);
- Themes: `app/templates/<name>/langs/<lang>.php` (Schlüssel mit Präfix
  `theme.<name>.`).

Der Übersetzer führt diese Quellen zusammen (zuerst der Kern, dann Plugins,
dann Themes - die letzteren gewinnen bei Kollisionen), mit Englisch (`en`) als
**Basissprache** (Fallback), gefolgt von der aktiven Sprache. `t('key',
$params)` führt die `{param}`-Interpolation aus; ein unbekannter Schlüssel wird
unverändert zurückgegeben.

## 2. Installierte Sprachen

10 Sprachen sind verfügbar: **de, en, es, fr, it, nl, pl, pt, ru, tr**. Die
Basissprache (Fallback) ist **Englisch**: `app/src/MXWebMail/Locale/en.php`
muss vollständig bleiben.

## 3. Sprachwahl

`language` in `config.php` (oder Admin > *Allgemein*):

- `auto` (Standard): der `Accept-Language`-Header des Browsers;
- sonst der Code einer installierten Sprache (`fr`, `en`…).

Letzter Fallback: `en`. Das Frontend erhält das vollständige Wörterbuch in
`window.MXWM.i18n` (verwendet via `i18n.js`).

## 4. Eine Sprache hinzufügen

1. `app/src/MXWebMail/Locale/<code>.php` anlegen:
   `return ['key' => 'text', …]`.
2. (Empfohlen) die Plugin- und Theme-Wörterbücher hinzufügen:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. Die Sprache erscheint automatisch in der Admin-Auswahl (Liste = vorhandene
   Dateien in `Locale/`).

Kein zentrales Register zu bearbeiten: das Vorhandensein der Datei genügt.

## 5. PHP- und JS-Seite

- PHP: `$this->t('key')` in Controllern/Plugins; `t('key')` in Templates.
- JS: `ctx.t('key')` in `mount(element, ctx)` der Plugins; `t('key')` via
  `i18n.js`.

Die `label`/`help`/`description` der Manifeste (`plugin.json`, `theme.json`)
und Schemata (`config.schema.json`) akzeptieren Übersetzungsschlüssel
(Pass-through, wenn es kein bekannter Schlüssel ist).

## 6. Wörterbuch-Parität

Englisch ist die Basissprache: jeder neue Schlüssel muss zuerst in `en.php`
hinzugefügt werden, und jedes Wörterbuch muss mit `en` auf Parität bleiben.

## 7. Übersetzte Dokumentation

Jede Webmail-Sprache hat ihr eigenes Verzeichnis `docs/<lang>/` (`fr`, `de`,
`en`, `es`, `it`, `nl`, `pl`, `pt`, `ru`, `tr`) mit denselben Dateinamen. Das
Französische unter `docs/fr/` ist die Referenzversion: jede Änderung eines
kanonischen Dokuments muss in den Übersetzungen nachgezogen werden, und jeder
neue Artikel muss in jeder Sprache angelegt werden.
