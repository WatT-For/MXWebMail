<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Suggerimenti di contatti da una directory LDAP (autocompletamento)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect non riuscito',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS negato',
	'plugin.ldap-contacts.bind_denied'   => 'Bind LDAP negato',
	'plugin.ldap-contacts.anon_bind_denied' => 'Bind anonimo negato',
	'plugin.ldap-contacts.search_failed' => 'Ricerca LDAP non riuscita',
	'plugin.ldap-contacts.f.host'        => 'Host LDAP',
	'plugin.ldap-contacts.f.port'        => 'Porta',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Password di bind',
	'plugin.ldap-contacts.f.base_dn'     => 'Base DN di ricerca',
	'plugin.ldap-contacts.f.filter'      => 'Filtro (%q = query)',
	'plugin.ldap-contacts.f.name_attr'   => 'Attributo nome',
	'plugin.ldap-contacts.f.mail_attr'   => 'Attributo email',
	'plugin.ldap-contacts.f.max_results' => 'Risultati massimi',
];