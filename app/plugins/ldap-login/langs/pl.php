<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Rozwiązywanie logowania przez LDAP: uid/krótki e-mail > pełny adres IMAP',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS odrzucone',
	'plugin.ldap-login.bind_denied'    => 'Bind LDAP odrzucony',
	'plugin.ldap-login.f.host'         => 'Host LDAP',
	'plugin.ldap-login.f.port'         => 'Port',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (puste = anonimowe)',
	'plugin.ldap-login.f.bind_pass'    => 'Hasło bind',
	'plugin.ldap-login.f.base_dn'      => 'Bazowy DN',
	'plugin.ldap-login.f.search_filter' => 'Filtr (%u = wpisany login)',
	'plugin.ldap-login.f.mail_attr'    => 'Atrybut zawierający e-mail IMAP',
];