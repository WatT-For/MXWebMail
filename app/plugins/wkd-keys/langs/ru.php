<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Автоматический поиск ключей PGP (WKD + сбор Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'Резерв keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Опрашивать публичный сервер ключей, когда WKD ничего не даёт',
	'plugin.wkd-keys.f.autocrypt'              => 'Собирать заголовки Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Сохранять ключи, присланные корреспондентами в заголовке Autocrypt',
	'plugin.wkd-keys.bad_email'                => 'Недействительный адрес электронной почты',
	'plugin.wkd-keys.import_confirm'           => 'Для {email} найден ключ PGP. Импортировать?',
	'plugin.wkd-keys.imported'                 => 'Ключ PGP импортирован.',
	'plugin.wkd-keys.label'                    => 'Ключи WKD',
];
