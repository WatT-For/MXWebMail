<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Подсказки контактов из каталога LDAP (автодополнение)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect не удался',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS отклонён',
	'plugin.ldap-contacts.bind_denied'   => 'Привязка LDAP отклонена',
	'plugin.ldap-contacts.anon_bind_denied' => 'Анонимная привязка отклонена',
	'plugin.ldap-contacts.search_failed' => 'Поиск LDAP не удался',
	'plugin.ldap-contacts.f.host'        => 'Хост LDAP',
	'plugin.ldap-contacts.f.port'        => 'Порт',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Пароль привязки',
	'plugin.ldap-contacts.f.base_dn'     => 'Базовый DN поиска',
	'plugin.ldap-contacts.f.filter'      => 'Фильтр (%q = запрос)',
	'plugin.ldap-contacts.f.name_attr'   => 'Атрибут имени',
	'plugin.ldap-contacts.f.mail_attr'   => 'Атрибут почты',
	'plugin.ldap-contacts.f.max_results' => 'Макс. результатов',
	'plugin.ldap-contacts.label'           => 'Контакты LDAP',
];