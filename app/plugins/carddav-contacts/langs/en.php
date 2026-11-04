<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Contacts synchronisation from a CardDAV address book (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Remote address book (CardDAV)',
	'plugin.carddav-contacts.title'          => 'CardDAV synchronisation',
	'plugin.carddav-contacts.help'           => 'Imports contacts from a remote address book (Nextcloud, Radicale, iCloud…) into your local contacts. Imported contacts are replaced at each sync; your local contacts are untouched.',
	'plugin.carddav-contacts.url'            => 'CardDAV URL',
	'plugin.carddav-contacts.url_hint'       => '(e.g. https://cloud.example.com or /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'User',
	'plugin.carddav-contacts.password'       => 'Password',
	'plugin.carddav-contacts.password_hint'  => '(empty = unchanged)',
	'plugin.carddav-contacts.sync'           => 'Synchronize',
	'plugin.carddav-contacts.last_sync'      => 'Last sync: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Never synchronized.',
	'plugin.carddav-contacts.saved'          => 'Settings saved.',
	'plugin.carddav-contacts.testing'        => 'Testing…',
	'plugin.carddav-contacts.syncing'        => 'Synchronizing…',
	'plugin.carddav-contacts.test_ok'        => 'Connection OK - address book “{book}”, {n} card(s).',
	'plugin.carddav-contacts.imported'       => '{n} contact(s) imported.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV not configured',
	'plugin.carddav-contacts.no_addressbook' => 'No CardDAV address book found at this URL',
	'plugin.carddav-contacts.bad_url'        => 'Invalid CardDAV URL (HTTPS and a public host are required)',
	'plugin.carddav-contacts.pass_clear'  => 'Clear password',
	'plugin.carddav-contacts.label'         => 'CardDAV contacts',
];
