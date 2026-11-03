<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Login-blacklist (wildcards en uitzonderingen)',
	'plugin.black-list.account_denied'   => 'Account niet toegestaan',
	'plugin.black-list.f.black_list'     => 'Blacklist',
	'plugin.black-list.f.black_list.help' => 'Geblokkeerde e-mails, gescheiden door spaties, wildcards ondersteund. Bijv.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Uitzonderingen',
	'plugin.black-list.f.exceptions.help' => 'E-mails op de blacklist die toegestaan blijven. Bijv.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Authenticatiefout',
	'plugin.black-list.f.auth_error.help' => 'Geeft een algemene inlogfout in plaats van "account niet toegestaan"',
];