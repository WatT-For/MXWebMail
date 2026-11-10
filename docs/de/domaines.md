# Domains und Mailserver

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

IMAP/SMTP/Sieve-Server werden **pro Domain** im Admin-Panel (*Domains*)
konfiguriert. Jede Domain beschreibt, wie der Mailserver ihrer Benutzer
erreicht wird.

## 1. Felder einer Domain

| Feld | Wert |
|---|---|
| `name` | Domainname (z. B. `example.com`) oder `*` für den Catch-all. |
| `imap_host` | Host des IMAP-Servers. |
| `imap_port` | IMAP-Port (Standard `993`). |
| `imap_encryption` | `ssl` (Standard), `tls` (STARTTLS) oder `none`. |
| `smtp_host` | Host des SMTP-Servers (Versand). |
| `smtp_port` | SMTP-Port (Standard `465`). |
| `smtp_encryption` | `ssl` (Standard), `tls` oder `none`. |
| `sieve_host` | ManageSieve-Host (optional). |
| `sieve_port` | Sieve-Port (Standard `4190`). |
| `active` | Die Domain aktivieren/deaktivieren. |

## 2. Erweiterte Optionen (`options`, JSON)

Das Feld `options` akzeptiert ein JSON-Objekt mit feinen Einstellungen pro
Domain:

- `whitelist` - Liste der für diese Domain erlaubten Adressen;
- `secondary_only` - die Domain ist nur als **Zweitkonto** nutzbar
  (Kontenhinzufügung von einem bereits angemeldeten Benutzer aus); der
  Primär-Login wird mit "Domain für Zweitkonten reserviert" abgelehnt;
- `oauth` - OAuth2-Provider der Domain (`google`, `microsoft`, `yahoo`):
  Zweitkonten werden über Provider-Consent (Popup) hinzugefügt, nie per
  Passwort (siehe §8);
- `imap.shortLogin` - den Login kürzen;
- `imap.lowerLogin` - den Login in Kleinbuchstaben erzwingen;
- `imap.timeout` - IMAP-Verbindungs-Timeout;
- `imap.disabled_capabilities` - zu deaktivierende IMAP-Capabilities
  (fehlerhafte Server). Beispiel: `ESORT` für einen Server, der es annonciert,
  ohne die RFC-5267-Rückgabeoptionen zu implementieren (`RETURN (PARTIAL
  ...)`) - der Webmail erkennt diesen Fall bereits und schaltet selbst auf
  klassisches Sortieren um; der Schlüssel dient nur, wenn die Erkennung nicht
  greift (Cache deaktiviert);
- `imap.body_text_limit` - Maximalgröße (Bytes) der beim Öffnen einer Nachricht
  abgerufenen Textkörper (Standard `0` = unbegrenzt; eine ~256-K-Decke spart
  den Fetch sehr langer Newsletter);
- `imap.message_list_limit` - darüber hinaus (Standard `10000` Nachrichten im
  Ordner) vermeidet MailSo das serverseitige SORT und cached die UID-Liste im
  Hintergrund;
- `smtp.useAuth` - SMTP-Authentifizierung erzwingen/deaktivieren;
- `smtp.timeout` - SMTP-Verbindungs-Timeout;
- `sieve.encryption` - Verschlüsselung der Sieve-Verbindung.

## 3. `*`-Domain (Catch-all)

Die `*`-Domain liefert die **Standardparameter**, die auf jede nicht deklarierte
Domain angewendet werden - aber nur wenn `restrict_domains = false`: unter
`restrict_domains` wird `*` ignoriert und kann keinen Primär-Login erlauben.
Es ist der einfachste Weg, mehrere Domains hinter demselben Mailserver zu
bedienen.

## 4. Domain-Auflösung beim Login

Beim Login wird die Domain so bestimmt:

1. Gibt der Benutzer einen Login mit `@domain` ein, wird diese Domain
   verwendet.
2. Sonst gilt `default_domain`: `auto` (Standard) = HTTP-Host ohne `www.` und
   Port; ein fester Name; oder `''` (keiner).
3. Bei `restrict_domains = true` (Standard) werden nur **benannte** Domains in
   der Tabelle akzeptiert (`*` und Erkennung werden ignoriert) - **also
   mindestens eine Domain vor jedem Login anlegen**.

## 5. Automatische Erkennung

`autodetect.*` (standardmäßig deaktiviert) entdeckt die Konfiguration einer
unbekannten Domain zum Zeitpunkt des Logins:

- `enabled`: aktiviert die Erkennung;
- `use_ispdb`: fragt die Mozilla-Basis ab (`autoconfig.thunderbird.net`);
- `probe`: sondiert `imap.`/`mail.`/`smtp.<domain>`.

Erkennungsreihenfolge: DNS SRV > Mozilla-Autoconfig > MX > Port-Sondierung.

Sieve wird unabhängig von der Quelle erkannt: `_sieve._tcp` (SRV), sonst
Sondierung von Port **4190** auf `sieve.<domain>` (wenn `probe` aktiv oder via
Schaltfläche *Erkennen*), dann auf dem gefundenen IMAP-Host - Dovecot liefert
ManageSieve fast immer auf demselben Host. Die Verschlüsselung muss nicht
konfiguriert werden: die Verbindung wechselt automatisch zu STARTTLS, wenn der
Server es annonciert.

Die Schaltfläche **Erkennen** der Seite *Domains* startet dieselbe Erkennung
auf Abruf, unabhängig von `autodetect.enabled`, und füllt die Domain-Felder.

> Der `/setup/`-Installer versucht diese Erkennung ebenfalls auf dem Host des
> vhosts (und seinen Eltern), um die erste Domain automatisch anzulegen.

## 6. Test

Die Schaltfläche **Verbindung testen** validiert das Protokoll des aktiven
Tabs der Modale (IMAP, SMTP oder Sieve; andere Tabs testen IMAP). Nach jeder
Änderung verwenden, um Zugangsdaten, Ports und Verschlüsselungen zu prüfen,
bevor Benutzer sich anmelden.

## 7. Häufiger Fall: ein einziger Mailserver

Für einen einzelnen Server (z. B. Postfix + Dovecot auf `mail.example.com`,
der `example.com` bedient):

1. Die Domain `example.com` mit `imap_host = mail.example.com`,
   `smtp_host = mail.example.com` und optional `sieve_host` anlegen.
2. `default_domain = auto` und `restrict_domains = true` belassen.
3. Benutzer melden sich mit `user@example.com` oder nur `user` an (die Domain
   wird vom HTTP-Host abgeleitet).

Multi-Domain-Variante: eine `*`-Domain mit `restrict_domains = false` dient
als Catch-all - jede nicht deklarierte Domain erbt ihre Parameter (siehe §3;
Achtung, der Primär-Login bleibt dann auf allen Domains möglich -
`secondary_only` kann es verweigern, siehe §8).

## 8. Öffentliche Domains als Zweitkonto (Gmail, Outlook, Yahoo)

Gmail, Outlook.com und Yahoo akzeptieren die IMAP-Authentifizierung per
einfachem Passwort nicht mehr - das Hinzufügen dieser Konten läuft über
**OAuth2** (Consent in einem Popup, dann XOAUTH2/OAUTHBEARER für IMAP und
SMTP).

Die OAuth2-App-Zugangsdaten (Client-ID, Secret, Microsoft-Tenant) sind **pro
Benutzer spezifisch**: der Admin registriert keine App - er erlaubt nur den
Provider auf der Domain. Jeder Benutzer erstellt seine eigene Anwendung in der
Entwicklerkonsole des Providers.

### Konfiguration (Admin)

Diese drei Domains werden **bei der Installation vorab angelegt** (Zweitkonto +
OAuth-Provider bereits eingestellt). Falls Sie sie gelöscht haben, legen Sie
sie so neu an:

1. **Domain anlegen** (*Domains > Hinzufügen* oder Schaltfläche *Erkennen*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` für Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` für Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` für Yahoo.
2. **Nur Zweitkonto** ankreuzen (Primär-Login abgelehnt) und den Provider in
   **OAuth2-Auth** wählen.

### App-Registrierung (Benutzer)

Beim ersten Hinzufügen eines Kontos der Domain fragt der Webmail die
App-Zugangsdaten ab und speichert sie für den Benutzer - danach verwaltbar in
*Einstellungen > OAuth2-Apps* (eine App pro Provider, für alle Konten dieses
Providers wiederverwendet). In der Konsole zu deklarierende Redirect-URI:
`<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > Web-OAuth-Client,
  Scope `https://mail.google.com/`): *eingeschränkter* Scope - eine App im
  "Testing"-Modus ist auf ~100 Testbenutzer begrenzt und ihre Refresh-Tokens
  laufen nach ~7 Tagen ab; Produktion verlangt die Google-Verifizierung.
- **Microsoft** (Entra > App registrations, `signInAudience` einschließlich
  persönlicher Konten): delegierte Berechtigungen "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, impliziter `offline_access`-Scope.
  Möglicher "nicht verifizierter Herausgeber"-Banner beim Consent (nicht
  blockierend).
- **Yahoo** (Developer Console > OpenID-Connect- + Mail-API-App, Scopes
  `openid`, `mail-r`, `mail-w`): der Mail-API-Zugang verlangt eine
  **vorherige Genehmigung durch Yahoo** (Anfrageformular + manuelle Prüfung),
  und die Propagierung der Redirect-URI kann ~24 h dauern.

### Funktionsweise

- Der Benutzer fügt das Konto aus dem Kontenmenü oder *Einstellungen > Konten*
  hinzu: Adresse eingeben > (beim ersten Mal: App-Zugangsdaten eingeben) >
  Consent-Popup > das Konto erscheint mit Provider-Badge. *Einstellungen >
  Konten* erlaubt auch das Umschalten, Aktualisieren der Zugangsdaten, das
  Hochstufen zum Hauptkonto und das Löschen.
- Ein Zweitkonto kann **zum Hauptkonto hochgestuft** werden (Stern in
  *Einstellungen > Konten*) - das `secondary_only`-Flag der Domain blockiert
  nur den Primär-*login*, nicht diese Hochstufung.
- Das *Refresh-Token* wird verschlüsselt gespeichert (`accounts.imap_pass`);
  jede IMAP/SMTP-Verbindung tauscht ein ephemeres Access-Token. Microsoft und
  Yahoo **geben bei jeder Nutzung ein neues Refresh-Token aus**: es wird in der
  Datenbank automatisch ersetzt.
- Das App-Secret wird verschlüsselt (`oauth_apps.secret`, gleicher Mechanismus
  wie Passwörter) und nie an die Oberfläche zurückgegeben - "leer =
  unverändert" beim Bearbeiten.
- **Eine App bearbeiten oder löschen** bricht die zugehörigen Konten: das
  Refresh-Token bleibt an die ursprüngliche client_id gebunden - das Konto
  löschen und neu hinzufügen.
- Kein ManageSieve bei diesen Providern: Sieve-Filter sind auf OAuth-Konten
  nicht verfügbar.

`secondary_only` auf der `*`-Domain mit normalen internen Domains zu
kombinieren reserviert den Primär-Login für gelistete Domains, während
Zweitkonten überall offen bleiben - nützlich ohne `restrict_domains`.
