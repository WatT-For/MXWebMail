<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Login resolution via LDAP: uid/short email > full IMAP address',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS denied',
	'plugin.ldap-login.bind_denied'    => 'LDAP bind denied',
	'plugin.ldap-login.f.host'         => 'LDAP host',
	'plugin.ldap-login.f.port'         => 'Port',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (empty = anonymous)',
	'plugin.ldap-login.f.bind_pass'    => 'Bind password',
	'plugin.ldap-login.f.base_dn'      => 'Base DN',
	'plugin.ldap-login.f.search_filter' => 'Filter (%u = login input)',
	'plugin.ldap-login.f.mail_attr'    => 'Attribute containing the IMAP email',
];
