<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Spam/ham-leerfeed (rspamc, sa-learn of dovecot-antispam-map)',
	'plugin.spam-learn.f.driver'           => 'Leerdriver',
	'plugin.spam-learn.f.driver.help'      => 'Hoe geleerde berichten aan de antispam-engine worden doorgegeven',
	'plugin.spam-learn.f.driver.exec'      => 'Commando (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'IMAP-map (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Spam-leercommando',
	'plugin.spam-learn.f.command_ham'      => 'Ham-leercommando',
	'plugin.spam-learn.f.command.help'     => 'Alleen binary + argumenten; de .eml wordt naar stdin gepiped',
	'plugin.spam-learn.f.folder_spam'      => 'Spam-leermap',
	'plugin.spam-learn.f.folder_ham'       => 'Ham-leermap',
	'plugin.spam-learn.f.folder.help'      => 'Wordt automatisch aangemaakt indien afwezig',
	'plugin.spam-learn.f.max_bytes'        => 'Max. gevoede grootte (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Bericht wordt boven deze grootte afgekapt',
];
