<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Contactsuggesties uit een LDAP-directory (automatisch aanvullen)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect mislukt',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS geweigerd',
	'plugin.ldap-contacts.bind_denied'   => 'LDAP-bind geweigerd',
	'plugin.ldap-contacts.anon_bind_denied' => 'Anonieme bind geweigerd',
	'plugin.ldap-contacts.search_failed' => 'LDAP-zoekopdracht mislukt',
	'plugin.ldap-contacts.f.host'        => 'LDAP-host',
	'plugin.ldap-contacts.f.port'        => 'Poort',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind-DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Bind-wachtwoord',
	'plugin.ldap-contacts.f.base_dn'     => 'Zoekbasis-DN',
	'plugin.ldap-contacts.f.filter'      => 'Filter (%q = zoekopdracht)',
	'plugin.ldap-contacts.f.name_attr'   => 'Naamattribuut',
	'plugin.ldap-contacts.f.mail_attr'   => 'E-mailattribuut',
	'plugin.ldap-contacts.f.max_results' => 'Max. resultaten',
	'plugin.ldap-contacts.label'           => 'LDAP-contacten',
];