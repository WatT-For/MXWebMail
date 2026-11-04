<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Scansione ClamAV (clamd) degli allegati caricati',
	'plugin.attachments-clamav.f.socket'           => 'Socket clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port o unix:///percorso/di/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Timeout (secondi)',
	'plugin.attachments-clamav.f.on_error'         => 'Quando clamd non è raggiungibile',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Consenti upload (avviso nel log)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Rifiuta upload (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny blocca ogni upload finché clamd è giù',
	'plugin.attachments-clamav.virus_found'        => 'Virus rilevato in "{name}"',
	'plugin.attachments-clamav.unavailable'        => 'Antivirus non raggiungibile',
	'plugin.attachments-clamav.bad_response'       => 'Risposta imprevista dello scanner: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Impossibile leggere il file “{name}”',
	'plugin.attachments-clamav.label'           => 'Antivirus ClamAV',
];
