<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Voegt de X-Originating-IP-header (IP van de afzender) toe aan uitgaande berichten',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Proxies respecteren',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Gebruikt het echte client-IP (X-Forwarded-For via trusted_proxies) in plaats van REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'X-Originating-IP-header',
];