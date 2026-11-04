<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Login-Whitelist (Wildcards und Ausnahmen)',
	'plugin.white-list.account_denied'   => 'Konto nicht erlaubt',
	'plugin.white-list.f.white_list'     => 'Whitelist',
	'plugin.white-list.f.white_list.help' => 'Erlaubte E-Mails, durch Leerzeichen getrennt, Wildcards unterstützt. Z. B.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Ausnahmen',
	'plugin.white-list.f.exceptions.help' => 'Von den E-Mails außerhalb der Whitelist werden nur diese abgelehnt (leer = alle abgelehnt). Z. B.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Authentifizierungsfehler',
	'plugin.white-list.f.auth_error.help' => 'Gibt statt „Konto nicht erlaubt" einen generischen Fehler zurück',
	'plugin.white-list.label'            => 'Weiße Liste',
];