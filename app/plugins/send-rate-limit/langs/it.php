<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Limite di invio',
	'plugin.send-rate-limit.description' => 'Quota di invio per account: limita i messaggi per ora/giorno e i destinatari per giorno - limita i danni in caso di compromissione (solo webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Limite di invio orario raggiunto ({n} messaggi/ora) - riprova più tardi',
	'plugin.send-rate-limit.exceeded_day' => 'Limite di invio giornaliero raggiunto ({n} messaggi/giorno) - riprova domani',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Limite giornaliero di destinatari raggiunto ({n} destinatari/giorno)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Max messaggi / ora',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Per account. 0 = illimitato',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Max messaggi / giorno',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Per account. 0 = illimitato',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Max destinatari / giorno',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Totale To+Cc+Bcc per account e per giorno. 0 = illimitato',
	'plugin.send-rate-limit.f.exempt' => 'Account esenti',
	'plugin.send-rate-limit.f.exempt.help' => 'Wildcard mittente, una per riga (es. admin@domain.tld, postmaster@*) - vuoto = nessuno esente',
];
