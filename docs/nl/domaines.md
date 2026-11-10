# Domeinen en mailservers

*Vertaald uit het Frans - de Franse versie is leidend.*

De IMAP/SMTP/Sieve-servers worden **per domein** geconfigureerd in het
admin-paneel (*Domeinen*). Elk domein beschrijft hoe de mailserver van zijn
gebruikers bereikt wordt.

## 1. Velden van een domein

| Veld | Waarde |
|---|---|
| `name` | Domeinnaam (bijv. `example.com`), of `*` voor de catch-all. |
| `imap_host` | Host van de IMAP-server. |
| `imap_port` | IMAP-poort (standaard `993`). |
| `imap_encryption` | `ssl` (standaard), `tls` (STARTTLS) of `none`. |
| `smtp_host` | Host van de SMTP-server (verzenden). |
| `smtp_port` | SMTP-poort (standaard `465`). |
| `smtp_encryption` | `ssl` (standaard), `tls` of `none`. |
| `sieve_host` | ManageSieve-host (optioneel). |
| `sieve_port` | Sieve-poort (standaard `4190`). |
| `active` | Het domein activeren/deactiveren. |

## 2. Geavanceerde opties (`options`, JSON)

Het veld `options` aanvaardt een JSON-object met fijne instellingen per
domein:

- `whitelist` - lijst van toegestane adressen voor dit domein;
- `secondary_only` - het domein is alleen bruikbaar als **subaccount**
  (account-toevoeging vanuit een al ingelogde gebruiker); de primaire login
  wordt geweigerd met "Domein voorbehouden aan subaccounts";
- `oauth` - OAuth2-provider van het domein (`google`, `microsoft`, `yahoo`):
  subaccounts worden via provider-consent toegevoegd (popup), nooit met een
  wachtwoord (zie §8);
- `imap.shortLogin` - de login inkorten;
- `imap.lowerLogin` - de login naar kleine letters forceren;
- `imap.timeout` - IMAP-verbindings-timeout;
- `imap.disabled_capabilities` - uit te schakelen IMAP-capabilities
  (defecte servers). Voorbeeld: `ESORT` voor een server die het adverteert
  zonder de RFC 5267-return-opties te implementeren (`RETURN (PARTIAL …)`) -
  de webmail detecteert dat geval al en schakelt zelf over naar klassiek
  sorteren; de sleutel dient alleen als de detectie niet geldt (cache uit);
- `imap.body_text_limit` - maximale grootte (bytes) van de opgehaalde
  tekstbodies bij het openen van een bericht (standaard `0` = onbeperkt; een
  plafond ~256 K bespaart de fetch van zeer lange nieuwsbrieven);
- `imap.message_list_limit` - daarboven (standaard `10000` berichten in de
  map) vermijdt MailSo de server-SORT en cachet de UID-lijst op de
  achtergrond;
- `smtp.useAuth` - SMTP-authenticatie forceren/uitschakelen;
- `smtp.timeout` - SMTP-verbindings-timeout;
- `sieve.encryption` - versleuteling van de Sieve-verbinding.

## 3. `*`-domein (catch-all)

Het `*`-domein levert de **standaardparameters** die op elk niet-gedeclareerd
domein worden toegepast - maar alleen als `restrict_domains = false`: onder
`restrict_domains` wordt `*` genegeerd en kan het geen primaire login
autoriseren. Het is de eenvoudigste manier om meerdere domeinen achter
dezelfde mailserver te bedienen.

## 4. Domeinresolutie bij login

Bij de login wordt het domein zo bepaald:

1. Als de gebruiker een login met `@domein` typt, wordt dat domein gebruikt.
2. Anders geldt `default_domain`: `auto` (standaard) = HTTP-host zonder
   `www.` en zonder poort; een vaste naam; of `''` (geen).
3. Als `restrict_domains = true` (standaard), worden alleen de **benoemde**
   domeinen in de tabel aanvaard (`*` en detectie worden genegeerd) - **maak
   dus minstens een domein aan vóór elke login**.

## 5. Automatische detectie

`autodetect.*` (standaard uit) laat toe de configuratie van een onbekend
domein bij de login te ontdekken:

- `enabled`: activeert de detectie;
- `use_ispdb`: raadpleegt de Mozilla-database (`autoconfig.thunderbird.net`);
- `probe`: sondeert `imap.`/`mail.`/`smtp.<domein>`.

De detectievolgorde is: DNS SRV > Mozilla-autoconfig > MX > poortsondering.

Sieve wordt gedetecteerd wat de bron ook is: `_sieve._tcp` (SRV), anders
sondering van poort **4190** op `sieve.<domein>` (als `probe` actief is of
via de knop *Detecteren*) daarna op de gevonden IMAP-host - Dovecot serveert
ManageSieve vrijwel altijd op dezelfde host. De versleuteling hoeft niet
geconfigureerd: de verbinding schakelt automatisch naar STARTTLS als de
server het adverteert.

De knop **Detecteren** van de pagina *Domeinen* lanceert dezelfde detectie op
aanvraag, onafhankelijk van `autodetect.enabled`, en vult de domeinvelden.

> De installer `/setup/` probeert deze detectie ook op de vhost-host (en zijn
> ouders) om het eerste domein automatisch aan te maken.

## 6. Test

De knop **Verbinding testen** valideert het protocol van de actieve tab van
het modaal (IMAP, SMTP of Sieve; de andere tabs testen IMAP). Gebruik het na
elke wijziging om te controleren dat credentials, poorten en versleuteling
correct zijn voordat gebruikers inloggen.

## 7. Gangbaar geval: een enkele mailserver

Voor een enkele server (bijv. Postfix + Dovecot op `mail.example.com` die
`example.com` bedient):

1. Maak het domein `example.com` met `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, en eventueel `sieve_host`.
2. Laat `default_domain = auto` en `restrict_domains = true`.
3. Gebruikers loggen in met `user@example.com` of gewoon `user` (het domein
   wordt uit de HTTP-host afgeleid).

Multi-domein-variant: een `*`-domein met `restrict_domains = false` dient als
catch-all - elk niet-gedeclareerd domein erft zijn parameters (zie §3; let
op, de primaire login blijft dan op alle domeinen mogelijk - `secondary_only`
kan hem weigeren, zie §8).

## 8. Publieke domeinen als subaccount (Gmail, Outlook, Yahoo)

Gmail, Outlook.com en Yahoo aanvaarden geen IMAP-authenticatie met een gewoon
wachtwoord meer - het toevoegen van deze accounts gebeurt via **OAuth2**
(consent in een popup, daarna XOAUTH2/OAUTHBEARER voor IMAP en SMTP).

De OAuth2-app-credentials (client ID, secret, Microsoft-tenant) zijn
**eigen aan elke gebruiker**: de admin registreert geen app - hij
autoriseert alleen de provider op het domein. Elke gebruiker maakt zijn
eigen applicatie in de developer-console van de provider.

### Configuratie (admin)

Deze drie domeinen worden **bij de installatie vooraf aangemaakt**
(subaccount + OAuth-provider al ingesteld). Heeft u ze verwijderd, maak ze
dan zo opnieuw aan:

1. **Maak het domein** (*Domeinen > Toevoegen* of knop *Detecteren*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` voor Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` voor Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` voor Yahoo.
2. Vink **Alleen subaccount** (primaire login geweigerd) aan en kies de
   provider bij **OAuth2-auth**.

### App-registratie (gebruiker)

Bij de eerste toevoeging van een account van het domein vraagt de webmail de
app-credentials en registreert ze voor de gebruiker - daarna beheerbaar in
*Instellingen > OAuth2-applicaties* (een app per provider, hergebruikt voor
alle accounts van die provider). Redirect-URI om in de console te declareren:
`<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > OAuth
  Web-client, scope `https://mail.google.com/`): scope *restricted* - een app
  in "Testing"-modus is beperkt tot ~100 testgebruikers en zijn
  refresh-tokens verlopen na ~7 dagen; productie vereist Google-verificatie.
- **Microsoft** (Entra > App registrations, `signInAudience` inclusief
  persoonlijke accounts): gedelegeerde permissies "Office 365 Exchange
  Online" `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access`
  impliciet. Mogelijke "unverified publisher"-banner bij het consent (niet
  blokkerend).
- **Yahoo** (Developer Console > OpenID Connect + Mail API-app, scopes
  `openid`, `mail-r`, `mail-w`): de Mail API-toegang vereist een
  **voorafgaande goedkeuring van Yahoo** (aanvraagformulier + manuele review)
  en de propagatie van de redirect-URI kan ~24 u duren.

### Werking

- De gebruiker voegt het account toe via het accountmenu of *Instellingen >
  Accounts*: adres invoeren > (eerste keer: app-credentials invoeren) >
  consent-popup > het account verschijnt met een provider-badge.
  *Instellingen > Accounts* laat ook wisselen, credentials bijwerken,
  promotie tot hoofdaccount en verwijdering toe.
- Een subaccount kan **tot hoofdaccount gepromoveerd** worden (ster in
  *Instellingen > Accounts*) - de `secondary_only`-flag van het domein
  blokkeert alleen de *primaire login*, niet deze promotie.
- Het *refresh token* wordt versleuteld bewaard (`accounts.imap_pass`); elke
  IMAP/SMTP-verbinding wisselt een vluchtig access-token uit. Microsoft en
  Yahoo **geven bij elk gebruik een refresh-token opnieuw uit**: het wordt
  automatisch in de database vervangen.
- Het app-secret wordt versleuteld (`oauth_apps.secret`, zelfde mechanisme
  als de wachtwoorden) en nooit naar de interface teruggestuurd - "leeg =
  ongewijzigd" bij bewerking.
- **Een app wijzigen of verwijderen** breekt de gekoppelde accounts: het
  refresh-token blijft aan de oorspronkelijke client_id gebonden - het
  account verwijderen en opnieuw toevoegen.
- Geen ManageSieve bij deze providers: de Sieve-filters zijn niet
  beschikbaar op OAuth-accounts.

`secondary_only` op het `*`-domein combineren met normale interne domeinen
reserveert de primaire login voor de opgesomde domeinen terwijl de
subaccounts overal open blijven - nuttig zonder `restrict_domains`.
