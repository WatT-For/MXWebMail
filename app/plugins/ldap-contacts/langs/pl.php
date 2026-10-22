<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Sugestie kontaktów z katalogu LDAP (autouzupełnianie)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect nie powiódł się',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS odrzucone',
	'plugin.ldap-contacts.bind_denied'   => 'Bind LDAP odrzucony',
	'plugin.ldap-contacts.anon_bind_denied' => 'Anonimowy bind odrzucony',
	'plugin.ldap-contacts.search_failed' => 'Wyszukiwanie LDAP nie powiodło się',
	'plugin.ldap-contacts.f.host'        => 'Host LDAP',
	'plugin.ldap-contacts.f.port'        => 'Port',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Hasło bind',
	'plugin.ldap-contacts.f.base_dn'     => 'Bazowy DN wyszukiwania',
	'plugin.ldap-contacts.f.filter'      => 'Filtr (%q = zapytanie)',
	'plugin.ldap-contacts.f.name_attr'   => 'Atrybut nazwy',
	'plugin.ldap-contacts.f.mail_attr'   => 'Atrybut e-maila',
	'plugin.ldap-contacts.f.max_results' => 'Maks. wyników',
];