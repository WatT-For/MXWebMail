# MXWebMail Documentation

**Languages** : [fr](../fr/README.md) · [de](../de/README.md) · **en** · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Translated from French - the French version is authoritative.*

Documentation for **administrators** who install, deploy and operate
MXWebMail, and for **developers** extending the project (plugins, themes, core).

## Suggested reading order

- **Install**: [installation.md](installation.md)
- **Configure**: [configuration.md](configuration.md)
- **Day-to-day administration**: [administration.md](administration.md)
- **Hook up mail servers**: [domaines.md](domaines.md)
- **Troubleshoot**: [depannage.md](depannage.md)

## Contents

| File | Content |
|---|---|
| [installation.md](installation.md) | Requirements, `/setup/` wizard, manual installation, Apache/Nginx deployment, post-installation steps. |
| [configuration.md](configuration.md) | Full reference of `app/config/config.php` keys (default values and purpose). |
| [administration.md](administration.md) | Admin panel guide: sections, users, logs, updates. |
| [domaines.md](domaines.md) | Per-domain IMAP/SMTP/Sieve server configuration, `*` domain, autodetection. |
| [plugins.md](plugins.md) | The 30 bundled plugins (role, configuration) + building a plugin (hooks, API, schema). |
| [themes.md](themes.md) | Theme system (`app/templates/`), inheritance, building a theme. |
| [localisation.md](localisation.md) | Available languages, adding a language, plugin/theme dictionaries. |
| [base-de-donnees.md](base-de-donnees.md) | Schema, migrations, SQLite ↔ MariaDB, `app.key`, per-user databases. |
| [securite.md](securite.md) | Security model and hardening recommendations. |
| [fail2ban.md](fail2ban.md) | fail2ban integration: authentication log, filter, jail, logrotate. |
| [developpement.md](developpement.md) | Architecture, autoloader, core hooks. |
| [depannage.md](depannage.md) | Common errors, logs, FAQ. |

## Conventions

- Paths are relative to the project root.
- Quoted default values match `app/config/config.example.php` and the code
  (`app/src/MXWebMail/…`), version `0.53.0`.
- This documentation describes the **reality of the code**: if a file diverges, fix it.
- Translations: each webmail language has its own `docs/<lang>/` directory;
  `fr/` is the reference version.
