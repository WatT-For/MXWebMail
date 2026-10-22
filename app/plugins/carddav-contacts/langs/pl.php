<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Synchronizacja kontaktów z książki adresowej CardDAV (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Zdalna książka adresowa (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Synchronizacja CardDAV',
	'plugin.carddav-contacts.help'           => 'Importuje kontakty ze zdalnej książki adresowej (Nextcloud, Radicale, iCloud…) do lokalnych kontaktów. Zaimportowane kontakty są zastępowane przy każdej synchronizacji; lokalne kontakty pozostają nienaruszone.',
	'plugin.carddav-contacts.url'            => 'URL CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(np. https://cloud.example.com lub /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Użytkownik',
	'plugin.carddav-contacts.password'       => 'Hasło',
	'plugin.carddav-contacts.password_hint'  => '(puste = bez zmian)',
	'plugin.carddav-contacts.sync'           => 'Synchronizuj',
	'plugin.carddav-contacts.last_sync'      => 'Ostatnia synchronizacja: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Nigdy nie synchronizowano.',
	'plugin.carddav-contacts.saved'          => 'Ustawienia zapisane.',
	'plugin.carddav-contacts.testing'        => 'Testowanie…',
	'plugin.carddav-contacts.syncing'        => 'Synchronizacja…',
	'plugin.carddav-contacts.test_ok'        => 'Połączenie OK — książka «{book}», {n} kart.',
	'plugin.carddav-contacts.imported'       => 'Zaimportowano {n} kontaktów.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV nieskonfigurowane',
	'plugin.carddav-contacts.no_addressbook' => 'Nie znaleziono książki CardDAV pod tym adresem URL',
	'plugin.carddav-contacts.bad_url'        => 'Nieprawidłowy adres URL CardDAV (wymagane HTTPS i publiczny host)',
];