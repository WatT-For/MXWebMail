# Fail2ban

*Переведено с французского - французская версия является исходной.*

MXWebMail может писать выделенный журнал аутентификации, предназначенный
для чтения [fail2ban](https://github.com/fail2ban/fail2ban) с целью бана IP,
множащих неудачные входы. Эта страница описывает включение журнала и полную
интеграцию fail2ban (фильтр, jail, ротация).

## Включение

Три ключа `app/config/config.php` (справочник:
[configuration.md](configuration.md#журнал-аутентификации-в-стиле-fail2ban)),
также редактируемые в админе, раздел *Журналы*:

| Ключ | По умолчанию | Роль |
|---|---|---|
| `auth_logging` | `false` | Включает журнал. |
| `auth_logging_filename` | `auth.log` | Файл в `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Формат строки. |

С форматом по умолчанию строка выглядит так:

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` принимает `AUTH OK`, `AUTH FAILED (<код http>)` или
  `AUTH FAILED (totp)` (введён неверный код TOTP - начальный запрос второго
  фактора не является неудачей и ничего не журналирует).
- Попытки на **вход админа** покрыты: логин получает префикс `admin:`
  (напр. `admin:admintest`). Действительный логин IMAP без прав админа
  журналируется как `AUTH FAILED (403)`.
- Попытки, уже заблокированные внутренним rate-limit, журналируются как
  `AUTH FAILED (429)` - поэтому тоже учитываются fail2ban (рецидив).
- Переводы строк нейтрализуются в логине и результате: поддельная запись не
  может внедрить фальшивые строки в файл.

## Фильтр

`/etc/fail2ban/filter.d/mxwebmail.conf` (для формата по умолчанию):

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Если `auth_logging_format` настроен, адаптируйте `failregex` (и
`datepattern`, если `{date:FMT}` меняется - fail2ban требует распознаваемую
метку времени в начале строки или идентифицируемую через `datepattern`).

Проверьте, что фильтр соответствует реальному файлу:

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (или `jail.local`):

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Затем `fail2ban-client reload` и `fail2ban-client status mxwebmail`.

## Моменты внимания

- **Часовой пояс**: fail2ban сравнивает метки времени строк с системным
  временем. Оставьте `logs.time_zone` пустым (= зона сервера) или установите
  **тот же** пояс, что у системы, где работает fail2ban - смещение исказит
  `findtime` и бан.
- **Обратный прокси**: без заполненного `trusted_proxies` журнал содержит IP
  прокси - fail2ban забанил бы тогда прокси и отрезал бы доступ **всем**
  пользователям. Объявите доверенные прокси и проверьте, что журналируемый
  IP - IP клиента.
- **Ротация**: файл растёт без ограничения. Пример `logrotate`:

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

- **Дополнение, не замена**: fail2ban действует на сетевом уровне;
  внутренний rate-limit (`login_max_attempts`, `login_lock_seconds` - см.
  [securite.md](securite.md#rate-limit-при-входе)) остаётся первой линией
  защиты приложения. Оба независимы и суммируемы.
