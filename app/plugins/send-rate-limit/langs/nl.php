<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Verzendlimiet',
	'plugin.send-rate-limit.description' => 'Verzendquotum per account: begrenst berichten per uur/dag en ontvangers per dag - beperkt schade bij accountcompromittering (alleen webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Uurlijks verzendlimiet bereikt ({n} berichten/uur) - probeer later opnieuw',
	'plugin.send-rate-limit.exceeded_day' => 'Dagelijks verzendlimiet bereikt ({n} berichten/dag) - probeer morgen opnieuw',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Dagelijkse ontvangerslimiet bereikt ({n} ontvangers/dag)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Max. berichten / uur',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Per account. 0 = onbeperkt',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Max. berichten / dag',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Per account. 0 = onbeperkt',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Max. ontvangers / dag',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Totaal To+Cc+Bcc per account en per dag. 0 = onbeperkt',
	'plugin.send-rate-limit.f.exempt' => 'Vrijgestelde accounts',
	'plugin.send-rate-limit.f.exempt.help' => 'Afzender-wildcards, een per regel (bijv. admin@domain.tld, postmaster@*) - leeg = niemand vrijgesteld',
];
