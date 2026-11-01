<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Invia allegati troppo grandi come link di download a scadenza',
	'plugin.filelink.f.max_size'       => 'Dimensione max file (MB)',
	'plugin.filelink.f.max_size.help'  => 'I file più grandi vengono rifiutati',
	'plugin.filelink.f.ttl_days'       => 'Durata del link (giorni)',
	'plugin.filelink.f.ttl_days.help'  => 'I link di download scadono dopo questo periodo',
	'plugin.filelink.error_upload'     => 'Upload filelink non riuscito',
	'plugin.filelink.error_too_big'    => 'Il file supera {mb} MB',
	'plugin.filelink.expired'          => 'Questo link è scaduto o non è valido.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
];
