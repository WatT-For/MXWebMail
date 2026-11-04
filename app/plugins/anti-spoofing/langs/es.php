<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Detección de suplantación de direcciones y dominios en el lector de mensajes',
	'plugin.anti-spoofing.title'              => 'Advertencia - posible suplantación',
	'plugin.anti-spoofing.clean'              => 'No se detectó suplantación',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'La dirección de envío ({return_path}) no coincide con el remitente mostrado ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Las respuestas se enviarán a {reply_to} en lugar del remitente mostrado ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Este mensaje fue enviado por {sender} en nombre de {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'El nombre para mostrar "{name}" suplanta a un remitente conocido, pero la dirección real es {email}.',
	'plugin.anti-spoofing.alert.display_name_homoglyph' => 'El nombre para mostrar "{name}" usa caracteres engañosos para suplantar a un remitente conocido; la dirección real es {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'El dominio {domain} imita al dominio legítimo {protected}.',
	'plugin.anti-spoofing.alert.lookalike_subdomain'    => 'El dominio {domain} hace pasar {protected} por un subdominio.',
	'plugin.anti-spoofing.alert.lookalike_tld'          => 'El dominio {domain} reutiliza abusivamente el nombre "{protected}".',

	'plugin.anti-spoofing.f.protected_domains'      => 'Dominios protegidos',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Dominios legítimos a proteger, además de tu propio dominio y los dominios del servidor (protegidos automáticamente).',
	'plugin.anti-spoofing.f.brands'                 => 'Marcas / palabras clave',
	'plugin.anti-spoofing.f.brands.help'            => 'Palabras clave detectadas en el nombre para mostrar (la lista predeterminada se aplica si está vacío).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Comprobar envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Comprobar Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Comprobar cabecera Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Detectar suplantación del nombre para mostrar',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Detectar dominios similares',
	'plugin.anti-spoofing.f.check_auth_results'   => 'Comprobar SPF/DKIM/DMARC (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'La comprobación SPF falló para este remitente',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'La comprobación de la firma DKIM falló',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'La comprobación DMARC falló',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF softfail (remitente no autorizado)',
	'plugin.anti-spoofing.alert.auth_dkim_fail_thirdparty' => 'Firma DKIM de terceros inválida ({domain}) - probablemente un relé.',
	'plugin.anti-spoofing.alert.auth_pass'         => 'Autenticación verificada: {mechs}',
	'plugin.anti-spoofing.label'                          => 'Antisuplantación',
];