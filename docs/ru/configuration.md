# Справочник конфигурации

*Переведено с французского - французская версия является исходной.*

Файл `app/config/config.php` определяет только ключи, **переопределяющие**
значения по умолчанию (`app/config/config.example.php`, загружаемый
`Config.php`). Скопируйте `config.example.php` в `config.php` и
адаптируйте. Панель администрирования редактирует большинство этих ключей
по разделам; лишь некоторые требуют ручной правки (`update.*` прежде всего,
но также `import_max_size`, `smime_ca_path`, `logs.max_size` и `logs.keep`).

Ассоциативные массивы глубоко сливаются; списки заменяются.

## Хранилище

| Ключ | По умолчанию | Роль |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) или полный PDO DSN (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Пользователь PDO (MariaDB). |
| `db_pass` | `''` | Пароль PDO (MariaDB). |

## Приложение

| Ключ | По умолчанию | Роль |
|---|---|---|
| `app_name` | `MXWebMail` | Отображаемое имя (заголовок, PWA, TOTP). |
| `favicon` | `''` | Файл favicon, загруженный в `storage/branding`, отдаётся через `/favicon.ico`; пусто = значок по умолчанию. |
| `website_url` | `https://mxrelay.fr/webmail/` | Ссылка на официальный сайт (кнопка админа); пусто = скрыта. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Ссылка "Sponsor" на страницу пожертвований (кнопка админа + настройки webmail); пусто = скрыта. |
| `debug` | `false` | Возвращает внутренние ошибки API-клиентам (никогда в продакшене). |
| `language` | `auto` | `auto` = `Accept-Language` браузера; иначе код установленного языка (`fr`, `en`…). Финальный fallback `en`. |

## Домены

| Ключ | По умолчанию | Роль |
|---|---|---|
| `default_domain` | `auto` | Суффикс логина, введённого без `@домен`: `auto` = HTTP-хост (без `www.` и порта), `''` = никакой, или фиксированный домен. |
| `restrict_domains` | `true` | Только именованные домены таблицы `domains` принимаются при входе (ни `*`, ни детекция не применяются). |
| `autodetect.enabled` | `false` | Автоматическая детекция конфигурации неизвестного домена при входе. |
| `autodetect.use_ispdb` | `false` | Fallback на базу Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Зондирование портов `imap.`/`mail.`/`smtp.`/`sieve.<домен>`. |

Серверы IMAP/SMTP/Sieve настраиваются **по доменам** в админе (см.
[domaines.md](domaines.md)). Домен `*` служит catch-all-параметрами.

## Безопасность

| Ключ | По умолчанию | Роль |
|---|---|---|
| `admin_path` | `admin` | URL-сегмент админ-панели. |
| `admin_user` | `''` | Локальный админ-аккаунт; пусто = fallback на пользователя `is_admin`, проверенного через IMAP. |
| `admin_password_hash` | `''` | `password_hash('пароль', PASSWORD_DEFAULT)` локального админ-аккаунта. |
| `admin_totp_secret` | `''` | TOTP-секрет (base32) локального админ-аккаунта, записываемый плагином `two-factor-auth`; пусто = отключён. Не требуется, если плагин выключен. |
| `login_max_attempts` | `5` | Попытки входа до временной блокировки. |
| `login_lock_seconds` | `300` | Длительность блокировки (секунды). |
| `admin_ip_allowlist` | `''` | Разрешённые IP/CIDR (CSV) для админа; пусто = все. |
| `session_lifetime` | `0` | Длительность сессии в часах; `0` = cookie сессии. |
| `remember_days` | `30` | Срок жизни токенов "Оставаться в системе" в днях (1-365). |
| `maintenance` | `false` | Режим обслуживания: `/` отдаёт выделенную страницу в 503 (все аудитории, сессия сохраняется), а `/api/*` пользователя отвечает 503 - `/api/admin/*` и `/api/ping` остаются активными. Подключённые пользователи переключаются на страницу обслуживания при следующем API-запросе; она сама перезагружается в конце. |
| `force_https` | `false` | Перенаправляет HTTP > HTTPS (301). |
| `hsts` | `false` | Эмитирует `Strict-Transport-Security`, когда запрос HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (по умолчанию, требуется для возврата OAuth2) / `Strict` (ломает поток OAuth2) / `None` (требует HTTPS). |
| `csp_extra` | `''` | Дополнительные директивы CSP (напр. `img-src https:`). |
| `trusted_proxies` | `''` | IP/CIDR (CSV) доверенных обратных прокси. Обязательно, чтобы сделать `X-Forwarded-*` надёжным (rate-limit, allowlist, журналы). |
| `secfetch_allow` | `''` | Дополнительные правила `Sec-Fetch-*` (напр. `site=same-origin;mode=cors`). |

## Обновления

Официальный репозиторий заперт в `GitHubClient::REPO`
(`WatT-For/MXWebMail`). Эта секция - общая для апдейтера ядра и каталога
плагинов (читаемого в репо на ref `update.plugins_ref`, каждый скачанный
файл проверяется по sha1 git-blob) - не редактируется через админ (кроме
`channel`).

| Ключ | По умолчанию | Роль |
|---|---|---|
| `update.repo` | `''` | Dev-fallback, если `GitHubClient::REPO` пуст (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (единственная настройка, экспонируемая в админе). |
| `update.manifest_url` | `''` | Dev: JSON-манифест в формате релиза, обходит API GitHub. |
| `update.plugins_ref` | `'release'` | Git-ref источника каталога плагинов: `'release'` (тег последнего релиза), `'main'` (ветка - push `plugin.json` публикует обновление) или любой литеральный ref. |
| `update.plugins_local` | `''` | Dev/тесты: абсолютный путь папки плагинов - каталог + установка с диска, офлайн и без удалённой проверки. Приоритетнее `plugins_ref`. |

## Функциональность

| Ключ | По умолчанию | Роль |
|---|---|---|
| `attachment_max_size` | `25` | Макс. MB на файл. Должен быть ≤ `attachments_max_total` (выше лимит не действует: upload уже ограничивает `min` обоих - админ отклоняет несогласованное сохранение). |
| `attachments_max_total` | `25` | MB суммарно на сообщение. |
| `allow_additional_accounts` | `true` | Пользователь может добавлять/управлять дополнительными IMAP-аккаунтами (Настройки > Аккаунты). |
| `allow_additional_identities` | `true` | Пользователь может создавать дополнительные идентичности. |
| `popup_identity` | `false` | Открывает настройку идентичности при первом входе, если имя пусто. |
| `dangerous_actions` | `true` | Разрешает очищать/удалять папку, удалять сообщения (включая перемещение в корзину). |
| `contacts_enable` | `true` | Адресная книга + автодополнение. |
| `contacts_suggestions_limit` | `20` | Макс. число подсказок автодополнения (1-100). |
| `import_max_size` | `100` | Макс. размер импортируемого `.mbox` (MB) - `.eml` сохраняют потолок `attachment_max_size`. Не редактируется в админе. |
| `smime_ca_path` | `''` | Путь CA (PEM-файл) для доверия S/MIME; пусто = только проверенная криптографическая подпись (`PKCS7_NOVERIFY`). Не редактируется в админе. |

## `user_defaults` - дефолты настроек пользователя

Переопределяемые пользователем. Ключи, разрешённые на запись через
`/api/settings` - эти плюс объявленные плагинами (hook `settings.allowed`).

| Ключ | По умолчанию | Роль |
|---|---|---|
| `messages_per_page` | `50` | Сообщений на страницу (5-200). |
| `list_sort` | `REVERSE DATE` | Сортировка списка (критерий IMAP SORT в whitelist). |
| `view_images` | `ask` | Внешние изображения: `always` / `ask` / `match` (известный отправитель). |
| `msg_default_action` | `reply` | Кнопка ответа по умолчанию: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Секунды до пометки прочитанным открытого сообщения (0 = сразу). |
| `compose_autosave` | `60` | Секунды между автосохранениями черновика (0 = никогда). |
| `mdn_mode` | `ask` | Подтверждения прочтения: `ask` (баннер запроса) / `never` (скрыто). |
| `autologout` | `30` | Минуты неактивности до выхода (0 = никогда). Не отзывает токен "Оставаться в системе": сессия восстанавливается молча при следующем визите. |
| `list_refresh` | `5` | Минуты между автообновлениями (0 = никогда). |
| `notify_new_mail` | `false` | Уведомление браузера при приходе письма. |
| `contacts_autosave` | `true` | Автоматически сохранять получателя в контакты. |
| `folder_pane_width` | `240` | Ширина колонки папок (px, 140-600). |
| `message_pane_width` | `340` | Ширина колонки списка сообщений (px, 200-800). |
| `layout` | `horizontal` | Макет чтения: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Высота списка сообщений в вертикальном режиме (px, 120-1200). |
| `compose_modal_width` | `0` | Ширина модала композиции (px, 0 = дефолт Bootstrap). |
| `compose_modal_height` | `0` | Высота модала композиции (px, 0 = auto). |
| `identity_modal_width` | `0` | Ширина модала идентичности (px, 0 = дефолт Bootstrap). |
| `identity_modal_height` | `0` | Высота модала идентичности (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Очищать корзину при выходе. |
| `empty_junk_on_logout` | `false` | Очищать папку спама при выходе. |
| `list_threads` | `false` | Группировать сообщения в беседы (IMAP THREAD). |
| `after_delete` | `next` | После удаления/перемещения показанного письма: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Клавиатурные сокращения почтового вида (справка клавишей `?`). |
| `theme` | `default` | Шаблон интерфейса (папка `app/templates/<name>`). |

## TLS (исходящие подключения IMAP/SMTP/Sieve)

| Ключ | По умолчанию | Роль |
|---|---|---|
| `ssl.verify_certificate` | `true` | Проверять сертификат (`false` = самоподписанный сертификат). |
| `ssl.allow_self_signed` | `false` | Принимать самоподписанные сертификаты. |
| `ssl.cafile` / `ssl.capath` | `''` | Свой CA (абсолютный путь / папка). |
| `ssl.disable_compression` | `true` | Отключает сжатие TLS. |
| `ssl.security_level` | `1` | Уровень безопасности OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Возможный клиентский сертификат. |

## Прокси внешних изображений

| Ключ | По умолчанию | Роль |
|---|---|---|
| `image_proxy` | `true` | Отдавать удалённые изображения через `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Разрешённые домены (CSV, `*.домен` ок); пусто = все (приватные IP всегда заблокированы). |

## HTTP - сжатие и кэш браузера

Редактируются в админе, раздел *Производительность > HTTP*.

| Ключ | По умолчанию | Роль |
|---|---|---|
| `http.gzip` | `true` | Сжимает ответы текст/JSON в PHP (gzip) - работает и без `mod_deflate`. Игнорируется при активном `zlib.output_compression`. **За Apache/nginx: `false` рекомендуется** - сервер сжимает как фильтр (streaming), а PHP избегает `gzencode` в памяти. Держать `true` для `php -S` (dev, без `mod_deflate`). |
| `http.gzip_level` | `6` | Уровень сжатия 1-9. |
| `http.gzip_min_size` | `1024` | Минимальный размер тела (байт) для сжатия. |
| `http.etag` | `true` | ETag/Last-Modified + ответы 304 на ассетах, отдаваемых PHP (тема, плагины, favicon). |
| `http.assets_ttl` | `86400` | `max-age` ассетов темы/плагинов (`/assets/theme|plugin/*`). `0` = систематическая ревалидация. С `?v=` (mtime отданного файла) эмитируется `immutable`. |
| `http.favicon_ttl` | `3600` | `max-age` `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (приватный) изображений через `/api/proxy` - служит также TTL дискового кэша (`storage/cache/proxy`). `0` = без кэша (re-fetch + ревалидация SSRF при каждом показе). Очистка управляется `cache.gc_hours`: держать `proxy_ttl ≤ cache.gc_hours`. |

Приватные/API-ответы никогда не получают публичный кэш; HTML - `no-cache`.

**Продакшен**: включить **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ числу PHP-файлов - код сам инвалидирует
свои кэши через `opcache_invalidate`, `opcache.validate_timestamps` может
оставаться активным). Прикладные кэши (`classmap.php`, манифесты плагинов,
словари i18n, `themedata.php`) управляются кодом - ручная инвалидация не
требуется.

## Прикладной кэш (файловый)

Редактируется в админе, раздел *Производительность > Серверный кэш*.

| Ключ | По умолчанию | Роль |
|---|---|---|
| `cache.enabled` | `true` | Включает файловый кэш `app/storage/cache` (списки сообщений IMAP, индексированные на ETag папки, DNS, детекция домена, проверка обновления, GC папки). Не затрагивает ни автозагрузчик (`classmap.php`), ни `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL негативного кэша детекции IMAP/SMTP (с). |
| `cache.update_ttl` | `3600` | TTL результата проверки обновления (с). |
| `cache.gc_hours` | `168` | Интервал очистки просроченных файлов кэша (ч); `0` = никогда. |

## Журналы MailSo (протоколы IMAP/SMTP/Sieve)

| Ключ | По умолчанию | Роль |
|---|---|---|
| `logs.enabled` | `true` | Пишет `storage/logs/mail.log` (секреты маскированы). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` для трассировки протокола. |
| `logs.syslog` | `false` | Дублирование в `syslog()`, если файловый журнал неактивен. |
| `logs.time_zone` | `''` | Часовой пояс меток времени (напр. `Europe/Paris`); пусто = сервер. |
| `logs.max_size` | `5` | Ротация свыше N MiB - действует для `mail.log`, `app.log` и журнала auth. |
| `logs.keep` | `3` | Сохраняемые ротации (`app.1.log` … `app.{keep}.log`, потолок 20). |

## Журнал аутентификации (в стиле fail2ban)

| Ключ | По умолчанию | Роль |
|---|---|---|
| `auth_logging` | `false` | Включает выделенный журнал аутентификации. |
| `auth_logging_filename` | `auth.log` | Файл в `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Плейсхолдеры: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` принимает `AUTH OK`, `AUTH FAILED (<код http>)` или
`AUTH FAILED (totp)`. Журнал покрывает вход webmail и вход админа (логин с
префиксом `admin:`). Для привязки fail2ban (фильтр, jail, logrotate) см.
[fail2ban.md](fail2ban.md).

## Labs / совместимость

| Ключ | По умолчанию | Роль |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Показывать клиенту сырые ошибки SMTP (диагностика, может раскрывать внутренние детали). |
