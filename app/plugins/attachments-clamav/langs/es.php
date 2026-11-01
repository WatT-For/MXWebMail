<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Análisis ClamAV (clamd) de los adjuntos subidos',
	'plugin.attachments-clamav.f.socket'           => 'Socket de clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port o unix:///ruta/a/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Tiempo de espera (segundos)',
	'plugin.attachments-clamav.f.on_error'         => 'Cuando clamd no responde',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Permitir la subida (aviso en el log)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Rechazar la subida (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny bloquea toda subida mientras clamd esté caído',
	'plugin.attachments-clamav.virus_found'        => 'Virus detectado en «{name}»',
	'plugin.attachments-clamav.unavailable'        => 'Antivirus inaccesible',
	'plugin.attachments-clamav.bad_response'       => 'Respuesta inesperada del escáner: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'No se puede leer el archivo “{name}”',
];
