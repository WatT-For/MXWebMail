<?php
declare(strict_types=1);

/** Dictionnaire français du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => "Synchronisation des contacts depuis un carnet CardDAV (Nextcloud, Radicale, iCloud…)",
	'plugin.carddav-contacts.tab'            => 'Carnet distant (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Synchronisation CardDAV',
	'plugin.carddav-contacts.help'           => "Importe les contacts d'un carnet distant (Nextcloud, Radicale, iCloud…) dans vos contacts locaux. Les contacts importés sont remplacés à chaque synchro ; vos contacts locaux ne sont pas touchés.",
	'plugin.carddav-contacts.url'            => 'URL CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(ex. https://cloud.example.com ou /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Utilisateur',
	'plugin.carddav-contacts.password'       => 'Mot de passe',
	'plugin.carddav-contacts.password_hint'  => '(vide = inchangé)',
	'plugin.carddav-contacts.sync'           => 'Synchroniser',
	'plugin.carddav-contacts.last_sync'      => 'Dernière synchro : {date}',
	'plugin.carddav-contacts.never_synced'   => 'Jamais synchronisé.',
	'plugin.carddav-contacts.saved'          => 'Configuration enregistrée.',
	'plugin.carddav-contacts.testing'        => 'Test en cours…',
	'plugin.carddav-contacts.syncing'        => 'Synchronisation en cours…',
	'plugin.carddav-contacts.test_ok'        => 'Connexion OK — carnet « {book} », {n} fiche(s).',
	'plugin.carddav-contacts.imported'       => '{n} contact(s) importé(s).',
	'plugin.carddav-contacts.not_configured' => 'CardDAV non configuré',
	'plugin.carddav-contacts.no_addressbook' => 'Aucun carnet CardDAV trouvé à cette URL',
	'plugin.carddav-contacts.bad_url'        => 'URL CardDAV invalide (HTTPS et hôte public requis)',
];
