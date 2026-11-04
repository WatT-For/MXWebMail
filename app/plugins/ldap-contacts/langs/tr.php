<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin ldap-contacts. */
return [
	'plugin.ldap-contacts.description'   => 'Bir LDAP dizininden kişi önerileri (otomatik tamamlama)',
	'plugin.ldap-contacts.connect_failed' => 'ldap_connect başarısız',
	'plugin.ldap-contacts.starttls_denied' => 'STARTTLS reddedildi',
	'plugin.ldap-contacts.bind_denied'   => 'LDAP bind reddedildi',
	'plugin.ldap-contacts.anon_bind_denied' => 'Anonim bind reddedildi',
	'plugin.ldap-contacts.search_failed' => 'LDAP araması başarısız',
	'plugin.ldap-contacts.f.host'        => 'LDAP sunucusu',
	'plugin.ldap-contacts.f.port'        => 'Bağlantı noktası',
	'plugin.ldap-contacts.f.use_tls'     => 'STARTTLS',
	'plugin.ldap-contacts.f.bind_dn'     => 'Bind DN',
	'plugin.ldap-contacts.f.bind_pass'   => 'Bind parolası',
	'plugin.ldap-contacts.f.base_dn'     => 'Arama temel DN',
	'plugin.ldap-contacts.f.filter'      => 'Filtre (%q = sorgu)',
	'plugin.ldap-contacts.f.name_attr'   => 'Ad özniteliği',
	'plugin.ldap-contacts.f.mail_attr'   => 'E-posta özniteliği',
	'plugin.ldap-contacts.f.max_results' => 'En fazla sonuç',
	'plugin.ldap-contacts.label'           => 'LDAP kişileri',
];