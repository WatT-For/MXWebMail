<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Contact suggestions from an LDAP directory (autocomplete)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect failed',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS denied',
	'plugin.ldap-contacts.bind_denied'   => 'LDAP bind denied',
	'plugin.ldap-contacts.anon_bind_denied' => 'Anonymous bind denied',
	'plugin.ldap-contacts.search_failed' => 'LDAP search failed',
	'plugin.ldap-contacts.f.host'        => 'LDAP host',
	'plugin.ldap-contacts.f.port'        => 'Port',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Bind password',
	'plugin.ldap-contacts.f.base_dn'     => 'Search base DN',
	'plugin.ldap-contacts.f.filter'      => 'Filter (%q = query)',
	'plugin.ldap-contacts.f.name_attr'   => 'Name attribute',
	'plugin.ldap-contacts.f.mail_attr'   => 'Email attribute',
	'plugin.ldap-contacts.f.max_results' => 'Max results',
];
