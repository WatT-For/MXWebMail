<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Übergroße Anhänge als befristete Download-Links versenden',
	'plugin.filelink.f.max_size'       => 'Max. Dateigröße (MB)',
	'plugin.filelink.f.max_size.help'  => 'Größere Dateien werden abgelehnt',
	'plugin.filelink.f.ttl_days'       => 'Link-Lebensdauer (Tage)',
	'plugin.filelink.f.ttl_days.help'  => 'Download-Links laufen nach dieser Frist ab',
	'plugin.filelink.error_upload'     => 'Filelink-Upload fehlgeschlagen',
	'plugin.filelink.error_too_big'    => 'Datei überschreitet {mb} MB',
	'plugin.filelink.expired'          => 'Dieser Link ist abgelaufen oder ungültig.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
];
