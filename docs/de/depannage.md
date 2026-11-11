# Fehlerbehebung / FAQ

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

## Installation

**Alle URLs leiten auf `/setup/` weiter**
Normal: die Sperre `app/storage/INSTALLED` fehlt. Installation abschließen oder
die Datei nach dem Anlegen von `config.php` manuell erstellen.

**Der Assistent meldet "config/ nicht beschreibbar"**
Nicht blockierende Warnung: den angezeigten Inhalt von Hand in
`app/config/config.php` kopieren, dann die Installation neu starten. Auch
prüfen, dass `app/storage/` beschreibbar ist (blockierend).

**Der MariaDB-Verbindungstest schlägt fehl**
Host/Port/Zugangsdaten prüfen, und dass der Benutzer das Recht hat, die
Datenbank anzulegen (`CREATE DATABASE`). Der Zeichensatz muss `utf8mb4` sein.

## Verbindung / Login

**Kein Benutzer kann sich nach der Installation anmelden**
Mit `restrict_domains = true` (Standard) muss **mindestens eine benannte
Domain** in der Tabelle stehen (der `*`-Wildcard wird für diese Prüfung
ignoriert). Der Installer versucht sie zu erkennen; sonst in *Domains*
anlegen.

**Login ohne Grund abgelehnt (gesperrtes Konto)**
- Zu viele Versuche: `login_lock_seconds` abwarten (Standard 300 s) oder
  `app/storage/cache/login_attempts.json` löschen.
- Die Plugins `black-list`/`white-list` können den Login verweigern (404/403
  oder generischer Fehler je nach `auth_error`).
- Ein `disabled`-Benutzer wird trotz gültiger Sitzung abgewiesen.

**Der Admin-Login funktioniert nicht**
- Lokales Konto (`admin_user`): `admin_password_hash` und das eventuelle TOTP
  prüfen.
- IMAP-Fallback: der Benutzer muss `is_admin = 1` haben.
- `admin_ip_allowlist` kann Ihre IP blockieren.

## Mailserver

**Verbindung zum IMAP/SMTP-Server unmöglich**
Die Schaltfläche **Verbindung testen** in *Domains* verwenden (Ergebnis pro
Protokoll). Häufige Ursachen: inkonsistente Port/Verschlüsselung (993/ssl vs
143/tls), selbstsigniertes Zertifikat (siehe
`ssl.verify_certificate`/`ssl.allow_self_signed`) oder Firewall.

**Selbstsigniertes Zertifikat abgelehnt**
`ssl.verify_certificate = false` oder `ssl.allow_self_signed = true` (nur im
vollen Bewusstsein der Folgen verwenden).

**Die Domain wird nicht automatisch erkannt**
`autodetect.enabled` ist standardmäßig deaktiviert. Aktivieren oder die
Schaltfläche **Erkennen** auf der Seite *Domains* verwenden.

**"Als Spam melden" filtert die folgenden Mails nicht**
Die Schaltfläche verschiebt die Nachricht in den Junk-Ordner (oder setzt das
`$Junk`-IMAP-Keyword, wenn der Server keinen Junk-Ordner hat) - sie lehrt dem
Server nichts. Sieve-Filter greifen nur bei der Zustellung: die Vorlage
*Anti-Spam* des Assistenten (Einstellungen > Filter) sortiert Mails ein, die
der MTA mit `X-Spam-Flag: YES` markiert. Automatisches Lernen (Spam/Ham)
erfordert serverseitige Konfiguration - Dovecot IMAPSieve
(report-spam/ham-Skript, durch Kopieren nach/von Junk ausgelöst, was der MOVE
der App auslöst) oder rspamd/sa-learn.

## Sicherheit / HTTPS

**Die Seite bleibt hinter einem Reverse Proxy auf HTTP**
`trusted_proxies` (Proxy-IP) eintragen, damit `X-Forwarded-Proto` beachtet
wird, dann `force_https` / `hsts` aktivieren.

**Rate-Limit oder Allowlist sehen nicht die echte IP**
Gleiche Ursache: ohne `trusted_proxies` wird `REMOTE_ADDR` unverändert
verwendet.

**Das Favicon wird nicht angezeigt**
Das hochgeladene Favicon muss ≤ 512 KB sein (ico/png/svg/jpg/webp); sonst
bleibt das Standard-Icon.

## Daten / Logs

**Verlust von `app.key`**
Alle gespeicherten Zugangsdaten werden unentschlüsselbar: Benutzer müssen sich
neu anmelden. `app.key` immer sichern.

**Wo sind die Logs?**
`app/storage/logs/app.log` (Anwendung), `mail.log` (IMAP/SMTP/Sieve-Protokolle,
falls `logs.enabled`) und das Auth-Protokoll (`auth_logging`). Im Admin
einsehbar (*Logs*). fail2ban-Integration: [fail2ban.md](fail2ban.md).

**Der Wechsel zu MariaDB hat die Daten gelöscht**
Erwartetes Verhalten: die Änderung von `db_dsn` startet auf einem leeren
Schema (die Kontaktdateien pro Benutzer bleiben erhalten). Es gibt kein
Datenmigrationstool.

## Aktualisierungen

**"Fehler: Auf GitHub nicht gefunden … privates Repo oder fehlende Ressource"**
Das offizielle Repository ist verriegelt (`GitHubClient::REPO` =
`WatT-For/MXWebMail`) und **öffentlich**: kein Token erforderlich. GitHub
liefert 404 - nicht 403 - für eine fehlende Ressource oder unzureichenden
Zugriff; die Meldung nennt den fehlerhaften Endpunkt (`releases/latest`,
`commits/…` oder einen `raw`-Pfad des Plugin-Katalogs). Ein anhaltender 404
bedeutet in der Regel eine fehlende Ressource (keine Release veröffentlicht,
unbekannter `update.plugins_ref`). Der Fall "privates Repo" betrifft nur
einen über `update.repo` + PAT in `config.php` konfigurierten Fork.

**HTTP 404 auf `…/releases/download/…` (Plugin-Katalog oder Update-Download)**
Auf dem öffentlichen offiziellen Repo funktioniert `browser_download_url`.
Der Fall betrifft nur einen privaten Fork über `update.repo`:
`browser_download_url` liefert dort selbst mit gültigem Token 404 - seit
0.36.1 behoben, Assets werden dann über den API-Endpunkt
`releases/assets/<id>` heruntergeladen, wenn ein PAT in `config.php`
konfiguriert ist.

**Die Seite antwortet 503 "update" / Wartungsseite während eines Updates**
Normal: das Flag `app/storage/update/applying` unterbricht die Benutzer-API
während des Dateikopiens (nie ein Mix aus altem/neuem Code). Es wird am Ende
entfernt; bleibt es nach einem Absturz, läuft es nach 15 min selbst ab - oder
`app/storage/update/applying` manuell löschen.

**"Ausstehende Migrationen" nach einem Update**
Die Finalisierung (`/api/admin/update/finalize`) lief nicht: Schaltfläche
*Finalisieren* in *Über*, oder per CLI `php app/bin/post-update.php`. Ohne
Aktion werden Migrationen trotzdem bei der nächsten Anfrage angewendet (lazy
Migration), aber es ist besser, sie zu kontrollieren.

**Nach einem Update zurückrollen**
Schaltfläche *Wiederherstellen* in *Über*: stellt die Dateien aus
`app/storage/update/backup-<ts>` wieder her (die letzten 3 Backups werden
behalten), löscht erstellte Dateien und kopiert die gesicherten
SQLite-Datenbanken zurück. Unter **MariaDB** sind Migrationen nicht
umkehrbar: zuerst die Datenbank aus Ihrem `mysqldump` wiederherstellen, dann
das Rollback neu starten (erzwungene Bestätigung wird angeboten). Für einen
Neustart: das Originalpaket neu installieren und `app/storage/`
wiederherstellen (einschließlich `app.key` und `db/`).

**Banner "Datenbankschema neuer als der Code"**
`meta.schema_ahead`: der installierte Code ist älter als das Schema
(Datei-Rollback ohne Datenbank-Wiederherstellung oder ein älteres Paket
deployed). Die Datenbank wiederherstellen oder die passende Version erneut
anwenden; die Migrationen bleiben ausgesetzt, solange die Lücke besteht.

## Verschiedenes

**"Unverändert weiterleiten" (Bounce): sieht der Empfänger die Mail als Spam?**
Möglich: das Weiterleiten bricht SPF/DMARC am finalen Ende (der Sendeserver
entspricht nicht mehr dem ursprünglichen Absender) - normales
Bounce-Verhalten, die `Resent-*`-Header dokumentieren die Weitergabe. Um die
Authentifizierung zu behalten, lieber klassisch weiterleiten.

**Sind unter Nginx die privaten Ordner erreichbar?**
Nein, wenn `root` auf `public/` zeigt und der Block `location ~ /\.` +
`^/(app|dist)` vorhanden ist. `root` nie auf den Projektstamm zeigen lassen.

**403-Fehler "cross_site" bei einer legitimen Anfrage**
Der `Sec-Fetch-*`-Schutz hat die Anfrage abgelehnt. Wenn Sie eine legitime
externe API einbinden, eine Regel in `secfetch_allow` hinzufügen.

**Einen internen Fehler debuggen (500)**
Vorübergehend `debug = true` aktivieren (nie in Produktion), um die Meldung zu
sehen, dann `app.log` prüfen.
