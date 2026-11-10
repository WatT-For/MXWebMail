# Çeviri / diller

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

## 1. Çalışma

Yerelleştirme `anahtar => metin` PHP sözlüklerine dayanır:

- çekirdek: `app/src/MXWebMail/Locale/<lang>.php`;
- eklentiler: `app/plugins/<name>/langs/<lang>.php` (`plugin.<name>.`
  önekli anahtarlar);
- temalar: `app/templates/<name>/langs/<lang>.php` (`theme.<name>.`
  önekli anahtarlar).

Çevirmen bu kaynakları birleştirir (önce çekirdek, sonra eklentiler, sonra
temalar - çakışmada sonuncular kazanır), **temel dil** (fallback) olarak
İngilizce (`en`) ardından aktif dil. `t('anahtar', $params)` `{param}`
interpolasyonunu yapar; bilinmeyen anahtar olduğu gibi döndürülür.

## 2. Kurulu diller

10 dil mevcuttur: **de, en, es, fr, it, nl, pl, pt, ru, tr**. Temel dil
(fallback) **İngilizce**'dir: `app/src/MXWebMail/Locale/en.php` eksiksiz
kalmalıdır.

## 3. Dil seçimi

`config.php` içinde `language` (veya admin > *Genel*):

- `auto` (varsayılan): tarayıcının `Accept-Language` başlığı;
- aksi halde kurulu bir dilin kodu (`fr`, `en`…).

Son fallback: `en`. Frontend tam sözlüğü `window.MXWM.i18n` içinde alır
(`i18n.js` üzerinden kullanılır).

## 4. Dil ekleme

1. `app/src/MXWebMail/Locale/<code>.php` oluşturun:
   `return ['anahtar' => 'metin', …]`.
2. (Önerilen) eklenti ve tema sözlüklerini ekleyin:
   `app/plugins/*/langs/<code>.php`, `app/templates/*/langs/<code>.php`.
3. Dil admin seçicide otomatik görünür (liste = `Locale/` içindeki
   dosyalar).

Merkezi kayıt yok: dosyanın varlığı yeterli.

## 5. PHP ve JS tarafı

- PHP: denetleyiciler/eklentilerde `$this->t('anahtar')`; şablonlarda
  `t('anahtar')`.
- JS: eklentilerin `mount(element, ctx)` içinde `ctx.t('anahtar')`;
  `i18n.js` üzerinden `t('anahtar')`.

Manifestoların (`plugin.json`, `theme.json`) ve şemaların
(`config.schema.json`) `label`/`help`/`description` alanları çeviri
anahtarları kabul eder (bilinen anahtar değilse pass-through).

## 6. Sözlük eşliği

İngilizce temel dildir: her yeni anahtar önce `en.php`'ye eklenmelidir ve
her sözlük `en` ile eşit kalmalıdır.

## 7. Çevrilmiş belgeler

Her webmail dilinin kendi `docs/<lang>/` dizini vardır (`fr`, `de`, `en`, `es`,
`it`, `nl`, `pl`, `pt`, `ru`, `tr`) ve aynı dosya adlarını içerir. `docs/fr/`
altındaki Fransızca referans sürümdür: kanonik bir belgedeki herhangi bir
değişiklik çevirilere yansıtılmalıdır ve her yeni makale her dilde
oluşturulmalıdır.
