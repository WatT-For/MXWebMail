<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Login whitelist (wildcards and exceptions)',
	'plugin.white-list.account_denied'   => 'Account not allowed',
	'plugin.white-list.f.white_list'     => 'Whitelist',
	'plugin.white-list.f.white_list.help' => 'Allowed emails, space-separated, wildcards supported. E.g.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Exceptions',
	'plugin.white-list.f.exceptions.help' => 'Among emails outside the whitelist, only these are rejected (empty = all rejected). E.g.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Authentication error',
	'plugin.white-list.f.auth_error.help' => 'Returns a generic credentials error instead of “account not allowed”',
];
