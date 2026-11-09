<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Лимит отправки',
	'plugin.send-rate-limit.description' => 'Квота отправки на аккаунт: ограничивает сообщений в час/день и получателей в день - ограничивает ущерб при компрометации (только веб-почта)',
	'plugin.send-rate-limit.exceeded_hour' => 'Достигнут часовой лимит отправки ({n} сообщений/час) - повторите позже',
	'plugin.send-rate-limit.exceeded_day' => 'Достигнут дневной лимит отправки ({n} сообщений/день) - повторите завтра',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Достигнут дневной лимит получателей ({n} получателей/день)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Макс. сообщений / час',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'На аккаунт. 0 = без ограничений',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Макс. сообщений / день',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'На аккаунт. 0 = без ограничений',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Макс. получателей / день',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Всего To+Cc+Bcc на аккаунт в день. 0 = без ограничений',
	'plugin.send-rate-limit.f.exempt' => 'Исключённые аккаунты',
	'plugin.send-rate-limit.f.exempt.help' => 'Wildcards отправителя, по одному в строке (напр. admin@domain.tld, postmaster@*) - пусто = без исключений',
];
