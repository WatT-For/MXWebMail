<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'               => 'Découverte automatique des clés PGP (WKD + récolte Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'      => 'Secours keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help' => 'Interroger le serveur de clés public quand WKD ne donne rien',
	'plugin.wkd-keys.f.autocrypt'               => 'Récolter les en-têtes Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'          => 'Mémoriser les clés envoyées par les correspondants dans l\'en-tête Autocrypt',
	'plugin.wkd-keys.bad_email'                 => 'Adresse email invalide',
	'plugin.wkd-keys.import_confirm'            => 'Une clé PGP a été trouvée pour {email}. L\'importer ?',
	'plugin.wkd-keys.imported'                  => 'Clé PGP importée.',
];
