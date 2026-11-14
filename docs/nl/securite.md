# Beveiliging

*Vertaald uit het Frans - de Franse versie is leidend.*

## 1. Ingebouwd beveiligingsmodel

### "Ingelogd blijven" (persistente tokens)

Het vinkje "Ingelogd blijven" geeft een cookie `mxwm_remember` uit
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, duur `remember_days` -
standaard 30 dagen). De tabel `auth_tokens` bewaart alleen de **SHA-256-hash**
van de validator; deze wordt **bij elk gebruik geroteerd** (een gestolen
cookie die al gepresenteerd werd, is detecteerbaar en ongeldig gemaakt). Het
herstel decrypteert het IMAP-wachtwoord opnieuw (`CredentialCrypt`) en
herverifieert de credentials - een mislukking verwijdert het token. Elk token
registreert het **IP bij uitgifte** (`ip`), het **laatst bekende IP**
(`last_ip`, bij elke herstel) en de User-Agent - zichtbaar in Instellingen >
Sessies om een onbekende sessie op te merken. De tokens zijn daar opgesomd en
intrekbbaar, en het huidige token wordt ingetrokken bij **handmatige**
uitlog (de inactiviteits-uitlog `autologout` bewaart hem: de sessie wordt
dan stil hersteld bij het volgende bezoek). Elke nieuwe sessie (normale
login of herstel) opent op het laatst gebruikte account - met terugval op
het standaardaccount.

### CSRF

Elke POST vereist een sessie-CSRF-token (`X-CSRF-Token`, formulierveld of
JSON-sleutel); de plugin-API's (`/api/plugin/…`) vereisen het ongeacht de
HTTP-methode. Een tweede laag, gebaseerd op **`Sec-Fetch-*`**-headers, weigert
cross-site requests behalve GET-navigatie naar een document - ook zonder
gebruikersactivatie, want de browser verwijdert `Sec-Fetch-User` na elke
redirect (portalen, herschreven links) (`secfetch_allow` laat deze regels
verruimen).

### HTTP-headers

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, optionele HSTS, en een
**CSP** met nonce per request (de `script-src`-directive omvat `unsafe-eval`,
vereist door Knockout). Inline geserveerde bijlagen krijgen
`Content-Security-Policy: sandbox`.

### Versleuteling van credentials

IMAP-wachtwoorden (en plugin-geheimen) worden versleuteld
**XSalsa20-Poly1305** (`sodium_crypto_secretbox`, libsodium) met de
hoofdsleutel `app/storage/app.key`, in sessie en database. Zie
[base-de-donnees.md](base-de-donnees.md).

### OAuth2-subaccounts

De publieke domeinen (Gmail, Outlook, Yahoo - zie [domaines.md](domaines.md))
worden toegevoegd via **authorization code + PKCE**: de anti-CSRF `state` en
de verifier worden in sessie gehouden, de `redirect_uri` wordt server-side
herconstrueerd (host gefilterd zoals `force_https`). Alleen het *refresh
token* wordt bewaard, versleuteld als een wachtwoord; het vluchtige *access
token* bestaat alleen in geheugen tijdens de verbinding
(XOAUTH2/OAUTHBEARER). De refresh-token-rotatie (Microsoft, Yahoo) wordt bij
elke uitwisseling opnieuw in de database toegepast. De e-mail van de
`id_token` dient als coherentiecontrole; het eigendomsbewijs blijft de
OAuth-IMAP-verbinding zelf. De callback (`/api/oauth/callback`) geeft alleen
een mini-pagina `postMessage`>`window.close()` terug voor de
consent-popup.

De OAuth2-app-credentials zijn **per gebruiker** (tabel `oauth_apps`,
ingevoerd bij de eerste account-toevoeging of in de instellingen): het
`secret` wordt met dezelfde hoofdsleutel versleuteld en nooit naar de
interface teruggestuurd. Een verwijderde of bewerkte app maakt de afhankelijke
accounts ongeldig (het refresh-token blijft gebonden aan de oorspronkelijke
client_id).

### Rate-limit van de login

`login_max_attempts` (5) daarna blokkering `login_lock_seconds` (300 s). De
teller wordt bewaard in `storage/cache/login_attempts.json` (sleutel
`sha256(IP|login)`, purge van entries inactief > 1 u, schrijven onder
`flock`-lock). Een actieve blokkering wordt noch verlengd noch hergelogd door
geweigerde pogingen (429). Geldt ook voor de admin-login. Een
authenticatielog in fail2ban-stijl is beschikbaar (`auth_logging*`,
placeholders `{request:ip}`, `{imap:login}`, `{result}`; CR/LF
geneutraliseerd): het dekt webmail- en admin-logins (login met `admin:`-
prefix) en TOTP-code-fouten. Volledige integratie:
[fail2ban.md](fail2ban.md).

### Sanitizing van bericht-HTML

De bericht-body wordt in de browser gesanitized (inerte DOMParser +
allowlist): beperkte tags en attributen (geen
`script`/`iframe`/`object`/`on*`…), blokkering van de
`javascript:`/`vbscript:`/`data:`-schemes, neutralisatie van verraderlijke
stijlen (`position:fixed`, verborgen inhoud), prefixen van klassen. De CSP is
de tweede laag.

### Verzenden van berichten

- **From**: strikte whitelist - het adres van het account of een gedeclareerde
  identiteit (IDN in beide vormen aanvaard); elke andere waarde wordt
  geweigerd (403).
- **Ontvangers**: de To/Cc/Bcc-velden passeren een strikte parse - een
  ongeldig segment wordt niet stil weggegooid, het verzenden wordt geweigerd
  (400) met de lijst van onbegrepen adressen.
- **CR/LF**: geneutraliseerd in het onderwerp, de threading-headers
  (In-Reply-To, References), de gerefereerde mapnamen en de MIME-parameters
  van bijlagen (`filename=`).
- **Bcc**: de header wordt uit de naar de SMTP-server verzonden data
  verwijderd maar bewaard in de kopie "Verzonden items" (alleen zichtbaar voor
  de afzender).
- **Editor**: het TinyMCE-iframe draagt een `Content-Security-Policy` die
  elke remote-load blokkeert - de inhoud van een doorgestuurd/geplakt bericht
  kan bij opening geen leesbevestiging exfiltreren.
- **Tijdelijke bijlagen**: tokens met gebruikersscope, gesanitizede naam
  (`basename` + controle-tekens verwijderd), purge na 24 u.
- **SMTP-fouten**: de client krijgt een generieke melding; ruwe
  serverantwoorden worden alleen getoond als `labs.smtp_show_server_errors` is
  geactiveerd (diagnose, kan interne details prijsgeven).

### OpenPGP

Ver-/ontsleuteling gebeurt **volledig in de browser** (OpenPGP.js). De server
bewaart alleen de armored sleutelbos; private sleutels worden door hun
passphrase beschermd en nooit server-side ontcijferd. **Alleen de
bericht-body wordt versleuteld**: het verzenden van bijlagen wordt geweigerd
als versleuteling aan staat (ze zouden in klartekst vertrekken).

### Afbeeldingen-proxy

Remote-afbeeldingen passeren `/api/proxy`: alleen `http(s)`, optionele
allowlist (wildcards), **anti-SSRF** (weigering van private/gereserveerde
IP's, hervalideerd bij **elke** redirect - max. 3), timeout, begrensde
grootte. De echte MIME van de body wordt geverifieerd tegen een
**bitmap-allowlist** (png, jpeg, gif, webp, avif, bmp, ico) - SVG en elk
actief type worden geweigerd - en het antwoord draagt
`Content-Security-Policy: sandbox` + `nosniff`. De logs bewaren alleen
scheme+host+pad (nooit de query, die tokens kan bevatten).

### IP- en proxy-beheer

`X-Forwarded-For`/`X-Forwarded-Proto` worden alleen meegenomen als
`REMOTE_ADDR` in `trusted_proxies` (CIDR) staat. De HTTPS-detectie
(`isHttps`) volgt dezelfde regel.

## 2. Hardening-aanbevelingen

- **Document root = `public/`** alleen; nooit op de projectroot wijzen. De
  privémappen (`app/`, `config/`, `storage/`…) hebben hun eigen
  `.htaccess`-bescherming, maar dat is alleen een vangnet.
- **HTTPS verplicht**: activeer `force_https` + `hsts` in productie.
- **Achter een reverse proxy** (Nginx, HAProxy, Cloudflare…): vul
  `trusted_proxies` in om rate-limit, allowlist en logs betrouwbaar te maken;
  zonder wordt elke `X-Forwarded-*` genegeerd.
- **Beperk de admin**: `admin_ip_allowlist` (IP/CIDR CSV) en een
  niet-triviale `admin_path`; activeer de **admin-TOTP** via de plugin
  `two-factor-auth` (het geheim `admin_totp_secret` wordt alleen afgedwongen
  zolang de plugin geactiveerd is).
- **Lokaal admin-account**: liever `admin_user` + `admin_password_hash`
  (sterk wachtwoord) dan de IMAP-fallback voor administratie.
- **Backup `app.key`** buiten de webroot; nooit committen.
- **`debug = false`** in productie (geen interne fouten exposen).
- **De `/setup`-wizard** zit in de kern ingebouwd: hij antwoordt 404 zodra
  het slot `app/storage/INSTALLED` is gezet - geen installatiebestand meer te
  verwijderen in de webroot.
- Controleer `ssl.verify_certificate`: `false` is alleen aanvaardbaar voor
  beheerste self-signed certificaten.
- In onderhoudsmodus serveert `maintenance = true` een toegewijde
  onderhoudspagina in 503 op `/` (ingelogde gebruikers worden erheen
  verplaatst bij hun volgende API-request, sessie behouden) en onderbreekt de
  `/api/*` voor gebruikers (admin en `/api/ping` blijven actief).

## 3. Bekende beperkingen

- De repository `GitHubClient::REPO` is vergrendeld op `WatT-For/MXWebMail`:
  de geinstalleerde pakketten (kern-zip van de release, plugin-bestanden
  gelezen in de repo op ref `update.plugins_ref` via de git-API +
  `raw.githubusercontent.com`, elk geverifieerd op git-blob-sha1) komen
  alleen van die repository - geen configureerbare willekeurige URL. Het
  kernpakket wordt op twee niveaus geverifieerd: sha256-som van het asset
  (`<zip>.sha256`-bestand van de release) daarna `files.sha256` (ingebouwd
  per-bestand-manifest, gecontroleerd na uitpakken); de constanten
  `APP_REQ_PHP`/`APP_REQ_FROM` van het `App.php` van het pakket declareren de
  vereisten (PHP-versie, minimale bronversie) die vóór het kopiëren worden
  afgedwongen. De officiële repo is **publiek**: de API, de assets en de
  `raw`'s werken zonder token (het anonieme API-quota van GitHub wordt gedekt
  door de `cache.update_ttl`-cache). Een optionele, in `config.php`
  geconfigureerde GitHub-PAT (`Contents: Read`-toegang tot de repo) kan het
  quota verhogen of een private fork bedienen via `update.repo` - assets
  worden dan geserveerd via het endpoint `releases/assets/<id>`
  (`browser_download_url` geeft 404 op een private repo, ook geauthenticeerd).
  `update.plugins_local` en `update.manifest_url` (alternatief
  release-manifest) zijn voorbehouden aan dev/tests - alleen aanpasbaar via
  `config.php`, nooit via de admin-API. Tijdens het kopiëren onderbreekt de
  flag `storage/update/applying` de gebruikers-API (503, vervalt na 15 min).
- De wissel SQLite > MariaDB migreert de gegevens niet (leeg schema).
- De OpenPGP-private-sleutels worden armored server-side bewaard (de
  passphrase beschermt de sleutel zelf, geen extra inkapseling).
