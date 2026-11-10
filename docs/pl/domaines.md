# Domeny i serwery poczty

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Serwery IMAP/SMTP/Sieve konfiguruje się **per domena** w panelu admina
(*Domeny*). Każda domena opisuje jak dotrzeć do serwera poczty jej
użytkowników.

## 1. Pola domeny

| Pole | Wartość |
|---|---|
| `name` | Nazwa domeny (np. `example.com`), lub `*` dla catch-all. |
| `imap_host` | Host serwera IMAP. |
| `imap_port` | Port IMAP (domyślnie `993`). |
| `imap_encryption` | `ssl` (domyślnie), `tls` (STARTTLS) lub `none`. |
| `smtp_host` | Host serwera SMTP (wysyłka). |
| `smtp_port` | Port SMTP (domyślnie `465`). |
| `smtp_encryption` | `ssl` (domyślnie), `tls` lub `none`. |
| `sieve_host` | Host ManageSieve (opcjonalnie). |
| `sieve_port` | Port Sieve (domyślnie `4190`). |
| `active` | Włącz/wyłącz domenę. |

## 2. Opcje zaawansowane (`options`, JSON)

Pole `options` akceptuje obiekt JSON z drobnymi ustawieniami per domena:

- `whitelist` - lista adresów dozwolonych dla tej domeny;
- `secondary_only` - domena użyteczna tylko jako **konto dodatkowe**
  (dodanie konta z poziomu już zalogowanego użytkownika); login pierwotny
  jest odrzucany z "Domena zarezerwowana dla kont dodatkowych";
- `oauth` - dostawca OAuth2 domeny (`google`, `microsoft`, `yahoo`): konta
  dodatkowe są dodawane przez zgodę dostawcy (popup), nigdy hasłem (zob.
  §8);
- `imap.shortLogin` - uciąć login;
- `imap.lowerLogin` - wymusić login małymi literami;
- `imap.timeout` - timeout połączenia IMAP;
- `imap.disabled_capabilities` - capability IMAP do wyłączenia (wadliwe
  serwery). Przykład: `ESORT` dla serwera który go ogłasza bez
  implementowania opcji zwrotu RFC 5267 (`RETURN (PARTIAL ...)`) - webmail
  już wykrywa ten przypadek i przechodzi sam do klasycznego sortowania;
  klucz służy tylko gdy detekcja nie ma zastosowania (cache wyłączony);
- `imap.body_text_limit` - maks. rozmiar (bajty) pobieranych ciał tekstu
  przy otwieraniu wiadomości (domyślnie `0` = bez limitu; sufit ~256 K
  oszczędza fetch bardzo długich biuletynów);
- `imap.message_list_limit` - powyżej (domyślnie `10000` wiadomości w
  folderze), MailSo unika server-SORT i cache'uje listę UID w tle;
- `smtp.useAuth` - wymusić/wyłączyć uwierzytelnienie SMTP;
- `smtp.timeout` - timeout połączenia SMTP;
- `sieve.encryption` - szyfrowanie połączenia Sieve.

## 3. Domena `*` (catch-all)

Domena `*` dostarcza **domyślne parametry** stosowane do każdej
niezadeklarowanej domeny - ale tylko gdy `restrict_domains = false`: pod
`restrict_domains`, `*` jest ignorowane i nie może autoryzować loginu
pierwotnego. To najprostszy sposób obsługi wielu domen za tym samym
serwerem poczty.

## 4. Rozdzielczość domeny przy loginie

Przy loginie domena jest ustalana tak:

1. Jeśli użytkownik wpisuje login z `@domena`, ta domena jest użyta.
2. Inaczej stosuje się `default_domain`: `auto` (domyślnie) = host HTTP bez
   `www.` i portu; stała nazwa; lub `''` (brak).
3. Jeśli `restrict_domains = true` (domyślnie), tylko **nazwane** domeny
   obecne w tabeli są akceptowane (`*` i detekcja są ignorowane) -
   **utwórz więc przynajmniej jedną domenę przed każdym loginem**.

## 5. Automatyczna detekcja

`autodetect.*` (domyślnie wyłączone) pozwala odkryć konfigurację nieznanej
domeny przy loginie:

- `enabled`: włącza detekcję;
- `use_ispdb`: odpytuje bazę Mozilla (`autoconfig.thunderbird.net`);
- `probe`: sonduje `imap.`/`mail.`/`smtp.<domena>`.

Kolejność detekcji to: DNS SRV > autoconfig Mozilla > MX > sondowanie
portów.

Sieve jest wykrywany niezależnie od źródła: `_sieve._tcp` (SRV), inaczej
sondowanie portu **4190** na `sieve.<domena>` (jeśli `probe` aktywne lub
przez przycisk *Wykryj*) potem na znalezionym hoście IMAP - Dovecot serwuje
ManageSieve prawie zawsze na tym samym hoście. Szyfrowanie nie wymaga
konfiguracji: połączenie przechodzi na STARTTLS automatycznie gdy serwer je
ogłasza.

Przycisk **Wykryj** strony *Domeny* uruchamia tę samą detekcję na żądanie,
niezależnie od `autodetect.enabled`, i wypełnia pola domeny.

> Instalator `/setup/` próbuje też tej detekcji na hoście vhost (i jego
> rodzicach) by automatycznie utworzyć pierwszą domenę.

## 6. Test

Przycisk **Testuj połączenie** waliduje protokół aktywnej zakładki modala
(IMAP, SMTP lub Sieve; inne zakładki testują IMAP). Użyj go po każdej
zmianie by zweryfikować że poświadczenia, porty i szyfrowanie są poprawne
zanim użytkownicy się zalogują.

## 7. Typowy przypadek: jeden serwer poczty

Dla pojedynczego serwera (np. Postfix + Dovecot na `mail.example.com`
obsługującego `example.com`):

1. Utwórz domenę `example.com` z `imap_host = mail.example.com`,
   `smtp_host = mail.example.com`, i ewentualnie `sieve_host`.
2. Zostaw `default_domain = auto` i `restrict_domains = true`.
3. Użytkownicy logują się `user@example.com` lub po prostu `user` (domena
   jest wydedukowana z hosta HTTP).

Wariant multi-domenowy: domena `*` z `restrict_domains = false` służy jako
catch-all - każda niezadeklarowana domena dziedziczy jej parametry (zob.
§3; uwaga, login pierwotny pozostaje wtedy możliwy na wszystkich domenach -
`secondary_only` może go odrzucić, zob. §8).

## 8. Publiczne domeny jako konto dodatkowe (Gmail, Outlook, Yahoo)

Gmail, Outlook.com i Yahoo już nie akceptują uwierzytelnienia IMAP prostym
hasłem - dodanie tych kont odbywa się przez **OAuth2** (zgoda w popupie,
potem XOAUTH2/OAUTHBEARER dla IMAP i SMTP).

Poświadczenia aplikacji OAuth2 (client ID, secret, tenant Microsoft) są
**własne każdego użytkownika**: admin nie rejestruje żadnej aplikacji -
autoryzuje tylko dostawcę na domenie. Każdy użytkownik tworzy własną
aplikację w konsoli deweloperów dostawcy.

### Konfiguracja (admin)

Te trzy domeny są **wstępnie utworzone przy instalacji** (konto dodatkowe
+ dostawca OAuth już ustawiony). Jeśli je usunąłeś, odtwórz je tak:

1. **Utwórz domenę** (*Domeny > Dodaj* lub przycisk *Wykryj*):
   `imap.gmail.com:993` / `smtp.gmail.com:465` dla Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` dla Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` dla Yahoo.
2. Zaznacz **Tylko konto dodatkowe** (login pierwotny odrzucany) i wybierz
   dostawcę w **Auth OAuth2**.

### Rejestracja aplikacji (użytkownik)

Przy pierwszym dodaniu konta domeny, webmail prosi o poświadczenia
aplikacji i rejestruje je dla użytkownika - zarządzane potem w *Ustawienia
> Aplikacje OAuth2* (jedna aplikacja per dostawca, używana ponownie dla
wszystkich kont tego dostawcy). URI przekierowania do zadeklarowania w
konsoli: `<base>/api/oauth/callback`.

- **Google** (Cloud Console > APIs & Services > Credentials > klient OAuth
  Web, scope `https://mail.google.com/`): scope *restricted* - aplikacja w
  trybie "Testing" jest ograniczona do ~100 testowych użytkowników i jej
  refresh tokeny wygasają po ~7 dniach; produkcja wymaga weryfikacji
  Google.
- **Microsoft** (Entra > App registrations, `signInAudience` obejmujący
  konta osobiste): uprawnienia delegowane "Office 365 Exchange Online"
  `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access` niejawny.
  Możliwy banner "niezweryfikowany wydawca" przy zgodzie (nieblokujący).
- **Yahoo** (Developer Console > aplikacja OpenID Connect + Mail API,
  scope `openid`, `mail-r`, `mail-w`): dostęp Mail API wymaga **wcześniejszej
  aprobacji Yahoo** (formularz wniosku + ręczna recenzja) a propagacja
  redirect URI może trwać ~24 h.

### Działanie

- Użytkownik dodaje konto z menu kont lub *Ustawienia > Konta*: wpisanie
  adresu > (pierwszy raz: wpisanie poświadczeń aplikacji) > popup zgody >
  konto pojawia się z badge dostawcy. *Ustawienia > Konta* pozwala też na
  zmianę, aktualizację poświadczeń, promocję do konta głównego i
  usunięcie.
- Konto dodatkowe może być **promowane do głównego** (gwiazdka w
  *Ustawienia > Konta*) - flaga `secondary_only` domeny blokuje tylko
  *login* pierwotny, nie tę promocję.
- *Refresh token* jest przechowywany zaszyfrowany (`accounts.imap_pass`);
  każde połączenie IMAP/SMTP wymienia efemeryczny access token. Microsoft
  i Yahoo **wydają ponownie refresh token przy każdym użyciu**: jest
  automatycznie zastępowany w bazie.
- Sekret aplikacji jest szyfrowany (`oauth_apps.secret`, ten sam mechanizm
  co hasła) i nigdy nie zwracany do interfejsu - "puste = bez zmian" przy
  edycji.
- **Zmiana lub usunięcie aplikacji** łamie powiązane konta: refresh token
  pozostaje związany z pierwotnym client_id - usuń i dodaj konto
  ponownie.
- Brak ManageSieve u tych dostawców: filtry Sieve nie są dostępne na
  kontach OAuth.

Połączenie `secondary_only` na domenie `*` z normalnymi domenami
wewnętrznymi rezerwuje login pierwotny dla domen wymienionych zostawiając
dodatkowe otwarte wszędzie - przydatne bez `restrict_domains`.
