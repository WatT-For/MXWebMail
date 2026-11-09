<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'ClamAV (clamd)-Scan hochgeladener Anhänge',
	'plugin.attachments-clamav.f.socket'           => 'clamd-Socket',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port oder unix:///pfad/zu/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Timeout (Sekunden)',
	'plugin.attachments-clamav.f.on_error'         => 'Wenn clamd nicht erreichbar ist',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Upload erlauben (Warnung loggen)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Upload ablehnen (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny blockiert jeden Upload, solange clamd ausgefallen ist',
	'plugin.attachments-clamav.virus_found'        => 'Virus in „{name}" erkannt',
	'plugin.attachments-clamav.unavailable'        => 'Virenscanner nicht erreichbar',
	'plugin.attachments-clamav.bad_response'       => 'Unerwartete Scanner-Antwort: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Datei „{name}" kann nicht gelesen werden',
	'plugin.attachments-clamav.label'           => 'ClamAV-Antivirus',
];
