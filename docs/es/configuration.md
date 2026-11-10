# Referencia de configuración

*Traducido del francés - la versión francesa es la que hace fe.*

El archivo `app/config/config.php` solo define las claves que **sobrescriben**
los valores por defecto (`app/config/config.example.php`, cargado por
`Config.php`). Copie `config.example.php` en `config.php` y adáptelo. El panel
de administración edita la mayoría de estas claves por sección; solo algunas
exigen edición manual (`update.*` sobre todo, pero también `import_max_size`,
`smime_ca_path`, `logs.max_size` y `logs.keep`).

Los arrays asociativos se fusionan en profundidad; las listas se reemplazan.

## Almacenamiento

| Clave | Defecto | Función |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) o DSN PDO completo (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Usuario PDO (MariaDB). |
| `db_pass` | `''` | Contraseña PDO (MariaDB). |

## Aplicación

| Clave | Defecto | Función |
|---|---|---|
| `app_name` | `MXWebMail` | Nombre mostrado (título, PWA, TOTP). |
| `favicon` | `''` | Archivo favicon subido a `storage/branding`, servido vía `/favicon.ico`; vacío = icono por defecto. |
| `website_url` | `https://mxrelay.fr/webmail/` | Enlace al sitio oficial (botón admin); vacío = oculto. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Enlace "Sponsor" a la página de donaciones (botón admin + ajustes webmail); vacío = oculto. |
| `debug` | `false` | Devuelve los errores internos a los clientes API (nunca en producción). |
| `language` | `auto` | `auto` = `Accept-Language` del navegador; si no, código de un idioma instalado (`fr`, `en`…). Fallback final `en`. |

## Dominios

| Clave | Defecto | Función |
|---|---|---|
| `default_domain` | `auto` | Sufijo de un login tecleado sin `@dominio`: `auto` = host HTTP (sin `www.` ni puerto), `''` = ninguno, o un dominio fijo. |
| `restrict_domains` | `true` | Solo los dominios nombrados de la tabla `domains` se aceptan en el login (ni `*` ni la detección se aplican). |
| `autodetect.enabled` | `false` | Detección automática de la configuración de un dominio desconocido en el login. |
| `autodetect.use_ispdb` | `false` | Fallback a la base Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Sondeo de puertos `imap.`/`mail.`/`smtp.`/`sieve.<dominio>`. |

Los servidores IMAP/SMTP/Sieve se configuran **por dominio** en el admin (ver
[domaines.md](domaines.md)). El dominio `*` sirve de parámetros catch-all.

## Seguridad

| Clave | Defecto | Función |
|---|---|---|
| `admin_path` | `admin` | Segmento de URL del panel admin. |
| `admin_user` | `''` | Cuenta admin local; vacío = fallback a un usuario `is_admin` verificado vía IMAP. |
| `admin_password_hash` | `''` | `password_hash('contraseña', PASSWORD_DEFAULT)` de la cuenta admin local. |
| `admin_totp_secret` | `''` | Secreto TOTP (base32) de la cuenta admin local, escrito por el plugin `two-factor-auth`; vacío = desactivado. No exigido si el plugin está desactivado. |
| `login_max_attempts` | `5` | Intentos de conexión antes de bloqueo temporal. |
| `login_lock_seconds` | `300` | Duración del bloqueo (segundos). |
| `admin_ip_allowlist` | `''` | IPs/CIDR (CSV) autorizadas sobre el admin; vacío = todas. |
| `session_lifetime` | `0` | Duración de sesión en horas; `0` = cookie de sesión. |
| `remember_days` | `30` | Duración de vida de los tokens "Permanecer conectado" en días (1-365). |
| `maintenance` | `false` | Modo mantenimiento: `/` sirve una página dedicada en 503 (todos públicos, sesión conservada) y el `/api/*` de usuario responde 503 - `/api/admin/*` y `/api/ping` siguen activos. Los usuarios conectados basculan a la página de mantenimiento en su próxima petición API; esta se recarga sola al final. |
| `force_https` | `false` | Redirige HTTP > HTTPS (301). |
| `hsts` | `false` | Emite `Strict-Transport-Security` cuando la petición es HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (por defecto, requerido para el retorno OAuth2) / `Strict` (rompe el flujo OAuth2) / `None` (exige HTTPS). |
| `csp_extra` | `''` | Directivas CSP adicionales (ej. `img-src https:`). |
| `trusted_proxies` | `''` | IPs/CIDR (CSV) de los reverse proxies de confianza. Obligatorio para hacer fiable `X-Forwarded-*` (rate-limit, allowlist, logs). |
| `secfetch_allow` | `''` | Reglas `Sec-Fetch-*` adicionales (ej. `site=same-origin;mode=cors`). |

## Actualizaciones

El repositorio oficial está bloqueado en `GitHubClient::REPO`
(`WatT-For/MXWebMail`). Esta sección - compartida por el updater núcleo y el
catálogo de plugins (leído en el repo en el ref `update.plugins_ref`, cada
archivo descargado verificado por sha1 de blob git) - no es editable vía el
admin (salvo `channel`).

| Clave | Defecto | Función |
|---|---|---|
| `update.repo` | `''` | Fallback dev si `GitHubClient::REPO` está vacío (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (único ajuste expuesto en el admin). |
| `update.manifest_url` | `''` | Dev: manifiesto JSON en formato release, elude la API GitHub. |
| `update.plugins_ref` | `'release'` | Ref git fuente del catálogo de plugins: `'release'` (tag de la última release), `'main'` (rama - un push de `plugin.json` publica la MAJ) o cualquier ref literal. |
| `update.plugins_local` | `''` | Dev/tests: ruta absoluta de una carpeta de plugins - catálogo + install desde disco, fuera de red y sin verificación remota. Prioritario sobre `plugins_ref`. |

## Funcionalidades

| Clave | Defecto | Función |
|---|---|---|
| `attachment_max_size` | `25` | MB máximo por archivo. Debe ser ≤ `attachments_max_total` (más allá, el límite no tiene efecto: el upload acota ya al `min` de ambos - el admin rechaza el guardado incoherente). |
| `attachments_max_total` | `25` | MB cumulados por mensaje. |
| `allow_additional_accounts` | `true` | El usuario puede añadir/gestionar cuentas IMAP secundarias (Ajustes > Cuentas). |
| `allow_additional_identities` | `true` | El usuario puede crear identidades adicionales. |
| `popup_identity` | `false` | Abre el ajuste de identidad en el primer login si el nombre está vacío. |
| `dangerous_actions` | `true` | Autoriza vaciar/eliminar una carpeta, eliminar mensajes (incluido el traslado a la papelera). |
| `contacts_enable` | `true` | Libreta de direcciones + autocompletado. |
| `contacts_suggestions_limit` | `20` | Número máx de sugerencias de autocompletado (1-100). |
| `import_max_size` | `100` | Tamaño máx de un `.mbox` importado (MB) - los `.eml` conservan el techo `attachment_max_size`. No editable en el admin. |
| `smime_ca_path` | `''` | Ruta CA (archivo PEM) para la confianza S/MIME; vacío = solo firma criptográfica verificada (`PKCS7_NOVERIFY`). No editable en el admin. |

## `user_defaults` - por defecto de los ajustes de usuario

Sobrescribibles por el usuario. Las claves autorizadas en escritura vía
`/api/settings` son estas más las declaradas por los plugins (hook
`settings.allowed`).

| Clave | Defecto | Función |
|---|---|---|
| `messages_per_page` | `50` | Mensajes por página (5-200). |
| `list_sort` | `REVERSE DATE` | Ordenación de la lista (criterio IMAP SORT en whitelist). |
| `view_images` | `ask` | Imágenes externas: `always` / `ask` / `match` (remitente conocido). |
| `msg_default_action` | `reply` | Botón de respuesta por defecto: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Segundos antes de marcar leído un mensaje abierto (0 = inmediato). |
| `compose_autosave` | `60` | Segundos entre guardados automáticos del borrador (0 = nunca). |
| `mdn_mode` | `ask` | Acuses de recibo: `ask` (banner de solicitud) / `never` (oculto). |
| `autologout` | `30` | Minutos de inactividad antes del cierre (0 = nunca). No revoca el token "Permanecer conectado": la sesión se restaura silenciosamente en la visita siguiente. |
| `list_refresh` | `5` | Minutos entre actualizaciones automáticas (0 = nunca). |
| `notify_new_mail` | `false` | Notificación del navegador al llegar un mail. |
| `contacts_autosave` | `true` | Guardar automáticamente el destinatario en contactos. |
| `folder_pane_width` | `240` | Anchura de la columna de carpetas (px, 140-600). |
| `message_pane_width` | `340` | Anchura de la columna de lista de mensajes (px, 200-800). |
| `layout` | `horizontal` | Disposición de lectura: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Altura de la lista de mensajes en modo vertical (px, 120-1200). |
| `compose_modal_width` | `0` | Anchura del modal de redacción (px, 0 = por defecto Bootstrap). |
| `compose_modal_height` | `0` | Altura del modal de redacción (px, 0 = auto). |
| `identity_modal_width` | `0` | Anchura del modal de identidad (px, 0 = por defecto Bootstrap). |
| `identity_modal_height` | `0` | Altura del modal de identidad (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Vaciar la papelera al cerrar sesión. |
| `empty_junk_on_logout` | `false` | Vaciar la carpeta de spam al cerrar sesión. |
| `list_threads` | `false` | Agrupar los mensajes en conversaciones (IMAP THREAD). |
| `after_delete` | `next` | Tras eliminar/mover el mail mostrado: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Atajos de teclado de la vista de correo (ayuda vía la tecla `?`). |
| `theme` | `default` | Plantilla de interfaz (carpeta `app/templates/<name>`). |

## TLS (conexiones salientes IMAP/SMTP/Sieve)

| Clave | Defecto | Función |
|---|---|---|
| `ssl.verify_certificate` | `true` | Verificar el certificado (`false` = certificado autofirmado). |
| `ssl.allow_self_signed` | `false` | Aceptar los certificados autofirmados. |
| `ssl.cafile` / `ssl.capath` | `''` | CA personalizada (ruta absoluta / carpeta). |
| `ssl.disable_compression` | `true` | Desactiva la compresión TLS. |
| `ssl.security_level` | `1` | Nivel de seguridad OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Eventual certificado de cliente. |

## Proxy de imágenes externas

| Clave | Defecto | Función |
|---|---|---|
| `image_proxy` | `true` | Servir las imágenes remotas vía `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Dominios autorizados (CSV, `*.dominio` ok); vacío = todos (IPs privadas siempre bloqueadas). |

## HTTP - compresión & caché del navegador

Editables en el admin, sección *Rendimiento > HTTP*.

| Clave | Defecto | Función |
|---|---|---|
| `http.gzip` | `true` | Comprime las respuestas texto/JSON en PHP (gzip) - funciona también sin `mod_deflate`. Ignorado si `zlib.output_compression` está activo. **Detrás de Apache/nginx: `false` aconsejado** - el servidor comprime en filtro (streaming) y PHP evita un `gzencode` en memoria. Mantener `true` para `php -S` (dev, sin `mod_deflate`). |
| `http.gzip_level` | `6` | Nivel de compresión 1-9. |
| `http.gzip_min_size` | `1024` | Tamaño mínimo del cuerpo (bytes) para comprimir. |
| `http.etag` | `true` | ETag/Last-Modified + respuestas 304 sobre los assets servidos por PHP (tema, plugins, favicon). |
| `http.assets_ttl` | `86400` | `max-age` de los assets tema/plugin (`/assets/theme|plugin/*`). `0` = revalidación sistemática. Con `?v=` (mtime del archivo servido), `immutable` se emite. |
| `http.favicon_ttl` | `3600` | `max-age` de `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (privado) de las imágenes vía `/api/proxy` - sirve también de TTL a la caché en disco (`storage/cache/proxy`). `0` = ninguna caché (re-fetch + revalidación SSRF en cada visualización). Purga gestionada por `cache.gc_hours`: mantener `proxy_ttl ≤ cache.gc_hours`. |

Las respuestas privadas/API nunca reciben caché público; el HTML es
`no-cache`.

**Producción**: activar **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ el número de archivos PHP - el código
invalida él mismo sus cachés vía `opcache_invalidate`,
`opcache.validate_timestamps` puede quedar activo). Las cachés de aplicación
(`classmap.php`, manifiestos de plugins, diccionarios i18n, `themedata.php`)
están gestionadas por el código - ninguna invalidación manual requerida.

## Caché de aplicación (archivo)

Editables en el admin, sección *Rendimiento > Caché de servidor*.

| Clave | Defecto | Función |
|---|---|---|
| `cache.enabled` | `true` | Activa la caché de archivo `app/storage/cache` (listas de mensajes IMAP indexadas sobre el ETag de la carpeta, DNS, detección de dominio, verificación de actualización, GC de la carpeta). No afecta ni al autocargador (`classmap.php`) ni a `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL de la caché negativa de detección IMAP/SMTP (s). |
| `cache.update_ttl` | `3600` | TTL del resultado de verificación de actualización (s). |
| `cache.gc_hours` | `168` | Intervalo de purga de los archivos de caché expirados (h); `0` = nunca. |

## Logs MailSo (protocolos IMAP/SMTP/Sieve)

| Clave | Defecto | Función |
|---|---|---|
| `logs.enabled` | `true` | Escribe `storage/logs/mail.log` (secretos enmascarados). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` para trazar el protocolo. |
| `logs.syslog` | `false` | Duplicado hacia `syslog()` si el log de archivo está inactivo. |
| `logs.time_zone` | `''` | Zona horaria de las marcas de tiempo (ej. `Europe/Paris`); vacío = servidor. |
| `logs.max_size` | `5` | Rotación más allá de N Mio - vale para `mail.log`, `app.log` y el registro de auth. |
| `logs.keep` | `3` | Rotaciones conservadas (`app.1.log` … `app.{keep}.log`, techo 20). |

## Registro de autenticación (estilo fail2ban)

| Clave | Defecto | Función |
|---|---|---|
| `auth_logging` | `false` | Activa el registro de autenticación dedicado. |
| `auth_logging_filename` | `auth.log` | Archivo en `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholders: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` vale `AUTH OK`, `AUTH FAILED (<código http>)` o
`AUTH FAILED (totp)`. El registro cubre el login webmail y el login admin
(login con prefijo `admin:`). Para la conexión fail2ban (filtro, jail,
logrotate), ver [fail2ban.md](fail2ban.md).

## Labs / compatibilidad

| Clave | Defecto | Función |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Mostrar los errores SMTP brutos al cliente (diagnóstico, puede revelar detalles internos). |
