# Base de datos

*Traducido del francés - la versión francesa es la que hace fe.*

MXWebMail funciona con **SQLite** (por defecto), **MariaDB >= 10.x** o
**MySQL >= 8.0**. El driver se elige mediante `db_dsn` en `config.php`.

## 1. Conexión

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (modo WAL +
  `foreign_keys`).
- Si no, un DSN PDO completo (ej. `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`)
  con `db_user`/`db_pass`.

Opciones PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

## 2. Migraciones

El esquema es **portable** entre SQLite/MariaDB/MySQL (claves primarias
autoincrementadas adaptadas al driver; MySQL >= 8.0 requerido por las funciones
de ventana SQL usadas en v12). Las migraciones están versionadas
(`meta.schema_version`) y se aplican automáticamente en el primer acceso a la
base (`App::db()`), incluso durante la instalación. Bajo el driver `mysql`,
los objetos ya presentes (columna, índice) se detectan vía `information_schema`:
una re-ejecución tras un fallo a mitad de migración es idempotente.

Tras una actualización del código, la pestaña *Acerca de* dispara la
**finalización** (`/api/admin/update/finalize`): las migraciones de la nueva
versión se ejecutan en contexto admin, luego las bases de contactos por usuario
se migran a su vez (una base en fallo se registra y se reintenta en su próximo
acceso). Como respaldo: `php app/bin/post-update.php` vuelve a ejecutar las
migraciones en CLI. Si el esquema es más reciente que el código (rollback de
archivos sin restaurar la base), se pone `meta.schema_ahead` y se señala en
*Acerca de* - las migraciones quedan entonces suspendidas.

## 3. Tablas globales

| Tabla | Contenido |
|---|---|
| `meta` | pares clave/valor (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (único), `display_name`, `is_admin`, `disabled`, `session_epoch` (incrementado por "Forzar cierre de sesión" - rechaza las sesiones activas en la siguiente petición), `created_at`, `last_login_at`. |
| `accounts` | cuentas IMAP de los usuarios: `email`, `imap_user`, `imap_pass` (cifrado), `is_default` (cuenta principal, modificable en Ajustes > Cuentas), `position` (orden de visualización de las cuentas secundarias, reordenable por arrastrar y soltar en Ajustes > Cuentas), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` almacena entonces el *refresh token* cifrado). |
| `oauth_apps` | aplicaciones OAuth2 por usuario: `provider`, `client_id`, `secret` (cifrado), `tenant` - una app por `(user_id, provider)` (ver [domaines.md](domaines.md) §8). |
| `user_settings` | ajustes de usuario (clave/valor), PK `(user_id, key)`. |
| `identities` | identidades de envío: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | servidores de correo por dominio (ver [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (reservado a admin si 0 - por defecto), `config` (JSON) por plugin. |
| `themes` | `config` (JSON) y `allowed` por tema. |
| `pgp_keys` | llavero OpenPGP (`key_data` armored, `is_private`). |
| `auth_tokens` | tokens "Permanecer conectado": `selector` (único), `token` (SHA-256 del validador, rotado en cada uso), `agent` (User-Agent), `ip` (en la emisión), `last_ip` (última restauración), `created_at`, `last_used_at`, `expires_at`. |

Las antiguas tablas `contacts` / `contact_emails` fueron desplazadas fuera de la
base global (ver §5).

## 4. `app.key`

`app/storage/app.key` es una clave maestra de 32 bytes (64 hex), generada en el
primer cifrado de credencial. Cifra (XSalsa20-Poly1305, libsodium secretbox):

- las contraseñas IMAP en sesión y en `accounts.imap_pass` (y los *refresh
  tokens* OAuth2 de las cuentas `auth != 'password'`);
- los secretos de aplicaciones OAuth2 de los usuarios (`oauth_apps.secret`);
- los secretos de plugins (ej. contraseña CardDAV).

**Haga una copia y no la pierda nunca**: su pérdida invalida todas las
credenciales almacenadas (los usuarios deben volver a conectarse). Nunca debe
ser servida ni versionada.

## 5. Bases por usuario (contactos)

Los contactos están aislados en bases SQLite individuales:
`app/storage/db/users/<id>.sqlite` (tablas `contacts`, `contact_emails`). Esto
vale **también bajo MariaDB**: los datos personales permanecen en archivos
SQLite.

La migración `contacts_moved` copia una sola vez los contactos de la base
global a estos archivos (transacción + `INSERT OR IGNORE`, autorreparable), y
luego elimina las tablas globales.

## 6. Pasar de SQLite a MariaDB/MySQL

1. En el admin (*Base de datos*), indique el DSN/host/puerto/nombre/usuario/
   contraseña de MariaDB. La prueba de conexión es **obligatoria** y crea la
   base (`utf8mb4`, colación `utf8mb4_unicode_ci`) si no existe.
2. Las migraciones reejecutan el esquema sobre la nueva base.

> **Importante**: el cambio de `db_dsn` reparte sobre un **esquema virgen** -
> los datos (usuarios, dominios, ajustes) **no se migran**. No existe
> herramienta de exportación. Los archivos de contactos por usuario, en cambio,
> permanecen inalterados.

## 7. Copia de seguridad / restauración

- SQLite: copie `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) y
  `app.key`.
- MariaDB/MySQL: haga un dump de la base con `mysqldump` (o `mariadb-dump` en
  MariaDB), más `app.key` y `db/users/*.sqlite`.
