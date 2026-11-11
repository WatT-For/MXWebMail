# Probleemoplossing / FAQ

*Vertaald uit het Frans - de Franse versie is leidend.*

## Installatie

**Alle URL's leiden door naar `/setup/`**
Normaal: het slot `app/storage/INSTALLED` ontbreekt. Rond de installatie af,
of maak het bestand handmatig na het plaatsen van `config.php`.

**De wizard meldt "config/ niet beschrijfbaar"**
Niet-blokkerende waarschuwing: kopieer de getoonde inhoud met de hand naar
`app/config/config.php` en herstart de installatie. Controleer ook dat
`app/storage/` beschrijfbaar is (blokkerend).

**De MariaDB-verbindingstest mislukt**
Controleer host/poort/credentials, en dat de gebruiker het recht heeft de
database aan te maken (`CREATE DATABASE`). De charset moet `utf8mb4` zijn.

## Verbinding / login

**Geen gebruiker kan inloggen na de installatie**
Met `restrict_domains = true` (standaard) moet **minstens een benoemd domein**
in de tabel staan (de `*`-wildcard wordt voor deze controle genegeerd). De
installer probeert hem te detecteren; maak hem anders aan in *Domeinen*.

**Login zonder reden geweigerd (geblokkeerd account)**
- Te veel pogingen: wacht `login_lock_seconds` (standaard 300 s) af, of purge
  `app/storage/cache/login_attempts.json`.
- De plugins `black-list`/`white-list` kunnen de login weigeren (404/403 of
  generieke fout afhankelijk van `auth_error`).
- Een `disabled`-gebruiker wordt geweigerd, zelfs met een geldige sessie.

**De admin-login werkt niet**
- Lokaal account (`admin_user`): controleer `admin_password_hash` en het
  eventuele TOTP.
- IMAP-fallback: de gebruiker moet `is_admin = 1` hebben.
- `admin_ip_allowlist` kan uw IP blokkeren.

## Mailservers

**Kan geen verbinding maken met de IMAP/SMTP-server**
Gebruik de knop **Verbinding testen** in *Domeinen* (resultaat per protocol).
Veelvoorkomende oorzaken: inconsistente poort/versleuteling (993/ssl vs
143/tls), self-signed certificaat (zie
`ssl.verify_certificate`/`ssl.allow_self_signed`), of firewall.

**Self-signed certificaat geweigerd**
`ssl.verify_certificate = false` of `ssl.allow_self_signed = true` (alleen
gebruiken met volle kennis van de gevolgen).

**Het domein wordt niet automatisch gedetecteerd**
`autodetect.enabled` staat standaard uit. Activeer het, of gebruik de knop
**Detecteren** op de pagina *Domeinen*.

**"Als spam melden" filtert de volgende mails niet**
De knop verplaatst het bericht naar de Junk-map (of zet het `$Junk`-IMAP-
keyword als de server geen Junk-map heeft) - het leert de server niets.
Sieve-filters werken alleen bij bezorging: het *Anti-spam*-sjabloon van de
wizard (Instellingen > Filters) sorteert mails die de MTA met
`X-Spam-Flag: YES` markeert. Automatisch leren (spam/ham) vereist
serverconfiguratie - Dovecot IMAPSieve (report-spam/ham-script getriggerd
door kopiëren naar/van Junk, wat de MOVE van de app triggert) of
rspamd/sa-learn.

## Beveiliging / HTTPS

**De site blijft op HTTP achter een reverse proxy**
Vul `trusted_proxies` in (IP van de proxy) zodat `X-Forwarded-Proto` wordt
gehonoreerd, activeer daarna `force_https` / `hsts`.

**De rate-limit of allowlist ziet het echte IP niet**
Zelfde oorzaak: zonder `trusted_proxies` wordt `REMOTE_ADDR` ongewijzigd
gebruikt.

**De favicon wordt niet getoond**
De geuploade favicon moet ≤ 512 KB zijn (ico/png/svg/jpg/webp); anders blijft
het standaardicoon.

## Gegevens / logs

**Verlies van `app.key`**
Alle opgeslagen credentials worden onontcijferbaar: gebruikers moeten
opnieuw inloggen. Maak altijd een backup van `app.key`.

**Waar zijn de logs?**
`app/storage/logs/app.log` (applicatie), `mail.log` (IMAP/SMTP/Sieve-
protocollen, als `logs.enabled`), en het auth-log (`auth_logging`). In de
admin te bekijken (*Logs*). fail2ban-integratie: [fail2ban.md](fail2ban.md).

**De overstap naar MariaDB heeft de gegevens geleegd**
Verwacht gedrag: de wijziging van `db_dsn` begint opnieuw op een leeg schema
(de contactbestanden per gebruiker blijven behouden). Er is geen
datamigratietool.

## Updates

**"Fout: Niet gevonden op GitHub … private repo of ontbrekende resource"**
De officiële repository is vergrendeld (`GitHubClient::REPO` =
`WatT-For/MXWebMail`) en **publiek**: geen token vereist. GitHub geeft 404 -
niet 403 - terug voor een ontbrekende resource of onvoldoende toegang; het
bericht noemt het schuldige endpoint (`releases/latest`, `commits/…` of een
`raw`-pad van de plugin-catalogus). Een aanhoudende 404 betekent meestal een
ontbrekende resource (geen release gepubliceerd, onbekende
`update.plugins_ref`). Het geval "private repo" betreft alleen een via
`update.repo` + PAT in `config.php` geconfigureerde fork.

**HTTP 404 op `…/releases/download/…` (plugin-catalogus of update-download)**
Op de publieke officiële repo werkt `browser_download_url`. Het geval is
alleen relevant voor een private fork via `update.repo`:
`browser_download_url` geeft daar zelfs met een geldige token 404 terug -
verholpen sinds 0.36.1, assets worden dan gedownload via het API-endpoint
`releases/assets/<id>` als een PAT is geconfigureerd in `config.php`.

**De site antwoordt 503 "update" / onderhoudspagina tijdens een update**
Normaal: de flag `app/storage/update/applying` onderbreekt de gebruikers-API
tijdens het kopiëren van bestanden (nooit een mix van oude/nieuwe code). Hij
wordt aan het einde verwijderd; blijft hij na een crash, dan verloopt hij
vanzelf na 15 min - of verwijder `app/storage/update/applying` handmatig.

**"Migraties in behandeling" na een update**
De afronding (`/api/admin/update/finalize`) heeft niet gedraaid: knop
*Afronden* in *Over*, of via CLI `php app/bin/post-update.php`. Zonder
actie worden migraties toch toegepast bij de volgende request (lui
migreren), maar het is beter ze te controleren.

**Terugdraaien na een update (rollback)**
Knop *Herstellen* in *Over*: herstelt de bestanden uit
`app/storage/update/backup-<ts>` (de laatste 3 backups worden bewaard),
verwijdert aangemaakte bestanden en kopieert de opgeslagen
SQLite-databases terug. Onder **MariaDB** zijn migraties niet omkeerbaar:
herstel eerst de database uit uw `mysqldump`, herstart daarna de rollback
(gedwongen bevestiging wordt aangeboden). Om opnieuw te beginnen:
herinstalleer het originele pakket en herstel `app/storage/` (inclusief
`app.key` en `db/`).

**Banner "databaseschema nieuwer dan de code"**
`meta.schema_ahead`: de geinstalleerde code is ouder dan het schema
(bestands-rollback zonder databaseherstel, of een ouder pakket
geredeployd). Herstel de database of pas de bijpassende versie opnieuw toe;
de migraties blijven opgeschort zolang het verschil bestaat.

## Diversen

**"Onveranderd doorsturen" (bounce): ziet de ontvanger de mail als spam?**
Mogelijk: het doorsturen breekt SPF/DMARC aan het einde (de verzendserver
komt niet meer overeen met de oorspronkelijke afzender) - normaal
bounce-gedrag, de `Resent-*`-headers documenteren de doorgeving. Om de
authenticatie te behouden, liever klassiek doorsturen.

**Zijn onder Nginx de privémappen bereikbaar?**
Nee, als `root` op `public/` wijst en het blok `location ~ /\.` +
`^/(app|dist)` aanwezig is. Wijs `root` nooit op de projectroot.

**403-fout "cross_site" op een legitieme request**
De `Sec-Fetch-*`-bescherming heeft de request geweigerd. Als u een legitieme
externe API integreert, voeg een regel toe in `secfetch_allow`.

**Een interne fout debuggen (500)**
Activeer tijdelijk `debug = true` (nooit in productie) om het bericht te
zien, raadpleeg daarna `app.log`.
