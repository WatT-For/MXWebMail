<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Risoluzione dell\'accesso via LDAP: uid/email breve > indirizzo IMAP completo',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS negato',
	'plugin.ldap-login.bind_denied'    => 'Bind LDAP negato',
	'plugin.ldap-login.f.host'         => 'Host LDAP',
	'plugin.ldap-login.f.port'         => 'Porta',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (vuoto = anonimo)',
	'plugin.ldap-login.f.bind_pass'    => 'Password di bind',
	'plugin.ldap-login.f.base_dn'      => 'Base DN',
	'plugin.ldap-login.f.search_filter' => 'Filtro (%u = input di login)',
	'plugin.ldap-login.f.mail_attr'    => 'Attributo contenente l\'email IMAP',
];