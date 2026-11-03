<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Чёрный список логинов (шаблоны и исключения)',
	'plugin.black-list.account_denied'   => 'Учётная запись не разрешена',
	'plugin.black-list.f.black_list'     => 'Чёрный список',
	'plugin.black-list.f.black_list.help' => 'Заблокированные адреса, через пробел, поддерживаются шаблоны. Напр.: *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Исключения',
	'plugin.black-list.f.exceptions.help' => 'Адреса из чёрного списка, которые остаются разрешёнными. Напр.: admin@domain1.com',
	'plugin.black-list.f.auth_error'     => 'Ошибка аутентификации',
	'plugin.black-list.f.auth_error.help' => 'Возвращает общую ошибку учётных данных вместо "учётная запись не разрешена"',
];