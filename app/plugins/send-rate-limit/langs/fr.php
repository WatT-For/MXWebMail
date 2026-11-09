<?php
declare(strict_types=1);
/** Dictionnaire français du plugin send-rate-limit. */
return [
	'plugin.send-rate-limit.label' => 'Quota d\'envoi',
	'plugin.send-rate-limit.description' => 'Quota d\'envoi par compte : borne les messages par heure/jour et les destinataires par jour - amortit un compte compromis (webmail uniquement)',
	'plugin.send-rate-limit.exceeded_hour' => 'Limite horaire d\'envoi atteinte ({n} messages/heure) - réessayez plus tard',
	'plugin.send-rate-limit.exceeded_day' => 'Limite quotidienne d\'envoi atteinte ({n} messages/jour) - réessayez demain',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Limite quotidienne de destinataires atteinte ({n} destinataires/jour)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Messages max / heure',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Par compte. 0 = illimité',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Messages max / jour',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Par compte. 0 = illimité',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Destinataires max / jour',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Total To+Cc+Bcc par compte et par jour. 0 = illimité',
	'plugin.send-rate-limit.f.exempt' => 'Comptes exemptés',
	'plugin.send-rate-limit.f.exempt.help' => 'Wildcards d\'expéditeurs, un par ligne (ex. admin@domaine.tld, postmaster@*) - vide = aucun exempt',
];
