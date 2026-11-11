# Administración

*Traducido del francés - la versión francesa es la que hace fe.*

El panel de administración es accesible en `/{admin_path}` (por defecto
`/admin`). Es una administración **global** (multi-dominios); no hay roles
finos.

## 1. Autenticación admin

Dos modos, según la configuración:

1. **Cuenta local** (`admin_user` + `admin_password_hash` en `config.php`):
   login/contraseña, eventualmente TOTP (`admin_totp_secret`). El TOTP es
   provisionado y aplicado por el plugin `two-factor-auth` (sub-pestaña
   "Doble autenticación" de *Seguridad*, con código QR) - plugin desactivado =
   no exigido en el login.
2. **Fallback IMAP** (si no hay cuenta local): un usuario autenticado vía
   IMAP con el campo `is_admin = 1` abre el admin. Ese derecho se revalida en
   cada petición (una cuenta degradada/bloqueada/eliminada es rechazada
   inmediatamente).

El rate-limit de conexión se aplica también al admin, así como
`admin_ip_allowlist` si está informado. Un admin IMAP no puede degradarse,
bloquearse ni eliminarse a sí mismo.

## 2. Secciones del panel

El panel se compone de pestañas (hash `#/<página>[/<sub-pestaña>]`).

### General

`app_name`, `admin_path`, `language` (lista de idiomas instalados + `auto`),
`session_lifetime`, `remember_days` (duración de los tokens "Permanecer
conectado"), `maintenance`, `update.channel`. Gestiona también el **favicon**
(subida ≤ 512 KB: ico/png/svg/jpg/webp, contenido validado). El modo
**mantenimiento** muestra una página dedicada (503) en `/`: los usuarios
conectados son basculados a ella en su próxima petición API (sesión
conservada) y se recarga sola al final; el admin (`/{admin_path}` +
`/api/admin/*`) permanece plenamente accesible.

### Base de datos

Driver SQLite o MariaDB/MySQL, host/puerto/nombre/usuario/contraseña. Una
prueba (`/api/admin/test-db`) es obligatoria antes de escribir: el servidor
valida la conexión y crea la base si hace falta. Ver
[base-de-donnees.md](base-de-donnees.md).

La tarjeta **Copia de seguridad** descarga un archivo zip (`config.php`,
`app.key`, bases SQLite, `storage/branding`) - **sensible** (credenciales +
clave de cifrado), a conservar en lugar seguro. Una base MariaDB no está
cubierta: `mysqldump` del lado del servidor.

### Funcionalidades

Sub-pestañas *Funcionalidades* y *Por defecto*:

- proxy de imágenes + allowlist, tamaños de los adjuntos;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- todos los ajustes `user_defaults.*` (por defecto de usuarios).

### Rendimiento

Sub-pestañas *HTTP* y *Caché de servidor*:

- `http.*`: compresión gzip (activación, nivel, tamaño mínimo), `http.etag`,
  TTL de los assets tema/plugin, del favicon y del proxy de imágenes;
- `cache.*`: caché de archivo de aplicación (activación, TTL de la detección
  de dominio y de la verificación de actualización, intervalo de purga).

Detalle de las claves en [configuration.md](configuration.md) (secciones
*HTTP* y *Caché de aplicación*).

### Seguridad

Sub-pestañas *Cuenta*, *Doble autenticación* (si el plugin `two-factor-auth`
está activado), *Acceso*, *Cabeceras*, *TLS*:

- cuenta admin local (`admin_user`) y **cambio de la contraseña** admin -
  rellenar los dos campos luego *Guardar*: la contraseña solo se modifica si
  están rellenados e idénticos;
- **TOTP** admin (sub-pestaña del plugin `two-factor-auth`): generación del
  secreto, código QR a escanear, código de confirmación obligatorio antes de
  activar (el secreto nunca se muestra tras el guardado);
- `admin_ip_allowlist` (botón "añadir mi IP"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- sección `ssl.*` completa (certificados salientes).

### Registros

Sub-pestañas *Visor* y *Parámetros*:

- visor de los registros `app.log`, `mail.log` y del registro de auth (200
  últimas líneas), con botón "Vaciar" para truncar el archivo seleccionado;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Dominios

Configuración de los servidores de correo por dominio - ver
[domaines.md](domaines.md). La pestaña *Parámetros* agrupa la resolución
(`default_domain`, `restrict_domains`, `autodetect.*`). Para los dominios
"cuenta secundaria únicamente + proveedor OAuth" (Gmail, Outlook, Yahoo), el
admin solo autoriza el proveedor (opción `oauth` del modal de dominio): las
credenciales de aplicación OAuth2 las registra cada usuario (*Ajustes >
Aplicaciones OAuth2*).

### Usuarios

Lista + búsqueda de usuarios, con botón de actualización (icono) para
recargar la lista. Por usuario (modal, pestañas *Perfil*, *Ajustes*,
*Cuentas/Identidades*, *Sesiones*):

- `display_name`, `is_admin`, `disabled`;
- ajustes de usuario efectivos (edición + reset - el reset restaura los
  `user_defaults` pero **preserva** las claves internas `plugin.*` y
  `user_folders`, ej. secreto 2FA, procedencia de las carpetas);
- cuentas IMAP secundarias (eliminación, salvo la cuenta por defecto);
- identidades (creación/edición/eliminación, identidad por defecto);
- pestaña **Sesiones**: tokens "Permanecer conectado" listados y revocables;
  **Forzar cierre de sesión** revoca los tokens y mata las sesiones activas
  (session_epoch incrementado, rechazo en la petición siguiente).

La eliminación de un usuario es en cascada y elimina también su base de
contactos (`storage/db/users/<id>.sqlite`).

### Extensiones (plugins)

Cada plugin se lista bajo su **nombre mostrado** (campo `label` del
manifiesto, traducido - el nombre técnico `name` queda mostrado al lado en
`code`).

Activación/desactivación de cada plugin + modal de **configuración** generado
desde `config.schema.json` (tipos `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). La columna **Acceso usuarios**
restringe un plugin activado a la superficie admin (página `/{admin_path}`,
`/api/admin/*`, sesiones admin): se vuelve inerte para los usuarios - ver
[plugins.md](plugins.md). Desmarcado por defecto (a autorizar
explícitamente); está ausente para los plugins que solo declaran hooks
`admin.*` (ninguna superficie de usuario).

**Instalación / actualización desde GitHub**: los plugins se leen directamente
en el repositorio bloqueado (`GitHubClient::REPO`, mismo que el updater
núcleo) en el ref `update.plugins_ref` - `'release'` = tag de la última
release por defecto, `'main'` en dev (un push de `plugin.json` publica la
actualización, ninguna release requerida). Cada archivo descargado vía
`raw.githubusercontent.com` se verifica por sha1 de blob git.
El catálogo se carga al abrir la pestaña (caché compartida con el check
núcleo) y es actualizable vía el botón **refrescar** arriba a la derecha; las
entradas del catálogo no instaladas se listan al final de la tabla de
plugins:

- botón **Actualizar** en los plugins instalados cuando una versión más
  reciente está publicada (la carpeta antigua se copia a
  `storage/update/plugins/backup-<name>-<ts>` - una sola conservada,
  rollback automático en caso de fallo);
- botón **Instalar** en las entradas del catálogo no presentes - un plugin
  recién instalado está **desactivado** por defecto;
- botón **Desinstalar**: eliminación **definitiva** de la carpeta y de la
  configuración (fila `plugins` purgada), sin backup;
- `min_app`/`max_app` del `plugin.json` remoto: una entrada incompatible con
  `APP_VERSION` se marca *incompatible* y su instalación se rechaza.

### Migración webmail (RainLoop / SnappyMail)

Si el plugin `webmail-migration` está activado, una página **Migración
webmail** aparece en el menú lateral (antes de *Acerca de*): migra los datos
de una carpeta `data/_data_/_default_/` de una instalación **RainLoop o
SnappyMail**, indicada por **ruta de servidor** o **subida ZIP** (extracción
en `storage/tmp`, purgada vía el botón dedicado o tras 2 h).

- **Analizar**: inventario sin escritura (dominios `*.ini`/`*.json`, cuentas
  encontradas, volúmenes contactos/identidades/claves/ajustes por usuario);
- **Lanzar la importación**: importación idempotente - dominios faltantes,
  contactos (libretas por cuenta + global + DSN PDO declarada en
  `application.ini`, frecuencias y tipos de emails conservados, contactos
  eliminados excluidos), identidades (por defecto = `Id` vacía), ajustes de
  usuario conocidos (mensajes por página, actualización, auto-guardado de
  contactos, carpetas sistema, tema si existe) y claves PGP armored.
  Re-ejecutable sin duplicados.

Solo las cuentas que corresponden a un **usuario existente** se importan;
las demás se listan en el informe. Las contraseñas de las cuentas
adicionales no son recuperables (cifradas por el webmail de origen) - a
recrear manualmente. Los filtros Sieve viven del lado del servidor de correo:
nada que importar.

Del lado del usuario, la pestaña *Ajustes > Contactos* ofrece también una
**importación** `.vcf`/`.csv`/`.sqlite` (libreta `AddressBook.sqlite`
RainLoop/SnappyMail - en una libreta multi-usuarios solo los contactos de la
cuenta se retoman) y una **exportación** `.vcf`, cubriendo toda otra libreta,
incluida una exportación manual de SnappyMail.

### Temas

- tema **por defecto** (escribe `user_defaults.theme`);
- **autorización** por tema (`allowed`): un usuario solo puede elegir un tema
  autorizado (el por defecto queda siempre seleccionable);
- modal de **configuración** del tema (mismos tipos + `color`);
- visualización de la cadena de herencia (`extends`).

Ver [themes.md](themes.md).

### Acerca de

Versión (`APP_VERSION`), PHP/SAPI/límites, driver y tamaño de la base, rutas,
extensiones cargadas. Un enlace al sitio oficial se muestra en la
navbar. La tarjeta de cabecera integra también las **actualizaciones** vía
GitHub Releases (verificación, descarga, aplicación, restauración):

- *Verificar* > *Descargar* > *Aplicar* > finalización automática de las
  migraciones; estados conservados en `storage/update/state.json`;
- en la extracción, `files.sha256` (manifiesto por archivo del paquete) se
  verifica, los prerrequisitos del paquete (`APP_REQ_PHP`/`APP_REQ_FROM` en
  su `App.php`: versión PHP, versión fuente mínima) se controlan, y los
  archivos modificados localmente se señalan antes de la aplicación;
- durante la copia, la API de usuario responde 503 (flag
  `storage/update/applying`, página de mantenimiento del lado web - expira
  tras 15 min en caso de fallo);
- cada archivo sobrescrito se copia en `storage/update/backup-<ts>` con
  `manifest.json` (versión, `schema_version`, listas); bajo SQLite la base
  (`webmail.sqlite` + `db/users/*.sqlite`) se copia tras checkpoint WAL -
  bajo MariaDB, la copia externa (`mysqldump`) sigue a cargo del admin
  (advertencia antes de la aplicación);
- *Restaurar* (rollback): restaura los archivos del último backup, elimina
  los archivos creados y recopia las bases SQLite; bajo MariaDB, rechazo si
  el esquema ha avanzado (restaure el dump luego confirme el forzado);
- el banner "esquema más reciente que el código" señala un rollback de
  archivos sin restauración de base;
- acción *Reinicializar* para abandonar un paquete descargado/extraído.

> El repositorio oficial está bloqueado en `GitHubClient::REPO` y es
> **público**: la verificación funciona sin configuración ni token (solo está
> desactivada mientras la constante no esté informada). `update.channel`
> sigue editable aquí; `update.repo` se ajusta manualmente en `config.php`.

## 3. Escritura de `config.php`

El admin escribe `config.php` **por sección**, de forma atómica (archivo
temporal + `rename`, `chmod 0600`), preservando las claves no provistas. Si
`app/config/` no es escribible, la interfaz lo indica (`saved_readonly`) y los
cambios se muestran pero no se persisten.

## 4. Probar la conexión

En *Dominios*, **Probar la conexión** valida el protocolo de la pestaña activa
del modal (IMAP, SMTP o Sieve - las otras pestañas prueban IMAP), y
**Detectar** rellena los campos sin guardar. La detección (`DomainDetector`)
funciona independientemente de `autodetect.enabled`. Guardar manualmente un
dominio borra el marcador "auto-detectado" (`options.detected`).
