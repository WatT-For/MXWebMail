# MXWebMail Belgeleri

**Diller** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · [ru](../ru/README.md) · **tr**

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

MXWebMail'i kuran, dağıtan ve işleten **yöneticilere** ve projeyi genişleten
**geliştiricilere** (eklentiler, temalar, çekirdek) yönelik belgeler.

## Önerilen okuma sırası

- **Kurulum**: [installation.md](installation.md)
- **Yapılandırma**: [configuration.md](configuration.md)
- **Günlük yönetim**: [administration.md](administration.md)
- **Posta sunucularını bağlama**: [domaines.md](domaines.md)
- **Sorun giderme**: [depannage.md](depannage.md)

## Dizin

| Dosya | İçerik |
|---|---|
| [installation.md](installation.md) | Gereksinimler, `/setup/` sihirbazı, manuel kurulum, Apache/Nginx dağıtımı, kurulum sonrası adımlar. |
| [configuration.md](configuration.md) | `app/config/config.php` anahtarlarının tam referansı (varsayılanlar ve görev). |
| [administration.md](administration.md) | Yönetim paneli kılavuzu: bölümler, kullanıcılar, günlükler, güncellemeler. |
| [domaines.md](domaines.md) | Etki alanı başına IMAP/SMTP/Sieve yapılandırması, `*` etki alanı, otomatik algılama. |
| [plugins.md](plugins.md) | 30 beraber gelen eklenti (görev, yapılandırma) + eklenti oluşturma (hook'lar, API, şema). |
| [themes.md](themes.md) | Tema sistemi (`app/templates/`), kalıtım, tema oluşturma. |
| [localisation.md](localisation.md) | Mevcut diller, dil ekleme, eklenti/tema sözlükleri. |
| [base-de-donnees.md](base-de-donnees.md) | Şema, geçişler, SQLite ↔ MariaDB, `app.key`, kullanıcı başına veritabanları. |
| [securite.md](securite.md) | Güvenlik modeli ve sağlamlaştırma önerileri. |
| [fail2ban.md](fail2ban.md) | fail2ban entegrasyonu: kimlik doğrulama günlüğü, filtre, jail, logrotate. |
| [developpement.md](developpement.md) | Mimari, otomatik yükleyici, çekirdek hook'ları. |
| [depannage.md](depannage.md) | Sık hatalar, günlükler, SSS. |

## Kurallar

- Yollar proje köküne göredir.
- Alıntılanan varsayılanlar `app/config/config.example.php` ve koda
  (`app/src/MXWebMail/…`), sürüm `0.53.0` karşılık gelir.
- Bu belgeler **kodun gerçekliğini** tanımlar: bir dosya saparsa düzeltin.
- Çeviriler: her dilin kendi `docs/<lang>/` dizini vardır; `fr/` referans
  sürümdür.
