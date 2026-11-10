# Документация MXWebMail

**Языки** : [fr](../fr/README.md) · [de](../de/README.md) · [en](../en/README.md) · [es](../es/README.md) · [it](../it/README.md) · [nl](../nl/README.md) · [pl](../pl/README.md) · [pt](../pt/README.md) · **ru** · [tr](../tr/README.md)

*Переведено с французского - французская версия является исходной.*

Документация предназначена для **администраторов**, которые устанавливают,
развёртывают и эксплуатируют MXWebMail, и для **разработчиков**,
расширяющих проект (плагины, темы, ядро).

## Рекомендуемый порядок чтения

- **Установка**: [installation.md](installation.md)
- **Настройка**: [configuration.md](configuration.md)
- **Повседневное администрирование**: [administration.md](administration.md)
- **Подключение почтовых серверов**: [domaines.md](domaines.md)
- **Решение проблем**: [depannage.md](depannage.md)

## Оглавление

| Файл | Содержание |
|---|---|
| [installation.md](installation.md) | Требования, мастер `/setup/`, ручная установка, развёртывание Apache/Nginx, шаги после установки. |
| [configuration.md](configuration.md) | Полный справочник ключей `app/config/config.php` (значения по умолчанию и роль). |
| [administration.md](administration.md) | Руководство по панели администрирования: разделы, пользователи, журналы, обновления. |
| [domaines.md](domaines.md) | Настройка серверов IMAP/SMTP/Sieve по доменам, домен `*`, автоопределение. |
| [plugins.md](plugins.md) | 30 поставляемых плагинов (роль, настройка) + создание плагина (хуки, API, схема). |
| [themes.md](themes.md) | Система тем (`app/templates/`), наследование, создание темы. |
| [localisation.md](localisation.md) | Доступные языки, добавление языка, словари плагинов/тем. |
| [base-de-donnees.md](base-de-donnees.md) | Схема, миграции, SQLite ↔ MariaDB, `app.key`, базы на пользователя. |
| [securite.md](securite.md) | Модель безопасности и рекомендации по усилению. |
| [fail2ban.md](fail2ban.md) | Интеграция fail2ban: журнал аутентификации, фильтр, jail, logrotate. |
| [developpement.md](developpement.md) | Архитектура, автозагрузчик, хуки ядра. |
| [depannage.md](depannage.md) | Частые ошибки, журналы, FAQ. |

## Соглашения

- Пути указаны относительно корня проекта.
- Цитируемые значения по умолчанию соответствуют
  `app/config/config.example.php` и коду (`app/src/MXWebMail/…`), версия
  `0.53.0`.
- Эта документация описывает **реальность кода**: если файл расходится,
  исправьте его.
- Переводы: у каждого языка свой каталог `docs/<lang>/`; `fr/` - эталонная
  версия.
