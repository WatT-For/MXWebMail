<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Automatische PGP-sleuteldetectie (WKD + Autocrypt-harvesting)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'keys.openpgp.org-fallback',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Openbare keyserver raadplegen wanneer WKD niets oplevert',
	'plugin.wkd-keys.f.autocrypt'              => 'Autocrypt-headers verzamelen',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Sleutels opslaan die correspondenten in de Autocrypt-header sturen',
	'plugin.wkd-keys.bad_email'                => 'Ongeldig e-mailadres',
	'plugin.wkd-keys.import_confirm'           => 'Er is een PGP-sleutel gevonden voor {email}. Importeren?',
	'plugin.wkd-keys.imported'                 => 'PGP-sleutel geïmporteerd.',
	'plugin.wkd-keys.label'                    => 'WKD-sleutels',
];
