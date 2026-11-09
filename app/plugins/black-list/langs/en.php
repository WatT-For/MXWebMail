<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Login blacklist (wildcards and exceptions)',
	'plugin.black-list.account_denied'   => 'Account not allowed',
	'plugin.black-list.f.black_list'     => 'Blacklist',
	'plugin.black-list.f.black_list.help' => 'Blocked emails, space-separated, wildcards supported. E.g.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Exceptions',
	'plugin.black-list.f.exceptions.help' => 'Blacklisted emails that remain allowed. E.g.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Authentication error',
	'plugin.black-list.f.auth_error.help' => 'Returns a generic credentials error instead of "account not allowed"',
	'plugin.black-list.label'            => 'Blacklist',
];
