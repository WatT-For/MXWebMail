<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Análise ClamAV (clamd) dos anexos enviados',
	'plugin.attachments-clamav.f.socket'           => 'Socket do clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port ou unix:///caminho/para/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Tempo limite (segundos)',
	'plugin.attachments-clamav.f.on_error'         => 'Quando o clamd está inacessível',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Permitir envio (aviso no log)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Rejeitar envio (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny bloqueia todos os envios enquanto o clamd estiver em baixo',
	'plugin.attachments-clamav.virus_found'        => 'Vírus detetado em "{name}"',
	'plugin.attachments-clamav.unavailable'        => 'Antivírus inacessível',
	'plugin.attachments-clamav.bad_response'       => 'Resposta inesperada do scanner: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Não é possível ler o ficheiro “{name}”',
];
