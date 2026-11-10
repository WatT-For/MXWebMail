# Plugins

*Traducido del francés - la versión francesa es la que hace fe.*

Los plugins son extensiones confinadas en `app/plugins/<name>/`, cargadas
cuando están activadas (desde el admin, pestaña *Extensiones*). Se registran
en **hooks** del núcleo.

> **Sesión de solo lectura**: los endpoints GET cierran la sesión PHP pronto
> (`session_write_close`) para liberar el bloqueo. Los hooks llamados desde un
> GET (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **no deben nunca escribir
> `$_SESSION`** - esas escrituras se perderían silenciosamente.

## 0. Distribución vía el repositorio GitHub

Los plugins **no están incluidos** en el zip de release `mxwebmail-<ver>.zip`:
se leen directamente en el repositorio a un **ref configurable**
(`update.plugins_ref` - `'release'` = tag de la última release por defecto,
`'main'` o cualquier ref git). El catálogo (`PluginPackageService`, pestaña
*Extensiones* - las entradas no instaladas figuran al final de la tabla) se
construye desde la API git:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` lista los archivos bajo
  `app/plugins/` con su **sha1 de blob git** y su tamaño;
- el `plugin.json` de cada plugin se lee vía `raw.githubusercontent.com`
  (fijado al sha del commit) - versión, descripción, `min_app`/`max_app`;
- **Instalar / actualizar**: cada archivo del plugin se descarga vía `raw`,
  se verifica (tamaño + sha1 de blob del tree), luego `plugin.json` se valida
  (nombre concordante, versión semver, `main` sano, compatibilidad
  `APP_VERSION`) antes de reemplazar la carpeta - un plugin instalado está
  **desactivado** por defecto; la carpeta antigua va a
  `storage/update/plugins/backup-<name>-<ts>` (una sola conservada, rollback
  automático);
- **Desinstalar**: eliminación definitiva de la carpeta + purga de la fila
  `plugins` (configuración perdida), sin backup.

Publicar una versión de plugin = bumpear `version` en su `plugin.json` y
pushear sobre la rama seguida por `update.plugins_ref` - ninguna release ni
asset requerido.

El repositorio está bloqueado en `GitHubClient::REPO` (misma constante que el
updater núcleo) - ninguna URL arbitraria se acepta. `update.channel` y la
caché de release (`cache.update_ttl`) se comparten con la verificación de
actualización del núcleo; el ref>commit se cachea a ese TTL, el tree y los
manifiestos se direccionan por su sha (inmutables). El `check` estático exige
`version` semver en `plugin.json`.

Para dev/tests fuera de red, `update.plugins_local` (ruta absoluta de una
carpeta de plugins en `config.php`) sirve de fuente: catálogo e instalación
por copia de disco, sin verificación remota.

Un plugin activado puede también **reservarse a la superficie admin**
(columna *Acceso usuarios* de la pestaña *Extensiones*, flag `user_allowed`
en la tabla `plugins`, **desmarcado por defecto**): solo se carga entonces en
la página `/{admin_path}`, las rutas `/api/admin/*` y las sesiones admin. En
otro lugar es totalmente inerte (ningún hook, `/api/plugin/<name>/*` > 404) -
incluso para los usuarios `is_admin` no conectados al panel. Los hooks de
login admin (`admin.post-login`, `admin.totp-required`, `admin.mounts`)
siguen funcionando. Un plugin cuyos hooks declarados en el manifiesto son
todos hooks `admin.*` no tiene ninguna superficie de usuario: la columna no
muestra entonces switch para él (`-`).

## 1. Plugins del catálogo oficial

| Plugin | Función | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Añade `X-Originating-IP` (IP del remitente) a los envíos. | `message.before-send` |
| `anti-spoofing` | Detección de usurpación en el lector: Return-Path/Reply-To/Sender incoherentes, display-name imitando una marca (homoglifos y confundibles detectados), dominio señuelo (subdominio tramposo, typosquat, trans-TLD), SPF/DKIM/DMARC evaluados en alineación con el dominio del remitente (veredictos leídos en `Authentication-Results`, `Received-SPF` o las cabeceras SpamAssassin/rspamd) - los subdominios de los dominios protegidos son legítimos. | `imap.message-headers`, `message.view` |
| `avatars` | Avatar del remitente: iconos de servicios conocidos, Gravatar, favicon del dominio, iniciales. | `app.data` |
| `black-list` | Lista negra de conexión (wildcards + excepciones) - verificada sobre el email **resuelto** (post-mapping). | `auth.login.mapped` |
| `white-list` | Lista blanca de conexión (wildcards + excepciones) - verificada sobre el email **resuelto** (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Sincronización de contactos desde una libreta CardDAV (Nextcloud, Radicale, iCloud…) - **HTTPS + host público exigidos**. | `app.data` |
| `change-password` | Cambio de contraseña (drivers poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Etiquetas de color sobre los mensajes (manuales + reglas automáticas). | `app.data`, `message.meta` |
| `ics-viewer` | Vista previa de las invitaciones/adjuntos `.ics` en el lector **+ respuesta iTIP** (Aceptar/Tal vez/Rechazar sobre `METHOD:REQUEST`, enviada por SMTP al organizador). | `app.data` |
| `imap-contacts-suggestions` | Sugerencias de contactos desde los remitentes de una carpeta IMAP. | `contacts.provider` |
| `ldap-contacts` | Sugerencias de contactos desde un directorio LDAP (autocompletado). | `contacts.provider` |
| `ldap-login` | Resolución LDAP: uid/alias > dirección IMAP real antes del login. | `auth.login.mapping` |
| `mailbox-detect` | Detección de las carpetas sistema y creación de las que falten. | `app.data` |
| `pwa` | Hace el webmail instalable (manifiesto + service worker mínimo, iconos). | `app.head`, `app.routes` |
| `webmail-migration` | Página admin de importación desde una carpeta `data` de **RainLoop o SnappyMail** (ruta de servidor o ZIP): dominios, libretas `AddressBook.sqlite`/PDO, identidades, ajustes, carpetas sistema, claves PGP - escaneo dry-run luego importación idempotente, cuentas desconocidas ignoradas. | `admin.mounts` |
| `two-factor-auth` | TOTP de dos factores (Google Authenticator, Aegis…) - cuentas de usuario **y** cuenta admin local (provisioning QR en una sub-pestaña dedicada de Admin > Seguridad; exigido en el login admin mientras el plugin esté activado). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Banner de anuncio del administrador (página app, opcionalmente página de login - mantenimiento). Solo texto en claro. | `app.head` |
| `attachments-clamav` | Escaneo ClamAV (`clamd`, `zINSTREAM`) de los adjuntos subidos y de las importaciones - rechazo en detección, política `on_error` configurable. | `attachment.upload` |
| `filelink` | Adjuntos demasiado grandes para el SMTP enviados como **enlace de descarga expirable** (`/filelink/<token>` - attachment forzado, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Aprendizaje spam/ham en cada (des)marcado: comando externo (`rspamc`, `sa-learn`) o copia IMAP hacia una carpeta dovecot-antispam. | `message.spam` |
| `wkd-keys` | Descubrimiento automático de las claves PGP de los destinatarios: **WKD** (advanced + direct), cosecha de las cabeceras **Autocrypt**, rescate `keys.openpgp.org` opcional - importación propuesta al redactar. | `app.data`, `message.view` |
| `seasonal-decorations` | Paisaje monocromo discreto en los banners según la fecha (Año Nuevo, Pascua, 1 de abril, Halloween, Navidad): biblioteca de objetos SVG recompuesta en capas `mask-image` vía `scenes.json` ({svg, pos, size}), color contrastado automático - ventanas ajustables, opcional fuera de sesión, sin animación. | `app.head` |
| `link-confirmation` | Modal de confirmación antes de abrir un enlace del cuerpo de un mail (anti-phishing): destino real mostrado, señalización de enlaces sospechosos (texto ≠ URL, IP literal, punycode, credenciales en la URL), dominios de confianza y "no preguntar más para este dominio". | `app.head` |
| `always-cc-bcc` | Destinatarios Cc o Bcc sistemáticos al enviar (archivo de conformidad), por cuenta o todas - el Bcc queda invisible. | `message.before-send` |
| `report-abuse` | Botón "Señalar" (barra de acciones): reenvía los mails marcados como `.eml` adjuntos hacia una dirección abuse administrada, marcado spam opcional. | `app.data` |
| `vcf-viewer` | Vista previa de los adjuntos `.vcf` en el lector + importación en un clic a la libreta (botón integrado al frontend cuando `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Cuota de envío por cuenta (mensajes/hora, mensajes/día, destinatarios/día) - rechazo 429 más allá. Solo webmail. | `message.before-send` |
| `login-notify` | Mail de alerta a la cuenta en una conexión desde una IP desconocida (modelo Gmail) - depositado no leído en la bandeja, opt-out por usuario. | `auth.post-login`, `app.data` |
| `sso-header` | SSO vía proxy de autenticación (cabecera de usuario de confianza) - exige IPs de proxy declaradas y una contraseña maestra IMAP. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Página admin agregando los informes DMARC (rua) de una bandeja IMAP dedicada - parsing XML/gzip, agregados por dominio e IP origen. | `admin.mounts` |

## 2. Configuración de un plugin

En el admin (*Extensiones* > botón ⚙), la configuración se genera desde el
archivo `config.schema.json` del plugin. Tipos soportados: `string`, `text`,
`integer`, `number`, `password`, `bool`, `enum`, `color`, `list` - `color`
renderiza un selector nativo + un campo `#RRGGBB` (el valor vacío se acepta:
el campo recae en el replegue del plugin). Los valores se almacenan en la
tabla `plugins` (columna `config`, JSON).

Detalles de configuración notables:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (por defecto `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimato), luego los parámetros
  propios del driver (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` con
  placeholders `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`, `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (placeholder `%q`), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (un dominio protegido cubre
  también sus subdominios, ej. `accountprotection.microsoft.com`), `brands`
  (detectados en el nombre mostrado tras normalización de los acentos y de
  los caracteres confundibles - "AppIe TV" es reconocido como "Apple TV"), y
  los conmutadores
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  Los resultados SPF/DKIM/DMARC solo se tienen en cuenta cuando están
  alineados con el dominio del `From`: un `dkim=fail` sobre la firma del
  transportista o un `spf=fail` sobre un mailfrom relayeado (SRS) no
  desencadena alerta.
- **`two-factor-auth`** - `issuer` (por defecto `app_name`), `label`
  (`email` = dirección completa, `local` = parte antes de `@`; `local` evita
  que las apps TOTP deduzcan un icono de marca del dominio, ej. Yahoo) y
  `icon_url` (parámetro `image=` no estándar, mostrado por
  Aegis/andOTP/FreeOTP - ignorado por Authy; vacío = favicon público de la
  instancia). El plugin provisiona también la 2FA de la cuenta admin local:
  secreto `admin_totp_secret` en `config.php` (vía la API admin
  `/api/admin/plugin/two-factor-auth/*`), pending en sesión, exigido en el
  login admin vía el hook `admin.post-login`. Desactivar el plugin desactiva
  la aplicación admin.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  valores separados por espacios, comas o punto y comas), `exceptions`,
  `auth_error` (403 "cuenta no autorizada" > 401 genérico). Atención a la
  semántica heredada del plugin legacy: para black-list, `exceptions` =
  emails de la lista negra que **siguen autorizados**; para white-list, si
  `exceptions` está vacío todo email fuera de la lista blanca se rechaza,
  pero si está informado **solo los emails de las excepciones se rechazan**
  y todo lo demás pasa - no poner allí direcciones "autorizadas además"
  (efecto inverso al esperado).

- **`link-confirmation`** - `mode` (`all` = todo enlace externo, `suspicious`
  = solo los enlaces sospechosos), `whitelist` (dominios de confianza, uno
  por línea o separados por espacio/coma/punto y coma - subdominios
  incluidos), `warn_mailto` (confirmar también `mailto:`/`tel:`, desactivado
  por defecto) y `remember` (casilla "no preguntar más para este dominio" -
  memorizado en el navegador vía localStorage, no del lado del servidor).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (destinos, vacío =
  inactivo), `senders` (wildcards de remitentes, `*` = todos).
- **`report-abuse`** - `abuse_address` (requerido, vacío = inactivo),
  `subject_prefix`, `mark_spam` (marca también en spam vía `message.spam` -
  alimenta el aprendizaje).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = ilimitado), `exempt` (wildcards de cuentas eximidas). Solo acota el
  webmail: un cliente IMAP/SMTP directo elude la cuota.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (pestaña Ajustes de opt-out por usuario).
- **`sso-header`** - `header` (`X-Forwarded-User` o `REMOTE_USER`),
  `trusted_ips` (**requerido, vacío = plugin inerte**), `master_pass`
  (requerido; modo *same* = passdb `master=yes`), `master_user` (opcional:
  login `user*master`), `allowed_domains`, `hide_form`. La instancia no debe
  ser alcanzable eludiendo el proxy (la cabecera sería falsificable).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (bandeja
  IMAP de los informes), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Conexión IMAP propia del plugin (las opciones del
  dominio no se aplican).

Algunos plugins no tienen esquema admin: su configuración es **por usuario**
(ej. `carddav-contacts`, `color-tags`, `two-factor-auth` para el estado de la
cuenta).

## 3. Crear un plugin

### Estructura mínima

```
app/plugins/mi-plugin/
├── plugin.json          # manifiesto
├── MiPlugin.php         # clase principal
├── config.schema.json   # (opcional) esquema de configuración admin
├── langs/<lang>.php     # diccionarios (claves con prefijo plugin.<name>.)
└── js/ o assets/        # (opcional) assets servidos vía /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "mi-plugin",
	"label": "plugin.mi-plugin.label",
	"class": "MiPlugin",
	"main": "MiPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mi-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (opcional) es el nombre mostrado del plugin en Admin > Extensiones
(clave i18n `plugin.<name>.label` recomendada, texto literal aceptado; el
nombre técnico `name` queda mostrado al lado y sirve de identificador).

`min_app`/`max_app` (opcionales, semver `x.y.z`) acotan la compatibilidad con
`APP_VERSION` - la instalación o actualización se rechaza fuera de rango, y
el catálogo marca la entrada *incompatible*.

### Clase principal

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MiPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Hooks disponibles

- **Núcleo**: `app.routes`, `app.head` (HTML inyectado en el `<head>` de
  `app.php`, `login.php` y `maintenance.php` - las vistas `admin*.php` no lo
  emiten), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > descriptor `{js, plugin, section,
  tab?, label?}` renderizado por `pluginMount` en el panel admin - sin `tab`:
  bloque inline en la zona llamada `section`, ej. `security-account`; con
  `tab` + `label`: sub-pestaña dedicada de la página llamada `section`, ej.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, emitido en el login de la cuenta admin local -
  lanzar `TotpRequiredException` exige un segundo factor),
  `admin.totp-required` (`first` > booleano: muestra el campo TOTP en la
  página de login admin).

Cada plugin declara en `hooks` los hooks que escucha. Un manifiesto que solo
declara hooks `admin.*` marca el plugin como puramente admin - la columna
*Acceso usuarios* de la pestaña Extensiones no muestra switch para él. El
núcleo llama `HookRegistry::run('hook', …)`; el plugin implementa un método
del mismo nombre (o un `on<Hook>` según la convención del núcleo).

### API genérica

Todo plugin es llamable vía `/api/plugin/<name>/<action>` (GET/POST) >
método `api<Action>(Request $r, ?array $user)`. El token CSRF se exige en
todos los métodos (el cliente `api.js` lo envía sistemáticamente) - las
llamadas directas sin cabecera `X-CSRF-Token` se rechazan en 403.

Del lado admin, `/api/admin/plugin/<name>/<action>` (GET/POST) > método
`apiAdmin<Action>(Request $r)`: sesión admin (`$_SESSION['admin']`) + CSRF
requerido; no hay `$user` (la cuenta admin local no es una fila `users`).
Atención: `requireAdmin` cierra la sesión en escritura - para almacenar un
estado allí (ej. secreto en espera), reabrirla vía
`$this->app->startSession()` luego `session_write_close()`.

### Pestaña de usuario

Para añadir una pestaña en `#/@settings/…`, provea JS servido vía
`/assets/plugin/<name>/<file>` y declare la pestaña (ver los plugins
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth` como
ejemplos).

### Traducciones

Claves con prefijo `plugin.<name>.` en `langs/<lang>.php`;
`description`/`label`/`help` de los manifiestos y esquemas aceptan estas
claves (pass-through si no). Lado PHP: `$this->t('clave')`, lado JS:
`ctx.t('clave')` en `mount(element, ctx)`.
