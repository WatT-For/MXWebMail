<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'Login-Auflösung via LDAP: uid/kurze E-Mail > vollständige IMAP-Adresse',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS abgelehnt',
	'plugin.ldap-login.bind_denied'    => 'LDAP-Bind abgelehnt',
	'plugin.ldap-login.f.host'         => 'LDAP-Host',
	'plugin.ldap-login.f.port'         => 'Port',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind-DN (leer = anonym)',
	'plugin.ldap-login.f.bind_pass'    => 'Bind-Passwort',
	'plugin.ldap-login.f.base_dn'      => 'Basis-DN',
	'plugin.ldap-login.f.search_filter' => 'Filter (%u = Login-Eingabe)',
	'plugin.ldap-login.f.mail_attr'    => 'Attribut mit der IMAP-E-Mail',
];