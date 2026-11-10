# Темы

*Переведено с французского - французская версия является исходной.*

Темы (шаблоны интерфейса) живут в `app/templates/<name>/`. Поставляются три:
`default`, `dark` и `midnight` (последние две наследуют от `default`).

- **`default`** - светлый/тёмный интерфейс по ОС (`dark_mode: auto`), синий
  акцент Bootstrap.
- **`dark`** - та же структура, что у `default`, принудительно тёмная:
  градиентная панель, тонированная активная папка с боковой каймой, сниженная
  плотность списка.
- **`midnight`** - "ночная" идентичность: teal-акцент (`#0d9488`/`#2dd4bf`),
  тонированная navy-палитра, смягчённые радиусы, активные папки-таблетки,
  teal-кайма под верхней панелью, тонкие (`regular`) иконки и страница входа
  в градиенте.

## 1. Структура темы

```
app/templates/<name>/
├── theme.json          # манифест
├── config.schema.json  # (опционально) настройки темы
├── pages/              # PHP-вью (app, login, admin, admin-login)
├── assets/             # CSS/JS (app.css, …)
└── langs/<lang>.php    # словари (ключи с префиксом theme.<name>.)
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

- `label` может быть ключом перевода (иначе pass-through);
- `extends` определяет родительскую тему (опционально).

### `config.schema.json`

Плоский формат `{ключ: {label, type, default, options?, help?}}` с типами
`string`, `text`, `integer`, `number`, `bool`, `enum`, `color`. Пример
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

Значения хранятся в таблице `themes` (колонка `config`, JSON), редактируются
в админе и экспонируются через CSS-переменные `--mxwm-*`, атрибут
`<html data-dark-mode>` и inline-скрипт (разрешение `auto` через
`prefers-color-scheme`).

Переменные `--mxwm-*` ключей `X_color` эмитируются под `:root` - имя в
kebab-case (`folder_active_color` > `--mxwm-folder-active`), раскладываются
на:
- `-rgb` (тройка `r, g, b` для `rgba()`);
- `-text`/`-text-rgb` (читаемый текст на этом фоне, контраст вычисляется на
  сервере);
- `-hover`/`-active`/`-active-border` (оттенки −15 %/−20 %/−25 %, PHP-эквивалент
  Sass `shade-color()` Bootstrap);
- `-active-rgb` (оттенок −20 % в rgb-тройке);
- `-tint` (+50 %, кайма фокуса) и `-focus-rgb` (+15 %, гало кнопок).

CSS шаблона переобъявляет компоненты Bootstrap - скомпилированные с
литеральными цветами - на эти переменные: кнопки
`btn-primary`/`btn-outline-primary`, чекбоксы `form-check-input`, фокус
`form-control`/`form-select`/`btn-close`, hover ссылок и `link-primary`,
`progress-bar` следуют `primary_color`; "выбранные" элементы
(`.dropdown-item.active`, `.list-group-item.active`, активные
`.nav-pills`/`.pagination`) следуют `folder_active_color`.

Светлый цвет применяется в обоих режимах: каждый ключ `X_color_dark`
повторно эмитирует свой вариант (оттенки включительно) под
`[data-bs-theme=dark]` **только если значение задано** (сохранённое или
дефолт поля) - иначе светлый цвет сохраняется и в тёмном. Селектор следует
`data-bs-theme`, поэтому вариант применяется при `dark_mode` и `auto`, и
`dark`.
Пустое поле `color` падает на дефолт поля; для ключа `*_dark` без дефолта
пустое = тот же цвет, что в светлом режиме.

## 2. Наследование

Цепочка разрешения: `name > extends > … > default` (неявно если отсутствует).
`default` - всегда последнее средство. Антицикл и максимальная глубина 8.

- Каждый файл (страница, ассет, схема) разрешается в **первый существующий**
  цепочки.
- `theme.json` и `langs/` **не** наследуются (у каждой темы свои).
- Вью, рендерящиеся приложением: `app`, `login`, `admin`, `admin-login`,
  `maintenance` (все поставляются `default`, следовательно наследуемы).
- Пользовательская вью должна эхоить `<?= $pluginHead ?>` в своём `<head>`
  (после `$themeScript`), чтобы `app.head`-инъекции плагинов там работали -
  см. `default/pages/app.php`. Без него HTML плагинов, слушающих `app.head`,
  собирается и затем молча игнорируется на этой странице.

Пример: `dark` и `midnight` поставляют только `assets/app.css` и свой
`theme.json`/схему; всё остальное (страницы, словари) берётся из `default`.

## 3. Создание темы

1. Создайте `app/templates/<name>/theme.json` (возможно с `extends`).
2. Добавьте недостающие вью в `pages/` (иначе наследуются) и
   `assets/app.css`.
3. Добавьте `config.schema.json` для настроек и `langs/<lang>.php` для
   меток (ключи `theme.<name>.`; `label`/`help`/опции схемы принимают эти
   ключи).

## 4. Администрирование тем

В админе (*Темы*):

- задать **тему по умолчанию** (пишет `user_defaults.theme`);
- **авторизовать** тему для пользователей (`allowed`) - тема по умолчанию
  остаётся всегда выбираемой;
- **настроить** каждую тему (модал из `config.schema.json`).

Пользователь выбирает тему через настройку `theme`; CSS применяется на
лету.
