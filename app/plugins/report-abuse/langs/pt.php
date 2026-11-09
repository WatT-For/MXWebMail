<?php
declare(strict_types=1);
/** Dicionário português do plugin report-abuse. */
return [
	'plugin.report-abuse.label' => 'Denúncia de phishing',
	'plugin.report-abuse.description' => 'Botão "Denunciar" na barra de ações: reencaminha as mensagens marcadas como anexos .eml para um endereço abuse administrado',
	'plugin.report-abuse.action' => 'Denunciar',
	'plugin.report-abuse.confirm' => 'Denunciar {n} mensagem(ns) para o endereço abuse? Serão reencaminhadas como anexos .eml.',
	'plugin.report-abuse.done' => '{n} mensagem(ns) denunciada(s)',
	'plugin.report-abuse.done_partial' => '{n} denunciada(s), {f} falhada(s)',
	'plugin.report-abuse.not_configured' => 'A denúncia não está configurada (falta o endereço abuse)',
	'plugin.report-abuse.bad_items' => 'Seleção inválida',
	'plugin.report-abuse.failed' => 'A denúncia falhou',
	'plugin.report-abuse.body' => "Denúncia de phishing.\r\n\r\nConta: {account}\r\nPasta: {folder}\r\nData: {date}\r\n\r\nA mensagem denunciada vai anexa como ficheiro .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Endereço abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Destinatário das denúncias (ex. abuse@dominio.tld ou endereço SOC). Vazio = plugin inativo',
	'plugin.report-abuse.f.subject_prefix' => 'Prefixo de assunto',
	'plugin.report-abuse.f.subject_prefix.help' => 'Anteposto ao assunto da mensagem denunciada',
	'plugin.report-abuse.f.mark_spam' => 'Marcar também como spam',
	'plugin.report-abuse.f.mark_spam.help' => 'Após a denúncia, marca as mensagens como spam (mover/flag conforme a conta) - também alimenta a aprendizagem antispam',
];
