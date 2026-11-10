# Plugins

*Vertaald uit het Frans - de Franse versie is leidend.*

Plugins zijn extensies geconfindeerd in `app/plugins/<name>/`, geladen als ze
geactiveerd zijn (vanuit de admin, tab *Extensies*). Ze registreren zich op
**hooks** van de kern.

> **Alleen-lezen sessie**: de GET-endpoints sluiten de PHP-sessie vroeg
> (`session_write_close`) om de lock vrij te geven. Hooks die vanuit een GET
> worden aangeroepen (`app.data`, `contacts.provider`, `message.meta`,
> `message.view`, `imap.before-connect`, `sieve.before-connect`…) **mogen
> nooit `$_SESSION` schrijven** - die schrijvingen zouden stil verloren
> gaan.

## 0. Distributie via de GitHub-repository

Plugins zijn **niet opgenomen** in de release-zip `mxwebmail-<ver>.zip`: ze
worden rechtstreeks in de repository gelezen op een **configureerbare ref**
(`update.plugins_ref` - `'release'` = tag van de laatste release standaard,
`'main'` of elke git-ref). De catalogus (`PluginPackageService`, tab
*Extensies* - niet-geinstalleerde entries staan onderaan de tabel) wordt
uit de git-API opgebouwd:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` somt de bestanden onder
  `app/plugins/` op met hun **git-blob-sha1** en grootte;
- de `plugin.json` van elke plugin wordt via `raw.githubusercontent.com`
  gelezen (gepind op de commit-sha) - versie, beschrijving,
  `min_app`/`max_app`;
- **Installeren / bijwerken**: elk plugin-bestand wordt via `raw`
  gedownload, geverifieerd (grootte + tree-blob-sha1), daarna `plugin.json`
  gevalideerd (overeenkomende naam, semver-versie, gezonde `main`,
  `APP_VERSION`-compatibiliteit) vóór de map wordt vervangen - een
  geinstalleerde plugin staat standaard **uit**; de oude map gaat naar
  `storage/update/plugins/backup-<name>-<ts>` (een enkele bewaard,
  automatische rollback);
- **Deinstalleren**: definitieve verwijdering van de map + purge van de
  `plugins`-rij (configuratie verloren), zonder backup.

Een plugin-versie publiceren = `version` in zijn `plugin.json` bumpen en
pushen op de branch die `update.plugins_ref` volgt - geen release en geen
asset vereist.

De repository is vergrendeld in `GitHubClient::REPO` (dezelfde constante als
de kern-updater) - geen willekeurige URL wordt aanvaard. `update.channel` en
de release-cache (`cache.update_ttl`) worden met de kern-update-check
gedeeld; de ref>commit wordt op die TTL gecached, de tree en manifesten
worden via hun sha geadresseerd (onveranderlijk). De statische `check`
vereist `version` semver in `plugin.json`.

Voor offline dev/tests dient `update.plugins_local` (absoluut pad van een
plugin-map in `config.php`) als bron: catalogus en installatie via
schijfkopie, zonder remote-verificatie.

Een geactiveerde plugin kan ook **tot het admin-oppervlak beperkt** worden
(kolom *Gebruikerstoegang* van de tab *Extensies*, `user_allowed`-flag in de
tabel `plugins`, **standaard uitgevinkt**): hij wordt dan alleen geladen op
de pagina `/{admin_path}`, de routes `/api/admin/*` en admin-sessies. Elders
is hij volledig inert (geen hook, `/api/plugin/<name>/*` > 404) - ook voor
`is_admin`-gebruikers die niet in het paneel ingelogd zijn. De
admin-login-hooks (`admin.post-login`, `admin.totp-required`, `admin.mounts`)
blijven werken. Een plugin waarvan de in het manifest gedeclareerde hooks
allemaal `admin.*`-hooks zijn, heeft geen gebruikersoppervlak: de kolom
toont dan geen schakelaar voor hem (`-`).

## 1. Plugins van de officiele catalogus

| Plugin | Rol | Hooks |
|---|---|---|
| `add-x-originating-ip-header` | Voegt `X-Originating-IP` (afzender-IP) toe aan verzendingen. | `message.before-send` |
| `anti-spoofing` | Spoofing-detectie in de lezer: inconsistente Return-Path/Reply-To/Sender, display-name die een merk imiteert (homogliefen en confusables gedetecteerd), lokdomein (valse subdomein, typosquat, trans-TLD), SPF/DKIM/DMARC beoordeeld in alignment met het afzenderdomein (verdicten gelezen uit `Authentication-Results`, `Received-SPF` of de SpamAssassin/rspamd-headers) - subdomeinen van beschermde domeinen zijn legitiem. | `imap.message-headers`, `message.view` |
| `avatars` | Afzenderavatar: iconen van bekende diensten, Gravatar, domeinfavicon, initialen. | `app.data` |
| `black-list` | Login-blacklist (wildcards + uitzonderingen) - geverifieerd op het **opgeloste** e-mail (post-mapping). | `auth.login.mapped` |
| `white-list` | Login-whitelist (wildcards + uitzonderingen) - geverifieerd op het **opgeloste** e-mail (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Contactsynchronisatie uit een CardDAV-adresboek (Nextcloud, Radicale, iCloud…) - **HTTPS + publieke host vereist**. | `app.data` |
| `change-password` | Wachtwoordwijziging (drivers poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Kleurlabels op berichten (manueel + automatische regels). | `app.data`, `message.meta` |
| `ics-viewer` | Preview van `.ics`-uitnodigingen/-bijlagen in de lezer **+ iTIP-antwoord** (Aanvaarden/Misschien/Weigeren op `METHOD:REQUEST`, via SMTP naar de organisator verzonden). | `app.data` |
| `imap-contacts-suggestions` | Contactsuggesties uit afzenders van een IMAP-map. | `contacts.provider` |
| `ldap-contacts` | Contactsuggesties uit een LDAP-directory (autocompletie). | `contacts.provider` |
| `ldap-login` | LDAP-resolutie: uid/alias > echt IMAP-adres vóór de login. | `auth.login.mapping` |
| `mailbox-detect` | Detectie van de systeemmappen en aanmaak van ontbrekende. | `app.data` |
| `pwa` | Maakt de webmail installeerbaar (manifest + minimale service worker, iconen). | `app.head`, `app.routes` |
| `webmail-migration` | Admin-importpagina uit een **RainLoop- of SnappyMail**-`data`-map (serverpad of ZIP): domeinen, `AddressBook.sqlite`/PDO-adresboeken, identiteiten, instellingen, systeemmappen, PGP-sleutels - dry-run-scan daarna idempotente import, onbekende accounts genegeerd. | `admin.mounts` |
| `two-factor-auth` | Tweedfactor-TOTP (Google Authenticator, Aegis…) - gebruikersaccounts **en** lokaal admin-account (QR-provisioning in een eigen subtab van Admin > Beveiliging; vereist bij admin-login zolang de plugin geactiveerd is). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Admin-aankondigingsbanner (app-pagina, optioneel login-pagina - onderhoud). Alleen platte tekst. | `app.head` |
| `attachments-clamav` | ClamAV-scan (`clamd`, `zINSTREAM`) van geuploade bijlagen en imports - weigering bij detectie, configureerbaar `on_error`-beleid. | `attachment.upload` |
| `filelink` | Voor SMTP te grote bijlagen verzonden als **vervallende downloadlink** (`/filelink/<token>` - afgedwongen attachment, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Spam/ham-leren bij elke (de)markering: extern commando (`rspamc`, `sa-learn`) of IMAP-kopie naar een dovecot-antispam-map. | `message.spam` |
| `wkd-keys` | Automatische ontdekking van de PGP-sleutels van de ontvangers: **WKD** (advanced + direct), oogst van de **Autocrypt**-headers, optionele `keys.openpgp.org`-fallback - import voorgesteld bij het opstellen. | `app.data`, `message.view` |
| `seasonal-decorations` | Discreet monochroom landschap in de banners volgens de datum (Nieuwjaar, Pasen, 1 april, Halloween, Kerstmis): SVG-objectenbibliotheek via `scenes.json` ({svg, pos, size}) gerecombineerd in `mask-image`-lagen, automatisch gecontrasteerde kleur - instelbare vensters, optioneel buiten sessie, geen animatie. | `app.head` |
| `link-confirmation` | Bevestigingsmodaal vóór het openen van een link uit een mailbody (anti-phishing): echte bestemming getoond, markering van verdachte links (tekst ≠ URL, letterlijk IP, punycode, credentials in de URL), vertrouwde domeinen en "voor dit domein niet meer vragen". | `app.head` |
| `always-cc-bcc` | Systematische Cc- of Bcc-ontvangers bij verzending (compliance-archief), per account of allen - de Bcc blijft onzichtbaar. | `message.before-send` |
| `report-abuse` | "Melden"-knop (actiebalk): stuurt de aangevinkte mails als `.eml`-bijlagen door naar een beheerd abuse-adres, optionele spam-markering. | `app.data` |
| `vcf-viewer` | Preview van `.vcf`-bijlagen in de lezer + een-klik-import in het adresboek (knop geintegreerd in het frontend als `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Verzendquota per account (berichten/uur, berichten/dag, ontvangers/dag) - 429-weigering daarboven. Alleen webmail. | `message.before-send` |
| `login-notify` | Waarschuwingsmail naar het account bij een login vanaf een onbekend IP (Gmail-model) - ongelezen in de inbox gedeponeerd, opt-out per gebruiker. | `auth.post-login`, `app.data` |
| `sso-header` | SSO via authenticatieproxy (vertrouwde gebruikersheader) - vereist gedeclareerde proxy-IP's en een IMAP-masterwachtwoord. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Admin-pagina die de DMARC-(rua)-rapporten van een toegewijde IMAP-box aggregeert - XML/gzip-parsing, aggregaten per domein en bron-IP. | `admin.mounts` |

## 2. Configuratie van een plugin

In de admin (*Extensies* > ⚙-knop) wordt de configuratie uit het
`config.schema.json`-bestand van de plugin gegenereerd. Ondersteunde types:
`string`, `text`, `integer`, `number`, `password`, `bool`, `enum`, `color`,
`list` - `color` rendert een native picker + een `#RRGGBB`-veld (lege waarde
aanvaard: het veld valt terug op de plugin-default). De waarden worden in de
tabel `plugins` opgeslagen (kolom `config`, JSON).

Opmerkelijke configuratiedetails:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (standaard `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimiteit), daarna de
  driver-eigen parameters (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` met
  placeholders `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`,
  `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (`%u`-placeholder), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (`%q`-placeholder), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (een beschermd domein dekt ook
  zijn subdomeinen, bijv. `accountprotection.microsoft.com`), `brands`
  (gedetecteerd in de display-name na normalisatie van accenten en
  confusables - "AppIe TV" wordt als "Apple TV" herkend), en de schakelaars
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  De SPF/DKIM/DMARC-resultaten tellen alleen mee als ze met het
  `From`-domein uitgelijnd zijn: een `dkim=fail` op de handtekening van de
  transporteur of een `spf=fail` op een relayed (SRS-)mailfrom triggert geen
  alert.
- **`two-factor-auth`** - `issuer` (standaard `app_name`), `label` (`email` =
  volledig adres, `local` = deel vóór `@`; `local` voorkomt dat TOTP-apps een
  merkicoon uit het domein afleiden, bijv. Yahoo) en `icon_url`
  (niet-gestandaardiseerde `image=`-parameter, getoond door
  Aegis/andOTP/FreeOTP - genegeerd door Authy; leeg = publieke favicon van de
  instantie). De plugin provisioneert ook de 2FA van het lokale
  admin-account: `admin_totp_secret`-secret in `config.php` (via de
  admin-API `/api/admin/plugin/two-factor-auth/*`), pending in sessie,
  afgedwongen bij de admin-login via de hook `admin.post-login`. De plugin
  deactiveren deactiveert de admin-afdwinging.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  waarden gescheiden door spaties, komma's of puntkomma's), `exceptions`,
  `auth_error` (403 "account niet toegestaan" > generieke 401). Let op de van
  de legacy-plugin geërfde semantiek: voor black-list zijn `exceptions` =
  e-mails van de blacklist die **toegestaan blijven**; voor white-list wordt
  bij lege `exceptions` elke e-mail buiten de whitelist geweigerd, maar als
  die ingevuld is **worden alleen de e-mails van de uitzonderingen
  geweigerd** en passeert al het andere - er geen "extra toegestane"
  adressen in zetten (omgekeerd verwacht effect).

- **`link-confirmation`** - `mode` (`all` = elke externe link, `suspicious` =
  alleen verdachte links), `whitelist` (vertrouwde domeinen, een per regel of
  gescheiden door spatie/komma/puntkomma - subdomeinen inbegrepen),
  `warn_mailto` (ook `mailto:`/`tel:` bevestigen, standaard uit) en
  `remember` (vinkje "voor dit domein niet meer vragen" - in de browser via
  localStorage onthouden, niet server-side).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (bestemmingen, leeg
  = inactief), `senders` (afzender-wildcards, `*` = allen).
- **`report-abuse`** - `abuse_address` (vereist, leeg = inactief),
  `subject_prefix`, `mark_spam` (markeert ook als spam via `message.spam` -
  voedt het leren).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = onbeperkt), `exempt` (wildcards van vrijgestelde accounts). Begrenst
  alleen de webmail: een directe IMAP/SMTP-client omzeilt de quota.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (opt-out-instellingentab per gebruiker).
- **`sso-header`** - `header` (`X-Forwarded-User` of `REMOTE_USER`),
  `trusted_ips` (**vereist, leeg = inerte plugin**), `master_pass`
  (vereist; *same*-modus = `master=yes`-passdb), `master_user` (optioneel:
  `user*master`-login), `allowed_domains`, `hide_form`. De instantie mag
  niet bereikbaar zijn met omzeiling van de proxy (de header zou
  vervalschbaar zijn).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (IMAP-box
  van de rapporten), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Plugin-eigen IMAP-verbinding (de domeinopties gelden
  niet).

Sommige plugins hebben geen admin-schema: hun configuratie is **per
gebruiker** (bijv. `carddav-contacts`, `color-tags`, `two-factor-auth` voor
de accounttoestand).

## 3. Een plugin maken

### Minimale structuur

```
app/plugins/mijn-plugin/
├── plugin.json          # manifest
├── MijnPlugin.php       # hoofdklasse
├── config.schema.json   # (optioneel) admin-configuratieschema
├── langs/<lang>.php     # woordenboeken (sleutels met prefix plugin.<name>.)
└── js/ of assets/       # (optioneel) assets geserveerd via /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "mijn-plugin",
	"label": "plugin.mijn-plugin.label",
	"class": "MijnPlugin",
	"main": "MijnPlugin.php",
	"version": "0.1.0",
	"description": "plugin.mijn-plugin.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (optioneel) is de weergavenaam van de plugin in Admin > Extensies
(i18n-sleutel `plugin.<name>.label` aanbevolen, letterlijke tekst aanvaard;
de technische naam `name` blijft ernaast getoond en dient als identificator).

`min_app`/`max_app` (optioneel, semver `x.y.z`) begrenzen de compatibiliteit
met `APP_VERSION` - installatie of update wordt buiten het bereik geweigerd,
en de catalogus markeert de entry als *incompatibel*.

### Hoofdklasse

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MijnPlugin extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Beschikbare hooks

- **Kern**: `app.routes`, `app.head` (HTML geinjecteerd in de `<head>` van
  `app.php`, `login.php` en `maintenance.php` - de `admin*.php`-views zenden
  het niet uit), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > `{js, plugin, section, tab?,
  label?}`-descriptor gerenderd door `pluginMount` in het admin-paneel -
  zonder `tab`: inline-blok in de `section`-zone, bijv. `security-account`;
  met `tab` + `label`: eigen subtab van de `section`-pagina, bijv.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, uitgegeven bij de login van het lokale
  admin-account - een `TotpRequiredException` werpen vereist een tweede
  factor),
  `admin.totp-required` (`first` > boolean: toont het TOTP-veld op de
  admin-loginpagina).

Elke plugin declareert in `hooks` de hooks waarnaar hij luistert. Een
manifest dat alleen `admin.*`-hooks declareert markeert de plugin als puur
admin - de kolom *Gebruikerstoegang* van de Extensies-tab toont geen
schakelaar voor hem. De kern roept `HookRegistry::run('hook', …)` aan; de
plugin implementeert een gelijknamige methode (of een `on<Hook>` volgens de
kernconventie).

### Generieke API

Elke plugin is via `/api/plugin/<name>/<action>` (GET/POST) aanroepbaar >
methode `api<Action>(Request $r, ?array $user)`. Het CSRF-token is op alle
methoden vereist (de `api.js`-client stuurt het systematisch) - directe
aanroepen zonder `X-CSRF-Token`-header worden met 403 geweigerd.

Admin-zijde, `/api/admin/plugin/<name>/<action>` (GET/POST) > methode
`apiAdmin<Action>(Request $r)`: admin-sessie (`$_SESSION['admin']`) + CSRF
vereist; er is geen `$user` (het lokale admin-account is geen `users`-rij).
Let op: `requireAdmin` sluit de sessie voor schrijven - om er een toestand
op te slaan (bijv. pending secret), heropen die via
`$this->app->startSession()` daarna `session_write_close()`.

### Gebruikerstab

Om een tab in `#/@settings/…` toe te voegen, lever JS geserveerd via
`/assets/plugin/<name>/<file>` en declareer de tab (zie de plugins
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth` als
voorbeelden).

### Vertalingen

Sleutels met prefix `plugin.<name>.` in `langs/<lang>.php`;
`description`/`label`/`help` van manifesten en schema's aanvaarden deze
sleutels (anders pass-through). PHP-kant: `$this->t('sleutel')`, JS-kant:
`ctx.t('sleutel')` in `mount(element, ctx)`.
