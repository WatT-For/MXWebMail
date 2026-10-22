<?php
declare(strict_types=1);

/** Dictionnaire français du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => "Ajoute l'en-tête X-Originating-IP (IP de l'expéditeur) aux messages sortants",
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Tenir compte des proxies',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => "Utilise l'IP cliente réelle (X-Forwarded-For via trusted_proxies) au lieu de REMOTE_ADDR",
];
