<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin add-x-originating-ip-header. */
return [
	'plugin.add-x-originating-ip-header.description' => 'Aggiunge l\'header X-Originating-IP (IP del mittente) ai messaggi in uscita',
	'plugin.add-x-originating-ip-header.f.check_proxy' => 'Rispetta i proxy',
	'plugin.add-x-originating-ip-header.f.check_proxy.help' => 'Usa l\'IP reale del client (X-Forwarded-For via trusted_proxies) invece di REMOTE_ADDR',
	'plugin.add-x-originating-ip-header.label'             => 'Intestazione X-Originating-IP',
];