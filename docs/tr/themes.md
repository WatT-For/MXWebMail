# Temalar

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

Temalar (arayüz şablonları) `app/templates/<name>/` içinde yaşar. Üçü
beraber gelir: `default`, `dark` ve `midnight` (son ikisi `default`'tan
kalıtır).

- **`default`** - OS'e göre açık/koyu arayüz (`dark_mode: auto`), Bootstrap
  mavi vurgu.
- **`dark`** - `default` ile aynı yapı, zorla koyu: degrade çubuk, yan
  kenarlı tonlu aktif klasör, azaltılmış liste yoğunluğu.
- **`midnight`** - "gece" kimliği: teal vurgu (`#0d9488`/`#2dd4bf`), tonlu
  lacivert palet, yumuşatılmış yarıçaplar, hap şeklinde aktif klasörler,
  üst çubuğun altında teal kenar, ince (`regular`) ikonlar ve degrade giriş
  sayfası.

## 1. Tema yapısı

```
app/templates/<name>/
├── theme.json          # manifesto
├── config.schema.json  # (opsiyonel) tema ayarları
├── pages/              # PHP görünümleri (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # sözlükler (theme.<name>. önekli anahtarlar)
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

- `label` bir çeviri anahtarı olabilir (aksi halde pass-through);
- `extends` üst temayı tanımlar (opsiyonel).

### `config.schema.json`

`{anahtar: {label, type, default, options?, help?}}` düz biçimi, tipler
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Örnek
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

Değerler `themes` tablosunda saklanır (`config` sütunu, JSON), admin'de
düzenlenir ve `--mxwm-*` CSS değişkenleri, `<html data-dark-mode>`
özniteliği ve satır içi betik (`auto` çözünürlüğü `prefers-color-scheme`
ile) aracılığıyla ortaya konur.

`X_color` anahtarlarının `--mxwm-*` değişkenleri `:root` altında yayınlanır
- kebab-case ad (`folder_active_color` > `--mxwm-folder-active`), şunlara
ayrışır:
- `-rgb` (`rgba()` için `r, g, b` üçlüsü);
- `-text`/`-text-rgb` (bu zeminde okunabilir metin, kontrast sunucu
  tarafında hesaplanır);
- `-hover`/`-active`/`-active-border` (−15 %/−20 %/−25 % tonlar, Bootstrap
  Sass `shade-color()`'un PHP eşdeğeri);
- `-active-rgb` (rgb üçlüsünde −20 % ton);
- `-tint` (+50 %, odak kenarı) ve `-focus-rgb` (+15 %, düğme parıltısı).

Şablon CSS'i Bootstrap bileşenlerini - sabit renklerle derlenmiş - bu
değişkenlere yeniden beyan eder: `btn-primary`/`btn-outline-primary`
düğmeleri, `form-check-input` onay kutuları, `form-control`/`form-select`/`btn-close`
odakları, bağlantı hover ve `link-primary`, `progress-bar` `primary_color`'u
izler; "seçili" öğeler (`.dropdown-item.active`, `.list-group-item.active`,
aktif `.nav-pills`/`.pagination`) `folder_active_color`'u izler.

Açık renk iki modda da uygulanır: her `X_color_dark` anahtarı varyantını
(tonlar dahil) `[data-bs-theme=dark]` altında **yalnızca değer ayarlıysa**
(kaydedilmiş veya alanın varsayılanı) yeniden yayınlar - aksi halde açık
renk karanlıkta da sürer. Seçici `data-bs-theme`'i izler, yani varyant
`dark_mode` `auto` veya `dark` olsun uygulanır.
Boş bir `color` alanı alanın varsayılanına düşer; varsayılanı olmayan
`*_dark` anahtarı için boş = açık moddaki renkle aynı.

## 2. Kalıtım

Çözümleme zinciri `name > extends > … > default` (yoksa örtük). `default`
her zaman son çaredir. Anti-döngü ve maksimum derinlik 8.

- Her dosya (sayfa, asset, şema) zincirdeki **ilk var olana** çözümlenir.
- `theme.json` ve `langs/` **kalıtılmaz** (her temanın kendisininkiler).
- Uygulama tarafından oluşturulan görünümler: `app`, `login`, `admin`,
  `admin-login`, `maintenance` (hepsi `default` tarafından sağlanır,
  dolayısıyla kalıtılır).
- Özel bir görünüm, `app.head` eklenti enjeksiyonlarının orada çalışması
  için `<head>`'inde `<?= $pluginHead ?>` echo etmelidir (`$themeScript`'ten
  sonra) - bkz. `default/pages/app.php`. Onsuz `app.head` dinleyen
  eklentilerin HTML'i toplanır ama bu sayfada sessizce yoksayılır.

Örnek: `dark` ve `midnight` yalnızca `assets/app.css` ve kendi
`theme.json`/şemasını sağlar; geri kalanı (sayfalar, sözlükler) `default`'tan
alınır.

## 3. Tema oluşturma

1. `app/templates/<name>/theme.json` oluşturun (muhtemelen `extends` ile).
2. `pages/` içine eksik görünümleri ekleyin (aksi halde kalıtılır) ve
   `assets/app.css`.
3. Ayarlar için `config.schema.json` ve etiketler için `langs/<lang>.php`
   ekleyin (`theme.<name>.` anahtarları; `label`/`help`/şema seçenekleri bu
   anahtarları kabul eder).

## 4. Tema yönetimi

Admin'de (*Temalar*):

- **varsayılan temayı** ayarla (`user_defaults.theme` yazar);
- tema başına **yetki** (`allowed`) - varsayılan tema her zaman seçilebilir
  kalır;
- her temayı **yapılandır** (modal `config.schema.json`'dan üretilir).

Kullanıcı temasını `theme` ayarı üzerinden seçer; CSS sıcağı sıcağına
uygulanır.
