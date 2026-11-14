# Seguridad

*Traducido del francés - la versión francesa es la que hace fe.*

## 1. Modelo de seguridad integrado

### "Permanecer conectado" (tokens persistentes)

La casilla "Permanecer conectado" emite una cookie `mxwm_remember`
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, duración
`remember_days` - 30 días por defecto). La tabla `auth_tokens` solo almacena
el **hash SHA-256** del validador; este se **rota en cada uso** (una cookie
robada ya presentada es detectable e invalidada). La restauración vuelve a
descifrar la contraseña IMAP (`CredentialCrypt`) y vuelve a verificar las
credenciales - un fallo elimina el token. Cada token registra la **IP en la
emisión** (`ip`), la **última IP** conocida (`last_ip`, en cada restauración)
y el User-Agent - visibles en Ajustes > Sesiones para detectar una sesión
desconocida. Los tokens se listan allí y son revocables, y el token actual se
revoca en el cierre de sesión **manual** (el cierre por inactividad
`autologout` lo preserva: la sesión se restaura silenciosamente en la visita
siguiente). Toda sesión nueva (login normal o restauración) se posiciona en
la última cuenta consultada - con repliegue a la cuenta por defecto.

### CSRF

Todo POST exige un token CSRF de sesión (`X-CSRF-Token`, campo de formulario o
clave JSON); las API de plugins (`/api/plugin/…`) lo exigen sea cual sea el
método HTTP. Una segunda capa, basada en las cabeceras **`Sec-Fetch-*`**,
rechaza las peticiones cross-site salvo navegación GET hacia un documento -
incluso sin activación del usuario, porque el navegador retira
`Sec-Fetch-User` tras cada redirección (portales, enlaces reescritos)
(`secfetch_allow` permite ampliar estas reglas).

### Cabeceras HTTP

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, HSTS opcional, y una
**CSP** con nonce por petición (la directiva `script-src` incluye
`unsafe-eval`, requerido por Knockout). Los adjuntos servidos en línea reciben
`Content-Security-Policy: sandbox`.

### Cifrado de credenciales

Las contraseñas IMAP (y los secretos de plugins) se cifran
**XSalsa20-Poly1305** (`sodium_crypto_secretbox`, libsodium) con la clave
maestra `app/storage/app.key`, en sesión y en base. Ver
[base-de-donnees.md](base-de-donnees.md).

### Cuentas secundarias OAuth2

Los dominios públicos (Gmail, Outlook, Yahoo - ver
[domaines.md](domaines.md)) se añaden por **authorization code + PKCE**: el
`state` anti-CSRF y el verifier se mantienen en sesión, el `redirect_uri` se
reconstruye del lado del servidor (host filtrado como `force_https`). Solo el
*refresh token* se persiste, cifrado como una contraseña; el *access token*
efímero solo existe en memoria durante la conexión (XOAUTH2/OAUTHBEARER). La
rotación de refresh token (Microsoft, Yahoo) se reaplica en base en cada
intercambio. El email del `id_token` sirve de control de coherencia; la prueba
de pertenencia sigue siendo la conexión IMAP OAuth en sí. El callback
(`/api/oauth/callback`) solo devuelve una mini-página
`postMessage`>`window.close()` para el popup de consentimiento.

Las credenciales de aplicación OAuth2 son **por usuario** (tabla
`oauth_apps`, introducidas en la primera adición de cuenta o en los ajustes):
el `secret` se cifra con la misma clave maestra y nunca se devuelve a la
interfaz. Una app eliminada o editada invalida las cuentas que dependen de
ella (el refresh token queda ligado al client_id de origen).

### Rate-limit del login

`login_max_attempts` (5) luego bloqueo `login_lock_seconds` (300 s). El
contador se persiste en `storage/cache/login_attempts.json` (clave
`sha256(IP|login)`, purga de entradas inactivas > 1 h, escritura bajo lock
`flock`). Un bloqueo activo ni se prolonga ni se re-registra por los intentos
rechazados (429). Se aplica también al login admin. Un registro de
autenticación tipo fail2ban está disponible (`auth_logging*`, placeholders
`{request:ip}`, `{imap:login}`, `{result}`; CR/LF neutralizados): cubre los
logins webmail y admin (login con prefijo `admin:`) así como los fallos de
código TOTP. Integración completa: [fail2ban.md](fail2ban.md).

### Saneamiento del HTML de los mensajes

El cuerpo de los mensajes se sanea en el navegador (DOMParser inerte +
allowlist): etiquetas y atributos restringidos (sin
`script`/`iframe`/`object`/`on*`…), bloqueo de los esquemas
`javascript:`/`vbscript:`/`data:`, neutralización de los estilos tramposos
(`position:fixed`, contenido oculto), prefijado de clases. La CSP es la
segunda capa.

### Envío de mensajes

- **From**: whitelist estricta - la dirección de la cuenta o una identidad
  declarada (IDN aceptado en sus dos formas); cualquier otro valor se rechaza
  (403).
- **Destinatarios**: los campos To/Cc/Bcc pasan por un parse estricto - un
  segmento inválido no se descarta silenciosamente, el envío se rechaza (400)
  con la lista de direcciones incomprendidas.
- **CR/LF**: neutralizados en el asunto, las cabeceras de threading
  (In-Reply-To, References), los nombres de carpetas referenciados y los
  parámetros MIME de los adjuntos (`filename=`).
- **Bcc**: la cabecera se retira de los datos enviados al servidor SMTP pero
  se conserva en la copia "Elementos enviados" (visible solo para el
  remitente).
- **Editor**: el iframe TinyMCE lleva una `Content-Security-Policy` que
  bloquea toda carga remota - el contenido de un mensaje reenviado/pegado no
  puede exfiltrar un acuse de lectura al abrirse.
- **Adjuntos temporales**: tokens con ámbito de usuario, nombre saneado
  (`basename` + caracteres de control eliminados), purga a las 24 h.
- **Errores SMTP**: el cliente recibe un mensaje genérico; las respuestas
  brutas del servidor solo se exponen si `labs.smtp_show_server_errors` está
  activado (diagnóstico, puede revelar detalles internos).

### OpenPGP

El cifrado/descifrado se hace **enteramente en el navegador** (OpenPGP.js). El
servidor solo almacena el llavero armored; las claves privadas están
protegidas por su passphrase y nunca se descifran del lado del servidor.
**Solo el cuerpo del mensaje se cifra**: el envío de adjuntos se rechaza
cuando el cifrado está activado (saldrían en claro).

### Proxy de imágenes

Las imágenes remotas pasan por `/api/proxy`: solo `http(s)`, allowlist
opcional (wildcards), **anti-SSRF** (rechazo de IPs privadas/reservadas,
revalidado en **cada** redirección - 3 máximo), timeout, tamaño acotado. El
MIME real del cuerpo se verifica contra una **allowlist bitmap** (png, jpeg,
gif, webp, avif, bmp, ico) - SVG y todo tipo activo se rechazan - y la
respuesta lleva `Content-Security-Policy: sandbox` + `nosniff`. Los registros
solo conservan scheme+host+ruta (nunca la query, que puede contener tokens).

### Gestión de IPs y proxies

`X-Forwarded-For`/`X-Forwarded-Proto` solo se tienen en cuenta si
`REMOTE_ADDR` figura en `trusted_proxies` (CIDR). La detección HTTPS
(`isHttps`) sigue la misma regla.

## 2. Recomendaciones de endurecimiento

- **Document root = `public/`** únicamente; nunca apuntar a la raíz del
  proyecto. Las carpetas privadas (`app/`, `config/`, `storage/`…) tienen sus
  `.htaccess` de protección, pero solo es una red de seguridad.
- **HTTPS obligatorio**: active `force_https` + `hsts` en producción.
- **Detrás de un proxy inverso** (Nginx, HAProxy, Cloudflare…): informe
  `trusted_proxies` para hacer fiables rate-limit, allowlist y logs; sin ello,
  todo `X-Forwarded-*` se ignora.
- **Restrinja el admin**: `admin_ip_allowlist` (IP/CIDR CSV) y un `admin_path`
  no trivial; active el **TOTP admin** vía el plugin `two-factor-auth` (el
  secreto `admin_totp_secret` solo se exige mientras el plugin está activado).
- **Cuenta admin local**: prefiera `admin_user` + `admin_password_hash`
  (contraseña fuerte) en lugar del fallback IMAP para la administración.
- **Haga copia de `app.key`** fuera del webroot; nunca lo versione.
- **`debug = false`** en producción (no exponer los errores internos).
- **El asistente `/setup`** está integrado al núcleo: responde 404 una vez
  puesto el bloqueo `app/storage/INSTALLED` - ya ningún archivo de
  instalación que eliminar en el webroot.
- Verifique `ssl.verify_certificate`: `false` solo es aceptable para
  certificados autofirmados controlados.
- En modo mantenimiento, `maintenance = true` sirve una página de
  mantenimiento dedicada en 503 en `/` (usuarios conectados basculados en su
  próxima petición API, sesión conservada) y corta el `/api/*` de usuario (el
  admin y `/api/ping` permanecen activos).

## 3. Límites conocidos

- El repositorio `GitHubClient::REPO` está bloqueado en `WatT-For/MXWebMail`:
  los paquetes instalados (zip núcleo de la release, archivos de plugins
  leídos en el repo en el ref `update.plugins_ref` vía la API git +
  `raw.githubusercontent.com`, cada uno verificado por sha1 de blob git) solo
  provienen de ese repositorio - ninguna URL arbitraria configurable. El
  paquete núcleo se verifica en dos niveles: suma sha256 del asset (archivo
  `<zip>.sha256` de la release) luego `files.sha256` (manifiesto por archivo
  embarcado, controlado tras extracción); las constantes
  `APP_REQ_PHP`/`APP_REQ_FROM` del `App.php` del paquete declaran los
  prerrequisitos (versión PHP, versión fuente mínima) aplicados antes de la
  copia. El repo oficial es **público**: la API, los assets y los `raw`
  funcionan sin token (la cuota anónima de la API de GitHub queda cubierta por
  la caché `cache.update_ttl`). Un PAT de GitHub opcional (acceso
  `Contents: Read` al repo) configurado en `config.php` puede elevar la cuota
  o servir un fork privado vía `update.repo` - los assets se sirven entonces
  por el endpoint `releases/assets/<id>` (`browser_download_url` devuelve 404
  en repo privado incluso autenticado). `update.plugins_local` y `update.manifest_url`
  (manifiesto de release alternativo) están reservados al dev/tests -
  modificables solo vía `config.php`, nunca vía la API admin. Durante la copia
  de los archivos, el flag `storage/update/applying` corta la API de usuario
  (503, expira tras 15 min).
- El cambio SQLite > MariaDB no migra los datos (esquema virgen).
- Las claves privadas OpenPGP se almacenan armored del lado del servidor (la
  passphrase protege la clave misma, sin encapsulado suplementario).
