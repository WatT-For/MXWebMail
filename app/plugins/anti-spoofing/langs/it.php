<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Rilevamento di spoofing di indirizzi email e domini nel lettore dei messaggi',
	'plugin.anti-spoofing.title'              => 'Avviso - possibile spoofing',
	'plugin.anti-spoofing.clean'              => 'Nessuno spoofing rilevato',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'L\'indirizzo di invio ({return_path}) non corrisponde al mittente visualizzato ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Le risposte verranno inviate a {reply_to} invece del mittente visualizzato ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Questo messaggio è stato inviato da {sender} per conto di {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Il nome visualizzato "{name}" impersona un mittente noto, ma l\'indirizzo reale è {email}.',
	'plugin.anti-spoofing.alert.display_name_homoglyph' => 'Il nome visualizzato "{name}" usa caratteri ingannevoli per impersonare un mittente noto; l\'indirizzo reale è {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Il dominio {domain} imita il dominio legittimo {protected}.',
	'plugin.anti-spoofing.alert.lookalike_subdomain'    => 'Il dominio {domain} spaccia {protected} per un sottodominio.',
	'plugin.anti-spoofing.alert.lookalike_tld'          => 'Il dominio {domain} riutilizza abusivamente il nome "{protected}".',

	'plugin.anti-spoofing.f.protected_domains'      => 'Domini protetti',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Domini legittimi da proteggere, oltre al proprio dominio e ai domini del server (protetti automaticamente).',
	'plugin.anti-spoofing.f.brands'                 => 'Marchi / parole chiave',
	'plugin.anti-spoofing.f.brands.help'            => 'Parole chiave rilevate nel nome visualizzato (se vuoto vale l\'elenco predefinito).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Controlla envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Controlla Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Controlla header Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Rileva spoofing del nome visualizzato',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Rileva domini simili',
	'plugin.anti-spoofing.f.check_auth_results'   => 'Verifica SPF/DKIM/DMARC (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'Verifica SPF non riuscita per questo mittente',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'Verifica della firma DKIM non riuscita',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'Verifica DMARC non riuscita',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF softfail (mittente non autorizzato)',
	'plugin.anti-spoofing.alert.auth_dkim_fail_thirdparty' => 'Firma DKIM di terze parti non valida ({domain}) - probabile relay.',
	'plugin.anti-spoofing.alert.auth_pass'         => 'Autenticazione verificata: {mechs}',
];