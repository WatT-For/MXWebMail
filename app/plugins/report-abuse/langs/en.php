<?php
declare(strict_types=1);
/** English dictionary of the report-abuse plugin. */
return [
	'plugin.report-abuse.label' => 'Phishing report',
	'plugin.report-abuse.description' => '"Report" button in the actions bar: forwards checked messages as .eml attachments to an admin-defined abuse address',
	'plugin.report-abuse.action' => 'Report',
	'plugin.report-abuse.confirm' => 'Report {n} message(s) to the abuse address? They will be forwarded as .eml attachments.',
	'plugin.report-abuse.done' => '{n} message(s) reported',
	'plugin.report-abuse.done_partial' => '{n} reported, {f} failed',
	'plugin.report-abuse.not_configured' => 'Reporting is not configured (missing abuse address)',
	'plugin.report-abuse.bad_items' => 'Invalid selection',
	'plugin.report-abuse.failed' => 'The report failed',
	'plugin.report-abuse.body' => "Phishing report.\r\n\r\nAccount: {account}\r\nFolder: {folder}\r\nDate: {date}\r\n\r\nThe reported message is attached as a .eml file.",
	'plugin.report-abuse.f.abuse_address' => 'Abuse address',
	'plugin.report-abuse.f.abuse_address.help' => 'Recipient of reports (e.g. abuse@domain.tld or a SOC address). Empty = plugin inactive',
	'plugin.report-abuse.f.subject_prefix' => 'Subject prefix',
	'plugin.report-abuse.f.subject_prefix.help' => 'Prepended to the reported message subject',
	'plugin.report-abuse.f.mark_spam' => 'Also mark as spam',
	'plugin.report-abuse.f.mark_spam.help' => 'After reporting, marks the messages as spam (move/flag per account setting) - also feeds spam learning',
];
