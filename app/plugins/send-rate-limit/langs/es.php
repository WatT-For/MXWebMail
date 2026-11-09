<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Límite de envío',
	'plugin.send-rate-limit.description' => 'Cuota de envío por cuenta: limita mensajes por hora/día y destinatarios por día - limita el daño si una cuenta se ve comprometida (solo webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Límite de envío por hora alcanzado ({n} mensajes/hora) - inténtalo más tarde',
	'plugin.send-rate-limit.exceeded_day' => 'Límite de envío diario alcanzado ({n} mensajes/día) - inténtalo mañana',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Límite de destinatarios diario alcanzado ({n} destinatarios/día)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Máx. mensajes / hora',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Por cuenta. 0 = ilimitado',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Máx. mensajes / día',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Por cuenta. 0 = ilimitado',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Máx. destinatarios / día',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Total To+Cc+Bcc por cuenta y día. 0 = ilimitado',
	'plugin.send-rate-limit.f.exempt' => 'Cuentas exentas',
	'plugin.send-rate-limit.f.exempt.help' => 'Comodines de remitente, uno por línea (ej. admin@domain.tld, postmaster@*) - vacío = ninguno exento',
];
