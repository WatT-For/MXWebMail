# Разработка

*Переведено с французского - французская версия является исходной.*

Документация для понимания архитектуры и расширения проекта (ядро, плагины,
темы).

## 1. Архитектура

```
public/                фронт-контроллер (index.php) + ассеты
app/src/MXWebMail/     код приложения:
  Application/App.php  контейнер сервисов + диспетчер + маршруты
  API.php, Utils.php   фасады совместимости для vendored-кода (MailSo/Lib)
  Compat/              мосты совместимости MailSo (ActionsBridge,
                       ConfigBridge, PluginsBridge)
  Config/              загрузка/запись конфигурации
  Controller/          PageController, ApiController, AdminController,
                       SetupController (мастер /setup, отдаётся пока нет
                       storage/INSTALLED - затем 404) ;
    Api/               API-контроллеры по доменам (Messages, Folders,
                       Contacts, Compose, Accounts, Settings, Filters,
                       Pgp, Poll)
    Admin/             админ-контроллеры по вкладкам (System, Domains, Users,
                       Plugins, Themes)
  Http/                Router, Request, Response, JsonResponse
  Locale/              Translator + словари
  Plugin/              HookRegistry, PluginManager, PluginBase
  Repository/          доступ к данным (Database, User*, Domain, Plugin, Theme…)
  Security/            Csrf, CredentialCrypt, Headers, IpMatch, TotpRequiredException, Wildcard
  Service/             AuthService, MailService, DomainDetector, OAuthService,
                       GitHubClient (общий HTTP Releases), UpdateService (ядро),
                       PluginPackageService (каталог/install/remove плагинов),
                       импорты контактов (CsvContacts, VCardContacts,
                       RainLoopContacts)
  Sieve/               ScriptBuilder - генерация правил фильтров
  Theme/               ThemeManager
app/src/MailSo/        vendored почтовая библиотека (неизменённая)
app/src/Sabre/         sabre/vobject+xml+uri (vCard CardDAV)
app/src/Lib/           утилиты MXWebMailLib (classmap)
app/src/bootstrap.php  собственный автозагрузчик
```

- **Фронтенд**: Knockout + Bootstrap 5.3, ES-модули в `public/assets/js/`
  (app.js, viewmodels/, models/, bindings/, ui/).
- **Без Composer**: собственный автозагрузчик (см. §2).

## 2. Автозагрузчик (`app/src/bootstrap.php`)

- **PSR-4**: `MXWebMail\` > `app/src/MXWebMail`, `Sabre\` > `app/src/Sabre`.
- **Регистронезависимый classmap**: `MailSo\` > `app/src/MailSo`,
  `MXWebMailLib\` > `app/src/Lib` (унаследованный код, файлы в нижнем
  регистре, мультикласс). Генерируется сканом `token_get_all`, кэшируется в
  `app/storage/cache/classmap.php`, пересобирается автоматически при промахе
  (раз на запрос).
- Загружает `functions.php` sabre/uri и sabre/xml ("files"-autoload).

## 3. Хуки ядра

Плагины регистрируются на: `app.routes`, `app.head`, `app.data`,
`auth.login.mapping`, `auth.login.mapped`, `auth.provider`, `auth.post-login`,
`contacts.provider`,
`identities.provider`, `password.change`, `settings.allowed`,
`imap.before-connect`,
`imap.message-headers`, `sieve.before-connect`, `smtp.before-connect`,
`message.meta`,
`message.view`, `message.before-send`, `message.spam`, `attachment.upload`,
`admin.mounts`, `admin.post-login`,
`admin.totp-required`.

На админ-панели: `admin.mounts` (collect) питает `adminMounts` bootstrap -
дескриптор `{js, plugin, section, tab?, label?}` монтируется через биндинг
`pluginMount` (опция `admin: true` > `ctx.admin`, вызовы модуля
префиксуются `/admin/plugin/…` к `Plugin::apiAdmin<Action>`). Без `tab`:
inline-блок в зоне `section` (напр. `security-account`); с `tab` + `label`:
собственная под-вкладка страницы `section` (напр. `security` >
`#/security/<tab>`), рендерится только если плагин активен. Специальная
секция `'page'` (+ опциональный `icon`, класс Font Awesome) создаёт
**страницу верхнего уровня** в боковом меню (`#/<tab>`). `admin.post-login`
эмитируется при входе локального админ-аккаунта; брошенный там
`TotpRequiredException` блокирует доступ (напр. админ-TOTP).

См. [plugins.md](plugins.md) для создания плагина.

## 3bis. Распространение плагинов (GitHubClient + PluginPackageService)

- `GitHubClient` обобщает доступ к GitHub для `UpdateService` (пакет ядра
  `mxwebmail-*.zip` через `coreAsset`) и `PluginPackageService` (чтение репо
  на ref `update.plugins_ref`: `resolveCommit()` > `GET /commits/<ref>` даёт
  sha + tree sha, `tree()` > `GET /git/trees/<sha>?recursive=1` маппит blobы
  `app/plugins/**`, `rawUrl()` > `raw.githubusercontent.com` привязанный к sha
  коммита). Репозиторий заперт константой `REPO`, общий кэш релизов
  `storage/cache/update-check.json`. Каждый обрезанный ассет несёт
  `download_url`, разрешаемый `pickUrl()`: эндпоинт API `releases/assets/<id>`
  (`Accept: application/octet-stream`) если PAT настроен в `config.php` -
  обязательно на приватном форке, где `browser_download_url` отдаёт 404,
  опционально иначе (квота API) - иначе `browser_download_url` (официальный
  репо публичный). Кэши плагинов: `plugins-ref.json` (TTL
  `update_ttl`), `plugins-tree-<sha>.json` и
  `plugin-manifest-<name>-<sha>.json` (неизменные).
- `PluginPackageService`: `catalog()`, `install()` (стейджит каждый файл
  через raw > проверка размера + `blobId()` sha1 git > валидация
  `plugin.json` + `min_app`/`max_app` > swap с единственным бэкапом на
  плагин > инвалидация classmap/opcache), `remove()` (rmrf + очистка строки
  `plugins` через `PluginRepository::delete()`). Общий лок
  `storage/update/update.lock` с `UpdateService`. Стейджинг в
  `storage/update/plugins/`. Альтернативный источник `update.plugins_local`
  (диск, dev/тесты, без удалённой проверки).
- Упаковка release-zip исключает `app/plugins/*` из zip ядра - никакой ассет
  плагина не публикуется: bump `version` в `plugin.json` + push на
  отслеживаемую ветку = публикация. Zip исключает также мета-файлы репо
  (`.github/`, `docs/`, `README.md`, `CHANGELOG.md`, `SECURITY.md`,
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`); `LICENSE` продолжает
  распространяться.

## 4. Кэш и сжатие

Всё настраивается (админ > *Производительность*, секции `http.*` / `cache.*`
в `config.php`):

- **Сжатие**: `App::sendResponse()` вызывает `Response::gzip()` в единственной
  точке отправки - покрывает `php -S`, Apache и Nginx. Уже закодированные или
  несжимаемые ответы пропускаются; `Vary: Accept-Encoding` ставится как только
  тип сжимаем. `zlib.output_compression` активен > no-op.
- **Статические ассеты** (`public/assets/img|js|vendor`): отдаются веб-сервером
  под версионированным URL `/assets/v<APP_VERSION>/…` (rewrite
  `public/.htaccess`, блок `location` nginx документирован в installation.md).
  TTL 1 год immutable под Apache; dev-сервер отдаёт `no-cache`.
- **PHP-ассеты** (`/assets/theme/{n}/{f}`, `/assets/plugin/{n}/{f}`, favicon):
  `PageController::serveAsset()` ставит `Cache-Control` из `http.assets_ttl` /
  `http.favicon_ttl` (+ `immutable` при `?v=`) и, при `http.etag`,
  `ETag`/`Last-Modified` > `304` на `If-None-Match`. `?v=` берутся из
  `theme.json`/`plugin.json` (`ThemeManager::version`, `PluginBase::version`) -
  точечный bump компонента бустит его собственный кэш.
- **Серверный кэш**: `ActionsBridge::Cacher()` возвращает клиента без
  драйвера при `cache.enabled=false` (Get/Set no-op). Периодический GC
  (`cache.gc_hours`, маркер `storage/cache/_gc.ts`) - чистит все файлы папки,
  включая `classmap.php` (пересобирается при следующем запросе). Свои TTL:
  `cache.domain_detect_ttl` (детекция), `cache.update_ttl` (релизы).
- **HTML**: `no-cache` - оболочка всегда ссылается на ассеты текущей версии.
  Ответы API/приватные никогда не получают публичный TTL.

## 5. Соглашения проекта

- **Bootstrap**: один JS-компонент на элемент (`Data`) - никогда `tooltip:`
  (ни другой биндинг) на элементе `data-bs-toggle`; ставить на дочерней
  иконке.
- **Версионирование** semver `0.MINOR.PATCH`: `PATCH` = исправление/рефактор,
  `MINOR` = фича или ломающее изменение. Точечный bump: `APP_VERSION` (ядро)
  или `plugin.json`/`theme.json` (изменённый компонент). Без глобального bump
  при каждом коммите.
