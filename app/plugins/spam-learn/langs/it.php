<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Alimentazione apprendimento spam/ham (rspamc, sa-learn o cartella dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Driver di apprendimento',
	'plugin.spam-learn.f.driver.help'      => 'Come i messaggi appresi vengono inviati al motore antispam',
	'plugin.spam-learn.f.driver.exec'      => 'Comando (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Cartella IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Comando apprendimento spam',
	'plugin.spam-learn.f.command_ham'      => 'Comando apprendimento ham',
	'plugin.spam-learn.f.command.help'     => 'Solo binario + argomenti; il .eml è passato su stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Cartella apprendimento spam',
	'plugin.spam-learn.f.folder_ham'       => 'Cartella apprendimento ham',
	'plugin.spam-learn.f.folder.help'      => 'Creata automaticamente se mancante',
	'plugin.spam-learn.f.max_bytes'        => 'Dimensione max inviata (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Il messaggio viene troncato oltre questa dimensione',
];
