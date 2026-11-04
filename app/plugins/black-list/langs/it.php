<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Blacklist di accesso (jolly ed eccezioni)',
	'plugin.black-list.account_denied'   => 'Account non consentito',
	'plugin.black-list.f.black_list'     => 'Blacklist',
	'plugin.black-list.f.black_list.help' => 'Email bloccate, separate da spazi, jolly supportati. Es.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Eccezioni',
	'plugin.black-list.f.exceptions.help' => 'Email in blacklist che restano consentite. Es.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Errore di autenticazione',
	'plugin.black-list.f.auth_error.help' => 'Restituisce un errore generico di credenziali invece di "account non consentito"',
	'plugin.black-list.label'            => 'Lista nera',
];