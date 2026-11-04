<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Enviar adjuntos demasiado grandes como enlaces de descarga con caducidad',
	'plugin.filelink.f.max_size'       => 'Tamaño máx. de archivo (MB)',
	'plugin.filelink.f.max_size.help'  => 'Los archivos más grandes se rechazan',
	'plugin.filelink.f.ttl_days'       => 'Vida del enlace (días)',
	'plugin.filelink.f.ttl_days.help'  => 'Los enlaces de descarga caducan tras este plazo',
	'plugin.filelink.error_upload'     => 'Falló la subida filelink',
	'plugin.filelink.error_too_big'    => 'El archivo supera {mb} MB',
	'plugin.filelink.expired'          => 'Este enlace ha caducado o no es válido.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
	'plugin.filelink.label'          => 'Adjuntos Filelink',
];
