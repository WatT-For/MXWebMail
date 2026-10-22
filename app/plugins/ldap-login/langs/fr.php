<?php
declare(strict_types=1);

/** Dictionnaire français du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => "Résolution du login via LDAP : uid/courriel court → adresse IMAP complète",
	'plugin.ldap-login.starttls_denied' => 'STARTTLS refusé',
	'plugin.ldap-login.bind_denied'    => 'Bind LDAP refusé',
	'plugin.ldap-login.f.host'         => 'Hôte LDAP',
	'plugin.ldap-login.f.port'         => 'Port',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'DN de bind (vide = anonyme)',
	'plugin.ldap-login.f.bind_pass'    => 'Mot de passe bind',
	'plugin.ldap-login.f.base_dn'      => 'Base DN',
	'plugin.ldap-login.f.search_filter' => 'Filtre (%u = saisie de login)',
	'plugin.ldap-login.f.mail_attr'    => "Attribut contenant l'email IMAP",
];
