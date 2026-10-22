<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Определение логина через LDAP: uid/короткий адрес → полный IMAP-адрес',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS отклонён',
	'plugin.ldap-login.bind_denied'    => 'Привязка LDAP отклонена',
	'plugin.ldap-login.f.host'         => 'Хост LDAP',
	'plugin.ldap-login.f.port'         => 'Порт',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (пусто = анонимно)',
	'plugin.ldap-login.f.bind_pass'    => 'Пароль привязки',
	'plugin.ldap-login.f.base_dn'      => 'Базовый DN',
	'plugin.ldap-login.f.search_filter' => 'Фильтр (%u = введённый логин)',
	'plugin.ldap-login.f.mail_attr'    => 'Атрибут с IMAP-адресом',
];