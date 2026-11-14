# Sicherheit

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

## 1. Eingebautes Sicherheitsmodell

### "Angemeldet bleiben" (persistente Tokens)

Die Checkbox "Angemeldet bleiben" stellt ein `mxwm_remember`-Cookie aus
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, Laufzeit
`remember_days` - 30 Tage standardmäßig). Die Tabelle `auth_tokens` speichert
nur den **SHA-256-Hash** des Validators; dieser wird **bei jeder Nutzung
rotiert** (ein bereits vorgelegtes gestohlenes Cookie ist erkennbar und wird
ungültig). Die Wiederherstellung entschlüsselt das IMAP-Passwort erneut
(`CredentialCrypt`) und prüft die Zugangsdaten erneut - ein Fehlschlag löscht
das Token. Jedes Token zeichnet die **IP bei Ausstellung** (`ip`), die
**letzte bekannte IP** (`last_ip`, bei jeder Wiederherstellung) und den
User-Agent auf - sichtbar in Einstellungen > Sitzungen, um eine unbekannte
Sitzung zu erkennen. Tokens werden dort gelistet und sind widerrufbar, und das
aktuelle Token wird bei **manueller** Abmeldung widerrufen (die
Inaktivitäts-Abmeldung `autologout` erhält es: die Sitzung wird dann beim
nächsten Besuch still wiederhergestellt). Jede neue Sitzung (normale
Anmeldung oder Wiederherstellung) startet auf dem zuletzt genutzten Konto -
Rückfall auf das Standardkonto.

### CSRF

Jedes POST verlangt ein Sitzungs-CSRF-Token (`X-CSRF-Token`, Formularfeld oder
JSON-Schlüssel); Plugin-APIs (`/api/plugin/…`) verlangen es unabhängig von der
HTTP-Methode. Eine zweite Schicht, auf den **`Sec-Fetch-*`**-Headern basierend,
weist Cross-Site-Anfragen ab - außer GET-Navigation zu einem Dokument, sogar
ohne Benutzeraktivierung, da der Browser `Sec-Fetch-User` nach jeder
Weiterleitung entfernt (Portale, umgeschriebene Links) (`secfetch_allow`
erlaubt, diese Regeln zu erweitern).

### HTTP-Header

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, optionales HSTS und eine
**CSP** mit Nonce pro Request (`script-src` enthält `unsafe-eval`, von Knockout
benötigt). Inline ausgelieferte Anhänge erhalten
`Content-Security-Policy: sandbox`.

### Verschlüsselung der Zugangsdaten

IMAP-Passwörter (und Plugin-Geheimnisse) werden **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) mit dem Masterschlüssel
`app/storage/app.key` verschlüsselt, in der Sitzung und in der Datenbank.
Siehe [base-de-donnees.md](base-de-donnees.md).

### OAuth2-Zweitkonten

Öffentliche Domains (Gmail, Outlook, Yahoo - siehe
[domaines.md](domaines.md)) werden über **authorization code + PKCE**
hinzugefügt: der Anti-CSRF-`state` und der Verifier werden in der Sitzung
gehalten, der `redirect_uri` wird serverseitig rekonstruiert (Host gefiltert
wie `force_https`). Nur das *Refresh-Token* wird persistiert, wie ein Passwort
verschlüsselt; das ephemere *Access-Token* existiert nur im Speicher für die
Dauer der Verbindung (XOAUTH2/OAUTHBEARER). Die Refresh-Token-Rotation
(Microsoft, Yahoo) wird bei jedem Austausch in die Datenbank zurückgeschrieben.
Die `id_token`-E-Mail dient als Kohärenzprüfung; der Besitznachweis bleibt die
OAuth-IMAP-Verbindung selbst. Der Callback (`/api/oauth/callback`) liefert nur
eine Mini-Seite `postMessage`>`window.close()` für das Consent-Popup.

OAuth2-App-Zugangsdaten sind **pro Benutzer** (Tabelle `oauth_apps`, beim
ersten Hinzufügen eines Kontos oder in den Einstellungen eingegeben): das
`secret` wird mit demselben Masterschlüssel verschlüsselt und nie an die
Oberfläche zurückgegeben. Eine gelöschte oder bearbeitete App macht die davon
abhängigen Konten ungültig (das Refresh-Token bleibt an die ursprüngliche
client_id gebunden).

### Rate-Limit beim Login

`login_max_attempts` (5), dann Sperre für `login_lock_seconds` (300 s). Der
Zähler wird in `storage/cache/login_attempts.json` persistiert (Schlüssel
`sha256(IP|login)`, Löschung von Einträgen inaktiv > 1 h, Schreiben unter
`flock`-Lock). Eine aktive Sperre wird durch abgelehnte Versuche weder
verlängert noch neu registriert (429). Gilt auch für den Admin-Login. Ein
fail2ban-artiges Authentifizierungsprotokoll ist verfügbar (`auth_logging*`,
Platzhalter `{request:ip}`, `{imap:login}`, `{result}`; CR/LF neutralisiert):
es deckt Webmail- und Admin-Logins (Login mit `admin:`-Präfix) sowie TOTP-Code-Fehler
ab. Vollständige Integration: [fail2ban.md](fail2ban.md).

### HTML-Bereinigung der Nachrichten

Nachrichtenkörper werden im Browser bereinigt (inertes DOMParser + Allowlist):
eingeschränkte Tags und Attribute (kein `script`/`iframe`/`object`/`on*`…),
Blockierung der `javascript:`/`vbscript:`/`data:`-Schemata, Neutralisierung
tückischer Styles (`position:fixed`, versteckter Inhalt), Klassenpräfix. Die
CSP ist die zweite Schicht.

### Versand von Nachrichten

- **From**: strikte Whitelist - die Adresse des Kontos oder eine deklarierte
  Identität (IDN in beiden Formen akzeptiert); jeder andere Wert wird
  abgelehnt (403).
- **Empfänger**: die Felder To/Cc/Bcc durchlaufen ein striktes Parsen - ein
  ungültiges Segment wird nicht still verworfen, der Versand wird abgelehnt
  (400) mit der Liste der unverständlichen Adressen.
- **CR/LF**: neutralisiert im Betreff, in Threading-Headern (In-Reply-To,
  References), in referenzierten Ordnernamen und in MIME-Parametern von
  Anhängen (`filename=`).
- **Bcc**: der Header wird aus den an den SMTP-Server gesendeten Daten
  entfernt, aber in der Kopie "Gesendete Elemente" behalten (nur für den
  Absender sichtbar).
- **Editor**: das TinyMCE-iframe trägt eine `Content-Security-Policy`, die
  jegliches Fernladen blockiert - der Inhalt einer weitergeleiteten/eingefügten
  Nachricht kann beim Öffnen keine Lesebestätigung exfiltrieren.
- **Temporäre Anhänge**: benutzerspezifische Tokens, bereinigter Name
  (`basename` + Steuerzeichen entfernt), Löschung nach 24 h.
- **SMTP-Fehler**: der Client erhält eine generische Meldung; rohe
  Serverantworten werden nur bei aktiviertem
  `labs.smtp_show_server_errors` angezeigt (Diagnose, kann interne Details
  offenbaren).

### OpenPGP

Ver-/Entschlüsselung erfolgt **vollständig im Browser** (OpenPGP.js). Der
Server speichert nur den armored Schlüsselbund; private Schlüssel sind durch
ihre Passphrase geschützt und werden nie serverseitig entschlüsselt. **Nur der
Nachrichtenkörper wird verschlüsselt**: der Versand von Anhängen wird
abgelehnt, wenn die Verschlüsselung aktiv ist (sie würden im Klartext
rausgehen).

### Bild-Proxy

Entfernte Bilder laufen über `/api/proxy`: nur `http(s)`, optionale Allowlist
(Wildcards), **Anti-SSRF** (private/reservierte IPs abgelehnt, bei **jeder**
Weiterleitung neu validiert - maximal 3), Timeout, begrenzte Größe. Der reale
MIME des Bodys wird gegen eine **Bitmap-Allowlist** geprüft (png, jpeg, gif,
webp, avif, bmp, ico) - SVG und jeder aktive Typ werden abgelehnt - und die
Antwort trägt `Content-Security-Policy: sandbox` + `nosniff`. Die Logs
behalten nur Schema+Host+Pfad (nie die Query, die Tokens enthalten kann).

### IP- und Proxy-Verwaltung

`X-Forwarded-For`/`X-Forwarded-Proto` werden nur beachtet, wenn `REMOTE_ADDR`
in `trusted_proxies` (CIDR) steht. Die HTTPS-Erkennung (`isHttps`) folgt
derselben Regel.

## 2. Härtungsempfehlungen

- **Document root = `public/`** nur; nie auf den Projektstamm zeigen. Die
  privaten Ordner (`app/`, `config/`, `storage/`…) haben ihre schützenden
  `.htaccess`, aber das ist nur ein Sicherheitsnetz.
- **HTTPS obligatorisch**: `force_https` + `hsts` in Produktion aktivieren.
- **Hinter einem Reverse Proxy** (Nginx, HAProxy, Cloudflare…):
  `trusted_proxies` eintragen, um Rate-Limit, Allowlist und Logs verlässlich zu
  machen; ohne es wird jedes `X-Forwarded-*` ignoriert.
- **Admin einschränken**: `admin_ip_allowlist` (IP/CIDR CSV) und ein nicht
  triviales `admin_path`; **Admin-TOTP** via Plugin `two-factor-auth`
  aktivieren (das `admin_totp_secret`-Geheimnis wird nur verlangt, solange das
  Plugin aktiviert ist).
- **Lokales Admin-Konto**: lieber `admin_user` + `admin_password_hash`
  (starkes Passwort) als den IMAP-Fallback für die Verwaltung.
- **`app.key` sichern** außerhalb des Webroots; nie versionieren.
- **`debug = false`** in Produktion (keine internen Fehler offenlegen).
- **Der `/setup`-Assistent** ist in den Kern integriert: er antwortet 404,
  sobald die Sperre `app/storage/INSTALLED` gesetzt ist - keine
  Installationsdatei mehr im Webroot zu löschen.
- `ssl.verify_certificate` prüfen: `false` ist nur für kontrollierte
  selbstsignierte Zertifikate akzeptabel.
- Im Wartungsmodus liefert `maintenance = true` eine eigene Wartungsseite in
  503 auf `/` (angemeldete Benutzer werden bei ihrer nächsten API-Anfrage
  umgeleitet, Sitzung erhalten) und unterbricht die Benutzer-`/api/*` (Admin
  und `/api/ping` bleiben aktiv).

## 3. Bekannte Grenzen

- Das `GitHubClient::REPO`-Repository ist auf `WatT-For/MXWebMail` verriegelt:
  installierte Pakete (Kern-Zip der Release, Plugin-Dateien aus dem Repo am Ref
  `update.plugins_ref` über die git-API + `raw.githubusercontent.com`, jede
  per git-blob-sha1 verifiziert) stammen nur aus diesem Repository - keine
  beliebige konfigurierbare URL. Das Kernpaket wird zweistufig verifiziert:
  sha256-Summe des Assets (`<zip>.sha256`-Datei der Release), dann
  `files.sha256` (eingebettetes Pro-Datei-Manifest, nach Extraktion geprüft);
  die `APP_REQ_PHP`/`APP_REQ_FROM`-Konstanten des `App.php` des Pakets
  deklarieren die Voraussetzungen (PHP-Version, minimale Quellversion), die vor
  dem Kopieren durchgesetzt werden. Das offizielle Repo ist **öffentlich**:
  API, Assets und `raw` funktionieren ohne Token (die anonyme API-Quote von
  GitHub wird durch den `cache.update_ttl`-Cache abgedeckt). Ein optionales,
  in `config.php` konfiguriertes GitHub-PAT (`Contents: Read`-Zugriff) kann
  die Quote erhöhen oder einen privaten Fork über `update.repo` bedienen -
  Assets werden dann über den `releases/assets/<id>`-Endpunkt ausgeliefert
  (`browser_download_url` liefert auf privatem Repo selbst authentifiziert 404). `update.plugins_local` und
  `update.manifest_url` (alternatives Release-Manifest) sind für Dev/Tests
  reserviert - nur via `config.php` editierbar, nie via Admin-API. Während des
  Dateikopiens unterbricht das `storage/update/applying`-Flag die
  Benutzer-API (503, läuft nach 15 min ab).
- Der Wechsel SQLite > MariaDB migriert keine Daten (leeres Schema).
- OpenPGP-Privatschlüssel werden armored serverseitig gespeichert (die
  Passphrase schützt den Schlüssel selbst, keine zusätzliche Kapselung).
