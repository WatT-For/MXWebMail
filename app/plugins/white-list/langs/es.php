<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Lista blanca de inicio de sesión (comodines y excepciones)',
	'plugin.white-list.account_denied'   => 'Cuenta no permitida',
	'plugin.white-list.f.white_list'     => 'Lista blanca',
	'plugin.white-list.f.white_list.help' => 'Correos permitidos, separados por espacios, se admiten comodines. Ej.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Excepciones',
	'plugin.white-list.f.exceptions.help' => 'Entre los correos fuera de la lista blanca, solo estos se rechazan (vacío = todos rechazados). Ej.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Error de autenticación',
	'plugin.white-list.f.auth_error.help' => 'Devuelve un error de credenciales genérico en lugar de «cuenta no permitida»',
];