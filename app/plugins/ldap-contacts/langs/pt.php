<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Sugestões de contatos de um diretório LDAP (autocompletar)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect falhou',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS negado',
	'plugin.ldap-contacts.bind_denied'   => 'Bind LDAP negado',
	'plugin.ldap-contacts.anon_bind_denied' => 'Bind anônimo negado',
	'plugin.ldap-contacts.search_failed' => 'Busca LDAP falhou',
	'plugin.ldap-contacts.f.host'        => 'Host LDAP',
	'plugin.ldap-contacts.f.port'        => 'Porta',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Senha de bind',
	'plugin.ldap-contacts.f.base_dn'     => 'Base DN de busca',
	'plugin.ldap-contacts.f.filter'      => 'Filtro (%q = consulta)',
	'plugin.ldap-contacts.f.name_attr'   => 'Atributo de nome',
	'plugin.ldap-contacts.f.mail_attr'   => 'Atributo de e-mail',
	'plugin.ldap-contacts.f.max_results' => 'Máx. de resultados',
];