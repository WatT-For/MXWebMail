<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Enviar anexos demasiado grandes como links de transferência com expiração',
	'plugin.filelink.f.max_size'       => 'Tamanho máx. do ficheiro (MB)',
	'plugin.filelink.f.max_size.help'  => 'Ficheiros maiores são rejeitados',
	'plugin.filelink.f.ttl_days'       => 'Vida útil do link (dias)',
	'plugin.filelink.f.ttl_days.help'  => 'Os links de transferência expiram após este prazo',
	'plugin.filelink.error_upload'     => 'Falha no envio filelink',
	'plugin.filelink.error_too_big'    => 'O ficheiro excede {mb} MB',
	'plugin.filelink.expired'          => 'Este link expirou ou é inválido.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
	'plugin.filelink.label'          => 'Anexos Filelink',
];
