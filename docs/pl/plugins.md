# Wtyczki

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Wtyczki to rozszerzenia zamknięte w `app/plugins/<name>/`, ładowane gdy są
aktywne (z admina, zakładka *Rozszerzenia*). Rejestrują się na **hookach**
rdzenia.

> **Sesja tylko do odczytu**: endpointy GET zamykają sesję PHP wcześnie
> (`session_write_close`) by zwolnić blokadę. Hooki wywoływane z GET
> (`app.data`, `contacts.provider`, `message.meta`, `message.view`,
> `imap.before-connect`, `sieve.before-connect`…) **nie mogą nigdy pisać
> `$_SESSION`** - te zapisy byłyby cicho utracone.

## 0. Dystrybucja przez repozytorium GitHub

Wtyczki **nie są zawarte** w zipie release `mxwebmail-<ver>.zip`: są
czytane wprost w repozytorium na **konfigurowalnym ref**
(`update.plugins_ref` - `'release'` = tag ostatniej release domyślnie,
`'main'` lub dowolny git-ref). Katalog (`PluginPackageService`, zakładka
*Rozszerzenia* - niezainstalowane wpisy figurują na końcu tabeli) jest
budowany z API git:

- `GET /repos/<repo>/git/trees/<sha>?recursive=1` wymienia pliki pod
  `app/plugins/` z ich **sha1 bloba git** i rozmiarem;
- `plugin.json` każdej wtyczki jest czytany przez `raw.githubusercontent.com`
  (przypięty na sha commita) - wersja, opis, `min_app`/`max_app`;
- **Instaluj / aktualizuj**: każdy plik wtyczki jest pobierany przez `raw`,
  weryfikowany (rozmiar + sha1 bloba tree), potem `plugin.json` walidowany
  (zgadzająca nazwa, wersja semver, zdrowy `main`, kompatybilność
  `APP_VERSION`) przed zastąpieniem folderu - zainstalowana wtyczka jest
  **wyłączona** domyślnie; stary folder idzie do
  `storage/update/plugins/backup-<name>-<ts>` (jeden zachowany,
  automatyczny rollback);
- **Odinstaluj**: definitywne usunięcie folderu + czyszczenie wiersza
  `plugins` (konfiguracja utracona), bez backupu.

Publikacja wersji wtyczki = bump `version` w jej `plugin.json` i push na
gałąź śledzoną przez `update.plugins_ref` - żadna release ani asset
niewymagane.

Repozytorium jest zaryglowane w `GitHubClient::REPO` (ta sama stała co
updater rdzenia) - żaden arbitralny URL nie jest akceptowany.
`update.channel` i cache release (`cache.update_ttl`) są współdzielone z
weryfikacją aktualizacji rdzenia; ref>commit jest cache'owany na ten TTL,
tree i manifesty są adresowane po swoim sha (niezmienne). Statyczny
`check` wymaga `version` semver w `plugin.json`.

Dla dev/testów offline `update.plugins_local` (absolutna ścieżka folderu
wtyczek w `config.php`) służy jako źródło: katalog i instalacja przez kopię
dyskową, bez weryfikacji zdalnej.

Aktywna wtyczka może też być **zarezerwowana dla powierzchni admina**
(kolumna *Dostęp użytkowników* zakładki *Rozszerzenia*, flaga `user_allowed`
w tabeli `plugins`, **domyślnie odznaczona**): jest wtedy ładowana tylko na
stronie `/{admin_path}`, trasach `/api/admin/*` i sesjach admina. Gdzie
indziej jest całkowicie obojętna (żaden hook, `/api/plugin/<name>/*` >
404) - nawet dla użytkowników `is_admin` niezalogowanych do panelu. Hooki
loginu admina (`admin.post-login`, `admin.totp-required`, `admin.mounts`)
nadal działają. Wtyczka, której hooki zadeklarowane w manifeście są
wszystkie hookami `admin.*`, nie ma żadnej powierzchni użytkownika: kolumna
nie pokazuje dla niej przełącznika (`-`).

## 1. Wtyczki oficjalnego katalogu

| Wtyczka | Rola | Hooki |
|---|---|---|
| `add-x-originating-ip-header` | Dodaje `X-Originating-IP` (IP nadawcy) do wysyłek. | `message.before-send` |
| `anti-spoofing` | Wykrywanie spoofingu w czytniku: niespójne Return-Path/Reply-To/Sender, display-name imitujący markę (homoglify i confusables wykrywane), domena przynętowa (podstępna subdomena, typosquat, trans-TLD), SPF/DKIM/DMARC oceniane w wyrównaniu z domeną nadawcy (werdykty czytane z `Authentication-Results`, `Received-SPF` lub nagłówków SpamAssassin/rspamd) - subdomeny chronionych domen są legalne. | `imap.message-headers`, `message.view` |
| `avatars` | Awatar nadawcy: ikony znanych usług, Gravatar, favicon domeny, inicjały. | `app.data` |
| `black-list` | Czarna lista logowania (wildcards + wyjątki) - weryfikowana na **rozwiązanym** emailu (post-mapping). | `auth.login.mapped` |
| `white-list` | Biała lista logowania (wildcards + wyjątki) - weryfikowana na **rozwiązanym** emailu (post-mapping). | `auth.login.mapped` |
| `carddav-contacts` | Synchronizacja kontaktów z książki CardDAV (Nextcloud, Radicale, iCloud…) - **HTTPS + publiczny host wymagane**. | `app.data` |
| `change-password` | Zmiana hasła (sterowniki poppassd, PDO, LDAP, Mailcow). | `password.change`, `app.data` |
| `color-tags` | Kolorowe etykiety na wiadomościach (ręczne + reguły automatyczne). | `app.data`, `message.meta` |
| `ics-viewer` | Podgląd zaproszeń/załączników `.ics` w czytniku **+ odpowiedź iTIP** (Zaakceptuj/Może/Odrzuć na `METHOD:REQUEST`, wysłana przez SMTP do organizatora). | `app.data` |
| `imap-contacts-suggestions` | Sugestie kontaktów z nadawców folderu IMAP. | `contacts.provider` |
| `ldap-contacts` | Sugestie kontaktów z katalogu LDAP (autouzupełnianie). | `contacts.provider` |
| `ldap-login` | Rozdzielczość LDAP: uid/alias > prawdziwy adres IMAP przed loginem. | `auth.login.mapping` |
| `mailbox-detect` | Wykrywanie folderów systemowych i tworzenie brakujących. | `app.data` |
| `pwa` | Czyni webmail instalowalnym (manifest + minimalny service worker, ikony). | `app.head`, `app.routes` |
| `webmail-migration` | Strona admina importu z folderu `data` **RainLoop lub SnappyMail** (ścieżka serwera lub ZIP): domeny, książki `AddressBook.sqlite`/PDO, tożsamości, ustawienia, foldery systemowe, klucze PGP - skan dry-run potem idempotentny import, nieznane konta ignorowane. | `admin.mounts` |
| `two-factor-auth` | Dwuskładnikowe TOTP (Google Authenticator, Aegis…) - konta użytkowników **i** lokalne konto admina (provisioning QR w dedykowanej pod-zakładce Admin > Bezpieczeństwo; wymagany przy loginie admina dopóki wtyczka aktywna). | `auth.post-login`, `app.data`, `admin.mounts`, `admin.post-login`, `admin.totp-required` |
| `announcement` | Banner ogłoszenia administratora (strona app, opcjonalnie strona loginu - konserwacja). Tylko czysty tekst. | `app.head` |
| `attachments-clamav` | Skan ClamAV (`clamd`, `zINSTREAM`) wgranych załączników i importów - odrzucenie przy wykryciu, konfigurowalna polityka `on_error`. | `attachment.upload` |
| `filelink` | Załączniki za duże dla SMTP wysyłane jako **wygasający link pobierania** (`/filelink/<token>` - wymuszony attachment, `nosniff`, noindex). | `app.data`, `app.routes` |
| `spam-learn` | Uczenie spam/ham przy każdym (od)znaczeniu: zewnętrzne polecenie (`rspamc`, `sa-learn`) lub kopia IMAP do folderu dovecot-antispam. | `message.spam` |
| `wkd-keys` | Automatyczne odkrywanie kluczy PGP odbiorców: **WKD** (advanced + direct), zbiory nagłówków **Autocrypt**, opcjonalny fallback `keys.openpgp.org` - import proponowany przy redakcji. | `app.data`, `message.view` |
| `seasonal-decorations` | Dyskretny monochromatyczny krajobraz w bannerach według daty (Nowy Rok, Wielkanoc, 1 kwietnia, Halloween, Boże Narodzenie): biblioteka obiektów SVG przez `scenes.json` ({svg, pos, size}) rekombinowana w warstwy `mask-image`, automatycznie kontrastowany kolor - regulowane okna, opcjonalnie poza sesją, bez animacji. | `app.head` |
| `link-confirmation` | Modal potwierdzenia przed otwarciem linku z ciała maila (anti-phishing): pokazany prawdziwy cel, oznaczanie podejrzanych linków (tekst ≠ URL, literałowe IP, punycode, poświadczenia w URL), zaufane domeny i "nie pytaj więcej dla tej domeny". | `app.head` |
| `always-cc-bcc` | Systematyczni odbiorcy Cc lub Bcc przy wysyłce (archiwum zgodności), per konto lub wszystkie - Bcc pozostaje niewidoczny. | `message.before-send` |
| `report-abuse` | Przycisk "Zgłoś" (pasek akcji): przekazuje zaznaczone maile jako załączniki `.eml` na zarządzany adres abuse, opcjonalne oznaczenie spamu. | `app.data` |
| `vcf-viewer` | Podgląd załączników `.vcf` w czytniku + jednoklikowy import do książki (przycisk zintegrowany z frontendem gdy `features.vcf_viewer`). | `app.data` |
| `send-rate-limit` | Kwota wysyłki per konto (wiadomości/godzinę, wiadomości/dzień, odbiorców/dzień) - odrzucenie 429 powyżej. Tylko webmail. | `message.before-send` |
| `login-notify` | Mail alertu na konto przy logowaniu z nieznanego IP (model Gmail) - zdeponowany nieprzeczytany w skrzynce, opt-out per użytkownik. | `auth.post-login`, `app.data` |
| `sso-header` | SSO przez proxy uwierzytelnienia (zaufany nagłówek użytkownika) - wymaga zadeklarowanych IP proxy i hasła master IMAP. | `app.routes`, `app.head`, `auth.provider`, `imap/smtp/sieve.before-connect` |
| `dmarc-reports` | Strona admina agregująca raporty DMARC (rua) dedykowanej skrzynki IMAP - parsowanie XML/gzip, agregaty per domena i źródłowe IP. | `admin.mounts` |

## 2. Konfiguracja wtyczki

W adminie (*Rozszerzenia* > przycisk ⚙) konfiguracja jest generowana z pliku
`config.schema.json` wtyczki. Wspierane typy: `string`, `text`, `integer`,
`number`, `password`, `bool`, `enum`, `color`, `list` - `color` renderuje
natywny selektor + pole `#RRGGBB` (pusta wartość akceptowana: pole spada na
fallback wtyczki). Wartości są przechowywane w tabeli `plugins` (kolumna
`config`, JSON).

Warte uwagi szczegóły konfiguracji:

- **`change-password`** - `driver` (`poppassd`/`pdo`/`ldap`/`mailcow`),
  `allowed_emails` (domyślnie `*`), `pass_min_length` (10),
  `pass_min_strength` (70), `check_hibp` (k-anonimowość), potem parametry
  własne sterownika (`poppassd_host/port`, `pdo_dsn`/`pdo_sql` z
  placeholderami `:email :oldpass :newpass :domain :username :login_name`,
  `ldap_uri`/`ldap_user_dn_format`,
  `mailcow_api_hostname`/`mailcow_api_token`).
- **`ldap-login`** - `host`, `port` (389), `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `search_filter` (placeholder `%u`), `mail_attr`.
- **`ldap-contacts`** - `host`, `port`, `use_tls`, `bind_dn`/`bind_pass`,
  `base_dn`, `filter` (placeholder `%q`), `name_attr`, `mail_attr`,
  `max_results`.
- **`anti-spoofing`** - `protected_domains` (chroniona domena pokrywa też
  swoje subdomeny, np. `accountprotection.microsoft.com`), `brands`
  (wykrywane w wyświetlanej nazwie po normalizacji akcentów i confusables -
  "AppIe TV" jest rozpoznawane jako "Apple TV"), i przełączniki
  `check_envelope`/`check_reply_to`/`check_sender`/`check_display`/`check_lookalike`/`check_auth_results`.
  Wyniki SPF/DKIM/DMARC są brane pod uwagę tylko gdy wyrównane z domeną
  `From`: `dkim=fail` na sygnaturze przewoźnika lub `spf=fail` na
  relayowanym (SRS-)mailfrom nie wywołuje alarmu.
- **`two-factor-auth`** - `issuer` (domyślnie `app_name`), `label` (`email`
  = pełny adres, `local` = część przed `@`; `local` zapobiega dedukcji
  ikony marki z domeny przez aplikacje TOTP, np. Yahoo) i `icon_url`
  (niestandardowy parametr `image=`, wyświetlany przez
  Aegis/andOTP/FreeOTP - ignorowany przez Authy; puste = publiczny favicon
  instancji). Wtyczka provisionuje też 2FA lokalnego konta admina: sekret
  `admin_totp_secret` w `config.php` (przez API admina
  `/api/admin/plugin/two-factor-auth/*`), pending w sesji, egzekwowany
  przy loginie admina przez hook `admin.post-login`. Wyłączenie wtyczki
  wyłącza egzekwowanie admina.
- **`black-list` / `white-list`** - `black_list`/`white_list` (wildcards;
  wartości oddzielone spacjami, przecinkami lub średnikami), `exceptions`,
  `auth_error` (403 "konto nieautoryzowane" > generyczne 401). Uwaga na
  semantykę odziedziczoną po legacy-wtyczce: dla black-list, `exceptions` =
  emaile z czarnej listy które **pozostają dozwolone**; dla white-list,
  przy pustych `exceptions` każdy email spoza białej listy jest odrzucany,
  ale jeśli jest wypełniona **tylko emaile wyjątków są odrzucane** i
  wszystko inne przechodzi - nie wkładać tam adresów "dodatkowo
  dozwolonych" (odwrotny oczekiwany efekt).

- **`link-confirmation`** - `mode` (`all` = każdy link zewnętrzny,
  `suspicious` = tylko podejrzane linki), `whitelist` (zaufane domeny,
  jedna na wiersz lub oddzielone spacją/przecinkiem/średnikiem -
  subdomeny włącznie), `warn_mailto` (potwierdzać też `mailto:`/`tel:`,
  domyślnie wyłączone) i `remember` (checkbox "nie pytaj więcej dla tej
  domeny" - zapamiętywane w przeglądarce przez localStorage, nie po
  stronie serwera).

- **`always-cc-bcc`** - `mode` (`bcc`/`cc`), `addresses` (cele, puste =
  nieaktywne), `senders` (wildcards nadawców, `*` = wszyscy).
- **`report-abuse`** - `abuse_address` (wymagane, puste = nieaktywne),
  `subject_prefix`, `mark_spam` (oznacza też jako spam przez
  `message.spam` - zasila uczenie).
- **`send-rate-limit`** - `max_msgs_hour`, `max_msgs_day`, `max_rcpts_day`
  (0 = bez limitu), `exempt` (wildcards zwolnionych kont). Ogranicza tylko
  webmail: bezpośredni klient IMAP/SMTP omija kwotę.
- **`login-notify`** - `folder` (`INBOX`), `ignore_private`, `mask_ip`,
  `include_ua`, `user_control` (zakładka Ustawienia opt-out per
  użytkownik).
- **`sso-header`** - `header` (`X-Forwarded-User` lub `REMOTE_USER`),
  `trusted_ips` (**wymagane, puste = wtyczka obojętna**), `master_pass`
  (wymagane; tryb *same* = passdb `master=yes`), `master_user`
  (opcjonalne: login `user*master`), `allowed_domains`, `hide_form`.
  Instancja nie może być osiągalna z ominięciem proxy (nagłówek byłby
  podrabialny).
- **`dmarc-reports`** - `host`/`port`/`encryption`/`user`/`pass` (skrzynka
  IMAP raportów), `folder`, `batch_size`, `delete_processed`,
  `processed_folder`. Własne połączenie IMAP wtyczki (opcje domeny nie
  mają zastosowania).

Niektóre wtyczki nie mają schematu admina: ich konfiguracja jest **per
użytkownik** (np. `carddav-contacts`, `color-tags`, `two-factor-auth` dla
stanu konta).

## 3. Tworzenie wtyczki

### Minimalna struktura

```
app/plugins/moja-wtyczka/
├── plugin.json          # manifest
├── MojaWtyczka.php      # klasa główna
├── config.schema.json   # (opcjonalnie) schemat konfiguracji admina
├── langs/<lang>.php     # słowniki (klucze z prefiksem plugin.<name>.)
└── js/ lub assets/      # (opcjonalnie) assety serwowane przez /assets/plugin/<name>/<file>
```

### `plugin.json`

```json
{
	"name": "moja-wtyczka",
	"label": "plugin.moja-wtyczka.label",
	"class": "MojaWtyczka",
	"main": "MojaWtyczka.php",
	"version": "0.1.0",
	"description": "plugin.moja-wtyczka.description",
	"hooks": ["app.data"],
	"min_app": "0.36.0",
	"max_app": "0.99.0"
}
```

`label` (opcjonalne) to wyświetlana nazwa wtyczki w Admin > Rozszerzenia
(zalecany klucz i18n `plugin.<name>.label`, akceptowany tekst dosłowny;
techniczna nazwa `name` pozostaje wyświetlana obok i służy jako
identyfikator).

`min_app`/`max_app` (opcjonalne, semver `x.y.z`) ograniczają
kompatybilność z `APP_VERSION` - instalacja lub aktualizacja jest
odrzucana poza zakresem, a katalog oznacza wpis *niekompatybilny*.

### Klasa główna

```php
namespace Plugin;

use MXWebMail\Plugin\PluginBase;

class MojaWtyczka extends PluginBase
{
	public function apiStatus(\MXWebMail\Http\Request $r, ?array $user): array
	{
		return ['ok' => true];
	}
}
```

### Dostępne hooki

- **Rdzeń**: `app.routes`, `app.head` (HTML wstrzyknięty do `<head>`
  `app.php`, `login.php` i `maintenance.php` - widoki `admin*.php` go nie
  emitują), `app.data`, `auth.login.mapping`, `auth.login.mapped`,
  `auth.provider`,
  `attachment.upload`, `message.spam`,
  `auth.post-login`, `contacts.provider`, `identities.provider`,
  `password.change`,
  `settings.allowed`, `imap.before-connect`, `imap.message-headers`,
  `sieve.before-connect`,
  `smtp.before-connect`, `message.meta`, `message.view`, `message.before-send`.
- **Admin**: `admin.mounts` (`collect` > deskryptor `{js, plugin, section,
  tab?, label?}` renderowany przez `pluginMount` w panelu admina - bez
  `tab`: blok inline w strefie `section`, np. `security-account`; z `tab`
  + `label`: dedykowana pod-zakładka strony `section`, np.
  `section: 'security', tab: '2fa'`),
  `admin.post-login` (`run`, emitowany przy loginie lokalnego konta
  admina - rzucenie `TotpRequiredException` wymaga drugiego czynnika),
  `admin.totp-required` (`first` > boolean: pokazuje pole TOTP na stronie
  loginu admina).

Każda wtyczka deklaruje w `hooks` hooki które nasłuchuje. Manifest
deklarujący tylko hooki `admin.*` oznacza wtyczkę jako czysto adminową -
kolumna *Dostęp użytkowników* zakładki Rozszerzenia nie pokazuje dla niej
przełącznika. Rdzeń wywołuje `HookRegistry::run('hook', …)`; wtyczka
implementuje metodę o tej samej nazwie (lub `on<Hook>` według konwencji
rdzenia).

### Generyczne API

Każda wtyczka jest wywoływalna przez `/api/plugin/<name>/<action>`
(GET/POST) > metoda `api<Action>(Request $r, ?array $user)`. Token CSRF
jest wymagany na wszystkich metodach (klient `api.js` wysyła go
systematycznie) - bezpośrednie wywołania bez nagłówka `X-CSRF-Token` są
odrzucane w 403.

Po stronie admina, `/api/admin/plugin/<name>/<action>` (GET/POST) > metoda
`apiAdmin<Action>(Request $r)`: sesja admina (`$_SESSION['admin']`) +
CSRF wymagane; nie ma `$user` (lokalne konto admina nie jest wierszem
`users`). Uwaga: `requireAdmin` zamyka sesję w zapisie - by przechować
tam stan (np. oczekujący sekret), otwórz ją ponownie przez
`$this->app->startSession()` potem `session_write_close()`.

### Zakładka użytkownika

Aby dodać zakładkę w `#/@settings/…`, dostarcz JS serwowany przez
`/assets/plugin/<name>/<file>` i zadeklaruj zakładkę (zob. wtyczki
`change-password`, `carddav-contacts`, `color-tags`, `two-factor-auth`
jako przykłady).

### Tłumaczenia

Klucze z prefiksem `plugin.<name>.` w `langs/<lang>.php`;
`description`/`label`/`help` manifestów i schematów akceptują te klucze
(pass-through inaczej). Strona PHP: `$this->t('klucz')`, strona JS:
`ctx.t('klucz')` w `mount(element, ctx)`.
