# Rozwiązywanie problemów / FAQ

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

## Instalacja

**Wszystkie URL przekierowują do `/setup/`**
Normalne: blokada `app/storage/INSTALLED` nie istnieje. Dokończ
instalację, lub utwórz plik ręcznie po umieszczeniu `config.php`.

**Kreator pokazuje "config/ niezapisywalny"**
Nieblokujące ostrzeżenie: skopiuj wyświetloną zawartość do
`app/config/config.php` ręcznie, potem uruchom ponownie instalację.
Sprawdź też, że `app/storage/` jest zapisywalny (blokujące).

**Test połączenia MariaDB nie przechodzi**
Sprawdź host/port/poświadczenia, i że użytkownik ma prawa do tworzenia
bazy (`CREATE DATABASE`). Charset musi być `utf8mb4`.

## Połączenie / login

**Żaden użytkownik nie może się zalogować po instalacji**
Przy `restrict_domains = true` (domyślnie), potrzebna jest **przynajmniej
jedna nazwana domena** w tabeli (wildcard `*` jest ignorowany dla tej
weryfikacji). Instalator próbuje ją wykryć; inaczej utwórz ją w *Domeny*.

**Login odrzucony bez powodu (konto zablokowane)**
- Za dużo prób: odczekaj `login_lock_seconds` (domyślnie 300 s), lub
  wyczyść `app/storage/cache/login_attempts.json`.
- Wtyczki `black-list`/`white-list` mogą odrzucać login (404/403 lub błąd
  generyczny według `auth_error`).
- Użytkownik `disabled` jest odrzucany nawet z ważną sesją.

**Login admina nie działa**
- Konto lokalne (`admin_user`): sprawdź `admin_password_hash` i
  ewentualne TOTP.
- Fallback IMAP: użytkownik musi mieć `is_admin = 1`.
- `admin_ip_allowlist` może blokować twoje IP.

## Serwery poczty

**Nie można połączyć się z serwerem IMAP/SMTP**
Użyj przycisku **Testuj połączenie** w *Domeny* (wynik per protokół).
Częste przyczyny: niespójny port/szyfrowanie (993/ssl vs 143/tls),
certyfikat self-signed (zob. `ssl.verify_certificate`/`ssl.allow_self_signed`),
lub firewall.

**Certyfikat self-signed odrzucony**
`ssl.verify_certificate = false` lub `ssl.allow_self_signed = true`
(używać tylko z pełną świadomością).

**Domena nie jest wykrywana automatycznie**
`autodetect.enabled` jest domyślnie wyłączone. Włącz go, lub użyj
przycisku **Wykryj** strony *Domeny*.

**"Oznacz jako spam" nie filtruje kolejnych maili**
Przycisk przenosi wiadomość do folderu Niechciane (lub ustawia keyword
IMAP `$Junk` jeśli serwer nie ma folderu Junk) - niczego nie uczy
serwera. Filtry Sieve działają tylko przy dostarczaniu: szablon
*Anti-spam* kreatora (Ustawienia > Filtry) sortuje maile oznaczone
`X-Spam-Flag: YES` przez MTA. Automatyczne uczenie (spam/ham) wymaga
konfiguracji po stronie serwera - Dovecot IMAPSieve (skrypt
report-spam/ham wywoływany kopią do/z Junk, co wywołuje MOVE aplikacji)
lub rspamd/sa-learn.

## Bezpieczeństwo / HTTPS

**Strona pozostaje na HTTP za reverse proxy**
Wypełnij `trusted_proxies` (IP proxy) by `X-Forwarded-Proto` było
honorowane, potem włącz `force_https` / `hsts`.

**Rate-limit lub allowlist nie widzą prawdziwego IP**
Ta sama przyczyna: bez `trusted_proxies`, `REMOTE_ADDR` jest używany jak
jest.

**Favicon się nie wyświetla**
Wgrany favicon musi mieć ≤ 512 KB (ico/png/svg/jpg/webp); inaczej
pozostaje domyślna ikona.

## Dane / logi

**Utrata `app.key`**
Wszystkie przechowane poświadczenia stają się nierozszyfrowalne:
użytkownicy muszą się zalogować ponownie. Zawsze rób kopię `app.key`.

**Gdzie są logi?**
`app/storage/logs/app.log` (aplikacyjny), `mail.log` (protokoły
IMAP/SMTP/Sieve, jeśli `logs.enabled`), i dziennik auth (`auth_logging`).
Widoczne w adminie (*Logi*). Integracja fail2ban:
[fail2ban.md](fail2ban.md).

**Przejście na MariaDB opróżniło dane**
Oczekiwane zachowanie: zmiana `db_dsn` startuje na czystym schemacie
(pliki kontaktów per użytkownik są zachowane). Nie ma narzędzia migracji
danych.

## Aktualizacje

**"Błąd: Nie znaleziono na GitHub … prywatne repo lub brakujący zasób"**
Oficjalne repozytorium jest zaryglowane (`GitHubClient::REPO` =
`WatT-For/MXWebMail`) i **publiczne**: token nie jest wymagany. GitHub
zwraca 404 - nie 403 - dla brakującego zasobu lub niewystarczającego
dostępu; wiadomość wskazuje winny endpoint (`releases/latest`, `commits/…`
lub ścieżkę `raw` katalogu wtyczek). Trwały 404 oznacza zwykle brakujący
zasób (żadna release opublikowana, nieznany `update.plugins_ref`).
Przypadek "prywatne repo" dotyczy tylko forka skonfigurowanego przez
`update.repo` + PAT w `config.php`.

**HTTP 404 na `…/releases/download/…` (katalog wtyczek lub pobieranie aktualizacji)**
Na publicznym oficjalnym repo `browser_download_url` działa. Przypadek ma
sens tylko dla prywatnego forka przez `update.repo`: `browser_download_url`
zwraca tam 404 nawet z ważnym tokenem - naprawione od 0.36.1, assety są
wtedy pobierane przez endpoint API `releases/assets/<id>` gdy PAT jest
skonfigurowany w `config.php`.

**Strona odpowiada 503 "update" / strona konserwacji podczas aktualizacji**
Normalne: flaga `app/storage/update/applying` przecina API użytkownika
podczas kopiowania plików (nigdy mieszanka starego/nowego kodu). Jest
zdejmowana na końcu; jeśli zostanie po awarii, wygasa sama po 15 min -
lub usuń `app/storage/update/applying` ręcznie.

**"Migracje oczekujące" po aktualizacji**
Finalizacja (`/api/admin/update/finalize`) nie zadziałała: przycisk
*Sfinalizuj* w *Informacje*, lub w CLI `php app/bin/post-update.php`.
Bez akcji migracje i tak się zastosują przy kolejnym żądaniu (leniwa
migracja), ale lepiej je kontrolować.

**Cofnięcie po aktualizacji (rollback)**
Przycisk *Przywróć* w *Informacje*: przywraca pliki z
`app/storage/update/backup-<ts>` (3 ostatnie backupy są zachowane),
usuwa utworzone pliki i przywraca zapisane bazy SQLite. Pod **MariaDB**
migracje nie są odwracalne: przywróć najpierw bazę z twojego `mysqldump`,
potem uruchom ponownie rollback (proponowane jest potwierdzenie
wymuszenia). Aby zacząć od zera: przeinstaluj pakiet źródłowy i przywróć
`app/storage/` (włącznie `app.key` i `db/`).

**Banner "schemat bazy nowszy niż kod"**
`meta.schema_ahead`: zainstalowany kod jest starszy niż schemat
(rollback plików bez przywrócenia bazy, lub starszy pakiet wdrożony).
Przywróć bazę lub zastosuj ponownie odpowiadającą wersję; migracje
pozostają zawieszone dopóki istnieje różnica.

## Różne

**"Prześlij tak jak jest" (bounce): czy odbiorca widzi maila jako spam?**
Możliwe: przesyłka łamie SPF/DMARC po stronie końcowej (serwer wysyłki
nie odpowiada już oryginalnemu nadawcy) - normalne zachowanie bounce,
nagłówki `Resent-*` dokumentują retransmisję. By zachować uwierzytelnienie,
preferuj klasyczne przekierowanie.

**Pod Nginx, czy prywatne foldery są dostępne?**
Nie, jeśli `root` wskazuje na `public/` i blok `location ~ /\.` +
`^/(app|dist)` jest obecny. Nigdy nie kieruj `root` na katalog główny
projektu.

**Błąd 403 "cross_site" przy legalnym żądaniu**
Ochrona `Sec-Fetch-*` odrzuciła żądanie. Jeśli integrujesz legalne
zewnętrzne API, dodaj regułę w `secfetch_allow`.

**Debugowanie błędu wewnętrznego (500)**
Włącz tymczasowo `debug = true` (nigdy w produkcji) by zobaczyć
komunikat, potem sprawdź `app.log`.
