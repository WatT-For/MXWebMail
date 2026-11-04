<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Fügt den Header X-Originating-IP (IP des Absenders) zu ausgehenden Nachrichten hinzu',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Proxys berücksichtigen',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Verwendet die echte Client-IP (X-Forwarded-For via trusted_proxies) statt REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'X-Originating-IP-Header',
];