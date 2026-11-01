<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Automatische PGP-Schlüsselsuche (WKD + Autocrypt-Harvesting)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'keys.openpgp.org-Fallback',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Öffentlichen Keyserver abfragen, wenn WKD nichts liefert',
	'plugin.wkd-keys.f.autocrypt'              => 'Autocrypt-Header sammeln',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Von Korrespondenten im Autocrypt-Header gesendete Schlüssel speichern',
	'plugin.wkd-keys.bad_email'                => 'Ungültige E-Mail-Adresse',
	'plugin.wkd-keys.import_confirm'           => 'Für {email} wurde ein PGP-Schlüssel gefunden. Importieren?',
	'plugin.wkd-keys.imported'                 => 'PGP-Schlüssel importiert.',
];
