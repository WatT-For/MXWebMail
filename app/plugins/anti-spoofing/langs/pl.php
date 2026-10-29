<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Wykrywanie fałszowania adresów e-mail i domen w czytniku wiadomości',
	'plugin.anti-spoofing.title'              => 'Ostrzeżenie — możliwe fałszowanie',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Adres wysyłki ({return_path}) nie pasuje do wyświetlanego nadawcy ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Odpowiedzi zostaną wysłane do {reply_to} zamiast do wyświetlanego nadawcy ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Ta wiadomość została wysłana przez {sender} w imieniu {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Nazwa wyświetlana «{name}» podszywa się pod znanego nadawcę, ale prawdziwy adres to {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Domena {domain} imituje prawdziwą domenę {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Chronione domeny',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Prawdziwe domeny do ochrony, oprócz własnej domeny i domen serwera (chronionych automatycznie).',
	'plugin.anti-spoofing.f.brands'                 => 'Marki / słowa kluczowe',
	'plugin.anti-spoofing.f.brands.help'            => 'Słowa kluczowe wykrywane w nazwie wyświetlanej (puste = lista domyślna).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Sprawdzaj envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Sprawdzaj Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Sprawdzaj nagłówek Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Wykrywaj fałszowanie nazwy wyświetlanej',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Wykrywaj podobne domeny',
	'plugin.anti-spoofing.f.check_auth_results'   => 'Check SPF/DKIM/DMARC (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'SPF check failed for this sender',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'DKIM signature check failed',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'DMARC check failed',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF softfail (sender not authorized)',
	'plugin.anti-spoofing.alert.auth_pass_all'     => 'SPF, DKIM and DMARC verified (pass)',
];