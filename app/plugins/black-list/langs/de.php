<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Login-Blacklist (Wildcards und Ausnahmen)',
	'plugin.black-list.account_denied'   => 'Konto nicht erlaubt',
	'plugin.black-list.f.black_list'     => 'Blacklist',
	'plugin.black-list.f.black_list.help' => 'Blockierte E-Mails, durch Leerzeichen getrennt, Wildcards unterstützt. Z. B.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Ausnahmen',
	'plugin.black-list.f.exceptions.help' => 'Blockierte E-Mails, die erlaubt bleiben. Z. B.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Authentifizierungsfehler',
	'plugin.black-list.f.auth_error.help' => 'Gibt statt „Konto nicht erlaubt" einen generischen Fehler zurück',
	'plugin.black-list.label'            => 'Schwarze Liste',
];