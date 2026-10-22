<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Добавляет заголовок X-Originating-IP (IP отправителя) к исходящим сообщениям',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Учитывать прокси',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Использует реальный IP клиента (X-Forwarded-For через trusted_proxies) вместо REMOTE_ADDR',
];