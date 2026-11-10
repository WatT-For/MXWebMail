# MXWebMail-documentatie

**Talen** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · **nl** · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Vertaald uit het Frans - de Franse versie is leidend.*

Documentatie voor **beheerders** die MXWebMail installeren, uitrollen en
exploiteren, en voor **ontwikkelaars** die het project uitbreiden (plugins,
thema's, kern).

## Aanbevolen leesvolgorde

- **Installeren**: [installation.md](installation.md)
- **Configureren**: [configuration.md](configuration.md)
- **Dagelijks beheren**: [administration.md](administration.md)
- **Mailservers aansluiten**: [domaines.md](domaines.md)
- **Problemen oplossen**: [depannage.md](depannage.md)

## Inhoud

| Bestand | Inhoud |
|---|---|
| [installation.md](installation.md) | Vereisten, `/setup/`-wizard, handmatige installatie, Apache/Nginx-deployment, stappen na installatie. |
| [configuration.md](configuration.md) | Volledige referentie van de sleutels van `app/config/config.php` (standaardwaarden en rol). |
| [administration.md](administration.md) | Gids voor het beheerpaneel: secties, gebruikers, logs, updates. |
| [domaines.md](domaines.md) | IMAP/SMTP/Sieve-serverconfiguratie per domein, `*`-domein, autodetectie. |
| [plugins.md](plugins.md) | De 30 meegeleverde plugins (rol, configuratie) + een plugin bouwen (hooks, API, schema). |
| [themes.md](themes.md) | Themasysteem (`app/templates/`), overerving, een thema bouwen. |
| [localisation.md](localisation.md) | Beschikbare talen, een taal toevoegen, plugin-/thema-woordenboeken. |
| [base-de-donnees.md](base-de-donnees.md) | Schema, migraties, SQLite ↔ MariaDB, `app.key`, databases per gebruiker. |
| [securite.md](securite.md) | Beveiligingsmodel en hardening-aanbevelingen. |
| [fail2ban.md](fail2ban.md) | fail2ban-integratie: authenticatielog, filter, jail, logrotate. |
| [developpement.md](developpement.md) | Architectuur, autoloader, kern-hooks. |
| [depannage.md](depannage.md) | Veelvoorkomende fouten, logs, FAQ. |

## Conventies

- Paden zijn relatief aan de projectroot.
- Genoemde standaardwaarden komen overeen met `app/config/config.example.php`
  en de code (`app/src/MXWebMail/…`), versie `0.53.0`.
- Deze documentatie beschrijft de **realiteit van de code**: wijkt een bestand
  af, corrigeer het dan.
- Vertalingen: elke taal heeft een eigen `docs/<lang>/`-map; `fr/` is de
  referentieversie.
