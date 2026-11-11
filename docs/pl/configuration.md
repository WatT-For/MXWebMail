# Referencja konfiguracji

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Plik `app/config/config.php` definiuje tylko klucze **nadpisujące**
wartości domyślne (`app/config/config.example.php`, ładowany przez
`Config.php`). Skopiuj `config.example.php` do `config.php` i dostosuj.
Panel administracyjny edytuje większość tych kluczy per sekcja; tylko
niektóre wymagają ręcznej edycji (`update.*` przede wszystkim, ale też
`import_max_size`, `smime_ca_path`, `logs.max_size` i `logs.keep`).

Tablice asocjacyjne są głęboko scalane; listy są zastępowane.

## Przechowywanie

| Klucz | Domyślnie | Rola |
|---|---|---|
| `db_dsn` | `sqlite` | `sqlite` (> `storage/db/webmail.sqlite`) lub pełny DSN PDO (`mysql:host=…;dbname=…;charset=utf8mb4`). |
| `db_user` | `''` | Użytkownik PDO (MariaDB). |
| `db_pass` | `''` | Hasło PDO (MariaDB). |

## Aplikacja

| Klucz | Domyślnie | Rola |
|---|---|---|
| `app_name` | `MXWebMail` | Wyświetlana nazwa (tytuł, PWA, TOTP). |
| `favicon` | `''` | Plik favicon wgrany do `storage/branding`, serwowany przez `/favicon.ico`; puste = domyślna ikona. |
| `website_url` | `https://mxrelay.fr/webmail/` | Link do oficjalnej strony (przycisk admin); puste = ukryty. |
| `sponsor_url` | `https://mxrelay.fr/webmail/don` | Link "Sponsor" do strony darowizn (przycisk admin + ustawienia webmail); puste = ukryty. |
| `debug` | `false` | Zwraca błędy wewnętrzne klientom API (nigdy w produkcji). |
| `language` | `auto` | `auto` = `Accept-Language` przeglądarki; inaczej kod zainstalowanego języka (`fr`, `en`…). Ostateczny fallback `en`. |

## Domeny

| Klucz | Domyślnie | Rola |
|---|---|---|
| `default_domain` | `auto` | Sufiks loginu wpisanego bez `@domena`: `auto` = host HTTP (bez `www.` i portu), `''` = brak, lub stała domena. |
| `restrict_domains` | `true` | Tylko nazwane domeny z tabeli `domains` są akceptowane przy loginie (ani `*` ani detekcja nie ma zastosowania). |
| `autodetect.enabled` | `false` | Automatyczna detekcja konfiguracji nieznanej domeny przy loginie. |
| `autodetect.use_ispdb` | `false` | Fallback na bazę Mozilla (`autoconfig.thunderbird.net`). |
| `autodetect.probe` | `false` | Sondowanie portów `imap.`/`mail.`/`smtp.`/`sieve.<domena>`. |

Serwery IMAP/SMTP/Sieve konfiguruje się **per domena** w adminie (zob.
[domaines.md](domaines.md)). Domena `*` służy jako parametry catch-all.

## Bezpieczeństwo

| Klucz | Domyślnie | Rola |
|---|---|---|
| `admin_path` | `admin` | Segment URL panelu admina. |
| `admin_user` | `''` | Lokalne konto admina; puste = fallback na użytkownika `is_admin` weryfikowanego przez IMAP. |
| `admin_password_hash` | `''` | `password_hash('hasło', PASSWORD_DEFAULT)` lokalnego konta admina. |
| `admin_totp_secret` | `''` | Sekret TOTP (base32) lokalnego konta admina, pisany przez wtyczkę `two-factor-auth`; puste = wyłączone. Nieegzekwowane jeśli wtyczka jest wyłączona. |
| `login_max_attempts` | `5` | Próby logowania przed czasową blokadą. |
| `login_lock_seconds` | `300` | Czas trwania blokady (sekundy). |
| `admin_ip_allowlist` | `''` | Dozwolone IP/CIDR (CSV) na admina; puste = wszystkie. |
| `session_lifetime` | `0` | Czas trwania sesji w godzinach; `0` = cookie sesji. |
| `remember_days` | `30` | Czas życia tokenów "Zapamiętaj logowanie" w dniach (1-365). |
| `maintenance` | `false` | Tryb konserwacji: `/` serwuje dedykowaną stronę w 503 (wszystkie publiczności, sesja zachowana) a `/api/*` użytkownika odpowiada 503 - `/api/admin/*` i `/api/ping` pozostają aktywne. Zalogowani użytkownicy są przenoszeni na stronę konserwacji przy kolejnym żądaniu API; ta przeładowuje się sama na końcu. |
| `force_https` | `false` | Przekierowuje HTTP > HTTPS (301). |
| `hsts` | `false` | Emituje `Strict-Transport-Security` gdy żądanie jest HTTPS. |
| `cookie_samesite` | `Lax` | `Lax` (domyślnie, wymagane dla powrotu OAuth2) / `Strict` (łamie przepływ OAuth2) / `None` (wymaga HTTPS). |
| `csp_extra` | `''` | Dodatkowe dyrektywy CSP (np. `img-src https:`). |
| `trusted_proxies` | `''` | IP/CIDR (CSV) zaufanych reverse proxy. Obowiązkowe by uczynić wiarygodnym `X-Forwarded-*` (rate-limit, allowlist, logi). |
| `secfetch_allow` | `''` | Dodatkowe reguły `Sec-Fetch-*` (np. `site=same-origin;mode=cors`). |

## Aktualizacje

Oficjalne repozytorium jest zaryglowane w `GitHubClient::REPO`
(`WatT-For/MXWebMail`) i **publiczne**: weryfikacja i pobieranie działają bez
tokenu. Ta sekcja - współdzielona przez updater rdzenia i
katalog wtyczek (czytany w repo na ref `update.plugins_ref`, każdy pobrany
plik weryfikowany po sha1 bloba git) - nie jest edytowalna przez admina
(poza `channel`).

| Klucz | Domyślnie | Rola |
|---|---|---|
| `update.repo` | `''` | Fallback dev jeśli `GitHubClient::REPO` jest pusty (`owner/repo`). |
| `update.channel` | `stable` | `stable` / `beta` (jedyna opcja eksponowana w adminie). |
| `update.manifest_url` | `''` | Dev: manifest JSON w formacie release, omija API GitHub. |
| `update.plugins_ref` | `'release'` | Git-ref źródła katalogu wtyczek: `'release'` (tag ostatniej release), `'main'` (gałąź - push `plugin.json` publikuje aktualizację) lub dowolny dosłowny ref. |
| `update.plugins_local` | `''` | Dev/testy: absolutna ścieżka folderu wtyczek - katalog + install z dysku, offline i bez weryfikacji zdalnej. Prewaluje nad `plugins_ref`. |

## Funkcjonalności

| Klucz | Domyślnie | Rola |
|---|---|---|
| `attachment_max_size` | `25` | MB maks. per plik. Musi być ≤ `attachments_max_total` (powyżej limit nie ma efektu: upload ogranicza już do `min` obu - admin odrzuca niespójny zapis). |
| `attachments_max_total` | `25` | MB łącznie per wiadomość. |
| `allow_additional_accounts` | `true` | Użytkownik może dodawać/zarządzać dodatkowymi kontami IMAP (Ustawienia > Konta). |
| `allow_additional_identities` | `true` | Użytkownik może tworzyć dodatkowe tożsamości. |
| `popup_identity` | `false` | Otwiera ustawienie tożsamości przy pierwszym loginie jeśli nazwa jest pusta. |
| `dangerous_actions` | `true` | Autoryzuje opróżnianie/usuwanie folderu, usuwanie wiadomości (włącznie przeniesieniem do kosza). |
| `contacts_enable` | `true` | Książka adresowa + autouzupełnianie. |
| `contacts_suggestions_limit` | `20` | Maks. liczba sugestii autouzupełniania (1-100). |
| `import_max_size` | `100` | Maks. rozmiar importowanego `.mbox` (MB) - `.eml` zachowują sufit `attachment_max_size`. Niedytowalne w adminie. |
| `smime_ca_path` | `''` | Ścieżka CA (plik PEM) dla zaufania S/MIME; puste = tylko sygnatura kryptograficzna weryfikowana (`PKCS7_NOVERIFY`). Niedytowalne w adminie. |

## `user_defaults` - domyślne ustawień użytkownika

Nadpisywalne przez użytkownika. Klucze dozwolone w zapisie przez
`/api/settings` to te plus zadeklarowane przez wtyczki (hook
`settings.allowed`).

| Klucz | Domyślnie | Rola |
|---|---|---|
| `messages_per_page` | `50` | Wiadomości per strona (5-200). |
| `list_sort` | `REVERSE DATE` | Sortowanie listy (kryterium IMAP SORT w whitelist). |
| `view_images` | `ask` | Obrazy zewnętrzne: `always` / `ask` / `match` (znany nadawca). |
| `msg_default_action` | `reply` | Domyślny przycisk odpowiedzi: `reply` / `reply_all`. |
| `mark_read_delay` | `5` | Sekundy zanim otwarta wiadomość będzie oznaczona przeczytaną (0 = natychmiast). |
| `compose_autosave` | `60` | Sekundy między autozapisami szkicu (0 = nigdy). |
| `mdn_mode` | `ask` | Potwierdzenia odczytu: `ask` (banner prośby) / `never` (ukryty). |
| `autologout` | `30` | Minuty nieaktywności przed wylogowaniem (0 = nigdy). Nie odwołuje tokenu "Zapamiętaj logowanie": sesja jest przywracana cicho przy następnej wizycie. |
| `list_refresh` | `5` | Minuty między automatycznymi odświeżeniami (0 = nigdy). |
| `notify_new_mail` | `false` | Powiadomienie przeglądarki przy nadejściu maila. |
| `contacts_autosave` | `true` | Automatycznie zapisywać odbiorcę w kontaktach. |
| `folder_pane_width` | `240` | Szerokość kolumny folderów (px, 140-600). |
| `message_pane_width` | `340` | Szerokość kolumny listy wiadomości (px, 200-800). |
| `layout` | `horizontal` | Układ czytania: `horizontal` / `vertical`. |
| `message_pane_height` | `320` | Wysokość listy wiadomości w trybie pionowym (px, 120-1200). |
| `compose_modal_width` | `0` | Szerokość modala redakcji (px, 0 = domyślne Bootstrap). |
| `compose_modal_height` | `0` | Wysokość modala redakcji (px, 0 = auto). |
| `identity_modal_width` | `0` | Szerokość modala tożsamości (px, 0 = domyślne Bootstrap). |
| `identity_modal_height` | `0` | Wysokość modala tożsamości (px, 0 = auto). |
| `empty_trash_on_logout` | `false` | Opróżniać kosz przy wylogowaniu. |
| `empty_junk_on_logout` | `false` | Opróżniać folder spamu przy wylogowaniu. |
| `list_threads` | `false` | Grupować wiadomości w konwersacje (IMAP THREAD). |
| `after_delete` | `next` | Po usunięciu/przeniesieniu wyświetlonego maila: `next` / `prev` / `close`. |
| `keyboard_shortcuts` | `true` | Skróty klawiatury widoku poczty (pomoc przez klawisz `?`). |
| `theme` | `default` | Szablon interfejsu (folder `app/templates/<name>`). |

## TLS (wychodzące połączenia IMAP/SMTP/Sieve)

| Klucz | Domyślnie | Rola |
|---|---|---|
| `ssl.verify_certificate` | `true` | Weryfikować certyfikat (`false` = certyfikat self-signed). |
| `ssl.allow_self_signed` | `false` | Akceptować certyfikaty self-signed. |
| `ssl.cafile` / `ssl.capath` | `''` | Własne CA (absolutna ścieżka / folder). |
| `ssl.disable_compression` | `true` | Wyłącza kompresję TLS. |
| `ssl.security_level` | `1` | Poziom bezpieczeństwa OpenSSL (0-5). |
| `ssl.local_cert` | `''` | Ewentualny certyfikat klienta. |

## Proxy obrazów zewnętrznych

| Klucz | Domyślnie | Rola |
|---|---|---|
| `image_proxy` | `true` | Serwować zdalne obrazy przez `/api/proxy`. |
| `image_proxy_allowlist` | `''` | Dozwolone domeny (CSV, `*.domena` ok); puste = wszystkie (prywatne IP zawsze blokowane). |

## HTTP - kompresja & cache przeglądarki

Edytowalne w adminie, sekcja *Wydajność > HTTP*.

| Klucz | Domyślnie | Rola |
|---|---|---|
| `http.gzip` | `true` | Kompresuje odpowiedzi tekst/JSON w PHP (gzip) - działa też bez `mod_deflate`. Ignorowane jeśli `zlib.output_compression` aktywne. **Za Apache/nginx: `false` zalecane** - serwer kompresuje jako filtr (streaming) a PHP unika `gzencode` w pamięci. Trzymać `true` dla `php -S` (dev, bez `mod_deflate`). |
| `http.gzip_level` | `6` | Poziom kompresji 1-9. |
| `http.gzip_min_size` | `1024` | Minimalny rozmiar body (bajty) do kompresji. |
| `http.etag` | `true` | ETag/Last-Modified + odpowiedzi 304 na assetach serwowanych przez PHP (motyw, wtyczki, favicon). |
| `http.assets_ttl` | `86400` | `max-age` assetów motyw/wtyczka (`/assets/theme|plugin/*`). `0` = systematyczna rewalidacja. Z `?v=` (mtime serwowanego pliku) emituje się `immutable`. |
| `http.favicon_ttl` | `3600` | `max-age` `/favicon.ico`. |
| `http.proxy_ttl` | `86400` | `max-age` (prywatny) obrazów przez `/api/proxy` - służy też jako TTL cache dyskowego (`storage/cache/proxy`). `0` = brak cache (re-fetch + rewalidacja SSRF przy każdym wyświetleniu). Czyszczenie zarządzane przez `cache.gc_hours`: trzymać `proxy_ttl ≤ cache.gc_hours`. |

Odpowiedzi prywatne/API nigdy nie dostają publicznego cache; HTML jest
`no-cache`.

**Produkcja**: włączyć **opcache** (`opcache.enable=1`,
`opcache.max_accelerated_files` ≥ liczba plików PHP - kod sam unieważnia
swoje cache przez `opcache_invalidate`, `opcache.validate_timestamps` może
pozostać aktywne). Cache aplikacyjne (`classmap.php`, manifesty wtyczek,
słowniki i18n, `themedata.php`) są zarządzane przez kod - żadna ręczna
unieważnienie nie jest wymagana.

## Cache aplikacyjne (plik)

Edytowalne w adminie, sekcja *Wydajność > Cache serwera*.

| Klucz | Domyślnie | Rola |
|---|---|---|
| `cache.enabled` | `true` | Włącza cache plikowy `app/storage/cache` (listy wiadomości IMAP indeksowane na ETag folderu, DNS, detekcja domeny, weryfikacja aktualizacji, GC folderu). Nie dotyka ani autoloadera (`classmap.php`) ani `login_attempts.json`. |
| `cache.domain_detect_ttl` | `86400` | TTL negatywnego cache detekcji IMAP/SMTP (s). |
| `cache.update_ttl` | `3600` | TTL wyniku weryfikacji aktualizacji (s). |
| `cache.gc_hours` | `168` | Interwał czyszczenia wygasłych plików cache (h); `0` = nigdy. |

## Logi MailSo (protokoły IMAP/SMTP/Sieve)

| Klucz | Domyślnie | Rola |
|---|---|---|
| `logs.enabled` | `true` | Pisze `storage/logs/mail.log` (sekrety maskowane). |
| `logs.level` | `LOG_WARNING` | `LOG_DEBUG` by śledzić protokół. |
| `logs.syslog` | `false` | Duplikat do `syslog()` jeśli log plikowy jest nieaktywny. |
| `logs.time_zone` | `''` | Strefa znaczników czasu (np. `Europe/Paris`); puste = serwer. |
| `logs.max_size` | `5` | Rotacja powyżej N MiB - dotyczy `mail.log`, `app.log` i dziennika auth. |
| `logs.keep` | `3` | Zachowane rotacje (`app.1.log` … `app.{keep}.log`, sufit 20). |

## Dziennik uwierzytelniania (styl fail2ban)

| Klucz | Domyślnie | Rola |
|---|---|---|
| `auth_logging` | `false` | Włącza dedykowany dziennik uwierzytelniania. |
| `auth_logging_filename` | `auth.log` | Plik w `storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Placeholdery: `{date:FMT}`, `{request:ip}`, `{imap:login}`, `{result}`. |

`{result}` przyjmuje `AUTH OK`, `AUTH FAILED (<kod http>)` lub
`AUTH FAILED (totp)`. Dziennik pokrywa login webmail i login admina (login
z prefiksem `admin:`). Dla powiązania fail2ban (filtr, jail, logrotate),
zob. [fail2ban.md](fail2ban.md).

## Labs / kompatybilność

| Klucz | Domyślnie | Rola |
|---|---|---|
| `labs.smtp_show_server_errors` | `false` | Pokazywać surowe błędy SMTP klientowi (diagnostyka, może ujawnić detale wewnętrzne). |
