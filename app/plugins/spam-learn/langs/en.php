<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Spam/ham learning feed (rspamc, sa-learn or dovecot-antispam folder)',
	'plugin.spam-learn.f.driver'           => 'Learning driver',
	'plugin.spam-learn.f.driver.help'      => 'How learned messages are fed to the antispam engine',
	'plugin.spam-learn.f.driver.exec'      => 'Command (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'IMAP folder (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Spam learn command',
	'plugin.spam-learn.f.command_ham'      => 'Ham learn command',
	'plugin.spam-learn.f.command.help'     => 'Binary + arguments only; the .eml is piped to stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Spam learn folder',
	'plugin.spam-learn.f.folder_ham'       => 'Ham learn folder',
	'plugin.spam-learn.f.folder.help'      => 'Created automatically if missing',
	'plugin.spam-learn.f.max_bytes'        => 'Max size fed (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Message is truncated beyond this size',
	'plugin.spam-learn.label'           => 'Spam learning',
];
