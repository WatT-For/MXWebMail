<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Белый список логинов (шаблоны и исключения)',
	'plugin.white-list.account_denied'   => 'Учётная запись не разрешена',
	'plugin.white-list.f.white_list'     => 'Белый список',
	'plugin.white-list.f.white_list.help' => 'Разрешённые адреса, через пробел, поддерживаются шаблоны. Напр.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Исключения',
	'plugin.white-list.f.exceptions.help' => 'Среди адресов вне белого списка отклоняются только эти (пусто = отклоняются все). Напр.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Ошибка аутентификации',
	'plugin.white-list.f.auth_error.help' => 'Возвращает общую ошибку учётных данных вместо "учётная запись не разрешена"',
];