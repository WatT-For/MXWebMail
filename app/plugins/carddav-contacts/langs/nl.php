<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Contactensynchronisatie vanuit een CardDAV-adresboek (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Extern adresboek (CardDAV)',
	'plugin.carddav-contacts.title'          => 'CardDAV-synchronisatie',
	'plugin.carddav-contacts.help'           => 'Importeert contacten van een extern adresboek (Nextcloud, Radicale, iCloud…) naar uw lokale contacten. Geïmporteerde contacten worden bij elke synchronisatie vervangen; uw lokale contacten blijven ongewijzigd.',
	'plugin.carddav-contacts.url'            => 'CardDAV-URL',
	'plugin.carddav-contacts.url_hint'       => '(bijv. https://cloud.example.com of /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Gebruiker',
	'plugin.carddav-contacts.password'       => 'Wachtwoord',
	'plugin.carddav-contacts.password_hint'  => '(leeg = ongewijzigd)',
	'plugin.carddav-contacts.sync'           => 'Synchroniseren',
	'plugin.carddav-contacts.last_sync'      => 'Laatste synchronisatie: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Nooit gesynchroniseerd.',
	'plugin.carddav-contacts.saved'          => 'Instellingen opgeslagen.',
	'plugin.carddav-contacts.testing'        => 'Testen…',
	'plugin.carddav-contacts.syncing'        => 'Synchroniseren…',
	'plugin.carddav-contacts.test_ok'        => 'Verbinding OK - adresboek "{book}", {n} kaart(en).',
	'plugin.carddav-contacts.imported'       => '{n} contact(en) geïmporteerd.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV niet geconfigureerd',
	'plugin.carddav-contacts.no_addressbook' => 'Geen CardDAV-adresboek gevonden op deze URL',
	'plugin.carddav-contacts.bad_url'        => 'Ongeldige CardDAV-URL (HTTPS en een publieke host vereist)',
	'plugin.carddav-contacts.pass_clear'  => 'Wachtwoord wissen',
];