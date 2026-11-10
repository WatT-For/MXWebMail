# Tłumaczenie / języki

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

## 1. Działanie

Lokalizacja opiera się na słownikach PHP `klucz => tekst`:

- rdzeń: `app/src/MXWebMail/Locale/<lang>.php`;
- wtyczki: `app/plugins/<name>/langs/<lang>.php` (klucze z prefiksem
  `plugin.<name>.`);
- motywy: `app/templates/<name>/langs/<lang>.php` (klucze z prefiksem
  `theme.<name>.`).

Tłumacz scala te źródła (najpierw rdzeń, potem wtyczki, potem motywy -
ostatnie wygrywają w razie kolizji), z angielskim (`en`) jako **językiem
bazowym** (fallback) a potem językiem aktywnym. `t('klucz', $params)`
wykonuje interpolację `{param}`; nieznany klucz jest zwracany tak jak jest.

## 2. Zainstalowane języki

Dostępnych jest 10 języków: **de, en, es, fr, it, nl, pl, pt, ru, tr**.
Językiem bazowym (fallback) jest **angielski**:
`app/src/MXWebMail/Locale/en.php` musi pozostać kompletny.

## 3. Wybór języka

`language` w `config.php` (lub admin > *Ogólne*):

- `auto` (domyślnie): nagłówek `Accept-Language` przeglądarki;
- inaczej kod zainstalowanego języka (`fr`, `en`…).

Ostateczny fallback: `en`. Frontend otrzymuje pełny słownik w
`window.MXWM.i18n` (używany przez `i18n.js`).

## 4. Dodanie języka

1. Utwórz `app/src/MXWebMail/Locale/<code>.php`:
   `return ['klucz' => 'tekst', …]`.
2. (Zalecane) dodaj słowniki wtyczek i motywów:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. Język pojawia się automatycznie w selektorze admina (lista = pliki
   obecne w `Locale/`).

Żadnego centralnego rejestru do edycji: wystarczy obecność pliku.

## 5. Strona PHP i JS

- PHP: `$this->t('klucz')` w kontrolerach/wtyczkach; `t('klucz')` w
  szablonach.
- JS: `ctx.t('klucz')` w `mount(element, ctx)` wtyczek; `t('klucz')` przez
  `i18n.js`.

`label`/`help`/`description` manifestów (`plugin.json`, `theme.json`) i
schematów (`config.schema.json`) akceptują klucze tłumaczeń
(pass-through, jeśli to nie znany klucz).

## 6. Paritet słowników

Angielski jest językiem bazowym: każdy nowy klucz należy najpierw dodać w
`en.php`, a każdy słownik musi pozostać na równi z `en`.

## 7. Tłumaczona dokumentacja

Każdy język webmaila ma własny katalog `docs/<lang>/` (`fr`, `de`, `en`, `es`,
`it`, `nl`, `pl`, `pt`, `ru`, `tr`) z tymi samymi nazwami plików. Francuski w
`docs/fr/` jest wersją referencyjną: każda zmiana kanonicznego dokumentu musi
być przeniesiona do tłumaczeń, a każdy nowy artykuł musi być utworzony w każdym
języku.
