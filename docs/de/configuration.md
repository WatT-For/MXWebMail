# Konfigurationsreferenz

*Aus dem Französischen übersetzt - die französische Version ist maßgeblich.*

Die Datei `app/config/config.php` definiert nur die Schlüssel, die
Standardwerte **überschreiben** (`app/config/config.example.php`, von
`Config.php` geladen). `config.example.php` nach `config.php` kopieren und
anpassen. Das Admin-Panel bearbeitet die meisten dieser Schlüssel pro Abschnitt;
nur einige erfordern manuelle Bearbeitung (`update.*` vor allem, aber auch
`import_max_size`, `smime_ca_path`, `logs.max_size` und `logs.keep`).

Assoziative Arrays werden tief gemergt; Listen werden ersetzt.

## Speicher

| Schlüssel | Standard | Zweck |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) oder vollständiger PDO-DSN (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | PDO-Benutzer (MariaDB). |
| `db_pass` | `''` | PDO-Passwort (MariaDB). |

## Anwendung

| Schlüssel | Standard | Zweck |
|---|---|---|
| `app_name` | `MXWebMail` | Anzeigename (Titel, PWA, TOTP). |
| `favicon` | `''` | Favicon-Datei hochgeladen nach `storage/branding`, ausgeliefert via `/favicon.ico`; leer = Standard-Icon. |
| `website_url` | `https://mxrelay.fr/webmail/` | Link zur offiziellen Website (Admin-Button); leer = ausgeblendet. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | "Sponsor"-Link zur Spendenseite (Admin-Button + Webmail-Einstellungen); leer = ausgeblendet. |
| `debug` | `false` | Gibt interne Fehler an API-Clients zurück (nie in Produktion). |
| `language` | `auto` | `auto` = `Accept-Language` des Browsers; sonst Code einer installierten Sprache (`fr`, `en`…). Letzter Fallback `en`. |

## Domains

| Schlüssel | Standard | Zweck |
|---|---|---|
| `default_domain` | `auto` | Suffix eines ohne `@domain` eingegebenen Logins: `auto` = HTTP-Host (ohne `www.` und Port), `''` = keiner, oder eine feste Domain. |
| `restrict_domains` | `true` | Nur benannte Domains der `domains`-Tabelle werden beim Login akzeptiert (weder `*` noch Erkennung greifen). |
| `autodetect.enabled` | `false` | Automatische Erkennung der Konfiguration einer unbekannten Domain beim Login. |
| `autodetect.use_ispdb` | `false` | Fallback auf die Mozilla-Basis (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Port-Sondierung von `imap.`/`mail.`/`smtp.`/`sieve.<domain>`. |

IMAP/SMTP/Sieve-Server werden **pro Domain** im Admin konfiguriert (siehe
[domaines.md](domaines.md)). Die `*`-Domain dient als Catch-all-Parameter.

## Sicherheit

| Schlüssel | Standard | Zweck |
|---|---|---|
| `admin_path` | `admin` | URL-Segment des Admin-Panels. |
| `admin_user` | `''` | Lokales Admin-Konto; leer = Fallback auf einen via IMAP verifizierten `is_admin`-Benutzer. |
| `admin_password_hash` | `''` | `password_hash('passwort', PASSWORD_DEFAULT)` des lokalen Admin-Kontos. |
| `admin_totp_secret` | `''` | TOTP-Secret (base32) des lokalen Admin-Kontos, vom `two-factor-auth`-Plugin geschrieben; leer = deaktiviert. Nicht verlangt, wenn das Plugin deaktiviert ist. |
| `login_max_attempts` | `5` | Login-Versuche vor temporärer Sperre. |
| `login_lock_seconds` | `300` | Dauer der Sperre (Sekunden). |
| `admin_ip_allowlist` | `''` | IPs/CIDRs (CSV), die auf dem Admin erlaubt sind; leer = alle. |
| `session_lifetime` | `0` | Sitzungsdauer in Stunden; `0` = Sitzungs-Cookie. |
| `remember_days` | `30` | Laufzeit der "Angemeldet bleiben"-Tokens in Tagen (1-365). |
| `maintenance` | `false` | Wartungsmodus: `/` liefert eine eigene Seite in 503 (alle Öffentlichkeiten, Sitzung erhalten) und die Benutzer-`/api/*` antwortet 503 - `/api/admin/*` und `/api/ping` bleiben aktiv. Angemeldete Benutzer wechseln bei ihrer nächsten API-Anfrage auf die Wartungsseite; sie lädt am Ende selbst neu. |
| `force_https` | `false` | Leitet HTTP > HTTPS um (301). |
| `hsts` | `false` | Emittiert `Strict-Transport-Security`, wenn die Anfrage HTTPS ist. |
| `cookie_samesite` | `Lax` | `Lax` (Standard, für die OAuth2-Rückkehr erforderlich) / `Strict` (bricht den OAuth2-Fluss) / `None` (erfordert HTTPS). |
| `csp_extra` | `''` | Zusätzliche CSP-Direktiven (z. B. `img-src https:`). |
| `trusted_proxies` | `''` | IPs/CIDRs (CSV) vertrauenswürdiger Reverse-Proxys. Erforderlich, um `X-Forwarded-*` verlässlich zu machen (Rate-Limit, Allowlist, Logs). |
| `secfetch_allow` | `''` | Zusätzliche `Sec-Fetch-*`-Regeln (z. B. `site=same-origin;mode=cors`). |

## Updates

Das offizielle Repository ist in `GitHubClient::REPO` (`WatT-For/MXWebMail`)
verriegelt und **öffentlich**: Prüfung und Download funktionieren ohne Token.
Dieser Abschnitt - vom Kern-Updater und dem Plugin-Katalog geteilt
(im Repo am Ref `update.plugins_ref` gelesen, jede heruntergeladene Datei per
git-blob-sha1 verifiziert) - ist nicht über den Admin editierbar (außer
`channel`).

| Schlüssel | Standard | Zweck |
|---|---|---|
| `update.repo` | `''` | Dev-Fallback, falls `GitHubClient::REPO` leer ist (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (einzige im Admin exponierte Einstellung). |
| `update.manifest_url` | `''` | Dev: Release-formatiertes JSON-Manifest, umgeht die GitHub-API. |
| `update.plugins_ref` | `'release'` | Git-Ref als Quelle des Plugin-Katalogs: `'release'` (Tag der letzten Release), `'main'` (Branch - ein Push von `plugin.json` veröffentlicht das Update) oder jeder literale Ref. |
| `update.plugins_local` | `''` | Dev/Tests: absoluter Pfad eines Plugin-Ordners - Katalog + Install von der Platte, offline und ohne Fernprüfung. Hat Vorrang vor `plugins_ref`. |

## Funktionen

| Schlüssel | Standard | Zweck |
|---|---|---|
| `attachment_max_size` | `25` | Max. MB pro Datei. Muss ≤ `attachments_max_total` sein (darüber hat das Limit keine Wirkung: der Upload begrenzt bereits auf das `min` der beiden - der Admin verweigert das inkonsistente Speichern). |
| `attachments_max_total` | `25` | Kumulative MB pro Nachricht. |
| `allow_additional_accounts` | `true` | Der Benutzer kann IMAP-Zweitkonten hinzufügen/verwalten (Einstellungen > Konten). |
| `allow_additional_identities` | `true` | Der Benutzer kann zusätzliche Identitäten anlegen. |
| `popup_identity` | `false` | Öffnet die Identitätseinstellung beim ersten Login, wenn der Name leer ist. |
| `dangerous_actions` | `true` | Erlaubt Leeren/Löschen eines Ordners, Löschen von Nachrichten (einschließlich Verschieben in den Papierkorb). |
| `contacts_enable` | `true` | Adressbuch + Autovervollständigung. |
| `contacts_suggestions_limit` | `20` | Max. Anzahl Autovervollständigungs-Vorschläge (1-100). |
| `import_max_size` | `100` | Max. Größe einer importierten `.mbox` (MB) - `.eml` behält die `attachment_max_size`-Decke. Nicht im Admin editierbar. |
| `smime_ca_path` | `''` | CA-Pfad (PEM-Datei) für S/MIME-Vertrauen; leer = nur kryptografische Signatur geprüft (`PKCS7_NOVERIFY`). Nicht im Admin editierbar. |

## `user_defaults` - Standards der Benutzereinstellungen

Vom Benutzer überschreibbar. Die über `/api/settings` schreibbaren Schlüssel
sind diese plus die von Plugins deklarierten (`settings.allowed`-Hook).

| Schlüssel | Standard | Zweck |
|---|---|---|
| `messages_per_page` | `50` | Nachrichten pro Seite (5-200). |
| `list_sort` | `REVERSE DATE` | Listensortierung (gewhitelistetes IMAP-SORT-Kriterium). |
| `view_images` | `ask` | Externe Bilder: `always` / `ask` / `match` (bekannter Absender). |
| `msg_default_action` | `reply` | Standard-Antwort-Button: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Sekunden, bevor eine geöffnete Nachricht als gelesen markiert wird (0 = sofort). |
| `compose_autosave` | `60` | Sekunden zwischen automatischen Entwurfsspeicherungen (0 = nie). |
| `mdn_mode` | `ask` | Lesebestätigungen: `ask` (Anfrage-Banner) / `never` (ausgeblendet). |
| `autologout` | `30` | Inaktivitätsminuten vor Abmeldung (0 = nie). Widerruft das "Angemeldet bleiben"-Token nicht: die Sitzung wird beim nächsten Besuch still wiederhergestellt. |
| `list_refresh` | `5` | Minuten zwischen automatischen Aktualisierungen (0 = nie). |
| `notify_new_mail` | `false` | Browser-Benachrichtigung bei neuer Mail. |
| `contacts_autosave` | `true` | Empfänger automatisch in Kontakte speichern. |
| `folder_pane_width` | `240` | Breite der Ordnerspalte (px, 140-600). |
| `message_pane_width` | `340` | Breite der Nachrichtenlisten-Spalte (px, 200-800). |
| `layout` | `horizontal` | Lese-Layout: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Höhe der Nachrichtenliste im Vertikalmodus (px, 120-1200). |
| `compose_modal_width` | `0` | Breite der Verfassen-Modale (px, 0 = Bootstrap-Standard). |
| `compose_modal_height` | `0` | Höhe der Verfassen-Modale (px, 0 = auto). |
| `identity_modal_width` | `0` | Breite der Identitäts-Modale (px, 0 = Bootstrap-Standard). |
| `identity_modal_height` | `0` | Höhe der Identitäts-Modale (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Papierkorb beim Abmelden leeren. |
| `empty_junk_on_logout` | `false` | Spam-Ordner beim Abmelden leeren. |
| `list_threads` | `false` | Nachrichten zu Konversationen gruppieren (IMAP THREAD). |
| `after_delete` | `next` | Nach Löschen/Verschieben der angezeigten Mail: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Tastaturkürzel der Mail-Ansicht (Hilfe über die `?`-Taste). |
| `theme` | `default` | Oberflächen-Template (Ordner `app/templates/<name>`). |

## TLS (ausgehende IMAP/SMTP/Sieve-Verbindungen)

| Schlüssel | Standard | Zweck |
|---|---|---|
| `ssl.verify_certificate` | `true` | Das Zertifikat prüfen (`false` = selbstsigniertes Zertifikat). |
| `ssl.allow_self_signed` | `false` | Selbstsignierte Zertifikate akzeptieren. |
| `ssl.cafile` / `ssl.capath` | `''` | Eigene CA (absoluter Pfad / Ordner). |
| `ssl.disable_compression` | `true` | TLS-Kompression deaktivieren. |
| `ssl.security_level` | `1` | OpenSSL-Sicherheitsniveau (0-5). |
| `ssl.local_cert` | `''` | Optionales Client-Zertifikat. |

## Externer Bild-Proxy

| Schlüssel | Standard | Zweck |
|---|---|---|
| `image_proxy` | `true` | Entfernte Bilder via `/api/proxy` ausliefern. |
| `image_proxy_allowlist` | `''` | Erlaubte Domains (CSV, `*.domain` ok); leer = alle (private IPs immer blockiert). |

## HTTP - Kompression & Browser-Cache

Im Admin editierbar, Abschnitt *Performance > HTTP*.

| Schlüssel | Standard | Zweck |
|---|---|---|
| `http.gzip` | `true` | Komprimiert Text/JSON-Antworten in PHP (gzip) - funktioniert auch ohne `mod_deflate`. Ignoriert, wenn `zlib.output_compression` aktiv ist. **Hinter Apache/nginx: `false` empfohlen** - der Server komprimiert als Filter (Streaming) und PHP vermeidet ein `gzencode` im Speicher. `true` behalten für `php -S` (Dev, kein `mod_deflate`). |
| `http.gzip_level` | `6` | Kompressionsniveau 1-9. |
| `http.gzip_min_size` | `1024` | Minimale Body-Größe (Bytes) zum Komprimieren. |
| `http.etag` | `true` | ETag/Last-Modified + 304-Antworten auf per PHP ausgelieferte Assets (Theme, Plugins, Favicon). |
| `http.assets_ttl` | `86400` | `max-age` der Theme/Plugin-Assets (`/assets/theme|plugin/*`). `0` = systematische Revalidierung. Mit `?v=` (mtime der ausgelieferten Datei) wird `immutable` emittiert. |
| `http.favicon_ttl` | `3600` | `max-age` von `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (privat) der Bilder via `/api/proxy` - dient auch als TTL des Festplatten-Caches (`storage/cache/proxy`). `0` = kein Cache (Re-fetch + SSRF-Revalidierung bei jeder Anzeige). Purge durch `cache.gc_hours` verwaltet: `proxy_ttl ≤ cache.gc_hours` behalten. |

Private/API-Antworten erhalten nie öffentlichen Cache; das HTML ist `no-cache`.

**Produktion**: **opcache** aktivieren (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ Anzahl der PHP-Dateien - der Code invalidiert
seine eigenen Caches via `opcache_invalidate`, `opcache.validate_timestamps`
darf aktiv bleiben). Die Anwendungscaches (`classmap.php`, Plugin-Manifeste,
i18n-Wörterbücher, `themedata.php`) werden vom Code verwaltet - keine manuelle
Invalidierung nötig.

## Anwendungscache (Datei)

Im Admin editierbar, Abschnitt *Performance > Server-Cache*.

| Schlüssel | Standard | Zweck |
|---|---|---|
| `cache.enabled` | `true` | Aktiviert den Datei-Cache `app/storage/cache` (IMAP-Nachrichtenlisten, indexiert auf den ETag des Ordners, DNS, Domain-Erkennung, Update-Prüfung, Ordner-GC). Betrifft weder den Autoloader (`classmap.php`) noch `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL des negativen IMAP/SMTP-Erkennungs-Caches (s). |
| `cache.update_ttl` | `3600` | TTL des Update-Prüfergebnisses (s). |
| `cache.gc_hours` | `168` | Purge-Intervall abgelaufener Cache-Dateien (h); `0` = nie. |

## MailSo-Logs (IMAP/SMTP/Sieve-Protokolle)

| Schlüssel | Standard | Zweck |
|---|---|---|
| `logs.enabled` | `true` | Schreibt `storage/logs/mail.log` (Geheimnisse maskiert). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG`, um das Protokoll zu tracen. |
| `logs.syslog` | `false` | Duplikat zu `syslog()`, wenn das Datei-Log inaktiv ist. |
| `logs.time_zone` | `''` | Zeitzone der Zeitstempel (z. B. `Europe/Paris`); leer = Server. |
| `logs.max_size` | `5` | Rotation ab N MiB - gilt für `mail.log`, `app.log` und das Auth-Protokoll. |
| `logs.keep` | `3` | Behaltene Rotationen (`app.1.log` … `app.{keep}.log`, Decke 20). |

## Authentifizierungsprotokoll (fail2ban-Stil)

| Schlüssel | Standard | Zweck |
|---|---|---|
| `auth_logging` | `false` | Aktiviert das dedizierte Authentifizierungsprotokoll. |
| `auth_logging_filename` | `auth.log` | Datei in `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Platzhalter: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` ist `AUTH OK`, `AUTH FAILED (<http-Code>)` oder
`AUTH FAILED (totp)`. Das Protokoll deckt Webmail-Login und Admin-Login ab
(Login mit `admin:`-Präfix). Für den fail2ban-Anschluss (Filter, Jail,
logrotate) siehe [fail2ban.md](fail2ban.md).

## Labs / Kompatibilität

| Schlüssel | Standard | Zweck |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Rohe SMTP-Fehler dem Client anzeigen (Diagnose, kann interne Details offenbaren). |
