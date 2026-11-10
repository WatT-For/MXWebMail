# Vertaling / talen

*Vertaald uit het Frans - de Franse versie is leidend.*

## 1. Werking

De lokalisatie steunt op PHP-woordenboeken `sleutel => tekst`:

- kern: `app/src/MXWebMail/Locale/<lang>.php`;
- plugins: `app/plugins/<name>/langs/<lang>.php` (sleutels met prefix
  `plugin.<name>.`);
- thema's: `app/templates/<name>/langs/<lang>.php` (sleutels met prefix
  `theme.<name>.`).

De vertaler voegt deze bronnen samen (eerst de kern, dan plugins, dan thema's
- de laatsten winnen bij botsing), met Engels (`en`) als **basistaal**
(fallback) gevolgd door de actieve taal. `t('sleutel', $params)` voert de
`{param}`-interpolatie uit; een onbekende sleutel wordt ongewijzigd
teruggegeven.

## 2. Geinstalleerde talen

Er zijn 10 talen beschikbaar: **de, en, es, fr, it, nl, pl, pt, ru, tr**. De
basistaal (fallback) is **Engels**: `app/src/MXWebMail/Locale/en.php` moet
volledig blijven.

## 3. Taalkeuze

`language` in `config.php` (of admin > *Algemeen*):

- `auto` (standaard): de `Accept-Language`-header van de browser;
- anders de code van een geinstalleerde taal (`fr`, `en`…).

Laatste fallback: `en`. De frontend ontvangt het volledige woordenboek in
`window.MXWM.i18n` (gebruikt via `i18n.js`).

## 4. Een taal toevoegen

1. Maak `app/src/MXWebMail/Locale/<code>.php`:
   `return ['sleutel' => 'tekst', …]`.
2. (Aanbevolen) voeg de woordenboeken van plugins en thema's toe:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. De taal verschijnt automatisch in de admin-selector (lijst = aanwezige
   bestanden in `Locale/`).

Geen centraal register te bewerken: het bestaan van het bestand volstaat.

## 5. PHP- en JS-kant

- PHP: `$this->t('sleutel')` in controllers/plugins; `t('sleutel')` in
  templates.
- JS: `ctx.t('sleutel')` in `mount(element, ctx)` van plugins; `t('sleutel')`
  via `i18n.js`.

De `label`/`help`/`description` van manifesten (`plugin.json`, `theme.json`)
en schema's (`config.schema.json`) accepteren vertaalsleutels (pass-through
als het geen bekende sleutel is).

## 6. Pariteit van woordenboeken

Engels is de basistaal: elke nieuwe sleutel moet eerst in `en.php` worden
toegevoegd, en elk woordenboek moet op pariteit met `en` blijven.

## 7. Vertaalde documentatie

Elke webmailtaal heeft een eigen `docs/<lang>/`-map (`fr`, `de`, `en`, `es`,
`it`, `nl`, `pl`, `pt`, `ru`, `tr`) met dezelfde bestandsnamen. Het Frans onder
`docs/fr/` is de referentieversie: elke wijziging van een canoniek document moet
in de vertalingen worden doorgevoerd, en elk nieuw artikel moet in elke taal
worden aangemaakt.
