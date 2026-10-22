<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Erkennung von E-Mail-Adress- und Domain-Spoofing im Nachrichtenleser',
	'plugin.anti-spoofing.title'              => 'Warnung — mögliches Spoofing',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Die Absenderadresse ({return_path}) stimmt nicht mit dem angezeigten Absender ({from}) überein.',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Antworten werden an {reply_to} gesendet statt an den angezeigten Absender ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Diese Nachricht wurde von {sender} im Auftrag von {from} gesendet.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Der Anzeigename „{name}" gibt sich als bekannter Absender aus, aber die echte Adresse ist {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Die Domain {domain} imitiert die legitime Domain {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Geschützte Domains',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Zu schützende legitime Domains, zusätzlich zu Ihrer eigenen Domain und den Serverdomains (automatisch geschützt).',
	'plugin.anti-spoofing.f.brands'                 => 'Marken / Schlüsselwörter',
	'plugin.anti-spoofing.f.brands.help'            => 'Im Anzeigenamen erkannte Schlüsselwörter (bei leer gilt die Standardliste).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Envelope prüfen (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Reply-To prüfen',
	'plugin.anti-spoofing.f.check_sender'           => 'Sender-Header prüfen',
	'plugin.anti-spoofing.f.check_display'          => 'Anzeigenamen-Spoofing erkennen',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Lookalike-Domains erkennen',
];