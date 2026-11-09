<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Limite de envio',
	'plugin.send-rate-limit.description' => 'Quota de envio por conta: limita mensagens por hora/dia e destinatários por dia - limita os danos se uma conta for comprometida (apenas webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Limite de envio por hora atingido ({n} mensagens/hora) - tente mais tarde',
	'plugin.send-rate-limit.exceeded_day' => 'Limite de envio diário atingido ({n} mensagens/dia) - tente amanhã',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Limite de destinatários diário atingido ({n} destinatários/dia)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Máx. mensagens / hora',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Por conta. 0 = ilimitado',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Máx. mensagens / dia',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Por conta. 0 = ilimitado',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Máx. destinatários / dia',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Total To+Cc+Bcc por conta e por dia. 0 = ilimitado',
	'plugin.send-rate-limit.f.exempt' => 'Contas isentas',
	'plugin.send-rate-limit.f.exempt.help' => 'Wildcards de remetente, uma por linha (ex. admin@domain.tld, postmaster@*) - vazio = nenhuma isenta',
];
