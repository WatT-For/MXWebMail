<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Sincronización de contactos desde una libreta de direcciones CardDAV (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Libreta remota (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Sincronización CardDAV',
	'plugin.carddav-contacts.help'           => 'Importa contactos de una libreta remota (Nextcloud, Radicale, iCloud…) a tus contactos locales. Los contactos importados se reemplazan en cada sincronización; tus contactos locales no se tocan.',
	'plugin.carddav-contacts.url'            => 'URL de CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(p. ej. https://cloud.example.com o /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Usuario',
	'plugin.carddav-contacts.password'       => 'Contraseña',
	'plugin.carddav-contacts.password_hint'  => '(vacío = sin cambios)',
	'plugin.carddav-contacts.sync'           => 'Sincronizar',
	'plugin.carddav-contacts.last_sync'      => 'Última sincronización: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Nunca sincronizada.',
	'plugin.carddav-contacts.saved'          => 'Ajustes guardados.',
	'plugin.carddav-contacts.testing'        => 'Probando…',
	'plugin.carddav-contacts.syncing'        => 'Sincronizando…',
	'plugin.carddav-contacts.test_ok'        => 'Conexión correcta — libreta «{book}», {n} tarjeta(s).',
	'plugin.carddav-contacts.imported'       => '{n} contacto(s) importados.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV no configurado',
	'plugin.carddav-contacts.no_addressbook' => 'No se encontró ninguna libreta CardDAV en esta URL',
	'plugin.carddav-contacts.bad_url'        => 'URL de CardDAV no válida (se requiere HTTPS y un host público)',
	'plugin.carddav-contacts.pass_clear'  => 'Borrar la contraseña',
];