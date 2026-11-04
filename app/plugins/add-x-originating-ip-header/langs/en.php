<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Adds the X-Originating-IP header (sender’s IP) to outgoing messages',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Honor proxies',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Uses the real client IP (X-Forwarded-For via trusted_proxies) instead of REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'X-Originating-IP header',
];
