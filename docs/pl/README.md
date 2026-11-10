# Dokumentacja MXWebMail

**Języki** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · **pl** · [pt](../pt/README.md) · [ru](../ru/README.md) · [tr](../tr/README.md)

*Tłumaczenie z francuskiego - wersja francuska jest wiążąca.*

Dokumentacja przeznaczona dla **administratorów**, którzy instalują,
wdrażają i eksploatują MXWebMail, oraz dla **programistów** rozszerzających
projekt (wtyczki, motywy, rdzeń).

## Zalecana kolejność lektury

- **Instalacja**: [installation.md](installation.md)
- **Konfiguracja**: [configuration.md](configuration.md)
- **Codzienne administrowanie**: [administration.md](administration.md)
- **Podłączanie serwerów poczty**: [domaines.md](domaines.md)
- **Rozwiązywanie problemów**: [depannage.md](depannage.md)

## Indeks

| Plik | Zawartość |
|---|---|
| [installation.md](installation.md) | Wymagania, kreator `/setup/`, instalacja ręczna, wdrożenie Apache/Nginx, kroki po instalacji. |
| [configuration.md](configuration.md) | Pełna referencja kluczy `app/config/config.php` (wartości domyślne i rola). |
| [administration.md](administration.md) | Przewodnik po panelu administracyjnym: sekcje, użytkownicy, logi, aktualizacje. |
| [domaines.md](domaines.md) | Konfiguracja serwerów IMAP/SMTP/Sieve per domena, domena `*`, autodetekcja. |
| [plugins.md](plugins.md) | 30 dostarczanych wtyczek (rola, konfiguracja) + tworzenie wtyczki (hooki, API, schemat). |
| [themes.md](themes.md) | System motywów (`app/templates/`), dziedziczenie, tworzenie motywu. |
| [localisation.md](localisation.md) | Dostępne języki, dodawanie języka, słowniki wtyczek/motywów. |
| [base-de-donnees.md](base-de-donnees.md) | Schemat, migracje, SQLite ↔ MariaDB, `app.key`, bazy per użytkownik. |
| [securite.md](securite.md) | Model bezpieczeństwa i zalecenia hardeningu. |
| [fail2ban.md](fail2ban.md) | Integracja fail2ban: dziennik uwierzytelniania, filtr, jail, logrotate. |
| [developpement.md](developpement.md) | Architektura, autoloader, hooki rdzenia. |
| [depannage.md](depannage.md) | Częste błędy, logi, FAQ. |

## Konwencje

- Ścieżki są względne wobec katalogu głównego projektu.
- Cytowane wartości domyślne odpowiadają `app/config/config.example.php` i
  kodowi (`app/src/MXWebMail/…`), wersja `0.53.0`.
- Ta dokumentacja opisuje **rzeczywistość kodu**: jeśli plik jest
  rozbieżny, popraw go.
- Tłumaczenia: każdy język ma własny katalog `docs/<lang>/`; `fr/` jest
  wersją referencyjną.
