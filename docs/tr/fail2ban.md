# Fail2ban

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

MXWebMail, giriş başarısızlıklarını biriktiren IP'leri yasaklamak için
[fail2ban](https://github.com/fail2ban/fail2ban) tarafından okunması amaçlı
özel bir kimlik doğrulama günlüğü yazabilir. Bu sayfa günlüğün etkinleştirilmesini
ve eksiksiz bir fail2ban entegrasyonunu (filtre, jail, rotasyon) açıklar.

## Etkinleştirme

`app/config/config.php` üç anahtarı (referans:
[configuration.md](configuration.md#kimlik-doğrulama-günlüğü-fail2ban-tarzı)),
admin'de *Günlükler* bölümünden de düzenlenebilir:

| Anahtar | Varsayılan | Görev |
|---|---|---|
| `auth_logging` | `false` | Günlüğü etkinleştirir. |
| `auth_logging_filename` | `auth.log` | `app/storage/logs` içinde dosya. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Satır biçimi. |

Varsayılan biçimle bir satır şöyle görünür:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` şunları alır: `AUTH OK`, `AUTH FAILED (<http kodu>)` veya
  `AUTH FAILED (totp)` (yanlış TOTP kodu girildi - ilk ikinci faktör
  istemi bir başarısızlık değildir ve hiçbir şey günlüklemez).
- **Admin girişi** denemeleri kapsanır: giriş `admin:` öneki alır
  (örn. `admin:admintest`). Geçerli ama admin olmayan IMAP girişi
  `AUTH FAILED (403)` olarak günlüklenebilir.
- İç rate-limit tarafından zaten engellenen denemeler `AUTH FAILED (429)`
  olarak günlüklenebilir - dolayısıyla fail2ban için de sayılır (tekerrür).
- Satır sonları girişte ve sonuçta nötralize edilir: sahte bir giriş dosyaya
  sahte satırlar enjekte edemez.

## Filtre

`/etc/fail2ban/filter.d/mxwebmail.conf` (varsayılan biçim için):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

`auth_logging_format` özelleştirilmişse `failregex`'i uyarlayın (ve
`{date:FMT}` değişirse `datepattern` - fail2ban satır başında tanınabilir
bir zaman damgası veya `datepattern` ile tanımlanabilir bir tane gerektirir).

Filtrenin gerçek dosyayla eşleştiğini doğrulayın:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (veya `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Ardından `fail2ban-client reload` ve `fail2ban-client status mxwebmail`.

## Dikkat noktaları

- **Saat dilimi**: fail2ban satırların zaman damgasını sistem saatiyle
  karşılaştırır. `logs.time_zone`'u boş bırakın (= sunucu bölgesi) veya
  fail2ban'ın çalıştığı sistemle **aynı** bölgeye ayarlayın - bir sapma
  `findtime` ve yasağı bozar.
- **Ters proxy**: `trusted_proxies` dolmadan günlük proxy'nin IP'sini içerir -
  fail2ban o zaman proxy'yi yasaklar ve **tüm** kullanıcıların erişimini
  keser. Güvenilir proxy'leri bildirin ve günlüklelenen IP'nin istemcinin
  IP'si olduğunu doğrulayın.
- **Rotasyon**: dosya sınırsız büyür. `logrotate` örneği:

  ```
  /var/www/mxwebmail/app/storage/logs/auth.log {
      weekly
      rotate 8
      missingok
      notifempty
      compress
      copytruncate
  }
  ```

- **Tamamlayıcı, ikame değil**: fail2ban ağ seviyesinde çalışır; iç
  rate-limit (`login_max_attempts`, `login_lock_seconds` - bkz.
  [securite.md](securite.md#oturum-açma-rate-limit)) uygulamanın ilk
  savunma hattı kalır. İkisi bağımsız ve biriktirilebilir.
