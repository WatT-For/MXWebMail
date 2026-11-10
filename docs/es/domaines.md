# Dominios y servidores de correo

*Traducido del francés - la versión francesa es la que hace fe.*

Los servidores IMAP/SMTP/Sieve se configuran **por dominio** en el panel admin
(*Dominios*). Cada dominio describe cómo alcanzar el servidor de correo de sus
usuarios.

## 1. Campos de un dominio

| Campo | Valor |
|---|---|
| `name` | Nombre de dominio (ej. `example.com`), o `*` para el catch-all. |
| `imap_host` | Host del servidor IMAP. |
| `imap_port` | Puerto IMAP (por defecto `993`). |
| `imap_encryption` | `ssl` (por defecto), `tls` (STARTTLS) o `none`. |
| `smtp_host` | Host del servidor SMTP (envío). |
| `smtp_port` | Puerto SMTP (por defecto `465`). |
| `smtp_encryption` | `ssl` (por defecto), `tls` o `none`. |
| `sieve_host` | Host ManageSieve (opcional). |
| `sieve_port` | Puerto Sieve (por defecto `4190`). |
| `active` | Activar/desactivar el dominio. |

## 2. Opciones avanzadas (`options`, JSON)

El campo `options` acepta un objeto JSON con ajustes finos por dominio:

- `whitelist` - lista de direcciones autorizadas para este dominio;
- `secondary_only` - el dominio solo es utilizable como **cuenta secundaria**
  (adición de cuenta desde un usuario ya conectado); el login primario se
  rechaza con "Dominio reservado a las cuentas secundarias";
- `oauth` - proveedor OAuth2 del dominio (`google`, `microsoft`, `yahoo`):
  las cuentas secundarias se añaden por consentimiento del proveedor (popup),
  nunca por contraseña (ver §8);
- `imap.shortLogin` - truncar el login;
- `imap.lowerLogin` - forzar el login en minúsculas;
- `imap.timeout` - tiempo de conexión IMAP;
- `imap.disabled_capabilities` - capacidades IMAP a desactivar (servidores
  defectuosos). Ejemplo: `ESORT` para un servidor que lo anuncia sin
  implementar las opciones de retorno RFC 5267 (`RETURN (PARTIAL ...)`) - el
  webmail ya detecta este caso y bascula solo a la ordenación clásica; la
  clave solo sirve si la detección no se aplica (caché desactivado);
- `imap.body_text_limit` - tamaño máx (bytes) de los cuerpos de texto
  recuperados al abrir un mensaje (por defecto `0` = ilimitado; un techo ~256 K
  economiza el fetch de los boletines muy largos);
- `imap.message_list_limit` - más allá (por defecto `10000` mensajes en la
  carpeta), MailSo evita el SORT del servidor y cachea la lista de UIDs en
  segundo plano;
- `smtp.useAuth` - forzar/desactivar la autenticación SMTP;
- `smtp.timeout` - tiempo de conexión SMTP;
- `sieve.encryption` - cifrado de la conexión Sieve.

## 3. Dominio `*` (catch-all)

El dominio `*` provee los **parámetros por defecto** aplicados a todo dominio
no declarado - pero solo cuando `restrict_domains = false`: bajo
`restrict_domains`, `*` se ignora y no puede autorizar un login primario. Es
la forma más simple de servir varios dominios detrás del mismo servidor de
correo.

## 4. Resolución del dominio en el login

En el login, el dominio se determina así:

1. Si el usuario teclea un login con `@dominio`, se usa ese dominio.
2. Si no, `default_domain` se aplica: `auto` (por defecto) = host HTTP sin
   `www.` ni puerto; un nombre fijo; o `''` (ninguno).
3. Si `restrict_domains = true` (por defecto), solo se aceptan los dominios
   **nombrados** presentes en la tabla (`*` y la detección se ignoran) -
   **cree pues al menos un dominio antes de todo login**.

## 5. Detección automática

`autodetect.*` (desactivado por defecto) permite descubrir la configuración de
un dominio desconocido en el momento del login:

- `enabled`: activa la detección;
- `use_ispdb`: consulta la base Mozilla (`autoconfig.thunderbird.net`);
- `probe`: sondea `imap.`/`mail.`/`smtp.<dominio>`.

El orden de detección es: DNS SRV > autoconfig Mozilla > MX > sondeo de
puertos.

Sieve se detecta cualquiera que sea la fuente: `_sieve._tcp` (SRV), si no
sondeo del puerto **4190** sobre `sieve.<dominio>` (si `probe` activo o vía el
botón *Detectar*) luego sobre el host IMAP encontrado - Dovecot sirve casi
siempre ManageSieve sobre el mismo host. El cifrado no hay que configurarlo:
la conexión bascula a STARTTLS automáticamente cuando el servidor lo anuncia.

El botón **Detectar** de la página *Dominios* lanza la misma detección a
demanda, sin depender de `autodetect.enabled`, y rellena los campos del
dominio.

> El instalador `/setup/` intenta también esta detección sobre el host del
> vhost (y sus padres) para crear el primer dominio automáticamente.

## 6. Prueba

El botón **Probar la conexión** valida el protocolo de la pestaña activa del
modal (IMAP, SMTP o Sieve; las otras pestañas prueban IMAP). Úselo tras cada
modificación para verificar que credenciales, puertos y cifrados son correctos
antes de dejar que los usuarios se conecten.

## 7. Caso común: un solo servidor de correo

Para un servidor único (ej. Postfix + Dovecot en `mail.example.com` sirviendo
`example.com`):

1. Cree el dominio `example.com` con `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, y eventualmente `sieve_host`.
2. Deje `default_domain = auto` y `restrict_domains = true`.
3. Los usuarios se conectan con `user@example.com` o simplemente `user` (el
   dominio se deduce del host HTTP).

Variante multi-dominios: un dominio `*` con `restrict_domains = false` sirve
de catch-all - todo dominio no declarado hereda sus parámetros (ver §3;
atención, el login primario sigue siendo posible en todos los dominios -
`secondary_only` puede rechazarlo, ver §8).

## 8. Dominios públicos como cuenta secundaria (Gmail, Outlook, Yahoo)

Gmail, Outlook.com y Yahoo ya no aceptan la autenticación IMAP por simple
contraseña - la adición de estas cuentas pasa por **OAuth2** (consentimiento
en un popup, luego XOAUTH2/OAUTHBEARER para IMAP y SMTP).

Las credenciales de aplicación OAuth2 (client ID, secret, tenant Microsoft)
son **propias de cada usuario**: el admin no registra ninguna app - solo
autoriza el proveedor sobre el dominio. Cada usuario crea su propia
aplicación en la consola de desarrollador del proveedor.

### Configuración (admin)

Estos tres dominios están **pre-creados en la instalación** (cuenta
secundaria + proveedor OAuth ya configurados). Si los ha eliminado, recréelos
así:

1. **Cree el dominio** (*Dominios > Añadir* o botón *Detectar*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` para Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` para Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` para Yahoo.
2. Marque **Cuenta secundaria únicamente** (login primario rechazado) y elija
   el proveedor en **Auth OAuth2**.

### Registro de la aplicación (usuario)

En la primera adición de una cuenta del dominio, el webmail pide las
credenciales de la app y las registra para el usuario - gestionables luego en
*Ajustes > Aplicaciones OAuth2* (una app por proveedor, reutilizada para todas
las cuentas de ese proveedor). URI de redirección a declarar en la consola:
`<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > cliente OAuth
  Web, scope `https://mail.google.com/`): scope *restringido* - una app en
  modo "Testing" está limitada a ~100 usuarios de prueba y sus refresh tokens
  expiran tras ~7 días; la producción exige la verificación de Google.
- **Microsoft** (Entra > App registrations, `signInAudience` incluyendo las
  cuentas personales): permisos delegados "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access` implícito.
  Banner "editor no verificado" posible en el consentimiento (no bloqueante).
- **Yahoo** (Developer Console > app OpenID Connect + Mail API, scopes
  `openid`, `mail-r`, `mail-w`): el acceso Mail API exige una **aprobación
  previa de Yahoo** (formulario de solicitud + revisión manual) y la
  propagación de la redirect URI puede tardar ~24 h.

### Funcionamiento

- El usuario añade la cuenta desde el menú de cuentas o *Ajustes > Cuentas*:
  entrada de la dirección > (primera vez: entrada de las credenciales de app)
  > popup de consentimiento > la cuenta aparece con un badge de proveedor.
  *Ajustes > Cuentas* permite también el cambio, la actualización de las
  credenciales, la promoción a cuenta principal y la eliminación.
- Una cuenta secundaria puede **promoverse a principal** (estrella en
  *Ajustes > Cuentas*) - el flag `secondary_only` del dominio solo bloquea el
  *login* primario, no esta promoción.
- El *refresh token* se almacena cifrado (`accounts.imap_pass`); cada conexión
  IMAP/SMTP intercambia un access token efímero. Microsoft y Yahoo
  **reemiten un refresh token en cada uso**: se reemplaza automáticamente en
  la base.
- El secret de app se cifra (`oauth_apps.secret`, mismo mecanismo que las
  contraseñas) y nunca se devuelve a la interfaz - "vacío = sin cambio" en la
  edición.
- **Modificar o eliminar una app** rompe las cuentas asociadas: el refresh
  token queda ligado al client_id de origen - elimine y vuelva a añadir la
  cuenta.
- No hay ManageSieve en estos proveedores: los filtros Sieve no están
  disponibles en las cuentas OAuth.

Combinar `secondary_only` sobre el dominio `*` y dominios internos normales
reserva el login primario a los dominios listados dejando las secundarias
abiertas en todas partes - útil sin `restrict_domains`.
