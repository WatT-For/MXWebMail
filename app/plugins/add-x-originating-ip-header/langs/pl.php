<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Dodaje nagłówek X-Originating-IP (adres IP nadawcy) do wychodzących wiadomości',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Uwzględniaj serwery proxy',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Używa rzeczywistego adresu IP klienta (X-Forwarded-For przez trusted_proxies) zamiast REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'Nagłówek X-Originating-IP',
];