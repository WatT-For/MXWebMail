<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Login-resolutie via LDAP: uid/korte e-mail → volledig IMAP-adres',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS geweigerd',
	'plugin.ldap-login.bind_denied'    => 'LDAP-bind geweigerd',
	'plugin.ldap-login.f.host'         => 'LDAP-host',
	'plugin.ldap-login.f.port'         => 'Poort',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind-DN (leeg = anoniem)',
	'plugin.ldap-login.f.bind_pass'    => 'Bind-wachtwoord',
	'plugin.ldap-login.f.base_dn'      => 'Basis-DN',
	'plugin.ldap-login.f.search_filter' => 'Filter (%u = login-invoer)',
	'plugin.ldap-login.f.mail_attr'    => 'Attribuut met de IMAP-e-mail',
];