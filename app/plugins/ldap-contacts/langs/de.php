<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Kontaktvorschläge aus einem LDAP-Verzeichnis (Autovervollständigung)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect fehlgeschlagen',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS abgelehnt',
	'plugin.ldap-contacts.bind_denied'   => 'LDAP-Bind abgelehnt',
	'plugin.ldap-contacts.anon_bind_denied' => 'Anonymer Bind abgelehnt',
	'plugin.ldap-contacts.search_failed' => 'LDAP-Suche fehlgeschlagen',
	'plugin.ldap-contacts.f.host'        => 'LDAP-Host',
	'plugin.ldap-contacts.f.port'        => 'Port',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind-DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Bind-Passwort',
	'plugin.ldap-contacts.f.base_dn'     => 'Such-Basis-DN',
	'plugin.ldap-contacts.f.filter'      => 'Filter (%q = Abfrage)',
	'plugin.ldap-contacts.f.name_attr'   => 'Namensattribut',
	'plugin.ldap-contacts.f.mail_attr'   => 'E-Mail-Attribut',
	'plugin.ldap-contacts.f.max_results' => 'Max. Ergebnisse',
	'plugin.ldap-contacts.label'           => 'LDAP-Kontakte',
];