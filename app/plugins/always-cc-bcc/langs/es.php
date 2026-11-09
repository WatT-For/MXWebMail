<?php
declare(strict_types=1);
/** Diccionario español del plugin always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'CC/BCC automático',
	'plugin.always-cc-bcc.description' => 'Añade destinatarios Cc o Bcc sistemáticos a cada envío (archivo de cumplimiento, dirección de seguimiento)',
	'plugin.always-cc-bcc.bad_addresses' => 'La lista de destinatarios automáticos contiene direcciones no válidas (corrija la configuración del plugin)',
	'plugin.always-cc-bcc.f.mode' => 'Modo',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (invisible)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (visible)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: el destinatario recibe el correo sin ser visible para los demás; Cc: aparece en copia para todos',
	'plugin.always-cc-bcc.f.addresses' => 'Destinatarios automáticos',
	'plugin.always-cc-bcc.f.addresses.help' => 'Una o varias direcciones separadas por coma o espacio. Vacío = plugin inactivo',
	'plugin.always-cc-bcc.f.senders' => 'Cuentas afectadas',
	'plugin.always-cc-bcc.f.senders.help' => 'Comodines de remitente, uno por línea (ej. *@dominio.tld, user*@x.tld) - vacío o * = todos',
];
