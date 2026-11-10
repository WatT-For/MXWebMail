# Traduzione / lingue

*Tradotto dal francese - la versione francese fa fede.*

## 1. Funzionamento

La localizzazione si basa su dizionari PHP `chiave => testo`:

- core: `app/src/MXWebMail/Locale/<lang>.php`;
- plugin: `app/plugins/<name>/langs/<lang>.php` (chiavi con prefisso
  `plugin.<name>.`);
- temi: `app/templates/<name>/langs/<lang>.php` (chiavi con prefisso
  `theme.<name>.`).

Il traduttore fonde queste fonti (prima il core, poi i plugin, poi i temi -
gli ultimi vincono in caso di collisione), con l'inglese (`en`) come **lingua
di base** (fallback) seguita dalla lingua attiva. `t('chiave', $params)`
esegue l'interpolazione `{param}`; una chiave sconosciuta viene restituita
così com'è.

## 2. Lingue installate

Sono disponibili 10 lingue: **de, en, es, fr, it, nl, pl, pt, ru, tr**. La
lingua di base (fallback) è l'**inglese**: `app/src/MXWebMail/Locale/en.php`
deve restare esaustivo.

## 3. Scelta della lingua

`language` in `config.php` (o admin > *Generale*):

- `auto` (predefinito): header `Accept-Language` del browser;
- altrimenti il codice di una lingua installata (`fr`, `en`…).

Fallback finale: `en`. Il frontend riceve il dizionario completo in
`window.MXWM.i18n` (usato via `i18n.js`).

## 4. Aggiungere una lingua

1. Create `app/src/MXWebMail/Locale/<code>.php`:
   `return ['chiave' => 'testo', …]`.
2. (Consigliato) aggiungete i dizionari dei plugin e dei temi:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. La lingua appare automaticamente nel selettore admin (elenco = file
   presenti in `Locale/`).

Nessun registro centrale da modificare: basta la presenza del file.

## 5. Lato PHP e JS

- PHP: `$this->t('chiave')` nei controller/plugin; `t('chiave')` nei template.
- JS: `ctx.t('chiave')` in `mount(element, ctx)` dei plugin; `t('chiave')` via
  `i18n.js`.

I `label`/`help`/`description` dei manifest (`plugin.json`, `theme.json`) e
degli schemi (`config.schema.json`) accettano chiavi di traduzione
(pass-through se non è una chiave nota).

## 6. Parità dei dizionari

L'inglese è la lingua di base: ogni nuova chiave va prima aggiunta in
`en.php`, e ogni dizionario deve restare alla pari con `en`.

## 7. Documentazione tradotta

Ogni lingua del webmail ha la propria directory `docs/<lang>/` (`fr`, `de`,
`en`, `es`, `it`, `nl`, `pl`, `pt`, `ru`, `tr`) con gli stessi nomi di file. Il
francese in `docs/fr/` è la versione di riferimento: ogni modifica di un
documento canonico deve essere riportata nelle traduzioni, e ogni nuovo articolo
deve essere creato in ogni lingua.
