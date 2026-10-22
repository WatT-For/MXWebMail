<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Añade la cabecera X-Originating-IP (IP del remitente) a los mensajes salientes',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Respetar proxies',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Usa la IP real del cliente (X-Forwarded-For vía trusted_proxies) en lugar de REMOTE_ADDR',
];