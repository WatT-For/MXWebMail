# Solución de problemas / FAQ

*Traducido del francés - la versión francesa es la que hace fe.*

## Instalación

**Todas las URL redirigen a `/setup/`**
Normal: el bloqueo `app/storage/INSTALLED` está ausente. Termine la
instalación, o cree el archivo manualmente tras haber puesto `config.php`.

**El asistente muestra "config/ no escribible"**
Aviso no bloqueante: copie el contenido mostrado en `app/config/config.php` a
mano, luego relance la instalación. Compruebe también que `app/storage/` es
escribible (bloqueante).

**La prueba de conexión MariaDB falla**
Verifique host/puerto/credenciales, y que el usuario tiene derechos para crear
la base (`CREATE DATABASE`). El charset debe ser `utf8mb4`.

## Conexión / login

**Ningún usuario puede conectarse tras la instalación**
Con `restrict_domains = true` (por defecto), hace falta **al menos un dominio
nombrado** en la tabla (el wildcard `*` se ignora para esta verificación). El
instalador intenta detectarlo; si no, créelo en *Dominios*.

**Login rechazado sin razón (cuenta bloqueada)**
- Demasiados intentos: espere `login_lock_seconds` (por defecto 300 s), o
  purgue `app/storage/cache/login_attempts.json`.
- Los plugins `black-list`/`white-list` pueden rechazar el login (404/403 o
  error genérico según `auth_error`).
- Un usuario `disabled` es rechazado incluso con una sesión válida.

**El login admin no funciona**
- Cuenta local (`admin_user`): verifique `admin_password_hash` y el TOTP
  eventual.
- Fallback IMAP: el usuario debe tener `is_admin = 1`.
- `admin_ip_allowlist` puede bloquear su IP.

## Servidores de correo

**Imposible conectarse al servidor IMAP/SMTP**
Use el botón **Probar la conexión** en *Dominios* (resultado por protocolo).
Causas frecuentes: puerto/cifrado incoherente (993/ssl vs 143/tls),
certificado autofirmado (ver `ssl.verify_certificate`/`ssl.allow_self_signed`),
o firewall.

**Certificado autofirmado rechazado**
`ssl.verify_certificate = false` o `ssl.allow_self_signed = true` (a usar solo
con pleno conocimiento de causa).

**El dominio no se detecta automáticamente**
`autodetect.enabled` está desactivado por defecto. Actívelo, o use el botón
**Detectar** de la página *Dominios*.

**"Señalar como spam" no filtra los mails siguientes**
El botón mueve el mensaje a la carpeta Correo no deseado (o pone el keyword
IMAP `$Junk` si el servidor no tiene carpeta Junk) - no enseña nada al
servidor. Los filtros Sieve solo se aplican en la entrega: la plantilla
*Anti-spam* del asistente (Ajustes > Filtros) clasifica los mails marcados
`X-Spam-Flag: YES` por el MTA. El aprendizaje automático (spam/ham) exige una
configuración del lado del servidor - Dovecot IMAPSieve (script report-spam/ham
disparado por la copia hacia/desde Junk, que el MOVE de la app dispara) o
rspamd/sa-learn.

## Seguridad / HTTPS

**El sitio permanece en HTTP detrás de un proxy inverso**
Informe `trusted_proxies` (IP del proxy) para que `X-Forwarded-Proto` sea
honrado, luego active `force_https` / `hsts`.

**El rate-limit o la allowlist no ven la IP real**
Misma causa: sin `trusted_proxies`, `REMOTE_ADDR` se usa tal cual.

**El favicon no se muestra**
El favicon subido debe pesar ≤ 512 KB (ico/png/svg/jpg/webp); si no, queda el
icono por defecto.

## Datos / registros

**Pérdida de `app.key`**
Todas las credenciales almacenadas se vuelven indescifrables: los usuarios
deben volver a conectarse. Haga siempre copia de `app.key`.

**¿Dónde están los registros?**
`app/storage/logs/app.log` (aplicativo), `mail.log` (protocolos
IMAP/SMTP/Sieve, si `logs.enabled`), y el registro de auth (`auth_logging`).
Visibles en el admin (*Registros*). Integración fail2ban:
[fail2ban.md](fail2ban.md).

**Pasar a MariaDB vació los datos**
Comportamiento esperado: el cambio de `db_dsn` reparte sobre un esquema virgen
(los archivos de contactos por usuario se conservan). No hay herramienta de
migración de datos.

## Actualizaciones

**"Fallo: No encontrado en GitHub … repo privado o recurso ausente"**
El repositorio oficial está bloqueado (`GitHubClient::REPO` =
`WatT-For/MXWebMail`). GitHub devuelve 404 - no 403 - para un recurso ausente
o un acceso insuficiente; el mensaje indica el endpoint culpable
(`releases/latest`, `commits/…` o una ruta `raw` del catálogo de plugins). Un
404 persistente significa en general un recurso ausente (ninguna release
publicada, `update.plugins_ref` desconocido).

**HTTP 404 en `…/releases/download/…` (catálogo de plugins o descarga de actualización)**
En repo privado, el `browser_download_url` de los assets devuelve 404 incluso
con un token válido. Corregido a partir de la 0.36.1: los assets se descargan
vía el endpoint API `releases/assets/<id>` cuando un PAT está configurado en
`config.php`.

**El sitio responde 503 "update" / página de mantenimiento durante una actualización**
Normal: el flag `app/storage/update/applying` corta la API de usuario durante
la copia de archivos (nunca mezcla de código antiguo/nuevo). Se retira al
final; si queda tras un fallo, expira solo al cabo de 15 min - o elimine
`app/storage/update/applying` manualmente.

**"Migraciones pendientes" tras una actualización**
La finalización (`/api/admin/update/finalize`) no se ejecutó: botón
*Finalizar* en *Acerca de*, o en CLI `php app/bin/post-update.php`. Sin
acción, las migraciones se aplican igualmente en la próxima petición
(migración perezosa), pero es preferible controlarlas.

**Volver atrás tras una actualización (rollback)**
Botón *Restaurar* en *Acerca de*: restaura los archivos de
`app/storage/update/backup-<ts>` (los 3 últimos backups se conservan), elimina
los archivos creados y recopia las bases SQLite guardadas. Bajo **MariaDB**,
las migraciones no son reversibles: restaure primero la base desde su
`mysqldump`, luego relance el rollback (se propone la confirmación forzada).
Si hay que partir de cero: reinstale el paquete de origen y restaure
`app/storage/` (incluido `app.key` y `db/`).

**Banner "esquema de la base más reciente que el código"**
`meta.schema_ahead`: el código instalado es más antiguo que el esquema
(rollback de archivos sin restauración de la base, o paquete más antiguo
desplegado). Restaure la base o reaplique la versión correspondiente; las
migraciones permanecen suspendidas mientras exista la diferencia.

## Varios

**"Reenviar tal cual" (bounce): ¿el destinatario ve el mail como spam?**
Posible: el reenvío rompe SPF/DMARC del lado final (el servidor de envío ya no
corresponde al remitente original) - comportamiento normal de los bounce, las
cabeceras `Resent-*` documentan la retransmisión. Para conservar la
autenticación, prefiera el reenvío clásico.

**¿Bajo Nginx, las carpetas privadas son accesibles?**
No, si el `root` apunta a `public/` y el bloque `location ~ /\.` +
`^/(app|dist)` está presente. Nunca apunte el `root` a la raíz del proyecto.

**Error 403 "cross_site" en una petición legítima**
La protección `Sec-Fetch-*` rechazó la petición. Si integra una API externa
legítima, añada una regla en `secfetch_allow`.

**Depurar un error interno (500)**
Active temporalmente `debug = true` (nunca en producción) para ver el mensaje,
luego consulte `app.log`.
