<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Automatyczne wykrywanie kluczy PGP (WKD + zbieranie Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'Awaryjnie keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Odpytaj publiczny serwer kluczy, gdy WKD nic nie daje',
	'plugin.wkd-keys.f.autocrypt'              => 'Zbieraj nagłówki Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Przechowuj klucze wysyłane przez korespondentów w nagłówku Autocrypt',
	'plugin.wkd-keys.bad_email'                => 'Nieprawidłowy adres e-mail',
	'plugin.wkd-keys.import_confirm'           => 'Znaleziono klucz PGP dla {email}. Zaimportować?',
	'plugin.wkd-keys.imported'                 => 'Klucz PGP zaimportowany.',
	'plugin.wkd-keys.label'                    => 'Klucze WKD',
];
