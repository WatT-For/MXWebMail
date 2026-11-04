<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Kontaktsynchronisierung aus einem CardDAV-Adressbuch (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Entferntes Adressbuch (CardDAV)',
	'plugin.carddav-contacts.title'          => 'CardDAV-Synchronisierung',
	'plugin.carddav-contacts.help'           => 'Importiert Kontakte aus einem entfernten Adressbuch (Nextcloud, Radicale, iCloud…) in Ihre lokalen Kontakte. Importierte Kontakte werden bei jeder Synchronisierung ersetzt; Ihre lokalen Kontakte bleiben unberührt.',
	'plugin.carddav-contacts.url'            => 'CardDAV-URL',
	'plugin.carddav-contacts.url_hint'       => '(z. B. https://cloud.example.com oder /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Benutzer',
	'plugin.carddav-contacts.password'       => 'Passwort',
	'plugin.carddav-contacts.password_hint'  => '(leer = unverändert)',
	'plugin.carddav-contacts.sync'           => 'Synchronisieren',
	'plugin.carddav-contacts.last_sync'      => 'Letzte Synchronisierung: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Noch nie synchronisiert.',
	'plugin.carddav-contacts.saved'          => 'Einstellungen gespeichert.',
	'plugin.carddav-contacts.testing'        => 'Test läuft…',
	'plugin.carddav-contacts.syncing'        => 'Synchronisierung läuft…',
	'plugin.carddav-contacts.test_ok'        => 'Verbindung OK - Adressbuch „{book}", {n} Karte(n).',
	'plugin.carddav-contacts.imported'       => '{n} Kontakt(e) importiert.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV nicht konfiguriert',
	'plugin.carddav-contacts.no_addressbook' => 'Kein CardDAV-Adressbuch unter dieser URL gefunden',
	'plugin.carddav-contacts.bad_url'        => 'Ungültige CardDAV-URL (HTTPS und öffentlicher Host erforderlich)',
	'plugin.carddav-contacts.pass_clear'  => 'Passwort löschen',
	'plugin.carddav-contacts.label'         => 'CardDAV-Kontakte',
];