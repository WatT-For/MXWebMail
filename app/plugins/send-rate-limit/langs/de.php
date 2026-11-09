<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Versand-Ratenlimit',
	'plugin.send-rate-limit.description' => 'Versandkontingent pro Konto: begrenzt Nachrichten pro Stunde/Tag und Empfänger pro Tag - begrenzt den Schaden bei Kompromittierung (nur Webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Stündliches Versandlimit erreicht ({n} Nachrichten/Stunde) - später erneut versuchen',
	'plugin.send-rate-limit.exceeded_day' => 'Tägliches Versandlimit erreicht ({n} Nachrichten/Tag) - morgen erneut versuchen',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Tägliches Empfängerlimit erreicht ({n} Empfänger/Tag)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Max. Nachrichten / Stunde',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Pro Konto. 0 = unbegrenzt',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Max. Nachrichten / Tag',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Pro Konto. 0 = unbegrenzt',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Max. Empfänger / Tag',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Summe To+Cc+Bcc pro Konto und Tag. 0 = unbegrenzt',
	'plugin.send-rate-limit.f.exempt' => 'Ausgenommene Konten',
	'plugin.send-rate-limit.f.exempt.help' => 'Absender-Wildcards, eine pro Zeile (z. B. admin@domain.tld, postmaster@*) - leer = keine ausgenommen',
];
