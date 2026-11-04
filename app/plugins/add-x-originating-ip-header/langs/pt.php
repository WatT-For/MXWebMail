<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Adiciona o cabeçalho X-Originating-IP (IP do remetente) às mensagens de saída',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Respeitar proxies',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Usa o IP real do cliente (X-Forwarded-For via trusted_proxies) em vez de REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'Cabeçalho X-Originating-IP',
];