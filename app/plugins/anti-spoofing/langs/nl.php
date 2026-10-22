<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Detectie van spoofing van e-mailadressen en domeinen in de berichtenlezer',
	'plugin.anti-spoofing.title'              => 'Waarschuwing — mogelijke spoofing',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Het verzendadres ({return_path}) komt niet overeen met de weergegeven afzender ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Antwoorden worden naar {reply_to} gestuurd in plaats van de weergegeven afzender ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Dit bericht is verzonden door {sender} namens {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'De weergavenaam «{name}» doet zich voor als een bekende afzender, maar het echte adres is {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Het domein {domain} imiteert het legitieme domein {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Beschermde domeinen',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Te beschermen legitieme domeinen, naast uw eigen domein en de serverdomeinen (automatisch beschermd).',
	'plugin.anti-spoofing.f.brands'                 => 'Merken / trefwoorden',
	'plugin.anti-spoofing.f.brands.help'            => 'Trefwoorden gedetecteerd in de weergavenaam (bij leeg geldt de standaardlijst).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Envelope controleren (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Reply-To controleren',
	'plugin.anti-spoofing.f.check_sender'           => 'Sender-header controleren',
	'plugin.anti-spoofing.f.check_display'          => 'Spoofing van weergavenaam detecteren',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Lookalike-domeinen detecteren',
];