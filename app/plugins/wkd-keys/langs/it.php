<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Scoperta automatica delle chiavi PGP (WKD + raccolta Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'Fallback keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Interroga il keyserver pubblico quando WKD non dà risultati',
	'plugin.wkd-keys.f.autocrypt'              => 'Raccogli header Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Memorizza le chiavi inviate dai corrispondenti nell\'header Autocrypt',
	'plugin.wkd-keys.bad_email'                => 'Indirizzo email non valido',
	'plugin.wkd-keys.import_confirm'           => 'Trovata una chiave PGP per {email}. Importarla?',
	'plugin.wkd-keys.imported'                 => 'Chiave PGP importata.',
	'plugin.wkd-keys.label'                    => 'Chiavi WKD',
];
