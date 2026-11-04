<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Sincronizzazione dei contatti da una rubrica CardDAV (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Rubrica remota (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Sincronizzazione CardDAV',
	'plugin.carddav-contacts.help'           => 'Importa i contatti da una rubrica remota (Nextcloud, Radicale, iCloud…) nei tuoi contatti locali. I contatti importati vengono sostituiti a ogni sincronizzazione; i tuoi contatti locali non vengono toccati.',
	'plugin.carddav-contacts.url'            => 'URL CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(es. https://cloud.example.com o /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Utente',
	'plugin.carddav-contacts.password'       => 'Password',
	'plugin.carddav-contacts.password_hint'  => '(vuoto = invariata)',
	'plugin.carddav-contacts.sync'           => 'Sincronizza',
	'plugin.carddav-contacts.last_sync'      => 'Ultima sincronizzazione: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Mai sincronizzata.',
	'plugin.carddav-contacts.saved'          => 'Impostazioni salvate.',
	'plugin.carddav-contacts.testing'        => 'Test…',
	'plugin.carddav-contacts.syncing'        => 'Sincronizzazione…',
	'plugin.carddav-contacts.test_ok'        => 'Connessione OK - rubrica "{book}", {n} scheda/e.',
	'plugin.carddav-contacts.imported'       => '{n} contatto/i importati.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV non configurato',
	'plugin.carddav-contacts.no_addressbook' => 'Nessuna rubrica CardDAV trovata a questo URL',
	'plugin.carddav-contacts.bad_url'        => 'URL CardDAV non valido (richiesti HTTPS e host pubblico)',
	'plugin.carddav-contacts.pass_clear'  => 'Cancella la password',
	'plugin.carddav-contacts.label'         => 'Contatti CardDAV',
];