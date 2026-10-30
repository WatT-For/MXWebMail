<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'ClamAV (clamd) scan of uploaded attachments',
	'plugin.attachments-clamav.f.socket'           => 'clamd socket',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port or unix:///path/to/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Timeout (seconds)',
	'plugin.attachments-clamav.f.on_error'         => 'When clamd is unreachable',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Allow upload (log warning)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Reject upload (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny blocks every upload while clamd is down',
	'plugin.attachments-clamav.virus_found'        => 'Virus detected in “{name}”',
	'plugin.attachments-clamav.unavailable'        => 'Virus scanner unreachable',
	'plugin.attachments-clamav.bad_response'       => 'Unexpected scanner response: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Datei „{name}“ kann nicht gelesen werden',
];
