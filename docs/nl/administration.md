# Beheer

*Vertaald uit het Frans - de Franse versie is leidend.*

Het beheerpaneel is bereikbaar op `/{admin_path}` (standaard `/admin`). Het
is een **globaal** beheer (multi-domein); er zijn geen fijne rollen.

## 1. Admin-authenticatie

Twee modi, afhankelijk van de configuratie:

1. **Lokaal account** (`admin_user` + `admin_password_hash` in `config.php`):
   login/wachtwoord, eventueel TOTP (`admin_totp_secret`). De TOTP wordt
   geprovisioneerd en afgedwongen door de plugin `two-factor-auth`
   (subtab "Tweefactorauthenticatie" van *Beveiliging*, met QR-code) -
   plugin uit = niet vereist bij login.
2. **IMAP-fallback** (zonder lokaal account): een via IMAP geauthenticeerde
   gebruiker met het veld `is_admin = 1` opent de admin. Dat recht wordt bij
   elke request hervalideerd (een gedegradeerd/geblokkeerd/verwijderd account
   wordt onmiddellijk geweigerd).

De login-rate-limit geldt ook voor de admin, evenals `admin_ip_allowlist`
indien ingevuld. Een IMAP-admin kan zichzelf niet degraderen, blokkeren of
verwijderen.

## 2. Paneelsecties

Het paneel bestaat uit tabs (hash `#/<pagina>[/<subtab>]`).

### Algemeen

`app_name`, `admin_path`, `language` (lijst van geinstalleerde talen +
`auto`), `session_lifetime`, `remember_days` (duur van de "Ingelogd
blijven"-tokens), `maintenance`, `update.channel`. Beheert ook de **favicon**
(upload ≤ 512 KB: ico/png/svg/jpg/webp, inhoud gevalideerd). De
**onderhoudsmodus** toont een toegewijde pagina (503) op `/`: ingelogde
gebruikers worden erheen verplaatst bij hun volgende API-request (sessie
behouden) en hij herlaadt zelf aan het einde; de admin (`/{admin_path}` +
`/api/admin/*`) blijft volledig bereikbaar.

### Database

Driver SQLite of MariaDB/MySQL, host/poort/naam/gebruiker/wachtwoord. Een
test (`/api/admin/test-db`) is verplicht vóór het schrijven: de server
valideert de verbinding en maakt de database zonodig aan. Zie
[base-de-donnees.md](base-de-donnees.md).

De kaart **Backup** downloadt een zip-archief (`config.php`, `app.key`,
SQLite-databases, `storage/branding`) - **gevoelig** (credentials +
versleutelingssleutel), op een veilige plek bewaren. Een MariaDB-database
wordt niet gedekt: `mysqldump` server-side.

### Functionaliteit

Subtabs *Functionaliteit* en *Standaards*:

- afbeeldingen-proxy + allowlist, bijlaggroottes;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- alle `user_defaults.*`-instellingen (gebruikersstandaards).

### Performance

Subtabs *HTTP* en *Servercache*:

- `http.*`: gzip-compressie (activering, niveau, minimumgrootte),
  `http.etag`, TTL van thema/plugin-assets, favicon en afbeeldingen-proxy;
- `cache.*`: applicatie-bestandscache (activering, TTL van domeindetectie en
  update-check, purge-interval).

Detail van de sleutels in [configuration.md](configuration.md) (secties
*HTTP* en *Applicatiecache*).

### Beveiliging

Subtabs *Account*, *Tweefactorauthenticatie* (als de plugin `two-factor-auth`
geactiveerd is), *Toegang*, *Headers*, *TLS*:

- lokaal admin-account (`admin_user`) en **wijziging van het
  admin-wachtwoord** - de twee velden invullen dan *Opslaan*: het wachtwoord
  wordt alleen gewijzigd als ze ingevuld en identiek zijn;
- admin-**TOTP** (subtab van de plugin `two-factor-auth`): secret genereren,
  te scannen QR-code, bevestigingscode verplicht vóór activering (het secret
  wordt na opslag nooit meer getoond);
- `admin_ip_allowlist` (knop "mijn IP toevoegen"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- volledige `ssl.*`-sectie (uitgaande certificaten).

### Logs

Subtabs *Viewer* en *Instellingen*:

- viewer van de logs `app.log`, `mail.log` en het auth-log (laatste 200
  regels), met knop "Legen" om het geselecteerde bestand te trunceren;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domeinen

Mailserverconfiguratie per domein - zie [domaines.md](domaines.md). De tab
*Instellingen* groepeert de resolutie (`default_domain`, `restrict_domains`,
`autodetect.*`). Voor de "alleen subaccount + OAuth-provider"-domeinen
(Gmail, Outlook, Yahoo) autoriseert de admin alleen de provider (`oauth`-
optie van het domeinmodaal): de OAuth2-app-credentials worden door elke
gebruiker geregistreerd (*Instellingen > OAuth2-applicaties*).

### Gebruikers

Lijst + zoeken naar gebruikers, met verversknop (icoon) om de lijst te
herladen. Per gebruiker (modaal, tabs *Profiel*, *Instellingen*,
*Accounts/Identiteiten*, *Sessies*):

- `display_name`, `is_admin`, `disabled`;
- effectieve gebruikersinstellingen (bewerken + reset - de reset herstelt de
  `user_defaults` maar **behoudt** de interne sleutels `plugin.*` en
  `user_folders`, bijv. 2FA-secret, herkomst van mappen);
- IMAP-subaccounts (verwijderen, behalve het standaardaccount);
- identiteiten (aanmaken/bewerken/verwijderen, standaardidentiteit);
- tab **Sessies**: "Ingelogd blijven"-tokens opgesomd en intrekbaar;
  **Afmelding afdwingen** trekt de tokens in en doodt actieve sessies
  (session_epoch verhoogd, weigering bij de volgende request).

Het verwijderen van een gebruiker is cascaderend en verwijdert ook zijn
contactdatabase (`storage/db/users/<id>.sqlite`).

### Extensies (plugins)

Elke plugin wordt onder zijn **weergavenaam** opgesomd (`label`-veld van het
manifest, vertaald - de technische naam `name` blijft ernaast in `code`
getoond).

Activeren/deactiveren van elke plugin + **configuratie**-modaal gegenereerd
uit `config.schema.json` (types `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). De kolom **Gebruikerstoegang**
beperkt een geactiveerde plugin tot het admin-oppervlak (pagina
`/{admin_path}`, `/api/admin/*`, admin-sessies): hij wordt inert voor de
gebruikers - zie [plugins.md](plugins.md). Standaard uitgevinkt (expliciet te
autoriseren); afwezig voor plugins die alleen `admin.*`-hooks declareren
(geen gebruikersoppervlak).

**Installatie / update vanaf GitHub**: de plugins worden rechtstreeks in de
vergrendelde repository gelezen (`GitHubClient::REPO`, dezelfde als de
kern-updater) op ref `update.plugins_ref` - `'release'` = tag van de laatste
release standaard, `'main'` in dev (een push van `plugin.json` publiceert de
update, geen release vereist). Elk via `raw.githubusercontent.com`
gedownload bestand wordt op git-blob-sha1 geverifieerd.
De catalogus wordt geladen bij het openen van de tab (cache gedeeld met de
kern-check) en is te verversen via de knop **verversen** rechtsboven; de
niet-geinstalleerde catalogus-entries staan onderaan de plugintabel:

- knop **Bijwerken** op geinstalleerde plugins als een nieuwere versie is
  gepubliceerd (de oude map wordt naar
  `storage/update/plugins/backup-<name>-<ts>` opgeslagen - een enkele
  bewaard, automatische rollback bij mislukking);
- knop **Installeren** op afwezige catalogus-entries - een net
  geinstalleerde plugin is standaard **uitgeschakeld**;
- knop **Deinstalleren**: **definitieve** verwijdering van de map en de
  configuratie (`plugins`-rij gepurged), zonder backup;
- `min_app`/`max_app` van de remote `plugin.json`: een met `APP_VERSION`
  incompatibele entry wordt *incompatibel* gemarkeerd en zijn installatie
  geweigerd.

### Webmail-migratie (RainLoop / SnappyMail)

Als de plugin `webmail-migration` geactiveerd is, verschijnt een pagina
**Webmail-migratie** in het zijmenu (voor *Over*): hij migreert de gegevens
van een `data/_data_/_default_/`-map van een **RainLoop- of
SnappyMail**-installatie, opgegeven via **serverpad** of **ZIP-upload**
(extractie in `storage/tmp`, gepurged via de toegewijde knop of na 2 u).

- **Analyseren**: inventaris zonder schrijven (domeinen `*.ini`/`*.json`,
  gevonden accounts, volumes contacten/identiteiten/sleutels/instellingen
  per gebruiker);
- **Import starten**: idempotente import - ontbrekende domeinen, contacten
  (adresboeken per account + globaal + PDO-DSN gedeclareerd in
  `application.ini`, frequenties en e-mailtypes behouden, verwijderde
  contacten uitgesloten), identiteiten (standaard = lege `Id`), bekende
  gebruikersinstellingen (berichten per pagina, verversing, contacten
  auto-opslaan, systeemmappen, thema als het bestaat) en armored
  PGP-sleutels. Opnieuw uitvoerbaar zonder duplicaten.

Alleen accounts die overeenkomen met een **bestaande gebruiker** worden
geimporteerd; de andere staan in het rapport. De wachtwoorden van extra
accounts zijn niet herstelbaar (versleuteld door de bron-webmail) - handmatig
opnieuw aanmaken. De Sieve-filters leven op de mailserver: niets te
importeren.

Gebruikerszijde biedt de tab *Instellingen > Contacten* ook een **import**
`.vcf`/`.csv`/`.sqlite` (`AddressBook.sqlite`-adresboek RainLoop/SnappyMail -
in een multi-user-adresboek worden alleen de contacten van het account
overgenomen) en een `.vcf`-**export**, wat elk ander adresboek dekt, ook een
manuele SnappyMail-export.

### Thema's

- **standaardthema** (schrijft `user_defaults.theme`);
- **toestemming** per thema (`allowed`): een gebruiker kan alleen een
  toegestaan thema kiezen (de standaard blijft altijd selecteerbaar);
- **configuratie**-modaal van het thema (dezelfde types + `color`);
- weergave van de overervingsketen (`extends`).

Zie [themes.md](themes.md).

### Over

Versie (`APP_VERSION`), PHP/SAPI/limieten, driver en grootte van de database,
paden, geladen extensies. Een link naar de officiele website wordt
in de navbar getoond. De kopkaart integreert ook de **updates** via GitHub
Releases (controle, download, toepassing, herstel):

- *Controleren* > *Downloaden* > *Toepassen* > automatische afronding van de
  migraties; statussen bewaard in `storage/update/state.json`;
- bij het uitpakken wordt `files.sha256` (per-bestand-manifest van het
  pakket) geverifieerd, worden de pakketvereisten
  (`APP_REQ_PHP`/`APP_REQ_FROM` in zijn `App.php`: PHP-versie, minimale
  bronversie) gecontroleerd, en worden lokaal gewijzigde bestanden vóór
  toepassing gemeld;
- tijdens het kopieren antwoordt de gebruikers-API 503 (flag
  `storage/update/applying`, onderhoudspagina aan de webkant - vervalt na
  15 min bij mislukking);
- elk overschreven bestand wordt in `storage/update/backup-<ts>` bewaard met
  `manifest.json` (versie, `schema_version`, lijsten); onder SQLite wordt de
  database (`webmail.sqlite` + `db/users/*.sqlite`) na WAL-checkpoint
  gekopieerd - onder MariaDB blijft de externe backup (`mysqldump`) op de
  admin (waarschuwing vóór toepassing);
- *Herstellen* (rollback): herstelt de bestanden van de laatste backup,
  verwijdert de aangemaakte bestanden en kopieert de SQLite-databases terug;
  onder MariaDB, weigering als het schema gevorderd is (herstel de dump en
  bevestig daarna de forcing);
- de banner "schema nieuwer dan de code" meldt een bestands-rollback zonder
  databaseherstel;
- actie *Herinitialiseren* om een gedownload/uitgepakt pakket te laten
  vallen.

> De officiele repository is vergrendeld in `GitHubClient::REPO`: de check is
> uitgeschakeld zolang die niet ingevuld is. `update.channel` blijft hier
> bewerkbaar; `update.repo` wordt handmatig in `config.php` ingesteld.

## 3. Schrijven van `config.php`

De admin schrijft `config.php` **per sectie**, atomair (tijdelijk bestand +
`rename`, `chmod 0600`), en behoudt de niet-geleverde sleutels. Als
`app/config/` niet beschrijfbaar is, geeft de interface dat aan
(`saved_readonly`) en worden wijzigingen getoond maar niet bewaard.

## 4. De verbinding testen

In *Domeinen* valideert **Verbinding testen** het protocol van de actieve tab
van het modaal (IMAP, SMTP of Sieve - de andere tabs testen IMAP), en
**Detecteren** vult de velden zonder op te slaan. De detectie
(`DomainDetector`) werkt onafhankelijk van `autodetect.enabled`. Een domein
handmatig opslaan wist de "auto-gedetecteerd"-markering (`options.detected`).
