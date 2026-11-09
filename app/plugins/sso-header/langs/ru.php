<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO через заголовок прокси',
	'plugin.sso-header.description' => 'Автовход через аутентифицирующий reverse-прокси (Authelia, authentik, oauth2-proxy), доверяющий заголовку пользователя - требует мастер-пароль IMAP и веб-почту, доступную ТОЛЬКО за прокси',
	'plugin.sso-header.denied' => 'Аутентификация SSO не удалась или истекла - повторите или используйте ручную форму',
	'plugin.sso-header.f.header' => 'Доверенный заголовок',
	'plugin.sso-header.f.header.help' => 'Заголовок, устанавливаемый прокси аутентификации (по умолчанию X-Forwarded-User ; REMOTE_USER для переменной Apache)',
	'plugin.sso-header.f.trusted_ips' => 'Доверенные IP прокси (обязательно)',
	'plugin.sso-header.f.trusted_ips.help' => 'Список CIDR, по одному в строке - прямой пир должен совпадать. ПУСТО = плагин отключён (fail-closed). Экземпляр НЕ должен быть доступен в обход прокси',
	'plugin.sso-header.f.master_pass' => 'Мастер-пароль IMAP (обязательно)',
	'plugin.sso-header.f.master_pass.help' => 'Хранится в конфиге плагина как другие секреты. Режим "same" (Dovecot passdb master=yes): работает как пароль каждого пользователя',
	'plugin.sso-header.f.master_user' => 'Мастер-пользователь (необязательно)',
	'plugin.sso-header.f.master_user.help' => 'Если задан, логин IMAP/SMTP/Sieve становится user*master (режим "separate") - только для основной SSO-сессии',
	'plugin.sso-header.f.allowed_domains' => 'Разрешённые домены',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, по одному в строке (напр. *@domain.tld). Пусто = все',
	'plugin.sso-header.f.hide_form' => 'Свернуть форму пароля',
	'plugin.sso-header.f.hide_form.help' => 'Ручной вход остаётся доступен за ссылкой - никогда не удаляется (резерв)',
	'plugin.sso-header.manual_login' => 'Ручной вход',
];
