# Documentazione MXWebMail

**Lingue** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · **it** · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Tradotto dal francese - la versione francese fa fede.*

Documentazione destinata agli **amministratori** che installano, distribuiscono
ed eseguono MXWebMail, e agli **sviluppatori** che estendono il progetto
(plugin, temi, core).

## Percorso consigliato

- **Installare**: [installation.md](installation.md)
- **Configurare**: [configuration.md](configuration.md)
- **Amministrare quotidianamente**: [administration.md](administration.md)
- **Collegare i server mail**: [domaines.md](domaines.md)
- **Risolvere i problemi**: [depannage.md](depannage.md)

## Indice

| File | Contenuto |
|---|---|
| [installation.md](installation.md) | Prerequisiti, procedura guidata `/setup/`, installazione manuale, distribuzione Apache/Nginx, passi post-installazione. |
| [configuration.md](configuration.md) | Riferimento completo delle chiavi di `app/config/config.php` (valori predefiniti e ruolo). |
| [administration.md](administration.md) | Guida al pannello di amministrazione: sezioni, utenti, log, aggiornamenti. |
| [domaines.md](domaines.md) | Configurazione dei server IMAP/SMTP/Sieve per dominio, dominio `*`, autorilevamento. |
| [plugins.md](plugins.md) | I 30 plugin forniti (ruolo, configurazione) + creazione di un plugin (hook, API, schema). |
| [themes.md](themes.md) | Sistema dei temi (`app/templates/`), ereditarietà, creazione di un tema. |
| [localisation.md](localisation.md) | Lingue disponibili, aggiunta di una lingua, dizionari plugin/temi. |
| [base-de-donnees.md](base-de-donnees.md) | Schema, migrazioni, SQLite ↔ MariaDB, `app.key`, database per utente. |
| [securite.md](securite.md) | Modello di sicurezza e raccomandazioni di hardening. |
| [fail2ban.md](fail2ban.md) | Integrazione fail2ban: log di autenticazione, filtro, jail, logrotate. |
| [developpement.md](developpement.md) | Architettura, autoloader, hook del core. |
| [depannage.md](depannage.md) | Errori comuni, log, FAQ. |

## Convenzioni

- I percorsi sono relativi alla radice del progetto.
- I valori predefiniti citati corrispondono a `app/config/config.example.php` e
  al codice (`app/src/MXWebMail/…`), versione `0.53.0`.
- Questa documentazione descrive la **realtà del codice**: se un file diverge,
  correggetelo.
- Traduzioni: ogni lingua ha la propria directory `docs/<lang>/`; `fr/` è la
  versione di riferimento.
