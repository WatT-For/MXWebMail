<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Send rate limit',
	'plugin.send-rate-limit.description' => 'Per-account sending quota: caps messages per hour/day and recipients per day - limits damage if an account is compromised (webmail only)',
	'plugin.send-rate-limit.exceeded_hour' => 'Hourly sending limit reached ({n} messages/hour) - try again later',
	'plugin.send-rate-limit.exceeded_day' => 'Daily sending limit reached ({n} messages/day) - try again tomorrow',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Daily recipient limit reached ({n} recipients/day)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Max messages / hour',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Per account. 0 = unlimited',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Max messages / day',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Per account. 0 = unlimited',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Max recipients / day',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Total To+Cc+Bcc per account and per day. 0 = unlimited',
	'plugin.send-rate-limit.f.exempt' => 'Exempt accounts',
	'plugin.send-rate-limit.f.exempt.help' => 'Sender wildcards, one per line (e.g. admin@domain.tld, postmaster@*) - empty = none exempt',
];
