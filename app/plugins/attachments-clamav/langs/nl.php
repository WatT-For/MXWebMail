<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'ClamAV (clamd)-scan van geüploade bijlagen',
	'plugin.attachments-clamav.f.socket'           => 'clamd-socket',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port of unix:///pad/naar/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Time-out (seconden)',
	'plugin.attachments-clamav.f.on_error'         => 'Wanneer clamd onbereikbaar is',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Upload toestaan (waarschuwing loggen)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Upload weigeren (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny blokkeert elke upload zolang clamd down is',
	'plugin.attachments-clamav.virus_found'        => 'Virus gedetecteerd in „{name}“',
	'plugin.attachments-clamav.unavailable'        => 'Virusscanner onbereikbaar',
	'plugin.attachments-clamav.bad_response'       => 'Onverwacht antwoord van de scanner: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Kan bestand “{name}” niet lezen',
	'plugin.attachments-clamav.label'           => 'ClamAV-antivirus',
];
