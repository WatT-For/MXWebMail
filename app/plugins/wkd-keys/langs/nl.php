<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Automatic PGP key discovery (WKD + Autocrypt harvesting)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'keys.openpgp.org fallback',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Query the public keyserver when WKD yields nothing',
	'plugin.wkd-keys.f.autocrypt'              => 'Harvest Autocrypt headers',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Store keys sent by correspondents in the Autocrypt header',
	'plugin.wkd-keys.bad_email'                => 'Invalid email address',
	'plugin.wkd-keys.import_confirm'           => 'A PGP key was found for {email}. Import it?',
	'plugin.wkd-keys.imported'                 => 'PGP key imported.',
];
