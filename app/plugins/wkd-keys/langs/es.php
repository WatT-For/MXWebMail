<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Descubrimiento automático de claves PGP (WKD + recolección Autocrypt)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'Respaldo keys.openpgp.org',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'Consultar el servidor de claves público cuando WKD no da resultados',
	'plugin.wkd-keys.f.autocrypt'              => 'Recolectar cabeceras Autocrypt',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Guardar las claves enviadas por los corresponsales en la cabecera Autocrypt',
	'plugin.wkd-keys.bad_email'                => 'Dirección de correo no válida',
	'plugin.wkd-keys.import_confirm'           => 'Se encontró una clave PGP para {email}. ¿Importarla?',
	'plugin.wkd-keys.imported'                 => 'Clave PGP importada.',
	'plugin.wkd-keys.label'                    => 'Claves WKD',
];
