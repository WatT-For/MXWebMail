<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Resolução de login via LDAP: uid/e-mail curto > endereço IMAP completo',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS negado',
	'plugin.ldap-login.bind_denied'    => 'Bind LDAP negado',
	'plugin.ldap-login.f.host'         => 'Host LDAP',
	'plugin.ldap-login.f.port'         => 'Porta',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (vazio = anônimo)',
	'plugin.ldap-login.f.bind_pass'    => 'Senha de bind',
	'plugin.ldap-login.f.base_dn'      => 'Base DN',
	'plugin.ldap-login.f.search_filter' => 'Filtro (%u = entrada de login)',
	'plugin.ldap-login.f.mail_attr'    => 'Atributo que contém o e-mail IMAP',
	'plugin.ldap-login.label'          => 'Início de sessão LDAP',
];