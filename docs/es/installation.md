# Instalación

*Traducido del francés - la versión francesa es la que hace fe.*

## 1. Requisitos

- **PHP ≥ 8.4** con las extensiones: `sodium`, `mbstring`, `intl`, `openssl`,
  `ctype`, `json`, `iconv`, `zlib`, `xml`, `dom`, y `pdo_sqlite` **o**
  `pdo_mysql`. `ldap` es opcional (solo plugins LDAP).
  **`opcache` fuertemente recomendado en producción** (`opcache.enable=1`) -
  la aplicación carga decenas de archivos PHP por petición.
- Un servidor **IMAP** accesible (y SMTP, ManageSieve opcional) para el
  correo.
- **Ninguna dependencia que instalar**: el repo es autosuficiente
  (autocargador propio, MailSo, Sabre, assets y librerías JS vendoradas).
- El document root del servidor web debe ser **`public/`**, nunca la raíz del
  proyecto.

## 2. Estructura de carpetas

```
public/            raíz web - index.php, define.php, assets/, .htaccess
app/               todo lo no público (puede desplazarse fuera de la raíz web)
app/src/           código de la aplicación + bibliotecas vendoradas
app/config/        config.php (no versionado) + config.example.php
app/plugins/       extensiones (vacío en la instalación - plugins del catálogo GitHub)
app/templates/     temas/páginas
app/storage/       runtime: db, cache, logs, tmp, app.key, INSTALLED - nunca en webroot
```

`public/define.php` define `MXWM_APP_DIR` (ruta de la carpeta `app/`). Para
sacar `app/` de la raíz web, desplace la carpeta y adapte esa ruta (absoluta o
relativa).

## 3. Instalación vía el asistente `/setup/` (recomendada)

1. Apunte el vhost sobre `public/`.
2. Abra `https://webmail.example.com/`. Mientras el bloqueo
   `app/storage/INSTALLED` no exista, toda URL se redirige a `/setup/` - el
   asistente es servido por la propia aplicación (ningún archivo de
   instalación en el webroot).
3. El asistente se desarrolla en 5 etapas:
   - **Requisitos**: verifica PHP, las extensiones, la presencia de los
     archivos vendorados y la escritura de `app/storage/` (bloqueante) y
     `app/config/` (no bloqueante - el `config.php` puede crearse a mano).
   - **Aplicación**: nombre mostrado (`app_name`), ruta de admin
     (`admin_path`), tamaño cumulativo máximo de los adjuntos
     (`attachments_max_total`, techado a 25 MB por defecto y acotado por
     `upload_max_filesize`/`post_max_size`). El límite por archivo
     `attachment_max_size` se escribe derivado: `min(25,
     attachments_max_total)`.
   - **Base de datos**: SQLite (por defecto), MariaDB >= 10.x o MySQL >= 8.0.
     En MariaDB/MySQL, se efectúa una prueba de conexión real; la base se crea
     (`utf8mb4`) en la instalación.
   - **Administrador**: cuenta admin local (`admin_user` + contraseña ≥ 8
     caracteres). Si deja estos campos vacíos, el login admin recae en un
     usuario `is_admin` autenticado vía IMAP (ver
     [administration.md](administration.md)).
   - **Resumen**: control de los valores luego botón **Instalar**.
4. La instalación escribe `app/config/config.php` (un archivo existente se
   archiva como `config.<fecha>.bak`), aplica las migraciones, intenta
   **detectar el dominio de alojamiento** (SRV > autoconfig Mozilla > MX >
   sondeo) y lo añade a la tabla `domains`, luego pone el bloqueo
   `app/storage/INSTALLED`.
   Al hacer clic en **Instalar**, una pantalla de progreso a página completa
   se muestra: la detección de dominio puede hacer durar la operación hasta
   ~2 minutos - no cierre la página (la instalación terminaría igualmente del
   lado del servidor). Tres dominios públicos están también **pre-autorizados
   como cuentas secundarias** - `gmail.com`, `outlook.com`, `yahoo.com`
   (OAuth2 requerido, login primario rechazado; ver [domaines.md](domaines.md)
   §8). Eliminables en admin > *Dominios*.
5. **Tras la instalación**: `/setup` responde ahora 404 (nada que eliminar) y
   **haga copia de `app/storage/app.key`** (ver §6).

> Si `app/config/` no es escribible, el asistente muestra el contenido de
> `config.php` a crear manualmente. La instalación continúa una vez el
> archivo en su sitio.

## 4. Instalación manual

```bash
cp app/config/config.example.php app/config/config.php   # editar los valores
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

La migración se aplica de todos modos en el primer acceso (`App::db()`), basta
pues con crear `config.php` luego `app/storage/INSTALLED` (el contenido
importa poco, solo `is_file()` se prueba). `app/storage/app.key` se genera
automáticamente en el primer cifrado de credencial.

## 5. Despliegue web

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

El `.htaccess` raíz puede reescribir hacia `public/` como red de transición,
pero el document root oficial es `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Debe ser >= attachment_max_size (25 MB por defecto).
    # attachments_max_total (cumul de adjuntos) se verifica al envío: cada
    # archivo llega en petición separada.
    client_max_body_size 30m;

    # Dotfiles nunca servidos
    location ~ /\.(?!well-known) { deny all; }

    # Red: carpeta privada rechazada aunque root apunte por error a la raíz
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Assets versionados /assets/vX.Y.Z/… > archivo real (equivalente del
    # RewriteRule del .htaccess de Apache). TTL largo: la versión cambia en
    # cada release. Las URLs /assets/theme|plugin/ pasan por try_files
    # > PHP (sus cabeceras quedan gestionadas por la config "http").
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

Observaciones:

- Los `.htaccess` son inertes bajo Nginx: es el bloque `location ~ /\.` el
  que oculta los dotfiles, y `^/(app|dist)` el que protege la carpeta
  privada.
- `autoindex` está desactivado por defecto (equivalente de
  `Options -Indexes`).
- Compresión opcional: `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Bajo Apache, `public/.htaccess` cubre ya `mod_deflate` + los TTL de assets;
  la compresión PHP (`http.gzip`) funciona en ambos casos.

### Rendimiento (recomendaciones servidor)

- **HTTP/2 (Apache)**: `a2enmod http2` luego `Protocols h2 h2c http/1.1` en el
  VirtualHost (`h2` basta bajo TLS; `h2c` solo es útil a clientes no
  navegadores - Edge/Firefox solo implementan h2 en HTTPS). `mod_http2`
  exige `mpm_event` (ya el por defecto con PHP-FPM) - es incompatible con
  `mpm_prefork`/`mod_php`.
- **PHP-FPM**: `pm.max_children` adaptado a la carga (el por defecto 5 apenas
  basta para algunas pestañas/usuarios simultáneos: el poll, las imágenes
  `cid:`, los adjuntos y el proxy de imágenes paralelizan las peticiones).
- **OPcache**: activo por defecto en el paquete PHP Debian
  (`opcache.enable=1` en FPM) - verifique `opcache_get_status()` en caso de
  duda.

## 6. Etapas post-instalación

1. **Haga copia de `app/storage/app.key`**: cifra todas las credenciales
   almacenadas (contraseñas IMAP, secretos de plugins). Su pérdida obliga a
   los usuarios a reconectarse; nunca debe ser servido ni versionado.
2. **Cree al menos un dominio** en el admin (página *Dominios*): con
   `restrict_domains = true` (por defecto), ningún login es posible sin
   dominio configurado. El instalador intenta detectarlo automáticamente.
3. **Instale los plugins** desde el admin, pestaña *Extensiones*: el zip de
   release ya no embarca los plugins - se descargan, actualizan y
   desinstalan desde ese catálogo (ver
   [administration.md](administration.md#extensiones-plugins) y
   [plugins.md](plugins.md)). En la actualización del núcleo, los plugins ya
   instalados se preservan (`app/plugins/` se excluye de la copia).
4. Verifique `force_https` / `hsts` si está detrás de un proxy inverso (ver
   [securite.md](securite.md) para `trusted_proxies`).
5. Ponga `admin_ip_allowlist` si el panel admin debe restringirse a ciertas
   IP.
6. **Actualizaciones**: se hacen desde el admin (*Acerca de*) vía las GitHub
   Releases - verificación `files.sha256` por archivo, copia de archivos +
   base SQLite, rollback asistido y migraciones de base orquestadas. Detalle
   en [administration.md](administration.md#acerca-de) y procedimientos de
   respaldo en [depannage.md](depannage.md#actualizaciones).

## 7. URLs principales

- `/` - webmail (login > carpetas > mensajes)
- `/{admin_path}` - panel de administración (por defecto `/admin`)
- `#/@settings/<pestaña>` - ajustes de usuario (general, identidades,
  contactos, filtros Sieve, OpenPGP, pestañas de plugins)
