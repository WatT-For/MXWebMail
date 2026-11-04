<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Skanowanie ClamAV (clamd) przesyłanych załączników',
	'plugin.attachments-clamav.f.socket'           => 'Gniazdo clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port lub unix:///ścieżka/do/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Limit czasu (sekundy)',
	'plugin.attachments-clamav.f.on_error'         => 'Gdy clamd jest nieosiągalny',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Zezwól na wysyłanie (ostrzeżenie w logu)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Odrzuć wysyłanie (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny blokuje każde wysyłanie, gdy clamd nie działa',
	'plugin.attachments-clamav.virus_found'        => 'Wykryto wirusa w „{name}”',
	'plugin.attachments-clamav.unavailable'        => 'Skaner antywirusowy nieosiągalny',
	'plugin.attachments-clamav.bad_response'       => 'Nieoczekiwana odpowiedź skanera: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Nie można odczytać pliku „{name}”',
	'plugin.attachments-clamav.label'           => 'Antywirus ClamAV',
];
