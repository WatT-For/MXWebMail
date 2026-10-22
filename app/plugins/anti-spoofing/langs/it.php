<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Rilevamento di spoofing di indirizzi email e domini nel lettore dei messaggi',
	'plugin.anti-spoofing.title'              => 'Avviso — possibile spoofing',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'L\'indirizzo di invio ({return_path}) non corrisponde al mittente visualizzato ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Le risposte verranno inviate a {reply_to} invece del mittente visualizzato ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Questo messaggio è stato inviato da {sender} per conto di {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Il nome visualizzato «{name}» impersona un mittente noto, ma l\'indirizzo reale è {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Il dominio {domain} imita il dominio legittimo {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Domini protetti',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Domini legittimi da proteggere, oltre al proprio dominio e ai domini del server (protetti automaticamente).',
	'plugin.anti-spoofing.f.brands'                 => 'Marchi / parole chiave',
	'plugin.anti-spoofing.f.brands.help'            => 'Parole chiave rilevate nel nome visualizzato (se vuoto vale l\'elenco predefinito).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Controlla envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Controlla Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Controlla header Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Rileva spoofing del nome visualizzato',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Rileva domini simili',
];