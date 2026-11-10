# Plugins

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Plugins sind in `app/plugins/<name>/` begrenzte Erweiterungen, geladen wenn
aktiviert (aus dem Admin, Tab *Erweiterungen*). Sie registrieren sich auf
**Hooks** des Kerns.

> **Schreibgeschützte Sitzung**: GET-Endpunkte schließen die PHP-Sitzung früh
> (`session_write_close`), um die Sperre freizugeben. Hooks, die von einem GET
> aufgerufen werden (`app.data`, `contacts.provider`, `message.meta`,
> `message.view`, `imap.before-connect`, `sieve.before-connect`…) **dürfen nie
> nach `$_SESSION` schreiben** - diese Schreibvorgänge würden still verloren
> gehen.

## 0. Verteilung über das GitHub-Repository

Plugins sind **nicht enthalten** im Release-Zip `mxwebmail-<ver>.zip`: sie
werden direkt im Repository an einem **konfigurierbaren Ref** gelesen
(`update.plugins_ref` - `'release'` = Tag der letzten Release standardmäßig,
`'main'` oder jeder git-Ref). Der Katalog (`PluginPackageService`, Tab
*Erweiterungen* - nicht installierte Einträge erscheinen am Ende der Tabelle)
wird aus der git-API aufgebaut:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` listet Dateien unter
  `app/plugins/` mit ihrem **git-blob-sha1** und ihrer Größe;
- das `plugin.json` jedes Plugins wird via `raw.githubusercontent.com` gelesen
  (auf den Commit-sha gepinnt) - Version, Beschreibung, `min_app`/`max_app`;
- **Installieren / aktualisieren**: jede Plugin-Datei wird via `raw`
  heruntergeladen, verifiziert (Größe + Tree-blob-sha1), dann `plugin.json`
  validiert (übereinstimmender Name, semver-Version, gesundes `main`,
  `APP_VERSION`-Kompatibilität), bevor der Ordner ersetzt wird - ein
  installiertes Plugin ist standardmäßig **deaktiviert**; der alte Ordner geht
  nach `storage/update/plugins/backup-<name>-<ts>` (ein einziges behalten,
  automatisches Rollback);
- **Deinstallieren**: endgültige Löschung des Ordners + Purge der
  `plugins`-Zeile (Konfiguration verloren), ohne Backup.

Eine Plugin-Version veröffentlichen = `version` in seinem `plugin.json` bumpen
und auf den von `update.plugins_ref` verfolgten Branch pushen - keine Release
und kein Asset nötig.

Das Repository ist in `GitHubClient::REPO` verriegelt (gleiche Konstante wie
der Kern-Updater) - keine beliebige URL wird akzeptiert. `update.channel` und
der Release-Cache (`cache.update_ttl`) werden mit der Kern-Update-Prüfung
geteilt; das ref>commit wird auf diese TTL gecacht, Tree und Manifeste werden
über ihren sha adressiert (unveränderlich). Der statische `check` verlangt eine
semver-`version` in `plugin.json`.

Für Offline-Dev/Tests dient `update.plugins_local` (absoluter Pfad eines
Plugin-Ordners in `config.php`) als Quelle: Katalog und Installation per
Plattenkopie, ohne Fernprüfung.

Ein aktiviertes Plugin kann auch **auf die Admin-Oberfläche beschränkt** werden
(Spalte *Benutzerzugriff* des Tabs *Erweiterungen*, `user_allowed`-Flag in der
`plugins`-Tabelle, **standardmäßig abgehakt**): es wird dann nur auf der Seite
`/{admin_path}`, den Routen `/api/admin/*` und Admin-Sitzungen geladen.
Anderswo ist es völlig inert (kein Hook, `/api/plugin/<name>/*` > 404) - auch
für `is_admin`-Benutzer, die nicht im Panel angemeldet sind. Die Admin-Login-Hooks
(`admin.post-login`, `admin.totp-required`, `admin.mounts`) funktionieren
weiter. Ein Plugin, dessen im Manifest deklarierte Hooks alle `admin.*`-Hooks
sind, hat keine Benutzer-Oberfläche: die Spalte zeigt dann keinen Schalter
(`-`).

## 1. Plugins des offiziellen Katalogs

| Plugin | Rolle | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Fügt `X-Originating-IP` (Absender-IP) zu Versänden hinzu. | `message.before-send` |
| `anti-spoofing` | Spoofing-Erkennung im Reader: inkonsistente Return-Path/Reply-To/Sender, Display-Name imitiert eine Marke (Homoglyphen und Confusables erkannt), Köder-Domain (gefälschte Subdomain, Typosquat, Trans-TLD), SPF/DKIM/DMARC in Ausrichtung mit der Absender-Domain bewertet (Verdicts aus `Authentication-Results`, `Received-SPF` oder SpamAssassin/rspamd-Headern gelesen) - Subdomains geschützter Domains sind legitim. | `imap.message-headers`, `message.view` |
| `avatars` | Absender-Avatar: Icons bekannter Dienste, Gravatar, Domain-Favicon, Initialen. | `app.data` |
| `black-list` | Login-Blacklist (Wildcards + Ausnahmen) - auf der **aufgelösten** E-Mail geprüft (post-mapping). | `auth.login.mapped` |
| `white-list` | Login-Whitelist (Wildcards + Ausnahmen) - auf der **aufgelösten** E-Mail geprüft (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Kontaktsynchronisation aus einem CardDAV-Buch (Nextcloud, Radicale, iCloud…) - **HTTPS + öffentlicher Host erforderlich**. | `app.data` |
| `change-password` | Passwortänderung (Treiber poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Farbetiketten auf Nachrichten (manuell + automatische Regeln). | `app.data`, `message.meta` |
| `ics-viewer` | Vorschau von `.ics`-Einladungen/-Anhängen im Reader **+ iTIP-Antwort** (Zusagen/Vielleicht/Ablehnen auf `METHOD:REQUEST`, per SMTP an den Organisator gesendet). | `app.data` |
| `imap-contacts-suggestions` | Kontaktvorschläge aus Absendern eines IMAP-Ordners. | `contacts.provider` |
| `ldap-contacts` | Kontaktvorschläge aus einem LDAP-Verzeichnis (Autovervollständigung). | `contacts.provider` |
| `ldap-login` | LDAP-Auflösung: uid/alias > reale IMAP-Adresse vor dem Login. | `auth.login.mapping` |
| `mailbox-detect` | Erkennung der Systemordner und Anlage fehlender. | `app.data` |
| `pwa` | Macht den Webmail installierbar (Manifest + minimaler Service Worker, Icons). | `app.head`, `app.routes` |
| `webmail-migration` | Admin-Importseite aus einem **RainLoop- oder SnappyMail**-`data`-Ordner (Serverpfad oder ZIP): Domains, `AddressBook.sqlite`/PDO-Bücher, Identitäten, Einstellungen, Systemordner, PGP-Schlüssel - Dry-Run-Scan dann idempotenter Import, unbekannte Konten ignoriert. | `admin.mounts` |
| `two-factor-auth` | Zwei-Faktor-TOTP (Google Authenticator, Aegis…) - Benutzerkonten **und** lokales Admin-Konto (QR-Provisioning in einem eigenen Sub-Tab von Admin > Sicherheit; beim Admin-Login verlangt, solange das Plugin aktiviert ist). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Admin-Ankündigungsbanner (App-Seite, optional Login-Seite - Wartung). Nur Klartext. | `app.head` |
| `attachments-clamav` | ClamAV-Scan (`clamd`, `zINSTREAM`) hochgeladener Anhänge und Importe - Ablehnung bei Erkennung, konfigurierbare `on_error`-Politik. | `attachment.upload` |
| `filelink` | Für SMTP zu große Anhänge als **ablaufbarer Download-Link** gesendet (`/filelink/<token>` - erzwungener Anhang, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Spam/Ham-Lernen bei jeder (Ent-)Markierung: externer Befehl (`rspamc`, `sa-learn`) oder IMAP-Kopie in einen dovecot-antispam-Ordner. | `message.spam` |
| `wkd-keys` | Automatische Entdeckung der PGP-Schlüssel der Empfänger: **WKD** (advanced + direct), Ernte der **Autocrypt**-Header, optionaler `keys.openpgp.org`-Fallback - Import beim Verfassen angeboten. | `app.data`, `message.view` |
| `seasonal-decorations` | Diskrete monochrome Szenerie in Bannern nach Datum (Neujahr, Ostern, 1. April, Halloween, Weihnachten): SVG-Objektbibliothek via `scenes.json` ({svg, pos, size}) zu `mask-image`-Ebenen rekombiniert, automatisch kontrastierte Farbe - einstellbare Fenster, optional außerhalb der Sitzung, ohne Animation. | `app.head` |
| `link-confirmation` | Bestätigungsmodale vor dem Öffnen eines Links aus einem Mail-Body (Anti-Phishing): reales Ziel angezeigt, Kennzeichnung verdächtiger Links (Text ≠ URL, literale IP, Punycode, Zugangsdaten in der URL), vertrauenswürdige Domains und "für diese Domain nicht mehr fragen". | `app.head` |
| `always-cc-bcc` | Systematische Cc- oder Bcc-Empfänger beim Versand (Compliance-Archiv), pro Konto oder alle - das Bcc bleibt unsichtbar. | `message.before-send` |
| `report-abuse` | "Melden"-Button (Aktionsleiste): leitet angehakte Mails als angehängte `.eml` an eine verwaltete Abuse-Adresse weiter, optionale Spam-Markierung. | `app.data` |
| `vcf-viewer` | Vorschau von `.vcf`-Anhängen im Reader + Ein-Klick-Import ins Adressbuch (Button ins Frontend integriert, wenn `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Versand-Quota pro Konto (Nachrichten/Stunde, Nachrichten/Tag, Empfänger/Tag) - 429-Ablehnung darüber. Nur Webmail. | `message.before-send` |
| `login-notify` | Alarm-Mail an das Konto bei Anmeldung von unbekannter IP (Gmail-Modell) - ungelesen in der Inbox abgelegt, Opt-out pro Benutzer. | `auth.post-login`, `app.data` |
| `sso-header` | SSO via Authentifizierungs-Proxy (vertrauenswürdiger Benutzer-Header) - verlangt deklarierte Proxy-IPs und ein IMAP-Master-Passwort. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Admin-Seite, die DMARC-(rua-)-Berichte einer dedizierten IMAP-Box aggregiert - XML/gzip-Parsing, Aggregate nach Domain und Quell-IP. | `admin.mounts` |

## 2. Konfiguration eines Plugins

Im Admin (*Erweiterungen* > ⚙-Button) wird die Konfiguration aus der Datei
`config.schema.json` des Plugins generiert. Unterstützte Typen: `string`,
`text`, `integer`, `number`, `password`, `bool`, `enum`, `color`, `list` -
`color` rendert einen nativen Picker + ein `#RRGGBB`-Feld (leerer Wert
akzeptiert: das Feld fällt auf den Plugin-Default zurück). Die Werte werden in
der `plugins`-Tabelle gespeichert (Spalte `config`, JSON).

Bemerkenswerte Konfigurationsdetails:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (Standard `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-Anonymität), dann die
  treibereigenen Parameter (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` mit
  Platzhaltern `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`,
  `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (`%u`-Platzhalter), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (`%q`-Platzhalter), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (eine geschützte Domain deckt auch
  ihre Subdomains, z. B. `accountprotection.microsoft.com`), `brands` (im
  Anzeigenamen nach Normalisierung von Akzenten und Confusables erkannt -
  "AppIe TV" wird als "Apple TV" erkannt), und die Schalter
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  SPF/DKIM/DMARC-Ergebnisse werden nur berücksichtigt, wenn sie mit der
  `From`-Domain ausgerichtet sind: ein `dkim=fail` auf der Signatur des
  Transporteurs oder ein `spf=fail` auf einen relayten (SRS-)mailfrom löst
  keinen Alarm aus.
- **`two-factor-auth`** - `issuer` (Standard `app_name`), `label` (`email` =
  vollständige Adresse, `local` = Teil vor `@`; `local` verhindert, dass
  TOTP-Apps ein Marken-Icon aus der Domain ableiten, z. B. Yahoo) und
  `icon_url` (nicht standardisierter `image=`-Parameter, von
  Aegis/andOTP/FreeOTP angezeigt - von Authy ignoriert; leer = öffentliches
  Favicon der Instanz). Das Plugin provisioniert auch die 2FA des lokalen
  Admin-Kontos: `admin_totp_secret`-Secret in `config.php` (über die Admin-API
  `/api/admin/plugin/two-factor-auth/*`), pending in der Sitzung, beim
  Admin-Login über den `admin.post-login`-Hook erzwungen. Das Deaktivieren des
  Plugins deaktiviert die Admin-Durchsetzung.
- **`black-list` / `white-list`** - `black_list`/`white_list` (Wildcards;
  Werte durch Leerzeichen, Kommas oder Semikolons getrennt), `exceptions`,
  `auth_error` (403 "Konto nicht autorisiert" > generisches 401). Achtung auf
  die vom Legacy-Plugin geerbte Semantik: für black-list sind `exceptions` =
  E-Mails der Blacklist, die **erlaubt bleiben**; für white-list wird bei
  leerem `exceptions` jede E-Mail außerhalb der Whitelist abgelehnt, aber wenn
  es gesetzt ist, **werden nur die E-Mails der Ausnahmen abgelehnt** und alles
  andere passiert - keine "zusätzlich erlaubten" Adressen hineinsetzen
  (umgekehrter erwarteter Effekt).

- **`link-confirmation`** - `mode` (`all` = jeder externe Link, `suspicious` =
  nur verdächtige Links), `whitelist` (vertrauenswürdige Domains, eine pro
  Zeile oder durch Leerzeichen/Komma/Semikolon getrennt - Subdomains
  inbegriffen), `warn_mailto` (auch `mailto:`/`tel:` bestätigen, standardmäßig
  deaktiviert) und `remember` (Checkbox "für diese Domain nicht mehr fragen" -
  im Browser via localStorage gespeichert, nicht serverseitig).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (Ziele, leer =
  inaktiv), `senders` (Absender-Wildcards, `*` = alle).
- **`report-abuse`** - `abuse_address` (erforderlich, leer = inaktiv),
  `subject_prefix`, `mark_spam` (markiert auch als Spam via `message.spam` -
  speist das Lernen).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day` (0 =
  unbegrenzt), `exempt` (Wildcards befreiter Konten). Begrenzt nur den Webmail:
  ein direkter IMAP/SMTP-Client umgeht die Quote.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (Opt-out-Einstellungs-Tab pro Benutzer).
- **`sso-header`** - `header` (`X-Forwarded-User` oder `REMOTE_USER`),
  `trusted_ips` (**erforderlich, leer = inertes Plugin**), `master_pass`
  (erforderlich; *same*-Modus = `master=yes`-Passdb), `master_user` (optional:
  `user*master`-Login), `allowed_domains`, `hide_form`. Die Instanz darf nicht
  unter Umgehung des Proxys erreichbar sein (der Header wäre fälschbar).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (IMAP-Box der
  Berichte), `folder`, `batch_size`, `delete_processed`, `processed_folder`.
  Plugin-eigene IMAP-Verbindung (die Domain-Optionen gelten nicht).

Manche Plugins haben kein Admin-Schema: ihre Konfiguration ist **pro Benutzer**
(z. B. `carddav-contacts`, `color-tags`, `two-factor-auth` für den
Kontozustand).

## 3. Ein Plugin erstellen

### Minimale Struktur

```
app/plugins/mein-plugin/
├── plugin.json          # Manifest
├── MeinPlugin.php       # Hauptklasse
├── config.schema.json   # (optional) Admin-Konfigurationsschema
├── langs/<lang>.php     # Wörterbücher (Schlüssel mit Präfix plugin.<name>.)
└── js/ oder assets/     # (optional) Assets, ausgeliefert via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "mein-plugin",
	"label": "plugin.mein-plugin.label",
	"class": "MeinPlugin",
	"main": "MeinPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mein-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (optional) ist der Anzeigename des Plugins in Admin > Erweiterungen
(i18n-Schlüssel `plugin.<name>.label` empfohlen, literaler Text akzeptiert; der
technische `name` bleibt daneben angezeigt und dient als Identifikator).

`min_app`/`max_app` (optional, semver `x.y.z`) begrenzen die Kompatibilität mit
`APP_VERSION` - Installation oder Update wird außerhalb der Spanne verweigert,
und der Katalog markiert den Eintrag als *inkompatibel*.

### Hauptklasse

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MeinPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Verfügbare Hooks

- **Kern**: `app.routes`, `app.head` (in den `<head>` von `app.php`,
  `login.php` und `maintenance.php` injiziertes HTML - die `admin*.php`-Views
  emittieren es nicht), `app.data`, `auth.login.mapping`,
  `auth.login.mapped`, `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > `{js, plugin, section, tab?, label?}`-
  Deskriptor, von `pluginMount` im Admin-Panel gerendert - ohne `tab`:
  Inline-Block in der `section`-Zone, z. B. `security-account`; mit `tab` +
  `label`: eigener Sub-Tab der `section`-Seite, z. B. `section: 'security',
  tab: '2fa'`),
  `admin.post-login` (`run`, beim Login des lokalen Admin-Kontos emittiert -
  ein `TotpRequiredException` verlangt einen zweiten Faktor),
  `admin.totp-required` (`first` > Boolean: zeigt das TOTP-Feld auf der
  Admin-Login-Seite).

Jedes Plugin deklariert in `hooks` die Hooks, die es abhört. Ein Manifest, das
nur `admin.*`-Hooks deklariert, markiert das Plugin als reinen Admin - die
Spalte *Benutzerzugriff* des Erweiterungen-Tabs zeigt keinen Schalter für es.
Der Kern ruft `HookRegistry::run('hook', …)`; das Plugin implementiert eine
Methode gleichen Namens (oder ein `on<Hook>` nach Kernkonvention).

### Generische API

Jedes Plugin ist via `/api/plugin/<name>/<action>` (GET/POST) aufrufbar >
Methode `api<Action>(Request $r, ?array $user)`. Das CSRF-Token ist auf allen
Methoden erforderlich (der `api.js`-Client sendet es systematisch) - direkte
Aufrufe ohne `X-CSRF-Token`-Header werden mit 403 abgelehnt.

Adminseitig `/api/admin/plugin/<name>/<action>` (GET/POST) > Methode
`apiAdmin<Action>(Request $r)`: Admin-Sitzung (`$_SESSION['admin']`) + CSRF
erforderlich; es gibt kein `$user` (das lokale Admin-Konto ist keine
`users`-Zeile). Achtung: `requireAdmin` schließt die Sitzung zum Schreiben - um
dort einen Zustand zu speichern (z. B. ausstehendes Secret), sie via
`$this->app->startSession()` neu öffnen, dann `session_write_close()`.

### Benutzer-Tab

Um einen Tab in `#/@settings/…` hinzuzufügen, JS via
`/assets/plugin/<name>/<file>` ausliefern und den Tab deklarieren (siehe die
Plugins `change-password`, `carddav-contacts`, `color-tags`,
`two-factor-auth` als Beispiele).

### Übersetzungen

Schlüssel mit Präfix `plugin.<name>.` in `langs/<lang>.php`;
`description`/`label`/`help` der Manifeste und Schemata akzeptieren diese
Schlüssel (sonst Pass-through). PHP-Seite: `$this->t('key')`, JS-Seite:
`ctx.t('key')` in `mount(element, ctx)`.
