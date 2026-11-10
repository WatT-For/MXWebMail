# Bezpieczeństwo

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

## 1. Wbudowany model bezpieczeństwa

### "Zapamiętaj logowanie" (tokeny trwałe)

Checkbox "Zapamiętaj logowanie" emituje cookie `mxwm_remember`
(`selector:validator`, HttpOnly, Secure, SameSite=Lax, czas `remember_days`
- domyślnie 30 dni). Tabela `auth_tokens` przechowuje tylko **hash SHA-256**
walidatora; ten jest **rotowany przy każdym użyciu** (skradzione cookie już
prezentowane jest wykrywalne i unieważniane). Przywrócenie odszyfrowuje
ponownie hasło IMAP (`CredentialCrypt`) i ponownie weryfikuje poświadczenia -
niepowodzenie usuwa token. Każdy token rejestruje **IP przy wydaniu** (`ip`),
**ostatnie znane IP** (`last_ip`, przy każdym przywróceniu) i User-Agent -
widoczne w Ustawienia > Sesje by wykryć nieznaną sesję. Tokeny są tam
wymieniane i odwoływalne, a bieżący token jest odwoływany przy **ręcznym**
wylogowaniu (wylogowanie z nieaktywności `autologout` go zachowuje: sesja
jest przywracana cicho przy następnej wizycie).

### CSRF

Każde POST wymaga tokenu CSRF sesji (`X-CSRF-Token`, pole formularza lub
klucz JSON); API wtyczek (`/api/plugin/…`) wymagają go niezależnie od metody
HTTP. Druga warstwa, oparta na nagłówkach **`Sec-Fetch-*`**, odrzuca
żądania cross-site poza nawigacją GET do dokumentu - nawet bez aktywacji
użytkownika, bo przeglądarka usuwa `Sec-Fetch-User` po każdym
przekierowaniu (portale, przepisane linki) (`secfetch_allow` pozwala
rozszerzyć te reguły).

### Nagłówki HTTP

`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, opcjonalny HSTS, i
**CSP** z nonce per żądanie (dyrektywa `script-src` zawiera `unsafe-eval`,
wymagany przez Knockout). Załączniki serwowane inline dostają
`Content-Security-Policy: sandbox`.

### Szyfrowanie poświadczeń

Hasła IMAP (i sekrety wtyczek) są szyfrowane **XSalsa20-Poly1305**
(`sodium_crypto_secretbox`, libsodium) kluczem głównym
`app/storage/app.key`, w sesji i bazie. Zob.
[base-de-donnees.md](base-de-donnees.md).

### Konta dodatkowe OAuth2

Publiczne domeny (Gmail, Outlook, Yahoo - zob. [domaines.md](domaines.md))
są dodawane przez **authorization code + PKCE**: `state` anti-CSRF i
verifier są trzymane w sesji, `redirect_uri` jest rekonstruowany po stronie
serwera (host filtrowany jak `force_https`). Tylko *refresh token* jest
utrwalany, szyfrowany jak hasło; efemeryczny *access token* istnieje tylko w
pamięci na czas połączenia (XOAUTH2/OAUTHBEARER). Rotacja refresh token
(Microsoft, Yahoo) jest ponownie stosowana w bazie przy każdej wymianie.
Email z `id_token` służy jako kontrola spójności; dowód przynależności
pozostaje samo połączenie OAuth IMAP. Callback (`/api/oauth/callback`)
zwraca tylko mini-stronę `postMessage`>`window.close()` dla popupu
zgody.

Poświadczenia aplikacji OAuth2 są **per użytkownik** (tabela `oauth_apps`,
wprowadzane przy pierwszym dodaniu konta lub w ustawieniach): `secret` jest
szyfrowany tym samym kluczem głównym i nigdy nie zwracany do interfejsu.
Usunięta lub edytowana aplikacja unieważnia zależne od niej konta (refresh
token pozostaje związany z pierwotnym client_id).

### Rate-limit logowania

`login_max_attempts` (5) potem blokada `login_lock_seconds` (300 s).
Licznik jest utrwalany w `storage/cache/login_attempts.json` (klucz
`sha256(IP|login)`, czyszczenie wpisów nieaktywnych > 1 h, zapis pod lockiem
`flock`). Aktywna blokada nie jest ani przedłużana ani re-logowana przez
odrzucane próby (429). Stosuje się też do loginu admina. Dziennik
uwierzytelniania w stylu fail2ban jest dostępny (`auth_logging*`,
placeholdery `{request:ip}`, `{imap:login}`, `{result}`; CR/LF
zneutralizowane): pokrywa loginy webmail i admin (login z prefiksem
`admin:`) oraz niepowodzenia kodu TOTP. Pełna integracja:
[fail2ban.md](fail2ban.md).

### Sanityzacja HTML wiadomości

Body wiadomości jest sanityzowane w przeglądarce (inertny DOMParser +
allowlist): ograniczone tagi i atrybuty (bez
`script`/`iframe`/`object`/`on*`…), blokada schematów
`javascript:`/`vbscript:`/`data:`, neutralizacja podstępnych stylów
(`position:fixed`, ukryta zawartość), prefiksowanie klas. CSP jest drugą
warstwą.

### Wysyłanie wiadomości

- **From**: ścisła whitelist - adres konta lub zadeklarowana tożsamość (IDN
  akceptowany w obu formach); każda inna wartość jest odrzucana (403).
- **Odbiorcy**: pola To/Cc/Bcc przechodzą ścisły parse - nieprawidłowy
  segment nie jest cicho odrzucany, wysyłka jest odrzucana (400) z listą
  niezrozumianych adresów.
- **CR/LF**: neutralizowane w temacie, nagłówkach threading (In-Reply-To,
  References), referencjonowanych nazwach folderów i parametrach MIME
  załączników (`filename=`).
- **Bcc**: nagłówek jest usuwany z danych wysyłanych do serwera SMTP, ale
  zachowany w kopii "Elementy wysłane" (widoczny tylko dla nadawcy).
- **Edytor**: iframe TinyMCE niesie `Content-Security-Policy` blokującą
  każde zdalne ładowanie - zawartość przesłanej/wklejonej wiadomości nie
  może wyciec potwierdzenia odczytu przy otwarciu.
- **Tymczasowe załączniki**: tokeny o zakresie użytkownika, nazwa
  sanityzowana (`basename` + znaki kontrolne usunięte), czyszczenie po 24 h.
- **Błędy SMTP**: klient dostaje generyczny komunikat; surowe odpowiedzi
  serwera są eksponowane tylko jeśli `labs.smtp_show_server_errors` jest
  włączone (diagnostyka, może ujawnić detale wewnętrzne).

### OpenPGP

Szyfrowanie/deszyfrowanie dzieje się **całkowicie w przeglądarce**
(OpenPGP.js). Serwer przechowuje tylko armored brelok; klucze prywatne są
chronione swoją passphrase i nigdy nie są deszyfrowane po stronie serwera.
**Tylko body wiadomości jest szyfrowane**: wysyłka załączników jest
odrzucana gdy szyfrowanie jest włączone (wychodziłyby wprost).

### Proxy obrazów

Zdalne obrazy przechodzą przez `/api/proxy`: tylko `http(s)`, opcjonalna
allowlist (wildcards), **anti-SSRF** (odrzucenie prywatnych/zarezerwowanych
IP, rewalidowane przy **każdym** przekierowaniu - maks. 3), timeout,
ograniczony rozmiar. Rzeczywisty MIME body jest weryfikowany wobec
**allowlist bitmap** (png, jpeg, gif, webp, avif, bmp, ico) - SVG i każdy
aktywny typ są odrzucane - a odpowiedź niesie
`Content-Security-Policy: sandbox` + `nosniff`. Logi zachowują tylko
scheme+host+ścieżkę (nigdy query, która może zawierać tokeny).

### Zarządzanie IP i proxy

`X-Forwarded-For`/`X-Forwarded-Proto` są brane pod uwagę tylko jeśli
`REMOTE_ADDR` figuruje w `trusted_proxies` (CIDR). Detekcja HTTPS
(`isHttps`) idzie za tą samą regułą.

## 2. Zalecenia hardeningu

- **Document root = `public/`** tylko; nigdy nie kieruj na katalog główny
  projektu. Prywatne foldery (`app/`, `config/`, `storage/`…) mają swoje
  `.htaccess` ochronne, ale to tylko siatka bezpieczeństwa.
- **HTTPS obowiązkowy**: włącz `force_https` + `hsts` w produkcji.
- **Za reverse proxy** (Nginx, HAProxy, Cloudflare…): wypełnij
  `trusted_proxies` by uczynić wiarygodnymi rate-limit, allowlist i logi;
  bez tego każdy `X-Forwarded-*` jest ignorowany.
- **Ogranicz admina**: `admin_ip_allowlist` (IP/CIDR CSV) i nietrywialny
  `admin_path`; włącz **TOTP admina** przez wtyczkę `two-factor-auth`
  (sekret `admin_totp_secret` jest egzekwowany tylko gdy wtyczka jest
  aktywna).
- **Lokalne konto admina**: wolę `admin_user` + `admin_password_hash`
  (silne hasło) niż fallback IMAP do administracji.
- **Kopia `app.key`** poza webroot; nigdy nie wersjonuj go.
- **`debug = false`** w produkcji (nie eksponuj błędów wewnętrznych).
- **Kreator `/setup`** jest wbudowany w rdzeń: odpowiada 404 gdy blokada
  `app/storage/INSTALLED` jest ustawiona - żaden plik instalacyjny do
  usunięcia w webroot.
- Sprawdź `ssl.verify_certificate`: `false` jest akceptowalne tylko dla
  kontrolowanych certyfikatów self-signed.
- W trybie konserwacji `maintenance = true` serwuje dedykowaną stronę
  konserwacji w 503 na `/` (zalogowani użytkownicy przenoszeni przy
  kolejnym żądaniu API, sesja zachowana) i przecina `/api/*` użytkownika
  (admin i `/api/ping` pozostają aktywne).

## 3. Znane ograniczenia

- Repozytorium `GitHubClient::REPO` jest zaryglowane na `WatT-For/MXWebMail`:
  instalowane pakiety (zip rdzenia release, pliki wtyczek czytane w repo na
  ref `update.plugins_ref` przez API git + `raw.githubusercontent.com`,
  każdy weryfikowany po sha1 bloba git) pochodzą tylko z tego repozytorium -
  żaden arbitralny URL konfigurowalny. Pakiet rdzenia jest weryfikowany na
  dwóch poziomach: suma sha256 assetu (plik `<zip>.sha256` release) potem
  `files.sha256` (osadzony manifest per-plik, kontrolowany po wypakowaniu);
  stałe `APP_REQ_PHP`/`APP_REQ_FROM` `App.php` pakietu deklarują wymagania
  (wersja PHP, minimalna wersja źródłowa) stosowane przed kopiowaniem.
  Dopóki repo pozostaje **prywatne**, wymagany jest PAT GitHub (dostęp
  `Contents: Read` do repo) skonfigurowany w `config.php`: API, assety
  (serwowane przez endpoint `releases/assets/<id>` -
  `browser_download_url` zwraca 404 na prywatnym repo nawet
  uwierzytelniony) i `raw` od niego zależą. `update.plugins_local` i
  `update.manifest_url` (alternatywny manifest release) są zarezerwowane
  dla dev/testów - zmienialne tylko przez `config.php`, nigdy przez API
  admina. Podczas kopiowania plików flaga `storage/update/applying`
  przecina API użytkownika (503, wygasa po 15 min).
- Zmiana SQLite > MariaDB nie migruje danych (czysty schemat).
- Klucze prywatne OpenPGP są przechowywane armored po stronie serwera
  (passphrase chroni sam klucz, bez dodatkowej enkapsulacji).
