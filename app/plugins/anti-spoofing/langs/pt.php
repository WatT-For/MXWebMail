<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Detecção de falsificação de endereços e domínios no leitor de mensagens',
	'plugin.anti-spoofing.title'              => 'Aviso — possível falsificação',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'O endereço de envio ({return_path}) não corresponde ao remetente exibido ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'As respostas serão enviadas para {reply_to} em vez do remetente exibido ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Esta mensagem foi enviada por {sender} em nome de {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'O nome de exibição «{name}» se passa por um remetente conhecido, mas o endereço real é {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'O domínio {domain} imita o domínio legítimo {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Domínios protegidos',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Domínios legítimos a proteger, além do seu próprio domínio e dos domínios do servidor (protegidos automaticamente).',
	'plugin.anti-spoofing.f.brands'                 => 'Marcas / palavras-chave',
	'plugin.anti-spoofing.f.brands.help'            => 'Palavras-chave detectadas no nome de exibição (a lista padrão se aplica se vazio).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Verificar envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Verificar Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Verificar cabeçalho Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Detectar falsificação do nome de exibição',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Detectar domínios parecidos',
];