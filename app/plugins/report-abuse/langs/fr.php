<?php
declare(strict_types=1);
/** Dictionnaire français du plugin report-abuse. */
return [
	'plugin.report-abuse.label' => 'Signalement de hameçonnage',
	'plugin.report-abuse.description' => 'Bouton "Signaler" dans la barre d\'actions : transfère les mails cochés en .eml joints à une adresse abuse administrée',
	'plugin.report-abuse.action' => 'Signaler',
	'plugin.report-abuse.confirm' => 'Signaler {n} message(s) à l\'adresse abuse ? Ils seront transférés en pièces jointes .eml.',
	'plugin.report-abuse.done' => '{n} message(s) signalé(s)',
	'plugin.report-abuse.done_partial' => '{n} signalé(s), {f} en échec',
	'plugin.report-abuse.not_configured' => 'Le signalement n\'est pas configuré (adresse abuse manquante)',
	'plugin.report-abuse.bad_items' => 'Sélection invalide',
	'plugin.report-abuse.failed' => 'Le signalement a échoué',
	'plugin.report-abuse.body' => "Signalement de hameçonnage.\r\n\r\nCompte : {account}\r\nDossier : {folder}\r\nDate : {date}\r\n\r\nLe message signalé est joint en pièce .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Adresse abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Destinataire des signalements (ex. abuse@domaine.tld ou une adresse du SOC). Vide = plugin inactif',
	'plugin.report-abuse.f.subject_prefix' => 'Préfixe de sujet',
	'plugin.report-abuse.f.subject_prefix.help' => 'Préfixé au sujet du message signalé',
	'plugin.report-abuse.f.mark_spam' => 'Marquer aussi en spam',
	'plugin.report-abuse.f.mark_spam.help' => 'Après le signalement, marque les messages comme spam (déplacement/flag selon le réglage du compte) - alimente aussi l\'apprentissage antispam',
];
