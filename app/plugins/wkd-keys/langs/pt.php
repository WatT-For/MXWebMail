<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Descoberta automática de chaves PGP (WKD + recolha Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'Alternativa keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Consultar o servidor público de chaves quando o WKD não devolve nada',
	'plugin.wkd-keys.f.autocrypt'              => 'Recolher cabeçalhos Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Guardar as chaves enviadas pelos correspondentes no cabeçalho Autocrypt',
	'plugin.wkd-keys.bad_email'                => 'Endereço de email inválido',
	'plugin.wkd-keys.import_confirm'           => 'Foi encontrada uma chave PGP para {email}. Importar?',
	'plugin.wkd-keys.imported'                 => 'Chave PGP importada.',
	'plugin.wkd-keys.label'                    => 'Chaves WKD',
];
