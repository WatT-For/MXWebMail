<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Lista de permissões de login (curingas e exceções)',
	'plugin.white-list.account_denied'   => 'Conta não permitida',
	'plugin.white-list.f.white_list'     => 'Lista de permissões',
	'plugin.white-list.f.white_list.help' => 'E-mails permitidos, separados por espaço, curingas suportados. Ex.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Exceções',
	'plugin.white-list.f.exceptions.help' => 'Entre os e-mails fora da lista de permissões, apenas estes são rejeitados (vazio = todos rejeitados). Ex.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Erro de autenticação',
	'plugin.white-list.f.auth_error.help' => 'Retorna um erro de credenciais genérico em vez de "conta não permitida"',
];