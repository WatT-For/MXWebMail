# Fail2ban

*Traducido del francés - la versión francesa es la que hace fe.*

MXWebMail puede escribir un registro de autenticación dedicado, pensado para
ser leído por [fail2ban](https://github.com/fail2ban/fail2ban) con el fin de
banear las IP que multiplican los fallos de conexión. Esta página describe la
activación del registro y una integración fail2ban completa (filtro, jail,
rotación).

## Activación

Tres claves de `app/config/config.php` (referencia:
[configuration.md](configuration.md#registro-de-autenticación-estilo-fail2ban)),
también editables en el admin, sección *Logs*:

| Clave | Defecto | Función |
|---|---|---|
| `auth_logging` | `false` | Activa el registro. |
| `auth_logging_filename` | `auth.log` | Archivo en `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Formato de línea. |

Con el formato por defecto, una línea se parece a:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` vale `AUTH OK`, `AUTH FAILED (<código http>)` o
  `AUTH FAILED (totp)` (código TOTP incorrecto introducido - la solicitud
  inicial de segundo factor no es un fallo y no registra nada).
- Los intentos sobre el **login admin** están cubiertos: el login lleva el
  prefijo `admin:` (ej. `admin:admintest`). Un login IMAP válido pero no admin
  se registra `AUTH FAILED (403)`.
- Los intentos ya bloqueados por el rate-limit interno se registran
  `AUTH FAILED (429)` - por tanto también cuentan para fail2ban (reincidencia).
- Los saltos de línea se neutralizan en el login y el resultado: una entrada
  forjada no puede inyectar líneas falsas en el archivo.

## Filtro

`/etc/fail2ban/filter.d/mxwebmail.conf` (para el formato por defecto):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Si `auth_logging_format` es personalizado, adapte `failregex` (y `datepattern`
si el `{date:FMT}` cambia - fail2ban exige una marca de tiempo reconocible al
inicio de la línea o identificable vía `datepattern`).

Compruebe que el filtro coincide con el archivo real:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (o `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Luego `fail2ban-client reload` y `fail2ban-client status mxwebmail`.

## Puntos de vigilancia

- **Zona horaria**: fail2ban compara la marca de tiempo de las líneas con la
  hora del sistema. Deje `logs.time_zone` vacío (= zona del servidor) o
  ajústelo a la **misma** zona que el sistema que ejecuta fail2ban - un
  desfase falsea `findtime` y el baneo.
- **Proxy inverso**: sin `trusted_proxies` informado, el registro contiene la
  IP del proxy - fail2ban banearía entonces al proxy y cortaría el acceso a
  **todos** los usuarios. Declare los proxies de confianza y verifique que la
  IP registrada es la del cliente.
- **Rotación**: el archivo crece sin límite. Ejemplo `logrotate`:

  ```
  /var/www/mxwebmail/app/storage/logs/auth.log {
      weekly
      rotate 8
      missingok
      notifempty
      compress
      copytruncate
  }
  ```

- **Complemento, no reemplazo**: fail2ban actúa a nivel de red; el rate-limit
  interno (`login_max_attempts`, `login_lock_seconds` - ver
  [securite.md](securite.md#rate-limit-del-login)) sigue siendo la primera
  línea de defensa de la aplicación. Ambos son independientes y acumulables.
