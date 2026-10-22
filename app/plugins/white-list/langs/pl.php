<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Biała lista logowania (wieloznaczniki i wyjątki)',
	'plugin.white-list.account_denied'   => 'Konto niedozwolone',
	'plugin.white-list.f.white_list'     => 'Biała lista',
	'plugin.white-list.f.white_list.help' => 'Dozwolone e-maile, oddzielone spacjami, obsługiwane wieloznaczniki. Np.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Wyjątki',
	'plugin.white-list.f.exceptions.help' => 'Spośród e-maili spoza białej listy odrzucane są tylko te (puste = wszystkie odrzucane). Np.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Błąd uwierzytelniania',
	'plugin.white-list.f.auth_error.help' => 'Zwraca ogólny błąd poświadczeń zamiast «konto niedozwolone»',
];