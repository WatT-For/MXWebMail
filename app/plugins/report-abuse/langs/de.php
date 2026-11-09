<?php
declare(strict_types=1);
/** Deutsches Wörterbuch des Plugins report-abuse. */
return [
	'plugin.report-abuse.label' => 'Phishing-Meldung',
	'plugin.report-abuse.description' => 'Schaltfläche "Melden" in der Aktionsleiste: leitet markierte Mails als .eml-Anhänge an eine admin-definierte Abuse-Adresse weiter',
	'plugin.report-abuse.action' => 'Melden',
	'plugin.report-abuse.confirm' => '{n} Nachricht(en) an die Abuse-Adresse melden? Sie werden als .eml-Anhänge weitergeleitet.',
	'plugin.report-abuse.done' => '{n} Nachricht(en) gemeldet',
	'plugin.report-abuse.done_partial' => '{n} gemeldet, {f} fehlgeschlagen',
	'plugin.report-abuse.not_configured' => 'Die Meldung ist nicht konfiguriert (Abuse-Adresse fehlt)',
	'plugin.report-abuse.bad_items' => 'Ungültige Auswahl',
	'plugin.report-abuse.failed' => 'Die Meldung ist fehlgeschlagen',
	'plugin.report-abuse.body' => "Phishing-Meldung.\r\n\r\nKonto: {account}\r\nOrdner: {folder}\r\nDatum: {date}\r\n\r\nDie gemeldete Nachricht ist als .eml-Datei angehängt.",
	'plugin.report-abuse.f.abuse_address' => 'Abuse-Adresse',
	'plugin.report-abuse.f.abuse_address.help' => 'Empfänger der Meldungen (z. B. abuse@domain.tld oder SOC-Adresse). Leer = Plugin inaktiv',
	'plugin.report-abuse.f.subject_prefix' => 'Betreff-Präfix',
	'plugin.report-abuse.f.subject_prefix.help' => 'Wird dem Betreff der gemeldeten Nachricht vorangestellt',
	'plugin.report-abuse.f.mark_spam' => 'Auch als Spam markieren',
	'plugin.report-abuse.f.mark_spam.help' => 'Nach der Meldung werden die Nachrichten als Spam markiert (Verschieben/Flag je nach Kontoeinstellung) - speist auch das Spam-Lernen',
];
