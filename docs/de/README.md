# MXWebMail-Dokumentation

**Sprachen** : [fr](../fr/README.md) · **de** · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Dokumentation für **Administratoren**, die MXWebMail installieren, bereitstellen
und betreiben, sowie für **Entwickler**, die das Projekt erweitern (Plugins,
Themes, Kern).

## Empfohlene Lesereihenfolge

- **Installieren**: [installation.md](installation.md)
- **Konfigurieren**: [configuration.md](configuration.md)
- **Tägliche Verwaltung**: [administration.md](administration.md)
- **Mailserver anbinden**: [domaines.md](domaines.md)
- **Fehlerbehebung**: [depannage.md](depannage.md)

## Inhalt

| Datei | Inhalt |
|---|---|
| [installation.md](installation.md) | Voraussetzungen, `/setup/`-Assistent, manuelle Installation, Apache/Nginx-Bereitstellung, Schritte nach der Installation. |
| [configuration.md](configuration.md) | Vollständige Referenz der Schlüssel von `app/config/config.php` (Standardwerte und Zweck). |
| [administration.md](administration.md) | Anleitung zum Admin-Panel: Bereiche, Benutzer, Logs, Updates. |
| [domaines.md](domaines.md) | IMAP/SMTP/Sieve-Serverkonfiguration pro Domain, `*`-Domain, Autodetektion. |
| [plugins.md](plugins.md) | Die 30 mitgelieferten Plugins (Rolle, Konfiguration) + ein Plugin erstellen (Hooks, API, Schema). |
| [themes.md](themes.md) | Theme-System (`app/templates/`), Vererbung, ein Theme erstellen. |
| [localisation.md](localisation.md) | Verfügbare Sprachen, eine Sprache hinzufügen, Plugin-/Theme-Wörterbücher. |
| [base-de-donnees.md](base-de-donnees.md) | Schema, Migrationen, SQLite ↔ MariaDB, `app.key`, Datenbanken pro Benutzer. |
| [securite.md](securite.md) | Sicherheitsmodell und Härtungsempfehlungen. |
| [fail2ban.md](fail2ban.md) | fail2ban-Integration: Authentifizierungsprotokoll, Filter, Jail, logrotate. |
| [developpement.md](developpement.md) | Architektur, Autoloader, Kern-Hooks. |
| [depannage.md](depannage.md) | Häufige Fehler, Logs, FAQ. |

## Konventionen

- Pfade sind relativ zum Projektstamm.
- Angegebene Standardwerte entsprechen `app/config/config.example.php` und dem
  Code (`app/src/MXWebMail/…`), Version `0.53.0`.
- Diese Dokumentation beschreibt die **Realität des Codes**: weicht eine Datei
  ab, wird sie korrigiert.
- Übersetzungen: jede Webmail-Sprache hat ein eigenes `docs/<lang>/`-Verzeichnis;
  `fr/` ist die Referenzversion.
