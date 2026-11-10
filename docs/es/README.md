# Documentación de MXWebMail

**Idiomas** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · **es** · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Traducido del francés - la versión francesa es la que hace fe.*

Documentación destinada a los **administradores** que instalan, despliegan y
explotan MXWebMail, así como a los **desarrolladores** que amplían el proyecto
(plugins, temas, núcleo).

## Recorrido aconsejado

- **Instalar**: [installation.md](installation.md)
- **Configurar**: [configuration.md](configuration.md)
- **Administrar a diario**: [administration.md](administration.md)
- **Conectar los servidores de correo**: [domaines.md](domaines.md)
- **Resolver problemas**: [depannage.md](depannage.md)

## Índice

| Archivo | Contenido |
|---|---|
| [installation.md](installation.md) | Requisitos, asistente `/setup/`, instalación manual, despliegue Apache/Nginx, pasos posteriores a la instalación. |
| [configuration.md](configuration.md) | Referencia completa de las claves de `app/config/config.php` (valores por defecto y función). |
| [administration.md](administration.md) | Guía del panel de administración: secciones, usuarios, logs, actualizaciones. |
| [domaines.md](domaines.md) | Configuración de servidores IMAP/SMTP/Sieve por dominio, dominio `*`, autodetección. |
| [plugins.md](plugins.md) | Los 30 plugins incluidos (función, configuración) + creación de un plugin (hooks, API, esquema). |
| [themes.md](themes.md) | Sistema de temas (`app/templates/`), herencia, creación de un tema. |
| [localisation.md](localisation.md) | Idiomas disponibles, añadir un idioma, diccionarios de plugins/temas. |
| [base-de-donnees.md](base-de-donnees.md) | Esquema, migraciones, SQLite ↔ MariaDB, `app.key`, bases por usuario. |
| [securite.md](securite.md) | Modelo de seguridad y recomendaciones de endurecimiento. |
| [fail2ban.md](fail2ban.md) | Integración fail2ban: registro de autenticación, filtro, jail, logrotate. |
| [developpement.md](developpement.md) | Arquitectura, autocargador, hooks del núcleo. |
| [depannage.md](depannage.md) | Errores comunes, registros, FAQ. |

## Convenciones

- Las rutas son relativas a la raíz del proyecto.
- Los valores por defecto citados corresponden a `app/config/config.example.php`
  y al código (`app/src/MXWebMail/…`), versión `0.53.0`.
- Esta documentación describe la **realidad del código**: si un archivo
  diverge, corríjalo.
- Traducciones: cada idioma tiene su directorio `docs/<lang>/`; `fr/` es la
  versión de referencia.
