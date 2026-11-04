<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Lista negra de inicio de sesión (comodines y excepciones)',
	'plugin.black-list.account_denied'   => 'Cuenta no permitida',
	'plugin.black-list.f.black_list'     => 'Lista negra',
	'plugin.black-list.f.black_list.help' => 'Correos bloqueados, separados por espacios, se admiten comodines. Ej.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Excepciones',
	'plugin.black-list.f.exceptions.help' => 'Correos en lista negra que siguen permitidos. Ej.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Error de autenticación',
	'plugin.black-list.f.auth_error.help' => 'Devuelve un error de credenciales genérico en lugar de "cuenta no permitida"',
	'plugin.black-list.label'            => 'Lista negra',
];