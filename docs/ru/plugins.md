# Плагины

*Переведено с французского - французская версия является исходной.*

Плагины - это расширения, ограниченные `app/plugins/<name>/`, загружаемые
при активации (из админа, вкладка *Расширения*). Они регистрируются на
**хуках** ядра.

> **Сессия только для чтения**: GET-эндпоинты закрывают PHP-сессию рано
> (`session_write_close`), чтобы освободить блокировку. Хуки, вызываемые из
> GET (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **никогда не должны писать
> `$_SESSION`** - эти записи были бы молча потеряны.

## 0. Распространение через репозиторий GitHub

Плагины **не включены** в zip релиза `mxwebmail-<ver>.zip`: они читаются
напрямую в репозитории на **настраиваемом ref** (`update.plugins_ref` -
`'release'` = тег последнего релиза по умолчанию, `'main'` или любой
git-ref). Каталог (`PluginPackageService`, вкладка *Расширения* -
неустановленные записи внизу таблицы) строится из git-API:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` перечисляет файлы под
  `app/plugins/` с их **sha1 git-blob** и размером;
- `plugin.json` каждого плагина читается через `raw.githubusercontent.com`
  (привязан к sha коммита) - версия, описание, `min_app`/`max_app`;
- **Установить / обновить**: каждый файл плагина скачивается через `raw`,
  проверяется (размер + sha1 blob дерева), затем `plugin.json` валидируется
  (совпадающее имя, semver-версия, здоровый `main`, совместимость
  `APP_VERSION`) до замены папки - установленный плагин по умолчанию
  **выключен**; старая папка уходит в
  `storage/update/plugins/backup-<name>-<ts>` (сохраняется одна,
  автоматический откат);
- **Удалить**: окончательное удаление папки + очистка строки `plugins`
  (конфигурация теряется), без резервной копии.

Публикация версии плагина = bump `version` в его `plugin.json` и push на
ветку, отслеживаемую `update.plugins_ref` - релиз и ассет не требуются.

Репозиторий заперт в `GitHubClient::REPO` (та же константа, что у апдейтера
ядра) - никакой произвольный URL не принимается. `update.channel` и кэш
релиза (`cache.update_ttl`) общие с проверкой обновления ядра; ref>commit
кэшируется на этот TTL, дерево и манифесты адресуются по их sha
(неизменны). Статический `check` требует semver `version` в `plugin.json`.

Для офлайн dev/тестов `update.plugins_local` (абсолютный путь папки
плагинов в `config.php`) служит источником: каталог и установка копией с
диска, без удалённой проверки.

Активированный плагин может также быть **зарезервирован для
админ-поверхности** (колонка *Доступ пользователей* вкладки *Расширения*,
флаг `user_allowed` в таблице `plugins`, **по умолчанию не отмечена**): он
тогда загружается только на странице `/{admin_path}`, маршрутах
`/api/admin/*` и админ-сессиях. В остальном он полностью инертен (никаких
хуков, `/api/plugin/<name>/*` > 404) - даже для `is_admin`-пользователей,
не подключённых к панели. Хуки входа админа (`admin.post-login`,
`admin.totp-required`, `admin.mounts`) продолжают работать. Плагин, чьи
объявленные в манифесте хуки - все `admin.*`, не имеет пользовательской
поверхности: колонка тогда не показывает для него переключателя (`-`).

## 1. Плагины официального каталога

| Плагин | Роль | Хуки |
|---|---|---|
| `add-x-originating-ip-header` | Добавляет `X-Originating-IP` (IP отправителя) к отправкам. | `message.before-send` |
| `anti-spoofing` | Обнаружение спуфинга в читалке: несогласованные Return-Path/Reply-To/Sender, display-name, имитирующий бренд (гомоглифы и confusables обнаруживаются), домен-приманка (ловушечный поддомен, typosquat, trans-TLD), SPF/DKIM/DMARC оцениваются в выравнивании с доменом отправителя (вердикты читаются из `Authentication-Results`, `Received-SPF` или заголовков SpamAssassin/rspamd) - поддомены защищённых доменов легитимны. | `imap.message-headers`, `message.view` |
| `avatars` | Аватар отправителя: иконки известных сервисов, Gravatar, favicon домена, инициалы. | `app.data` |
| `black-list` | Чёрный список входа (wildcards + исключения) - проверяется на **разрешённом** email (post-mapping). | `auth.login.mapped` |
| `white-list` | Белый список входа (wildcards + исключения) - проверяется на **разрешённом** email (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Синхронизация контактов из CardDAV-книги (Nextcloud, Radicale, iCloud…) - **требуются HTTPS + публичный хост**. | `app.data` |
| `change-password` | Смена пароля (драйверы poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Цветные метки на сообщениях (ручные + автоматические правила). | `app.data`, `message.meta` |
| `ics-viewer` | Предпросмотр `.ics`-приглашений/вложений в читалке **+ iTIP-ответ** (Принять/Возможно/Отклонить на `METHOD:REQUEST`, отправляется по SMTP организатору). | `app.data` |
| `imap-contacts-suggestions` | Подсказки контактов из отправителей IMAP-папки. | `contacts.provider` |
| `ldap-contacts` | Подсказки контактов из LDAP-каталога (автодополнение). | `contacts.provider` |
| `ldap-login` | LDAP-разрешение: uid/alias > реальный IMAP-адрес до входа. | `auth.login.mapping` |
| `mailbox-detect` | Обнаружение системных папок и создание недостающих. | `app.data` |
| `pwa` | Делает webmail устанавливаемым (манифест + минимальный service worker, иконки). | `app.head`, `app.routes` |
| `webmail-migration` | Админ-страница импорта из папки `data` **RainLoop или SnappyMail** (серверный путь или ZIP): домены, книги `AddressBook.sqlite`/PDO, идентичности, настройки, системные папки, PGP-ключи - dry-run-скан, затем идемпотентный импорт, неизвестные аккаунты игнорируются. | `admin.mounts` |
| `two-factor-auth` | Двухфакторный TOTP (Google Authenticator, Aegis…) - пользовательские аккаунты **и** локальный админ-аккаунт (QR-провизионирование в собственной под-вкладке Admin > Безопасность; требуется при входе админа, пока плагин активен). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Баннер объявления администратора (страница app, опционально страница входа - обслуживание). Только открытый текст. | `app.head` |
| `attachments-clamav` | ClamAV-скан (`clamd`, `zINSTREAM`) загруженных вложений и импортов - отказ при обнаружении, настраиваемая политика `on_error`. | `attachment.upload` |
| `filelink` | Слишком большие для SMTP вложения, отправленные как **истекаемая ссылка скачивания** (`/filelink/<token>` - принудительный attachment, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Обучение spam/ham при каждом (снятии)помечании: внешняя команда (`rspamc`, `sa-learn`) или IMAP-копия в папку dovecot-antispam. | `message.spam` |
| `wkd-keys` | Автоматическое обнаружение PGP-ключей получателей: **WKD** (advanced + direct), сбор заголовков **Autocrypt**, опциональный резерв `keys.openpgp.org` - импорт предлагается при написании. | `app.data`, `message.view` |
| `seasonal-decorations` | Сдержанный монохромный пейзаж в баннерах по дате (Новый год, Пасха, 1 апреля, Хэллоуин, Рождество): библиотека SVG-объектов через `scenes.json` ({svg, pos, size}), рекомбинированная в слои `mask-image`, автоматически контрастируемый цвет - регулируемые окна, опционально вне сессии, без анимации. | `app.head` |
| `link-confirmation` | Модал подтверждения перед открытием ссылки из тела письма (anti-phishing): показанная реальная цель, пометка подозрительных ссылок (текст ≠ URL, литеральный IP, punycode, учётные данные в URL), доверенные домены и "больше не спрашивать для этого домена". | `app.head` |
| `always-cc-bcc` | Систематические получатели Cc или Bcc при отправке (архив соответствия), на аккаунт или все - Bcc остаётся невидимым. | `message.before-send` |
| `report-abuse` | Кнопка "Пожаловаться" (панель действий): пересылает отмеченные письма как вложения `.eml` на администрируемый abuse-адрес, опциональная пометка спама. | `app.data` |
| `vcf-viewer` | Предпросмотр вложений `.vcf` в читалке + однокликовый импорт в книгу (кнопка интегрирована во фронтенд при `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Квота отправки на аккаунт (сообщений/час, сообщений/день, получателей/день) - отказ 429 сверх. Только webmail. | `message.before-send` |
| `login-notify` | Письмо-оповещение на аккаунт при входе с неизвестного IP (модель Gmail) - кладётся непрочитанным во входящие, opt-out на пользователя. | `auth.post-login`, `app.data` |
| `sso-header` | SSO через прокси аутентификации (доверенный заголовок пользователя) - требует объявленных IP прокси и мастер-пароль IMAP. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Админ-страница, агрегирующая DMARC-(rua)-отчёты выделенного IMAP-ящика - парсинг XML/gzip, агрегаты по домену и исходному IP. | `admin.mounts` |

## 2. Настройка плагина

В админе (*Расширения* > кнопка ⚙) настройка генерируется из файла
`config.schema.json` плагина. Поддерживаемые типы: `string`, `text`,
`integer`, `number`, `password`, `bool`, `enum`, `color`, `list` - `color`
рендерит нативный пикер + поле `#RRGGBB` (пустое значение принимается:
поле падает на дефолт плагина). Значения хранятся в таблице `plugins`
(колонка `config`, JSON).

Заметные детали настройки:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (по умолчанию `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-анонимность), затем собственные
  параметры драйвера (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` с
  плейсхолдерами `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (плейсхолдер `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (плейсхолдер `%q`), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (защищённый домен покрывает и
  свои поддомены, напр. `accountprotection.microsoft.com`), `brands`
  (обнаруживаются в отображаемом имени после нормализации акцентов и
  confusables - "AppIe TV" распознаётся как "Apple TV"), и переключатели
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  Результаты SPF/DKIM/DMARC учитываются только когда выровнены с доменом
  `From`: `dkim=fail` на подписи перевозчика или `spf=fail` на релеированном
  (SRS-)mailfrom не вызывает тревоги.
- **`two-factor-auth`** - `issuer` (по умолчанию `app_name`), `label`
  (`email` = полный адрес, `local` = часть до `@`; `local` не даёт
  TOTP-приложениям выводить иконку бренда из домена, напр. Yahoo) и
  `icon_url` (нестандартный параметр `image=`, показываемый
  Aegis/andOTP/FreeOTP - игнорируется Authy; пусто = публичный favicon
  инстанса). Плагин также провизионирует 2FA локального админ-аккаунта:
  секрет `admin_totp_secret` в `config.php` (через админ-API
  `/api/admin/plugin/two-factor-auth/*`), pending в сессии, применяется при
  входе админа через хук `admin.post-login`. Выключение плагина отключает
  применение в админе.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  значения через пробелы, запятые или точки с запятой), `exceptions`,
  `auth_error` (403 "аккаунт не авторизован" > общий 401). Внимание на
  семантику, унаследованную от legacy-плагина: для black-list `exceptions` =
  email чёрного списка, которые **остаются разрешёнными**; для white-list,
  если `exceptions` пуст, любой email вне белого списка отклоняется, но
  если он заполнен, **отклоняются только email исключений**, а всё
  остальное проходит - не класть туда адреса "разрешённые дополнительно"
  (обратный ожидаемому эффект).

- **`link-confirmation`** - `mode` (`all` = любая внешняя ссылка,
  `suspicious` = только подозрительные), `whitelist` (доверенные домены,
  один на строку или через пробел/запятую/точку с запятой - поддомены
  включительно), `warn_mailto` (подтверждать и `mailto:`/`tel:`, по
  умолчанию выключено) и `remember` (флажок "больше не спрашивать для
  этого домена" - запоминается в браузере через localStorage, не на
  стороне сервера).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (цели, пусто =
  неактивно), `senders` (wildcards отправителей, `*` = все).
- **`report-abuse`** - `abuse_address` (обязательно, пусто = неактивно),
  `subject_prefix`, `mark_spam` (также помечает спамом через
  `message.spam` - питает обучение).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = без лимита), `exempt` (wildcards освобождённых аккаунтов). Ограничивает
  только webmail: прямой IMAP/SMTP-клиент обходит квоту.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (вкладка Настройки opt-out на пользователя).
- **`sso-header`** - `header` (`X-Forwarded-User` или `REMOTE_USER`),
  `trusted_ips` (**обязательно, пусто = плагин инертен**), `master_pass`
  (обязательно; режим *same* = passdb `master=yes`), `master_user`
  (опционально: логин `user*master`), `allowed_domains`, `hide_form`.
  Инстанс не должен быть достижим в обход прокси (заголовок был бы
  подделываем).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (IMAP-ящик
  отчётов), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Собственное IMAP-подключение плагина (опции домена
  не применяются).

У некоторых плагинов нет админ-схемы: их настройка **по пользователю**
(напр. `carddav-contacts`, `color-tags`, `two-factor-auth` для состояния
аккаунта).

## 3. Создание плагина

### Минимальная структура

```
app/plugins/moy-plugin/
├── plugin.json          # манифест
├── MoyPlugin.php        # главный класс
├── config.schema.json   # (опционально) схема настройки админа
├── langs/<lang>.php     # словари (ключи с префиксом plugin.<name>.)
└── js/ или assets/      # (опционально) ассеты, отдаваемые через /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "moy-plugin",
	"label": "plugin.moy-plugin.label",
	"class": "MoyPlugin",
	"main": "MoyPlugin.php",
	"version": "0.1.0",
	"description": "plugin.moy-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (опционально) - отображаемое имя плагина в Admin > Расширения
(рекомендуемый i18n-ключ `plugin.<name>.label`, литеральный текст
принимается; техническое имя `name` показывается рядом и служит
идентификатором).

`min_app`/`max_app` (опционально, semver `x.y.z`) ограничивают совместимость
с `APP_VERSION` - установка или обновление отклоняется вне диапазона, и
каталог помечает запись *несовместимой*.

### Главный класс

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MoyPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Доступные хуки

- **Ядро**: `app.routes`, `app.head` (HTML, внедряемый в `<head>` `app.php`,
  `login.php` и `maintenance.php` - вью `admin*.php` его не эмитируют),
  `app.data`, `auth.login.mapping`, `auth.login.mapped`, `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Админ**: `admin.mounts` (`collect` > дескриптор `{js, plugin, section,
  tab?, label?}`, рендеримый `pluginMount` в админ-панели - без `tab`:
  inline-блок в зоне `section`, напр. `security-account`; с `tab` +
  `label`: собственная под-вкладка страницы `section`, напр.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, эмитируется при входе локального
  админ-аккаунта - брошенный `TotpRequiredException` требует второй фактор),
  `admin.totp-required` (`first` > boolean: показывает поле TOTP на
  странице входа админа).

Каждый плагин объявляет в `hooks` слушаемые хуки. Манифест, объявляющий
только хуки `admin.*`, помечает плагин как чисто админский - колонка
*Доступ пользователей* вкладки Расширения не показывает для него
переключателя. Ядро вызывает `HookRegistry::run('hook', …)`; плагин
реализует одноимённый метод (или `on<Hook>` по конвенции ядра).

### Общий API

Каждый плагин вызываем через `/api/plugin/<name>/<action>` (GET/POST) >
метод `api<Action>(Request $r, ?array $user)`. CSRF-токен требуется на всех
методах (клиент `api.js` посылает его систематически) - прямые вызовы без
заголовка `X-CSRF-Token` отклоняются в 403.

Со стороны админа, `/api/admin/plugin/<name>/<action>` (GET/POST) > метод
`apiAdmin<Action>(Request $r)`: админ-сессия (`$_SESSION['admin']`) + CSRF
требуются; нет `$user` (локальный админ-аккаунт - не строка `users`).
Внимание: `requireAdmin` закрывает сессию на запись - чтобы сохранить там
состояние (напр. ожидающий секрет), переоткройте её через
`$this->app->startSession()`, затем `session_write_close()`.

### Вкладка пользователя

Чтобы добавить вкладку в `#/@settings/…`, предоставьте JS, отдаваемый через
`/assets/plugin/<name>/<file>`, и объявите вкладку (см. плагины
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth` как
примеры).

### Переводы

Ключи с префиксом `plugin.<name>.` в `langs/<lang>.php`;
`description`/`label`/`help` манифестов и схем принимают эти ключи
(pass-through иначе). Сторона PHP: `$this->t('ключ')`, сторона JS:
`ctx.t('ключ')` в `mount(element, ctx)`.
