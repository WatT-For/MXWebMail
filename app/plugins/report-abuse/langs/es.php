<?php
declare(strict_types=1);
/** Diccionario español del plugin report-abuse. */
return [
	'plugin.report-abuse.label' => 'Reporte de phishing',
	'plugin.report-abuse.description' => 'Botón "Reportar" en la barra de acciones: reenvía los mensajes marcados como adjuntos .eml a una dirección abuse administrada',
	'plugin.report-abuse.action' => 'Reportar',
	'plugin.report-abuse.confirm' => '¿Reportar {n} mensaje(s) a la dirección abuse? Se reenviarán como adjuntos .eml.',
	'plugin.report-abuse.done' => '{n} mensaje(s) reportado(s)',
	'plugin.report-abuse.done_partial' => '{n} reportado(s), {f} fallido(s)',
	'plugin.report-abuse.not_configured' => 'El reporte no está configurado (falta la dirección abuse)',
	'plugin.report-abuse.bad_items' => 'Selección inválida',
	'plugin.report-abuse.failed' => 'El reporte ha fallado',
	'plugin.report-abuse.body' => "Reporte de phishing.\r\n\r\nCuenta: {account}\r\nCarpeta: {folder}\r\nFecha: {date}\r\n\r\nEl mensaje reportado se adjunta como archivo .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Dirección abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Destinatario de los reportes (ej. abuse@dominio.tld o dirección del SOC). Vacío = plugin inactivo',
	'plugin.report-abuse.f.subject_prefix' => 'Prefijo de asunto',
	'plugin.report-abuse.f.subject_prefix.help' => 'Antepuesto al asunto del mensaje reportado',
	'plugin.report-abuse.f.mark_spam' => 'Marcar también como spam',
	'plugin.report-abuse.f.mark_spam.help' => 'Tras el reporte, marca los mensajes como spam (movimiento/flag según la cuenta) - también alimenta el aprendizaje antispam',
];
