# Domini e server mail

*Tradotto dal francese - la versione francese fa fede.*

I server IMAP/SMTP/Sieve si configurano **per dominio** nel pannello admin
(*Domini*). Ogni dominio descrive come raggiungere il server di posta dei suoi
utenti.

## 1. Campi di un dominio

| Campo | Valore |
|---|---|
| `name` | Nome di dominio (es. `example.com`), o `*` per il catch-all. |
| `imap_host` | Host del server IMAP. |
| `imap_port` | Porta IMAP (default `993`). |
| `imap_encryption` | `ssl` (default), `tls` (STARTTLS) o `none`. |
| `smtp_host` | Host del server SMTP (invio). |
| `smtp_port` | Porta SMTP (default `465`). |
| `smtp_encryption` | `ssl` (default), `tls` o `none`. |
| `sieve_host` | Host ManageSieve (opzionale). |
| `sieve_port` | Porta Sieve (default `4190`). |
| `active` | Attivare/disattivare il dominio. |

## 2. Opzioni avanzate (`options`, JSON)

Il campo `options` accetta un oggetto JSON con impostazioni fini per dominio:

- `whitelist` - lista di indirizzi autorizzati per questo dominio;
- `secondary_only` - il dominio è utilizzabile solo come **account
  secondario** (aggiunta di account da un utente già connesso); il login
  primario è rifiutato con "Dominio riservato agli account secondari";
- `oauth` - provider OAuth2 del dominio (`google`, `microsoft`, `yahoo`): gli
  account secondari si aggiungono per consenso del provider (popup), mai per
  password (vedi §8);
- `imap.shortLogin` - troncare il login;
- `imap.lowerLogin` - forzare il login in minuscolo;
- `imap.timeout` - timeout di connessione IMAP;
- `imap.disabled_capabilities` - capability IMAP da disattivare (server
  difettosi). Esempio: `ESORT` per un server che lo annuncia senza
  implementare le opzioni di ritorno RFC 5267 (`RETURN (PARTIAL ...)`) - il
  webmail rileva già questo caso e passa da solo all'ordinamento classico;
  la chiave serve solo se il rilevamento non si applica (cache disattivata);
- `imap.body_text_limit` - dimensione max (byte) dei corpi testo recuperati
  all'apertura di un messaggio (default `0` = illimitato; un tetto ~256 K
  economizza il fetch delle newsletter molto lunghe);
- `imap.message_list_limit` - oltre (default `10000` messaggi nella cartella),
  MailSo evita il SORT server e mette in cache la lista degli UID in
  background;
- `smtp.useAuth` - forzare/disattivare l'autenticazione SMTP;
- `smtp.timeout` - timeout di connessione SMTP;
- `sieve.encryption` - cifratura della connessione Sieve.

## 3. Dominio `*` (catch-all)

Il dominio `*` fornisce i **parametri di default** applicati a ogni dominio
non dichiarato - ma solo quando `restrict_domains = false`: sotto
`restrict_domains`, `*` è ignorato e non può autorizzare un login primario. È
il modo più semplice di servire più domini dietro lo stesso server mail.

## 4. Risoluzione del dominio al login

Al login, il dominio è determinato così:

1. Se l'utente digita un login con `@dominio`, quel dominio è usato.
2. Altrimenti `default_domain` si applica: `auto` (default) = host HTTP senza
   `www.` né porta; un nome fisso; o `''` (nessuno).
3. Se `restrict_domains = true` (default), solo i domini **nominati** presenti
   in tabella sono accettati (`*` e il rilevamento sono ignorati) - **create
   quindi almeno un dominio prima di ogni login**.

## 5. Rilevamento automatico

`autodetect.*` (disattivato di default) permette di scoprire la configurazione
di un dominio sconosciuto al momento del login:

- `enabled`: attiva il rilevamento;
- `use_ispdb`: interroga la base Mozilla (`autoconfig.thunderbird.net`);
- `probe`: sonda `imap.`/`mail.`/`smtp.<dominio>`.

L'ordine di rilevamento è: DNS SRV > autoconfig Mozilla > MX > sondaggio
porte.

Sieve è rilevato qualunque sia la fonte: `_sieve._tcp` (SRV), altrimenti
sondaggio della porta **4190** su `sieve.<dominio>` (se `probe` attivo o via
il pulsante *Rileva*) poi sull'host IMAP trovato - Dovecot serve quasi sempre
ManageSieve sullo stesso host. La cifratura non va configurata: la connessione
passa a STARTTLS automaticamente quando il server la annuncia.

Il pulsante **Rileva** della pagina *Domini* lancia lo stesso rilevamento a
richiesta, indipendentemente da `autodetect.enabled`, e riempie i campi del
dominio.

> L'installer `/setup/` tenta anch'esso questo rilevamento sull'host del
> vhost (e sui suoi genitori) per creare automaticamente il primo dominio.

## 6. Test

Il pulsante **Testa la connessione** valida il protocollo della scheda attiva
del modale (IMAP, SMTP o Sieve; le altre schede testano IMAP). Usatelo dopo
ogni modifica per verificare che credenziali, porte e cifrature siano
corrette prima di lasciare connettere gli utenti.

## 7. Caso comune: un solo server mail

Per un server unico (es. Postfix + Dovecot su `mail.example.com` che serve
`example.com`):

1. Create il dominio `example.com` con `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, ed eventualmente `sieve_host`.
2. Lasciate `default_domain = auto` e `restrict_domains = true`.
3. Gli utenti si connettono con `user@example.com` o semplicemente `user` (il
   dominio è dedotto dall'host HTTP).

Variante multi-dominio: un dominio `*` con `restrict_domains = false` funge da
catch-all - ogni dominio non dichiarato eredita i suoi parametri (vedi §3;
attenzione, il login primario resta allora possibile su tutti i domini -
`secondary_only` può rifiutarlo, vedi §8).

## 8. Domini pubblici come account secondario (Gmail, Outlook, Yahoo)

Gmail, Outlook.com e Yahoo non accettano più l'autenticazione IMAP con semplice
password - l'aggiunta di questi account passa per **OAuth2** (consenso in un
popup, poi XOAUTH2/OAUTHBEARER per IMAP e SMTP).

Le credenziali di applicazione OAuth2 (client ID, secret, tenant Microsoft)
sono **proprie di ogni utente**: l'admin non registra nessuna app - autorizza
solo il provider sul dominio. Ogni utente crea la propria applicazione nella
console sviluppatori del provider.

### Configurazione (admin)

Questi tre domini sono **pre-creati all'installazione** (account secondario +
provider OAuth già impostati). Se li avete eliminati, ricreateli così:

1. **Create il dominio** (*Domini > Aggiungi* o pulsante *Rileva*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` per Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` per Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` per Yahoo.
2. Spuntate **Solo account secondario** (login primario rifiutato) e scegliete
   il provider in **Auth OAuth2**.

### Registrazione dell'applicazione (utente)

Alla prima aggiunta di un account del dominio, il webmail chiede le
credenziali dell'app e le registra per l'utente - gestibili poi in
*Impostazioni > Applicazioni OAuth2* (un'app per provider, riutilizzata per
tutti gli account di quel provider). URI di redirect da dichiarare nella
console: `<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > client OAuth
  Web, scope `https://mail.google.com/`): scope *ristretto* - un'app in modo
  "Testing" è limitata a ~100 utenti di test e i suoi refresh token scadono
  dopo ~7 giorni; la produzione richiede la verifica Google.
- **Microsoft** (Entra > App registrations, `signInAudience` includente gli
  account personali): permessi delegati "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access` implicito.
  Possibile banner "editore non verificato" al consenso (non bloccante).
- **Yahoo** (Developer Console > app OpenID Connect + Mail API, scope
  `openid`, `mail-r`, `mail-w`): l'accesso Mail API richiede una
  **pre-approvazione di Yahoo** (modulo di richiesta + revisione manuale) e
  la propagazione della redirect URI può richiedere ~24 h.

### Funzionamento

- L'utente aggiunge l'account dal menu account o *Impostazioni > Account*:
  inserimento dell'indirizzo > (prima volta: inserimento delle credenziali
  dell'app) > popup di consenso > l'account appare con un badge provider.
  *Impostazioni > Account* permette anche il cambio, l'aggiornamento delle
  credenziali, la promozione ad account principale e l'eliminazione.
- Un account secondario può essere **promosso principale** (stella in
  *Impostazioni > Account*) - il flag `secondary_only` del dominio blocca
  solo il *login* primario, non questa promozione.
- Il *refresh token* è memorizzato cifrato (`accounts.imap_pass`); ogni
  connessione IMAP/SMTP scambia un access token effimero. Microsoft e Yahoo
  **riemettono un refresh token a ogni uso**: è sostituito automaticamente
  nel database.
- Il secret dell'app è cifrato (`oauth_apps.secret`, stesso meccanismo delle
  password) e non è mai rinviato all'interfaccia - "vuoto = invariato" in
  modifica.
- **Modificare o eliminare un'app** rompe gli account associati: il refresh
  token resta legato al client_id d'origine - eliminate e ri-aggiungete
  l'account.
- Nessun ManageSieve presso questi provider: i filtri Sieve non sono
  disponibili sugli account OAuth.

Combinare `secondary_only` sul dominio `*` e domini interni normali riserva il
login primario ai domini elencati lasciando i secondari aperti ovunque - utile
senza `restrict_domains`.
