# Verwaltung

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Das Admin-Panel ist unter `/{admin_path}` erreichbar (Standard `/admin`). Es ist
eine **globale** Verwaltung (Multi-Domain); es gibt keine feinen Rollen.

## 1. Admin-Authentifizierung

Zwei Modi, je nach Konfiguration:

1. **Lokales Konto** (`admin_user` + `admin_password_hash` in `config.php`):
   Login/Passwort, optional TOTP (`admin_totp_secret`). Das TOTP wird vom
   `two-factor-auth`-Plugin bereitgestellt und erzwungen (Sub-Tab
   "Zwei-Faktor-Authentifizierung" von *Sicherheit*, mit QR-Code) - Plugin
   deaktiviert = nicht beim Login verlangt.
2. **IMAP-Fallback** (wenn kein lokales Konto): ein via IMAP authentifizierter
   Benutzer mit `is_admin = 1` öffnet den Admin. Dieses Recht wird bei jeder
   Anfrage neu validiert (ein herabgestuftes/gesperrtes/gelöschtes Konto wird
   sofort abgewiesen).

Das Login-Rate-Limit gilt auch für den Admin, ebenso `admin_ip_allowlist`,
wenn gesetzt. Ein IMAP-Admin kann sich nicht selbst herabstufen, sperren oder
löschen.

## 2. Bereiche des Panels

Das Panel besteht aus Tabs (Hash `#/<page>[/<sub-tab>]`).

### Allgemein

`app_name`, `admin_path`, `language` (Liste der installierten Sprachen +
`auto`), `session_lifetime`, `remember_days` (Laufzeit der
"Angemeldet bleiben"-Tokens), `maintenance`, `update.channel`. Verwaltet auch
das **Favicon** (Upload ≤ 512 KB: ico/png/svg/jpg/webp, Inhalt validiert). Der
**Wartungs**-Modus zeigt eine eigene Seite (503) auf `/`: angemeldete Benutzer
werden bei ihrer nächsten API-Anfrage dorthin umgeleitet (Sitzung erhalten) und
sie lädt am Ende selbst neu; der Admin (`/{admin_path}` + `/api/admin/*`)
bleibt voll erreichbar.

### Datenbank

SQLite- oder MariaDB/MySQL-Treiber, Host/Port/Name/Benutzer/Passwort. Ein Test
(`/api/admin/test-db`) ist vor dem Schreiben obligatorisch: der Server
validiert die Verbindung und legt die Datenbank bei Bedarf an. Siehe
[base-de-donnees.md](base-de-donnees.md).

Die Karte **Sicherung** lädt ein Zip-Archiv herunter (`config.php`, `app.key`,
SQLite-Datenbanken, `storage/branding`) - **sensibel** (Zugangsdaten +
Verschlüsselungsschlüssel), an einem sicheren Ort aufzubewahren. Eine
MariaDB-Datenbank ist nicht abgedeckt: `mysqldump` serverseitig.

### Funktionen

Sub-Tabs *Funktionen* und *Standards*:

- Bild-Proxy + Allowlist, Anhanggrößen;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- alle `user_defaults.*`-Einstellungen (Benutzerstandards).

### Performance

Sub-Tabs *HTTP* und *Server-Cache*:

- `http.*`: gzip-Kompression (Aktivierung, Niveau, Mindestgröße), `http.etag`,
  TTL der Theme/Plugin-Assets, des Favicons und des Bild-Proxys;
- `cache.*`: Anwendungs-Datei-Cache (Aktivierung, TTL der Domain-Erkennung und
  Update-Prüfung, Purge-Intervall).

Schlüsseldetails in [configuration.md](configuration.md) (Abschnitte *HTTP* und
*Anwendungscache*).

### Sicherheit

Sub-Tabs *Konto*, *Zwei-Faktor-Authentifizierung* (wenn das
`two-factor-auth`-Plugin aktiviert ist), *Zugriff*, *Header*, *TLS*:

- lokales Admin-Konto (`admin_user`) und **Admin-Passwortänderung** - beide
  Felder ausfüllen, dann *Speichern*: das Passwort wird nur geändert, wenn sie
  gefüllt und identisch sind;
- Admin-**TOTP** (Sub-Tab des `two-factor-auth`-Plugins): Secret-Erzeugung, zu
  scannender QR-Code, Bestätigungscode vor Aktivierung obligatorisch (das
  Secret wird nach dem Speichern nie angezeigt);
- `admin_ip_allowlist` (Button "meine IP hinzufügen"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- vollständiger `ssl.*`-Abschnitt (ausgehende Zertifikate).

### Logs

Sub-Tabs *Viewer* und *Einstellungen*:

- Viewer der Protokolle `app.log`, `mail.log` und des Auth-Protokolls (letzte
  200 Zeilen), mit "Leeren"-Button zum Abschneiden der gewählten Datei;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domains

Mailserver-Konfiguration pro Domain - siehe [domaines.md](domaines.md). Der Tab
*Einstellungen* gruppiert die Auflösung (`default_domain`,
`restrict_domains`, `autodetect.*`). Für "nur Zweitkonto + OAuth-Provider"-
Domains (Gmail, Outlook, Yahoo) erlaubt der Admin nur den Provider (`oauth`-
Option der Domain-Modale): die OAuth2-App-Zugangsdaten werden von jedem
Benutzer registriert (*Einstellungen > OAuth2-Apps*).

### Benutzer

Liste + Benutzersuche, mit Aktualisierungs-Button (Icon) zum Neuladen der
Liste. Pro Benutzer (Modale, Tabs *Profil*, *Einstellungen*,
*Konten/Identitäten*, *Sitzungen*):

- `display_name`, `is_admin`, `disabled`;
- effektive Benutzereinstellungen (Bearbeitung + Reset - der Reset stellt die
  `user_defaults` wieder her, **erhält** aber die internen `plugin.*`- und
  `user_folders`-Schlüssel, z. B. 2FA-Secret, Ordnerherkunft);
- IMAP-Zweitkonten (Löschung, außer dem Standardkonto);
- Identitäten (Anlegen/Bearbeiten/Löschen, Standard-Identität);
- Tab **Sitzungen**: "Angemeldet bleiben"-Tokens gelistet und widerrufbar;
  **Abmeldung erzwingen** widerruft Tokens und killt aktive Sitzungen
  (session_epoch erhöht, Abweisung bei der nächsten Anfrage).

Das Löschen eines Benutzers kaskadiert und löscht auch seine Kontaktdatenbank
(`storage/db/users/<id>.sqlite`).

### Erweiterungen (Plugins)

Jedes Plugin wird unter seinem **Anzeigenamen** gelistet (`label`-Feld des
Manifests, übersetzt - der technische `name` bleibt daneben in `code`
angezeigt).

Aktivierung/Deaktivierung jedes Plugins + **Konfigurations**-Modale aus
`config.schema.json` generiert (Typen `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). Die Spalte **Benutzerzugriff**
beschränkt ein aktiviertes Plugin auf die Admin-Oberfläche (Seite
`/{admin_path}`, `/api/admin/*`, Admin-Sitzungen): es wird für Benutzer inert -
siehe [plugins.md](plugins.md). Standardmäßig abgehakt (explizit zu erlauben);
sie fehlt bei Plugins, die nur `admin.*`-Hooks deklarieren (keine
Benutzer-Oberfläche).

**Installation / Update von GitHub**: Plugins werden direkt im verriegelten
Repository (`GitHubClient::REPO`, gleich wie der Kern-Updater) am Ref
`update.plugins_ref` gelesen - `'release'` = Tag der letzten Release
standardmäßig, `'main'` in Dev (ein Push von `plugin.json` veröffentlicht das
Update, keine Release nötig). Jede via `raw.githubusercontent.com`
heruntergeladene Datei wird per git-blob-sha1 verifiziert.
Der Katalog wird beim Öffnen des Tabs geladen (Cache mit der Kern-Prüfung
geteilt) und über den **Aktualisieren**-Button oben rechts neu ladbar; nicht
installierte Katalogeinträge werden am Ende der Plugin-Tabelle gelistet:

- **Aktualisieren**-Button auf installierten Plugins, wenn eine neuere Version
  veröffentlicht ist (der alte Ordner wird nach
  `storage/update/plugins/backup-<name>-<ts>` gesichert - ein einziges
  behalten, automatisches Rollback bei Fehlschlag);
- **Installieren**-Button auf nicht vorhandenen Katalogeinträgen - ein neu
  installiertes Plugin ist standardmäßig **deaktiviert**;
- **Deinstallieren**-Button: **endgültige** Löschung des Ordners und der
  Konfiguration (`plugins`-Zeile gelöscht), ohne Backup;
- `min_app`/`max_app` des entfernten `plugin.json`: ein mit `APP_VERSION`
  inkompatibler Eintrag wird als *inkompatibel* markiert und seine Installation
  verweigert.

### Webmail-Migration (RainLoop / SnappyMail)

Ist das `webmail-migration`-Plugin aktiviert, erscheint eine Seite
**Webmail-Migration** im Seitenmenü (vor *Über*): sie migriert die Daten eines
`data/_data_/_default_/`-Ordners einer **RainLoop- oder SnappyMail**-Installation,
angegeben per **Serverpfad** oder **ZIP-Upload** (Extraktion in `storage/tmp`,
über den dedizierten Button oder nach 2 h gelöscht).

- **Analysieren**: schreibfreies Inventar (`*.ini`/`*.json`-Domains, gefundene
  Konten, Kontakt-/Identitäts-/Schlüssel-/Einstellungs-Volumen pro Benutzer);
- **Import starten**: idempotenter Import - fehlende Domains, Kontakte
  (pro-Konto-Bücher + globales + in `application.ini` deklarierte PDO-DSN,
  E-Mail-Häufigkeiten und -Typen erhalten, gelöschte Kontakte ausgeschlossen),
  Identitäten (Standard = leere `Id`), bekannte Benutzereinstellungen
  (Nachrichten pro Seite, Aktualisierung, Kontakt-Autosave, Systemordner, Theme
  falls vorhanden) und armored PGP-Schlüssel. Ohne Duplikate wiederholbar.

Nur Konten, die einem **bestehenden Benutzer** entsprechen, werden importiert;
die anderen werden im Bericht gelistet. Die Passwörter der Zusatzkonten sind
nicht wiederherstellbar (vom Quell-Webmail verschlüsselt) - manuell neu zu
erstellen. Sieve-Filter leben auf dem Mailserver: nichts zu importieren.

Benutzerseitig bietet der Tab *Einstellungen > Kontakte* auch einen **Import**
`.vcf`/`.csv`/`.sqlite` (`AddressBook.sqlite`-Buch aus RainLoop/SnappyMail - in
einem Mehrbenutzer-Buch werden nur die Kontakte des Kontos übernommen) und
einen **Export** `.vcf`, der jedes andere Buch abdeckt, einschließlich eines
manuellen SnappyMail-Exports.

### Themes

- **Standard**-Theme (schreibt `user_defaults.theme`);
- **Freigabe** pro Theme (`allowed`): ein Benutzer kann nur ein freigegebenes
  Theme wählen (das Standard bleibt immer wählbar);
- **Konfigurations**-Modale des Themes (gleiche Typen + `color`);
- Anzeige der Vererbungskette (`extends`).

Siehe [themes.md](themes.md).

### Über

Version (`APP_VERSION`), PHP/SAPI/Limits, Datenbanktreiber und -größe, Pfade,
geladene Erweiterungen. Ein Link zur offiziellen Website wird in der
Navbar angezeigt. Die Kopfkarte integriert auch die **Updates** via GitHub
Releases (Prüfung, Download, Anwendung, Wiederherstellung):

- *Prüfen* > *Herunterladen* > *Anwenden* > automatische
  Migrations-Finalisierung; Zustände in `storage/update/state.json` gehalten;
- bei der Extraktion wird `files.sha256` (Pro-Datei-Manifest des Pakets)
  verifiziert, die Paket-Voraussetzungen (`APP_REQ_PHP`/`APP_REQ_FROM` in
  seinem `App.php`: PHP-Version, minimale Quellversion) geprüft, und lokal
  geänderte Dateien werden vor dem Anwenden gemeldet;
- während des Kopierens antwortet die Benutzer-API 503
  (`storage/update/applying`-Flag, Wartungsseite webseitig - läuft nach 15 min
  bei Fehlschlag ab);
- jede überschriebene Datei wird in `storage/update/backup-<ts>` mit
  `manifest.json` gesichert (Version, `schema_version`, Listen); unter SQLite
  wird die Datenbank (`webmail.sqlite` + `db/users/*.sqlite`) nach einem
  WAL-Checkpoint kopiert - unter MariaDB bleibt die externe Sicherung
  (`mysqldump`) in der Verantwortung des Admins (Warnung vor dem Anwenden);
- *Wiederherstellen* (Rollback): stellt die Dateien des letzten Backups wieder
  her, löscht erstellte Dateien und kopiert die SQLite-Datenbanken zurück;
  unter MariaDB Abweisung, wenn das Schema fortgeschritten ist (Dump
  wiederherstellen, dann Erzwingen bestätigen);
- das Banner "Schema neuer als der Code" meldet ein Datei-Rollback ohne
  Datenbank-Wiederherstellung;
- *Zurücksetzen*-Aktion, um ein heruntergeladenes/entpacktes Paket zu
  verwerfen.

> Das offizielle Repository ist in `GitHubClient::REPO` verriegelt und
> **öffentlich**: die Prüfung funktioniert ohne Konfiguration oder Token (sie
> ist nur deaktiviert, solange die Konstante nicht gesetzt ist).
> `update.channel` bleibt hier editierbar; `update.repo` wird manuell in
> `config.php` eingestellt.

## 3. Schreiben von `config.php`

Der Admin schreibt `config.php` **pro Abschnitt**, atomar (temporäre Datei +
`rename`, `chmod 0600`), unter Erhalt nicht gelieferter Schlüssel. Ist
`app/config/` nicht beschreibbar, zeigt die Oberfläche es an
(`saved_readonly`) und Änderungen werden angezeigt, aber nicht persistiert.

## 4. Verbindung testen

In *Domains* validiert **Verbindung testen** das Protokoll des aktiven Tabs der
Modale (IMAP, SMTP oder Sieve - andere Tabs testen IMAP), und **Erkennen**
füllt die Felder ohne zu speichern. Die Erkennung (`DomainDetector`)
funktioniert unabhängig von `autodetect.enabled`. Das manuelle Speichern einer
Domain löscht den Marker "auto-erkannt" (`options.detected`).
