<?php
declare(strict_types=1);
/** Dizionario italiano del plugin report-abuse. */
return [
	'plugin.report-abuse.label' => 'Segnalazione phishing',
	'plugin.report-abuse.description' => 'Pulsante "Segnala" nella barra delle azioni: inoltra i messaggi selezionati come allegati .eml a un indirizzo abuse amministrato',
	'plugin.report-abuse.action' => 'Segnala',
	'plugin.report-abuse.confirm' => 'Segnalare {n} messaggio/i all\'indirizzo abuse? Verranno inoltrati come allegati .eml.',
	'plugin.report-abuse.done' => '{n} messaggio/i segnalato/i',
	'plugin.report-abuse.done_partial' => '{n} segnalato/i, {f} fallito/i',
	'plugin.report-abuse.not_configured' => 'La segnalazione non è configurata (indirizzo abuse mancante)',
	'plugin.report-abuse.bad_items' => 'Selezione non valida',
	'plugin.report-abuse.failed' => 'La segnalazione è fallita',
	'plugin.report-abuse.body' => "Segnalazione phishing.\r\n\r\nAccount: {account}\r\nCartella: {folder}\r\nData: {date}\r\n\r\nIl messaggio segnalato è allegato come file .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Indirizzo abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Destinatario delle segnalazioni (es. abuse@dominio.tld o indirizzo SOC). Vuoto = plugin inattivo',
	'plugin.report-abuse.f.subject_prefix' => 'Prefisso oggetto',
	'plugin.report-abuse.f.subject_prefix.help' => 'Anteposto all\'oggetto del messaggio segnalato',
	'plugin.report-abuse.f.mark_spam' => 'Segna anche come spam',
	'plugin.report-abuse.f.mark_spam.help' => 'Dopo la segnalazione, marca i messaggi come spam (spostamento/flag secondo l\'account) - alimenta anche l\'apprendimento antispam',
];
