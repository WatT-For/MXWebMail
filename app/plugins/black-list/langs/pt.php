<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Lista negra de login (curingas e exceções)',
	'plugin.black-list.account_denied'   => 'Conta não permitida',
	'plugin.black-list.f.black_list'     => 'Lista negra',
	'plugin.black-list.f.black_list.help' => 'E-mails bloqueados, separados por espaço, curingas suportados. Ex.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Exceções',
	'plugin.black-list.f.exceptions.help' => 'E-mails da lista negra que permanecem permitidos. Ex.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Erro de autenticação',
	'plugin.black-list.f.auth_error.help' => 'Retorna um erro de credenciais genérico em vez de "conta não permitida"',
];