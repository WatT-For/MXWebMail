<?php
declare(strict_types=1);

/** Dictionnaire français du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => "Suggestions de contacts depuis un annuaire LDAP (autocomplete)",
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect impossible',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS refusé',
	'plugin.ldap-contacts.bind_denied'   => 'Bind LDAP refusé',
	'plugin.ldap-contacts.anon_bind_denied' => 'Bind anonyme refusé',
	'plugin.ldap-contacts.search_failed' => 'Recherche LDAP échouée',
	'plugin.ldap-contacts.f.host'        => 'Hôte LDAP',
	'plugin.ldap-contacts.f.port'        => 'Port',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'DN de bind',
	'plugin.ldap-contacts.f.bind_pass'   => 'Mot de passe bind',
	'plugin.ldap-contacts.f.base_dn'     => 'Base DN de recherche',
	'plugin.ldap-contacts.f.filter'      => 'Filtre (%q = requête)',
	'plugin.ldap-contacts.f.name_attr'   => 'Attribut nom',
	'plugin.ldap-contacts.f.mail_attr'   => 'Attribut email',
	'plugin.ldap-contacts.f.max_results' => 'Résultats max',
];
