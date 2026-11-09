<?php
declare(strict_types=1);
/** Dictionnaire français du plugin always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'CC/BCC automatique',
	'plugin.always-cc-bcc.description' => 'Ajoute des destinataires Cc ou Bcc systématiques à chaque envoi (archive de conformité, adresse de suivi)',
	'plugin.always-cc-bcc.bad_addresses' => 'La liste des destinataires automatiques contient des adresses invalides (configuration du plugin à corriger)',
	'plugin.always-cc-bcc.f.mode' => 'Mode',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (invisible)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (visible)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc : le destinataire reçoit le mail sans apparaître aux autres ; Cc : il apparaît en copie pour tous',
	'plugin.always-cc-bcc.f.addresses' => 'Destinataires automatiques',
	'plugin.always-cc-bcc.f.addresses.help' => 'Une ou plusieurs adresses séparées par virgule ou espace. Vide = plugin inactif',
	'plugin.always-cc-bcc.f.senders' => 'Comptes concernés',
	'plugin.always-cc-bcc.f.senders.help' => 'Wildcards d\'expéditeurs, un par ligne (ex. *@domaine.tld, user*@x.tld) - vide ou * = tous',
];
