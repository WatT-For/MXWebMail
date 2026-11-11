# Установка

*Переведено с французского - французская версия является исходной.*

## 1. Требования

- **PHP ≥ 8.4** с расширениями: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, и `pdo_sqlite` **или**
  `pdo_mysql`. `ldap` опционально (только LDAP-плагины).
  **`opcache` настоятельно рекомендуется в продакшене** (`opcache.enable=1`) -
  приложение загружает десятки PHP-файлов на запрос.
- Доступный **IMAP**-сервер (и SMTP, ManageSieve опционально) для почты.
- **Никаких зависимостей для установки**: репо самодостаточен (собственный
  автозагрузчик, MailSo, Sabre, ассеты и vendored JS-библиотеки).
- Document root веб-сервера должен быть **`public/`**, никогда корень
  проекта.

## 2. Структура папок

```
public/            веб-корень - index.php, define.php, assets/, .htaccess
app/               всё непубличное (перемещаемо вне веб-корня)
app/src/           код приложения + vendored библиотеки
app/config/        config.php (не версионируется) + config.example.php
app/plugins/       расширения (пусто при установке - плагины из каталога GitHub)
app/templates/     темы/страницы
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - никогда в webroot
```

`public/define.php` определяет `MXWM_APP_DIR` (путь к папке `app/`). Чтобы
вынести `app/` за веб-корень, переместите папку и адаптируйте этот путь
(абсолютный или относительный).

## 3. Установка через мастер `/setup/` (рекомендуется)

1. Направьте vhost на `public/`.
2. Откройте `https://webmail.example.com/`. Пока блокировка
   `app/storage/INSTALLED` не существует, каждый URL перенаправляется на
   `/setup/` - мастер отдаётся самим приложением (никакого установочного
   файла в webroot).
3. Мастер проходит в 5 этапов:
   - **Требования**: проверяет PHP, расширения, наличие vendored-файлов и
     записываемость `app/storage/` (блокирующее) и `app/config/`
     (неблокирующее - `config.php` можно создать вручную).
   - **Приложение**: отображаемое имя (`app_name`), путь админа
     (`admin_path`), максимальный совокупный размер вложений
     (`attachments_max_total`, ограничен 25 MB по умолчанию и ограничен
     `upload_max_filesize`/`post_max_size`). Лимит на файл
     `attachment_max_size` записывается производно: `min(25,
     attachments_max_total)`.
   - **База данных**: SQLite (по умолчанию), MariaDB >= 10.x или MySQL >= 8.0.
     Для MariaDB/MySQL выполняется реальный тест подключения; база создаётся
     (`utf8mb4`) при установке.
   - **Администратор**: локальный админ-аккаунт (`admin_user` + пароль ≥ 8
     символов). Если оставить эти поля пустыми, вход админа падает на
     пользователя `is_admin`, аутентифицированного через IMAP (см.
     [administration.md](administration.md)).
   - **Сводка**: контроль значений, затем кнопка **Установить**.
4. Установка пишет `app/config/config.php` (существующий файл архивируется
   как `config.<timestamp>.bak`), применяет миграции, пытается **обнаружить
   домен хостинга** (SRV > autoconfig Mozilla > MX > зондирование) и
   добавляет его в таблицу `domains`, затем ставит блокировку
   `app/storage/INSTALLED`.
   При клике **Установить** появляется полноэкранный экран прогресса:
   детекция домена может растянуть операцию до ~2 минут - не закрывайте
   страницу (установка завершится на стороне сервера в любом случае). Три
   публичных домена также **предварительно авторизованы как
   дополнительные аккаунты** - `gmail.com`, `outlook.com`, `yahoo.com`
   (требуется OAuth2, первичный вход отклонён; см. [domaines.md](domaines.md)
   §8). Удаляемы в админ > *Домены*.
5. **После установки**: `/setup` отвечает теперь 404 (ничего удалять) и
   **сделайте копию `app/storage/app.key`** (см. §6).

> Если `app/config/` не записываем, мастер показывает содержимое
> `config.php` для создания вручную. Установка продолжается после
> размещения файла.

## 4. Ручная установка

```bash
cp app/config/config.example.php app/config/config.php   # отредактируйте значения
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

Миграция всё равно применяется при первом доступе (`App::db()`), поэтому
достаточно создать `config.php`, затем `app/storage/INSTALLED` (содержимое
не важно, тестируется только `is_file()`). `app/storage/app.key` генерируется
автоматически при первом шифровании учётных данных.

## 5. Веб-развёртывание

### Apache

```apache
<VirtualHost *:443>
    ServerName webmail.example.com
    DocumentRoot /var/www/mxwebmail/public
    <Directory /var/www/mxwebmail/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Корневой `.htaccess` может переписывать в `public/` как переходная сеть, но
официальный document root - `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Должен быть >= attachment_max_size (25 MB по умолчанию).
    # attachments_max_total (сумма вложений) проверяется при отправке: каждый
    # файл приходит в отдельном запросе.
    client_max_body_size 30m;

    # Dotfiles никогда не отдаются
    location ~ /\.(?!well-known) { deny all; }

    # Сеть: приватная папка отклоняется, даже если root по ошибке указывает на корень
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Версионированные ассеты /assets/vX.Y.Z/… > реальный файл (эквивалент
    # RewriteRule .htaccess Apache). Длинный TTL: версия меняется в каждом
    # релизе. URL /assets/theme|plugin/ проходят через try_files
    # > PHP (их заголовки управляются конфигурацией "http").
    location ~ ^/assets/v[0-9.]+/ {
        rewrite ^/assets/v[0-9.]+/(.*)$ /assets/$1 last;
    }
    location ~ ^/assets/(img|js|vendor)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Примечания:

- `.htaccess` инертны под Nginx: блок `location ~ /\.` скрывает dotfiles, а
  `^/(app|dist)` защищает приватную папку.
- `autoindex` выключен по умолчанию (эквивалент `Options -Indexes`).
- Опциональное сжатие: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Под Apache `public/.htaccess` уже покрывает `mod_deflate` + TTL ассетов;
  PHP-сжатие (`http.gzip`) работает в обоих случаях.

### Производительность (рекомендации сервера)

- **HTTP/2 (Apache)**: `a2enmod http2`, затем `Protocols h2 h2c http/1.1` в
  VirtualHost (`h2` достаточно под TLS; `h2c` полезен только небраузерным
  клиентам - Edge/Firefox реализуют h2 только в HTTPS). `mod_http2` требует
  `mpm_event` (уже умолчание с PHP-FPM) - несовместим с
  `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` под нагрузку (дефолт 5 едва хватает на
  несколько вкладок/пользователей одновременно: poll, картинки `cid:`,
  вложения и прокси изображений параллелят запросы).
- **OPcache**: активен по умолчанию в пакете PHP Debian (`opcache.enable=1`
  в FPM) - проверьте `opcache_get_status()` при сомнении.

## 6. Шаги после установки

1. **Копия `app/storage/app.key`**: шифрует все сохранённые учётные данные
   (IMAP-пароли, секреты плагинов). Его потеря заставляет пользователей
   переподключиться; никогда не должен отдаваться или версионироваться.
2. **Создайте хотя бы один домен** в админе (страница *Домены*): при
   `restrict_domains = true` (по умолчанию) вход невозможен без
   настроенного домена. Установщик пытается обнаружить его автоматически.
3. **Установите плагины** из админа, вкладка *Расширения*: zip релиза
   больше не включает плагины - они скачиваются, обновляются и удаляются из
   этого каталога (см. [administration.md](administration.md#расширения-плагины)
   и [plugins.md](plugins.md)). При обновлении ядра уже установленные
   плагины сохраняются (`app/plugins/` исключён из копирования).
4. Проверьте `force_https` / `hsts`, если вы за обратным прокси (см.
   [securite.md](securite.md) для `trusted_proxies`).
5. Задайте `admin_ip_allowlist`, если панель админа должна быть ограничена
   определёнными IP.
6. **Обновления**: выполняются из админа (*О программе*) через GitHub
   Releases - проверка `files.sha256` по файлам, резервная копия файлов +
   SQLite-базы, управляемый откат и оркестрованные миграции базы. Детали в
   [administration.md](administration.md#о-программе) и запасные процедуры
   в [depannage.md](depannage.md#обновления).

## 7. Основные URL

- `/` - webmail (вход > папки > сообщения)
- `/{admin_path}` - панель администрирования (по умолчанию `/admin`)
- `#/@settings/<вкладка>` - настройки пользователя (общие, идентичности,
  контакты, Sieve-фильтры, OpenPGP, вкладки плагинов)
