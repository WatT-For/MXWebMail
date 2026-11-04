<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Erkennung von E-Mail-Adress- und Domain-Spoofing im Nachrichtenleser',
	'plugin.anti-spoofing.title'              => 'Warnung - mögliches Spoofing',
	'plugin.anti-spoofing.clean'              => 'Kein Spoofing erkannt',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Die Absenderadresse ({return_path}) stimmt nicht mit dem angezeigten Absender ({from}) überein.',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Antworten werden an {reply_to} gesendet statt an den angezeigten Absender ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Diese Nachricht wurde von {sender} im Auftrag von {from} gesendet.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Der Anzeigename „{name}" gibt sich als bekannter Absender aus, aber die echte Adresse ist {email}.',
	'plugin.anti-spoofing.alert.display_name_homoglyph' => 'Der Anzeigename „{name}" verwendet täuschende Zeichen, um einen bekannten Absender zu imitieren; die echte Adresse ist {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Die Domain {domain} imitiert die legitime Domain {protected}.',
	'plugin.anti-spoofing.alert.lookalike_subdomain'    => 'Die Domain {domain} gibt {protected} als Subdomain aus.',
	'plugin.anti-spoofing.alert.lookalike_tld'          => 'Die Domain {domain} nutzt den Namen „{protected}" widerrechtlich.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Geschützte Domains',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Zu schützende legitime Domains, zusätzlich zu Ihrer eigenen Domain und den Serverdomains (automatisch geschützt).',
	'plugin.anti-spoofing.f.brands'                 => 'Marken / Schlüsselwörter',
	'plugin.anti-spoofing.f.brands.help'            => 'Im Anzeigenamen erkannte Schlüsselwörter (bei leer gilt die Standardliste).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Envelope prüfen (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Reply-To prüfen',
	'plugin.anti-spoofing.f.check_sender'           => 'Sender-Header prüfen',
	'plugin.anti-spoofing.f.check_display'          => 'Anzeigenamen-Spoofing erkennen',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Lookalike-Domains erkennen',
	'plugin.anti-spoofing.f.check_auth_results'   => 'SPF/DKIM/DMARC prüfen (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'SPF-Prüfung für diesen Absender fehlgeschlagen',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'DKIM-Signaturprüfung fehlgeschlagen',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'DMARC-Prüfung fehlgeschlagen',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF-Softfail (Absender nicht autorisiert)',
	'plugin.anti-spoofing.alert.auth_dkim_fail_thirdparty' => 'Dritte DKIM-Signatur ungültig ({domain}) - vermutlich ein Relay.',
	'plugin.anti-spoofing.alert.auth_pass'         => 'Authentifizierung verifiziert: {mechs}',
	'plugin.anti-spoofing.label'                          => 'Anti-Spoofing',
];