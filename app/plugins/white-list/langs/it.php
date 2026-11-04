<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Whitelist di accesso (jolly ed eccezioni)',
	'plugin.white-list.account_denied'   => 'Account non consentito',
	'plugin.white-list.f.white_list'     => 'Whitelist',
	'plugin.white-list.f.white_list.help' => 'Email consentite, separate da spazi, jolly supportati. Es.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Eccezioni',
	'plugin.white-list.f.exceptions.help' => 'Tra le email fuori dalla whitelist, solo queste vengono rifiutate (vuoto = tutte rifiutate). Es.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Errore di autenticazione',
	'plugin.white-list.f.auth_error.help' => 'Restituisce un errore generico di credenziali invece di "account non consentito"',
	'plugin.white-list.label'            => 'Lista bianca',
];