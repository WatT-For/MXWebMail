<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Resolución de inicio de sesión vía LDAP: uid/correo corto > dirección IMAP completa',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS denegado',
	'plugin.ldap-login.bind_denied'    => 'Bind LDAP denegado',
	'plugin.ldap-login.f.host'         => 'Host LDAP',
	'plugin.ldap-login.f.port'         => 'Puerto',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (vacío = anónimo)',
	'plugin.ldap-login.f.bind_pass'    => 'Contraseña de bind',
	'plugin.ldap-login.f.base_dn'      => 'Base DN',
	'plugin.ldap-login.f.search_filter' => 'Filtro (%u = entrada de login)',
	'plugin.ldap-login.f.mail_attr'    => 'Atributo que contiene el correo IMAP',
	'plugin.ldap-login.label'          => 'Acceso LDAP',
];