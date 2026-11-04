<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin ldap-login. */
return [
	'plugin.ldap-login.description'    => 'LDAP ile giriş çözümleme: uid/kısa e-posta > tam IMAP adresi',
	'plugin.ldap-login.starttls_denied' => 'STARTTLS reddedildi',
	'plugin.ldap-login.bind_denied'    => 'LDAP bind reddedildi',
	'plugin.ldap-login.f.host'         => 'LDAP sunucusu',
	'plugin.ldap-login.f.port'         => 'Bağlantı noktası',
	'plugin.ldap-login.f.use_tls'      => 'STARTTLS',
	'plugin.ldap-login.f.bind_dn'      => 'Bind DN (boş = anonim)',
	'plugin.ldap-login.f.bind_pass'    => 'Bind parolası',
	'plugin.ldap-login.f.base_dn'      => 'Temel DN',
	'plugin.ldap-login.f.search_filter' => 'Filtre (%u = giriş girdisi)',
	'plugin.ldap-login.f.mail_attr'    => 'IMAP e-postasını içeren öznitelik',
	'plugin.ldap-login.label'          => 'LDAP girişi',
];