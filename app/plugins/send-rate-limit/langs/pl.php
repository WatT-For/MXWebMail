<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Limit wysyłki',
	'plugin.send-rate-limit.description' => 'Limit wysyłki per konto: ogranicza wiadomości na godzinę/dzień i odbiorców na dzień - ogranicza szkody przy przejęciu konta (tylko webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Osiągnięto godzinny limit wysyłki ({n} wiadomości/godz.) - spróbuj później',
	'plugin.send-rate-limit.exceeded_day' => 'Osiągnięto dzienny limit wysyłki ({n} wiadomości/dzień) - spróbuj jutro',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Osiągnięto dzienny limit odbiorców ({n} odbiorców/dzień)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Maks. wiadomości / godz.',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Na konto. 0 = bez limitu',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Maks. wiadomości / dzień',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Na konto. 0 = bez limitu',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Maks. odbiorców / dzień',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Łącznie To+Cc+Bcc na konto i na dzień. 0 = bez limitu',
	'plugin.send-rate-limit.f.exempt' => 'Konta wyłączone z limitu',
	'plugin.send-rate-limit.f.exempt.help' => 'Wildcards nadawcy, jedna na linię (np. admin@domain.tld, postmaster@*) - puste = brak wyłączonych',
];
