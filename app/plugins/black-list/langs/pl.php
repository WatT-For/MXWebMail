<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Czarna lista logowania (wieloznaczniki i wyjątki)',
	'plugin.black-list.account_denied'   => 'Konto niedozwolone',
	'plugin.black-list.f.black_list'     => 'Czarna lista',
	'plugin.black-list.f.black_list.help' => 'Zablokowane e-maile, oddzielone spacjami, obsługiwane wieloznaczniki. Np.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Wyjątki',
	'plugin.black-list.f.exceptions.help' => 'E-maile z czarnej listy, które pozostają dozwolone. Np.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Błąd uwierzytelniania',
	'plugin.black-list.f.auth_error.help' => 'Zwraca ogólny błąd poświadczeń zamiast "konto niedozwolone"',
	'plugin.black-list.label'            => 'Czarna lista',
];