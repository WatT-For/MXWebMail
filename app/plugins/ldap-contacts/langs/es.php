<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Sugerencias de contactos desde un directorio LDAP (autocompletado)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect falló',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS denegado',
	'plugin.ldap-contacts.bind_denied'   => 'Bind LDAP denegado',
	'plugin.ldap-contacts.anon_bind_denied' => 'Bind anónimo denegado',
	'plugin.ldap-contacts.search_failed' => 'Búsqueda LDAP fallida',
	'plugin.ldap-contacts.f.host'        => 'Host LDAP',
	'plugin.ldap-contacts.f.port'        => 'Puerto',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Contraseña de bind',
	'plugin.ldap-contacts.f.base_dn'     => 'Base DN de búsqueda',
	'plugin.ldap-contacts.f.filter'      => 'Filtro (%q = consulta)',
	'plugin.ldap-contacts.f.name_attr'   => 'Atributo de nombre',
	'plugin.ldap-contacts.f.mail_attr'   => 'Atributo de correo',
	'plugin.ldap-contacts.f.max_results' => 'Máx. resultados',
];