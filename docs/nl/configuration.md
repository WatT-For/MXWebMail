# Configuratie-referentie

*Vertaald uit het Frans - de Franse versie is leidend.*

Het bestand `app/config/config.php` definieert alleen de sleutels die de
standaardwaarden **overschrijven** (`app/config/config.example.php`, geladen
door `Config.php`). Kopieer `config.example.php` naar `config.php` en pas
het aan. Het beheerpaneel bewerkt de meeste van deze sleutels per sectie;
slechts enkele vereisen handbewerking (`update.*` vooral, maar ook
`import_max_size`, `smime_ca_path`, `logs.max_size` en `logs.keep`).

Associatieve arrays worden diep samengevoegd; lijsten worden vervangen.

## Opslag

| Sleutel | Standaard | Rol |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) of volledige PDO-DSN (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | PDO-gebruiker (MariaDB). |
| `db_pass` | `''` | PDO-wachtwoord (MariaDB). |

## Applicatie

| Sleutel | Standaard | Rol |
|---|---|---|
| `app_name` | `MXWebMail` | Weergegeven naam (titel, PWA, TOTP). |
| `favicon` | `''` | Favicon-bestand geupload in `storage/branding`, geserveerd via `/favicon.ico`; leeg = standaardicoon. |
| `website_url` | `https://mxrelay.fr/webmail/` | Link naar de officiële website (admin-knop); leeg = verborgen. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | "Sponsor"-link naar de donatiepagina (admin-knop + webmail-instellingen); leeg = verborgen. |
| `debug` | `false` | Stuurt interne fouten terug naar API-clients (nooit in productie). |
| `language` | `auto` | `auto` = `Accept-Language` van de browser; anders de code van een geinstalleerde taal (`fr`, `en`…). Laatste fallback `en`. |

## Domeinen

| Sleutel | Standaard | Rol |
|---|---|---|
| `default_domain` | `auto` | Suffix van een login getypt zonder `@domein`: `auto` = HTTP-host (zonder `www.` en poort), `''` = geen, of een vast domein. |
| `restrict_domains` | `true` | Alleen de benoemde domeinen uit de tabel `domains` worden bij login aanvaard (noch `*` noch detectie geldt). |
| `autodetect.enabled` | `false` | Automatische detectie van de configuratie van een onbekend domein bij login. |
| `autodetect.use_ispdb` | `false` | Fallback op de Mozilla-database (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Poortsondering `imap.`/`mail.`/`smtp.`/`sieve.<domein>`. |

De IMAP/SMTP/Sieve-servers worden **per domein** geconfigureerd in de admin
(zie [domaines.md](domaines.md)). Het `*`-domein dient als catch-all-
parameters.

## Beveiliging

| Sleutel | Standaard | Rol |
|---|---|---|
| `admin_path` | `admin` | URL-segment van het admin-paneel. |
| `admin_user` | `''` | Lokaal admin-account; leeg = fallback op een `is_admin`-gebruiker geverifieerd via IMAP. |
| `admin_password_hash` | `''` | `password_hash('wachtwoord', PASSWORD_DEFAULT)` van het lokale admin-account. |
| `admin_totp_secret` | `''` | TOTP-secret (base32) van het lokale admin-account, geschreven door de plugin `two-factor-auth`; leeg = uitgeschakeld. Niet afgedwongen als de plugin uit staat. |
| `login_max_attempts` | `5` | Inlogpogingen vóór tijdelijke blokkering. |
| `login_lock_seconds` | `300` | Duur van de blokkering (seconden). |
| `admin_ip_allowlist` | `''` | Toegestane IP's/CIDR's (CSV) op de admin; leeg = alle. |
| `session_lifetime` | `0` | Sessieduur in uren; `0` = sessie-cookie. |
| `remember_days` | `30` | Levensduur van de "Ingelogd blijven"-tokens in dagen (1-365). |
| `maintenance` | `false` | Onderhoudsmodus: `/` serveert een toegewijde pagina in 503 (alle doelgroepen, sessie behouden) en de `/api/*` voor gebruikers antwoordt 503 - `/api/admin/*` en `/api/ping` blijven actief. Ingelogde gebruikers worden naar de onderhoudspagina verplaatst bij hun volgende API-request; deze herlaadt zelf aan het einde. |
| `force_https` | `false` | Redirecteert HTTP > HTTPS (301). |
| `hsts` | `false` | Zendt `Strict-Transport-Security` als de request HTTPS is. |
| `cookie_samesite` | `Lax` | `Lax` (standaard, vereist voor OAuth2-terugkeer) / `Strict` (breekt de OAuth2-flow) / `None` (vereist HTTPS). |
| `csp_extra` | `''` | Extra CSP-directives (bijv. `img-src https:`). |
| `trusted_proxies` | `''` | IP's/CIDR's (CSV) van de vertrouwde reverse proxies. Verplicht om `X-Forwarded-*` betrouwbaar te maken (rate-limit, allowlist, logs). |
| `secfetch_allow` | `''` | Extra `Sec-Fetch-*`-regels (bijv. `site=same-origin;mode=cors`). |

## Updates

De officiele repository is vergrendeld in `GitHubClient::REPO`
(`WatT-For/MXWebMail`) en **publiek**: check en download werken zonder token.
Deze sectie - gedeeld door de kern-updater en de
plugin-catalogus (gelezen in de repo op ref `update.plugins_ref`, elk
gedownload bestand geverifieerd op git-blob-sha1) - is niet via de admin
bewerkbaar (behalve `channel`).

| Sleutel | Standaard | Rol |
|---|---|---|
| `update.repo` | `''` | Dev-fallback als `GitHubClient::REPO` leeg is (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (enige instelling getoond in de admin). |
| `update.manifest_url` | `''` | Dev: JSON-manifest in release-formaat, omzeilt de GitHub-API. |
| `update.plugins_ref` | `'release'` | Git-ref bron van de plugin-catalogus: `'release'` (tag van de laatste release), `'main'` (branch - een push van `plugin.json` publiceert de update) of elke letterlijke ref. |
| `update.plugins_local` | `''` | Dev/tests: absoluut pad van een plugin-map - catalogus + install vanaf schijf, offline en zonder remote-verificatie. Prevaleert op `plugins_ref`. |

## Functionaliteit

| Sleutel | Standaard | Rol |
|---|---|---|
| `attachment_max_size` | `25` | MB max per bestand. Moet ≤ `attachments_max_total` zijn (daarboven heeft de limiet geen effect: de upload begrenst al op het `min` van beiden - de admin weigert een inconsistente opslag). |
| `attachments_max_total` | `25` | MB cumulatief per bericht. |
| `allow_additional_accounts` | `true` | De gebruiker kan IMAP-subaccounts toevoegen/beheren (Instellingen > Accounts). |
| `allow_additional_identities` | `true` | De gebruiker kan extra identiteiten aanmaken. |
| `popup_identity` | `false` | Opent de identiteitsinstelling bij de eerste login als de naam leeg is. |
| `dangerous_actions` | `true` | Autoriseert legen/verwijderen van een map, verwijderen van berichten (inclusief verplaatsing naar de prullenbak). |
| `contacts_enable` | `true` | Adresboek + autocompletie. |
| `contacts_suggestions_limit` | `20` | Max aantal autocompletie-suggesties (1-100). |
| `import_max_size` | `100` | Max grootte van een geimporteerd `.mbox` (MB) - de `.eml` houden het plafond `attachment_max_size`. Niet bewerkbaar in de admin. |
| `smime_ca_path` | `''` | CA-pad (PEM-bestand) voor S/MIME-vertrouwen; leeg = alleen cryptografische handtekening geverifieerd (`PKCS7_NOVERIFY`). Niet bewerkbaar in de admin. |

## `user_defaults` - standaards van de gebruikersinstellingen

Overschrijfbaar door de gebruiker. De in schrijving toegestane sleutels via
`/api/settings` zijn deze plus de door plugins gedeclareerde (hook
`settings.allowed`).

| Sleutel | Standaard | Rol |
|---|---|---|
| `messages_per_page` | `50` | Berichten per pagina (5-200). |
| `list_sort` | `REVERSE DATE` | Sortering van de lijst (IMAP SORT-criterium in whitelist). |
| `view_images` | `ask` | Externe afbeeldingen: `always` / `ask` / `match` (bekende afzender). |
| `msg_default_action` | `reply` | Standaard antwoordknop: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Seconden vóór een geopend bericht als gelezen wordt gemarkeerd (0 = onmiddellijk). |
| `compose_autosave` | `60` | Seconden tussen automatische concept-opslag (0 = nooit). |
| `mdn_mode` | `ask` | Leesbevestigingen: `ask` (aanvraagbanner) / `never` (verborgen). |
| `autologout` | `30` | Minuten inactiviteit vóór uitloggen (0 = nooit). Trekt het "Ingelogd blijven"-token niet in: de sessie wordt stil hersteld bij het volgende bezoek. |
| `list_refresh` | `5` | Minuten tussen automatische verversingen (0 = nooit). |
| `notify_new_mail` | `false` | Browser-notificatie bij binnenkomende mail. |
| `contacts_autosave` | `true` | De ontvanger automatisch in de contacten opslaan. |
| `folder_pane_width` | `240` | Breedte mappenkolom (px, 140-600). |
| `message_pane_width` | `340` | Breedte berichtenlijst-kolom (px, 200-800). |
| `layout` | `horizontal` | Leesindeling: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Hoogte berichtenlijst in verticale modus (px, 120-1200). |
| `compose_modal_width` | `0` | Breedte opstelmodaal (px, 0 = Bootstrap-standaard). |
| `compose_modal_height` | `0` | Hoogte opstelmodaal (px, 0 = auto). |
| `identity_modal_width` | `0` | Breedte identiteitsmodaal (px, 0 = Bootstrap-standaard). |
| `identity_modal_height` | `0` | Hoogte identiteitsmodaal (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | De prullenbak legen bij uitloggen. |
| `empty_junk_on_logout` | `false` | De spammap legen bij uitloggen. |
| `list_threads` | `false` | Berichten groeperen in conversaties (IMAP THREAD). |
| `after_delete` | `next` | Na verwijderen/verplaatsen van de getoonde mail: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Toetsenbordsnelkoppelingen van de mailweergave (hulp via de toets `?`). |
| `theme` | `default` | Interface-template (map `app/templates/<name>`). |

## TLS (uitgaande IMAP/SMTP/Sieve-verbindingen)

| Sleutel | Standaard | Rol |
|---|---|---|
| `ssl.verify_certificate` | `true` | Het certificaat verifieren (`false` = self-signed certificaat). |
| `ssl.allow_self_signed` | `false` | Self-signed certificaten aanvaarden. |
| `ssl.cafile` / `ssl.capath` | `''` | Aangepaste CA (absoluut pad / map). |
| `ssl.disable_compression` | `true` | Schakelt TLS-compressie uit. |
| `ssl.security_level` | `1` | OpenSSL security level (0-5). |
| `ssl.local_cert` | `''` | Eventueel clientcertificaat. |

## Proxy van externe afbeeldingen

| Sleutel | Standaard | Rol |
|---|---|---|
| `image_proxy` | `true` | Remote-afbeeldingen serveren via `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Toegestane domeinen (CSV, `*.domein` ok); leeg = alle (private IP's altijd geblokkeerd). |

## HTTP - compressie & browser-cache

Bewerkbaar in de admin, sectie *Performance > HTTP*.

| Sleutel | Standaard | Rol |
|---|---|---|
| `http.gzip` | `true` | Comprimeert tekst/JSON-antwoorden in PHP (gzip) - werkt ook zonder `mod_deflate`. Genegeerd als `zlib.output_compression` actief is. **Achter Apache/nginx: `false` aanbevolen** - de server comprimeert als filter (streaming) en PHP vermijdt een `gzencode` in geheugen. `true` houden voor `php -S` (dev, geen `mod_deflate`). |
| `http.gzip_level` | `6` | Compressieniveau 1-9. |
| `http.gzip_min_size` | `1024` | Minimale bodygrootte (bytes) om te comprimeren. |
| `http.etag` | `true` | ETag/Last-Modified + 304-antwoorden op de door PHP geserveerde assets (thema, plugins, favicon). |
| `http.assets_ttl` | `86400` | `max-age` van de thema/plugin-assets (`/assets/theme|plugin/*`). `0` = systematische hervalidatie. Met `?v=` (mtime van het geserveerde bestand) wordt `immutable` uitgegeven. |
| `http.favicon_ttl` | `3600` | `max-age` van `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (private) van afbeeldingen via `/api/proxy` - dient ook als TTL voor de schijfcache (`storage/cache/proxy`). `0` = geen cache (re-fetch + SSRF-hervalidatie bij elke weergave). Purge beheerd door `cache.gc_hours`: houd `proxy_ttl ≤ cache.gc_hours`. |

Private/API-antwoorden krijgen nooit publieke cache; de HTML is `no-cache`.

**Productie**: **opcache** activeren (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ het aantal PHP-bestanden - de code
invalideert zijn eigen caches via `opcache_invalidate`,
`opcache.validate_timestamps` mag actief blijven). De applicatiecaches
(`classmap.php`, plugin-manifesten, i18n-woordenboeken, `themedata.php`)
worden door de code beheerd - geen manuele invalidatie vereist.

## Applicatiecache (bestand)

Bewerkbaar in de admin, sectie *Performance > Servercache*.

| Sleutel | Standaard | Rol |
|---|---|---|
| `cache.enabled` | `true` | Activeert de bestandscache `app/storage/cache` (IMAP-berichtenlijsten geindexeerd op de ETag van de map, DNS, domeindetectie, update-check, GC van de map). Raakt noch de autoloader (`classmap.php`) noch `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL van de negatieve IMAP/SMTP-detectiecache (s). |
| `cache.update_ttl` | `3600` | TTL van het update-check-resultaat (s). |
| `cache.gc_hours` | `168` | Purge-interval van verlopen cachebestanden (u); `0` = nooit. |

## MailSo-logs (IMAP/SMTP/Sieve-protocollen)

| Sleutel | Standaard | Rol |
|---|---|---|
| `logs.enabled` | `true` | Schrijft `storage/logs/mail.log` (geheimen gemaskeerd). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` om het protocol te traceren. |
| `logs.syslog` | `false` | Duplicaat naar `syslog()` als de file-log inactief is. |
| `logs.time_zone` | `''` | Tijdzone van de timestamps (bijv. `Europe/Paris`); leeg = server. |
| `logs.max_size` | `5` | Rotatie boven N MiB - geldt voor `mail.log`, `app.log` en het auth-log. |
| `logs.keep` | `3` | Bewaarde rotaties (`app.1.log` … `app.{keep}.log`, plafond 20). |

## Authenticatielog (fail2ban-stijl)

| Sleutel | Standaard | Rol |
|---|---|---|
| `auth_logging` | `false` | Activeert het toegewijde authenticatielog. |
| `auth_logging_filename` | `auth.log` | Bestand in `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholders: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` is `AUTH OK`, `AUTH FAILED (<http-code>)` of
`AUTH FAILED (totp)`. Het log dekt de webmail-login en de admin-login (login
met `admin:`-prefix). Voor de fail2ban-aansluiting (filter, jail, logrotate),
zie [fail2ban.md](fail2ban.md).

## Labs / compatibiliteit

| Sleutel | Standaard | Rol |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Ruwe SMTP-fouten aan de client tonen (diagnose, kan interne details prijsgeven). |
