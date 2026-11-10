# Administracja

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Panel administracyjny jest dostępny pod `/{admin_path}` (domyślnie
`/admin`). To administracja **globalna** (multi-domenowa); nie ma
szczegółowych ról.

## 1. Uwierzytelnienie admina

Dwa tryby, zależnie od konfiguracji:

1. **Konto lokalne** (`admin_user` + `admin_password_hash` w `config.php`):
   login/hasło, ewentualnie TOTP (`admin_totp_secret`). TOTP jest
   provisionowany i egzekwowany przez wtyczkę `two-factor-auth`
   (pod-zakładka "Uwierzytelnienie dwuskładnikowe" sekcji *Bezpieczeństwo*,
   z kodem QR) - wtyczka wyłączona = niewymagany przy loginie.
2. **Fallback IMAP** (bez konta lokalnego): użytkownik uwierzytelniony
   przez IMAP z polem `is_admin = 1` otwiera admina. To prawo jest
   rewalidowane przy każdym żądaniu (zdegradowane/zablokowane/usunięte
   konto jest natychmiast odrzucane).

Rate-limit logowania stosuje się też do admina, jak i `admin_ip_allowlist`
jeśli wypełniona. Admin IMAP nie może zdegradować, zablokować ani usunąć
siebie.

## 2. Sekcje panelu

Panel składa się z zakładek (hash `#/<strona>[/<pod-zakładka>]`).

### Ogólne

`app_name`, `admin_path`, `language` (lista zainstalowanych języków +
`auto`), `session_lifetime`, `remember_days` (czas życia tokenów
"Zapamiętaj logowanie"), `maintenance`, `update.channel`. Zarządza też
**favicon** (upload ≤ 512 KB: ico/png/svg/jpg/webp, zawartość walidowana).
Tryb **konserwacji** pokazuje dedykowaną stronę (503) na `/`: zalogowani
użytkownicy są przenoszeni na nią przy kolejnym żądaniu API (sesja
zachowana) i przeładowuje się sama na końcu; admin (`/{admin_path}` +
`/api/admin/*`) pozostaje w pełni dostępny.

### Baza danych

Sterownik SQLite lub MariaDB/MySQL, host/port/nazwa/użytkownik/hasło. Test
(`/api/admin/test-db`) jest obowiązkowy przed zapisem: serwer waliduje
połączenie i tworzy bazę w razie potrzeby. Zob.
[base-de-donnees.md](base-de-donnees.md).

Karta **Kopia zapasowa** pobiera archiwum zip (`config.php`, `app.key`,
bazy SQLite, `storage/branding`) - **wrażliwe** (poświadczenia + klucz
szyfrowania), przechowywać w bezpiecznym miejscu. Baza MariaDB nie jest
pokryta: `mysqldump` po stronie serwera.

### Funkcjonalności

Pod-zakładki *Funkcjonalności* i *Domyślne*:

- proxy obrazów + allowlist, rozmiary załączników;
- `contacts_enable` / `contacts_suggestions_limit`;
- `allow_additional_accounts` / `allow_additional_identities`,
  `popup_identity`, `dangerous_actions`;
- wszystkie ustawienia `user_defaults.*` (domyślne użytkowników).

### Wydajność

Pod-zakładki *HTTP* i *Cache serwera*:

- `http.*`: kompresja gzip (włączenie, poziom, minimalny rozmiar),
  `http.etag`, TTL assetów motywu/wtyczek, favicona i proxy obrazów;
- `cache.*`: plikowy cache aplikacyjny (włączenie, TTL detekcji domeny i
  weryfikacji aktualizacji, interwał czyszczenia).

Szczegóły kluczy w [configuration.md](configuration.md) (sekcje *HTTP* i
*Cache aplikacyjne*).

### Bezpieczeństwo

Pod-zakładki *Konto*, *Uwierzytelnienie dwuskładnikowe* (jeśli wtyczka
`two-factor-auth` jest aktywna), *Dostęp*, *Nagłówki*, *TLS*:

- lokalne konto admina (`admin_user`) i **zmiana hasła** admina - wypełnij
  oba pola potem *Zapisz*: hasło jest zmieniane tylko jeśli są wypełnione
  i identyczne;
- **TOTP** admina (pod-zakładka wtyczki `two-factor-auth`): generacja
  sekretu, kod QR do zeskanowania, obowiązkowy kod potwierdzenia przed
  włączeniem (sekret nigdy nie jest pokazywany po zapisie);
- `admin_ip_allowlist` (przycisk "dodaj moje IP"), `login_max_attempts` /
  `login_lock_seconds`, `trusted_proxies`;
- `force_https`, `hsts`, `cookie_samesite`, `csp_extra`, `secfetch_allow`;
- pełna sekcja `ssl.*` (certyfikaty wychodzące).

### Logi

Pod-zakładki *Przeglądarka* i *Parametry*:

- przeglądarka logów `app.log`, `mail.log` i dziennika auth (ostatnie 200
  wierszy), z przyciskiem "Wyczyść" do obcięcia wybranego pliku;
- `debug`, `logs.*`, `auth_logging*`, `labs.smtp_show_server_errors`.

### Domeny

Konfiguracja serwerów poczty per domena - zob. [domaines.md](domaines.md).
Zakładka *Parametry* grupuje rozdzielczość (`default_domain`,
`restrict_domains`, `autodetect.*`). Dla domen "tylko konto dodatkowe +
dostawca OAuth" (Gmail, Outlook, Yahoo) admin autoryzuje tylko dostawcę
(opcja `oauth` modala domeny): poświadczenia aplikacji OAuth2 rejestruje
każdy użytkownik (*Ustawienia > Aplikacje OAuth2*).

### Użytkownicy

Lista + wyszukiwanie użytkowników, z przyciskiem odświeżania (ikona) do
przeładowania listy. Per użytkownik (modal, zakładki *Profil*,
*Ustawienia*, *Konta/Tożsamości*, *Sesje*):

- `display_name`, `is_admin`, `disabled`;
- efektywne ustawienia użytkownika (edycja + reset - reset przywraca
  `user_defaults` ale **zachowuje** klucze wewnętrzne `plugin.*` i
  `user_folders`, np. sekret 2FA, pochodzenie folderów);
- konta IMAP dodatkowe (usunięcie, poza kontem domyślnym);
- tożsamości (tworzenie/edycja/usuwanie, tożsamość domyślna);
- zakładka **Sesje**: tokeny "Zapamiętaj logowanie" wymienione i
  odwoływalne; **Wymuś wylogowanie** odwołuje tokeny i zabija aktywne
  sesje (session_epoch inkrementowany, odrzucenie przy kolejnym żądaniu).

Usunięcie użytkownika jest kaskadowe i usuwa też jego bazę kontaktów
(`storage/db/users/<id>.sqlite`).

### Rozszerzenia (wtyczki)

Każda wtyczka jest wymieniona pod swoją **wyświetlaną nazwą** (pole
`label` manifestu, tłumaczone - techniczna nazwa `name` pozostaje
wyświetlona obok w `code`).

Włączanie/wyłączanie każdej wtyczki + modal **konfiguracji** generowany z
`config.schema.json` (typy `string`, `text`, `integer`, `number`,
`password`, `bool`, `enum`, `color`, `list`). Kolumna **Dostęp
użytkowników** ogranicza aktywną wtyczkę do powierzchni admina (strona
`/{admin_path}`, `/api/admin/*`, sesje admina): staje się obojętna dla
użytkowników - zob. [plugins.md](plugins.md). Domyślnie odznaczona (do
autoryzacji explicytne); nieobecna dla wtyczek deklarujących tylko hooki
`admin.*` (brak powierzchni użytkownika).

**Instalacja / aktualizacja z GitHub**: wtyczki są czytane wprost w
zaryglowanym repozytorium (`GitHubClient::REPO`, to samo co updater
rdzenia) na ref `update.plugins_ref` - `'release'` = tag ostatniej release
domyślnie, `'main'` w dev (push `plugin.json` publikuje aktualizację, żadna
release niewymagana). Każdy plik pobrany przez `raw.githubusercontent.com`
jest weryfikowany po sha1 bloba git.
Katalog jest ładowany przy otwarciu zakładki (cache współdzielony z
checkiem rdzenia) i odświeżalny przyciskiem **odśwież** w prawym górnym
rogu; niezainstalowane wpisy katalogu są wymienione na końcu tabeli
wtyczek:

- przycisk **Aktualizuj** na zainstalowanych wtyczkach gdy nowsza wersja
  jest opublikowana (stary folder jest zapisywany do
  `storage/update/plugins/backup-<name>-<ts>` - jeden zachowany,
  automatyczny rollback przy niepowodzeniu);
- przycisk **Zainstaluj** na nieobecnych wpisach katalogu - świeżo
  zainstalowana wtyczka jest **wyłączona** domyślnie;
- przycisk **Odinstaluj**: **definitywne** usunięcie folderu i
  konfiguracji (wiersz `plugins` wyczyszczony), bez backupu;
- `min_app`/`max_app` zdalnego `plugin.json`: wpis niekompatybilny z
  `APP_VERSION` jest oznaczony *niekompatybilny* a jego instalacja
  odrzucana.

### Migracja webmail (RainLoop / SnappyMail)

Jeśli wtyczka `webmail-migration` jest aktywna, strona **Migracja webmail**
pojawia się w menu bocznym (przed *Informacje*): migruje dane folderu
`data/_data_/_default_/` instalacji **RainLoop lub SnappyMail**, wskazanego
przez **ścieżkę serwera** lub **upload ZIP** (wypakowanie w `storage/tmp`,
czyszczone przyciskiem dedykowanym lub po 2 h).

- **Analizuj**: inwentarz bez zapisu (domeny `*.ini`/`*.json`, znalezione
  konta, wolumeny kontaktów/tożsamości/kluczy/ustawień per użytkownik);
- **Uruchom import**: idempotentny import - brakujące domeny, kontakty
  (książki per konto + globalna + DSN PDO zadeklarowana w
  `application.ini`, częstotliwości i typy emaili zachowane, usunięte
  kontakty wykluczone), tożsamości (domyślna = pusta `Id`), znane
  ustawienia użytkownika (wiadomości per strona, odświeżanie, autozapis
  kontaktów, foldery systemowe, motyw jeśli istnieje) i armored klucze
  PGP. Powtarzalny bez duplikatów.

Tylko konta odpowiadające **istniejącemu użytkownikowi** są importowane;
inne są wymienione w raporcie. Hasła kont dodatkowych nie są odzyskiwalne
(szyfrowane przez źródłowy webmail) - do odtworzenia ręcznie. Filtry Sieve
żyją na serwerze poczty: nic do importowania.

Po stronie użytkownika, zakładka *Ustawienia > Kontakty* oferuje też
**import** `.vcf`/`.csv`/`.sqlite` (książka `AddressBook.sqlite`
RainLoop/SnappyMail - w książce multi-użytkownikowej tylko kontakty konta
są pobierane) i **eksport** `.vcf`, pokrywający każdą inną książkę,
włącznie ręcznym eksportem SnappyMail.

### Motywy

- motyw **domyślny** (pisze `user_defaults.theme`);
- **autoryzacja** per motyw (`allowed`): użytkownik może wybrać tylko
  autoryzowany motyw (domyślny pozostaje zawsze wybieralny);
- modal **konfiguracji** motywu (te same typy + `color`);
- wyświetlanie łańcucha dziedziczenia (`extends`).

Zob. [themes.md](themes.md).

### Informacje

Wersja (`APP_VERSION`), PHP/SAPI/limity, sterownik i rozmiar bazy,
ścieżki, załadowane rozszerzenia. Link do oficjalnej strony
jest wyświetlany w navbar. Karta nagłówka integruje też **aktualizacje**
przez GitHub Releases (weryfikacja, pobieranie, zastosowanie,
przywrócenie):

- *Sprawdź* > *Pobierz* > *Zastosuj* > automatyczna finalizacja migracji;
  stany zachowane w `storage/update/state.json`;
- przy wypakowaniu `files.sha256` (manifest per-plik pakietu) jest
  weryfikowany, wymagania pakietu (`APP_REQ_PHP`/`APP_REQ_FROM` w jego
  `App.php`: wersja PHP, minimalna wersja źródłowa) są kontrolowane, a
  lokalnie zmodyfikowane pliki są sygnalizowane przed zastosowaniem;
- podczas kopiowania API użytkownika odpowiada 503 (flaga
  `storage/update/applying`, strona konserwacji po stronie web - wygasa
  po 15 min przy niepowodzeniu);
- każdy nadpisany plik jest zapisywany w `storage/update/backup-<ts>` z
  `manifest.json` (wersja, `schema_version`, listy); pod SQLite baza
  (`webmail.sqlite` + `db/users/*.sqlite`) jest kopiowana po checkpoint
  WAL - pod MariaDB zewnętrzna kopia (`mysqldump`) pozostaje na adminie
  (ostrzeżenie przed zastosowaniem);
- *Przywróć* (rollback): przywraca pliki ostatniego backupu, usuwa
  utworzone pliki i przywraca bazy SQLite; pod MariaDB odrzucenie jeśli
  schemat uległ awansowi (przywróć dump potem potwierdź wymuszenie);
- banner "schemat nowszy niż kod" sygnalizuje rollback plików bez
  przywrócenia bazy;
- akcja *Reinicjalizuj* by porzucić pobrany/wypakowany pakiet.

> Oficjalne repozytorium jest zaryglowane w `GitHubClient::REPO`:
> weryfikacja jest wyłączona dopóki nie jest wypełnione. `update.channel`
> pozostaje edytowalny tutaj; `update.repo` ustawia się ręcznie w
> `config.php`.

## 3. Zapis `config.php`

Admin pisze `config.php` **per sekcja**, atomowo (plik tymczasowy +
`rename`, `chmod 0600`), zachowując niedostarczone klucze. Jeśli
`app/config/` nie jest zapisywalny, interfejs to wskazuje (`saved_readonly`)
i zmiany są pokazywane ale nie trwałe.

## 4. Test połączenia

W *Domeny*, **Testuj połączenie** waliduje protokół aktywnej zakładki
modala (IMAP, SMTP lub Sieve - inne zakładki testują IMAP), a **Wykryj**
wypełnia pola bez zapisu. Detekcja (`DomainDetector`) działa niezależnie
od `autodetect.enabled`. Ręczny zapis domeny usuwa marker
"auto-wykryty" (`options.detected`).
