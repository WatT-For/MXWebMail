<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Login-whitelist (wildcards en uitzonderingen)',
	'plugin.white-list.account_denied'   => 'Account niet toegestaan',
	'plugin.white-list.f.white_list'     => 'Whitelist',
	'plugin.white-list.f.white_list.help' => 'Toegestane e-mails, gescheiden door spaties, wildcards ondersteund. Bijv.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Uitzonderingen',
	'plugin.white-list.f.exceptions.help' => 'Van de e-mails buiten de whitelist worden alleen deze geweigerd (leeg = alle geweigerd). Bijv.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Authenticatiefout',
	'plugin.white-list.f.auth_error.help' => 'Geeft een algemene inlogfout in plaats van "account niet toegestaan"',
	'plugin.white-list.label'            => 'Witte lijst',
];