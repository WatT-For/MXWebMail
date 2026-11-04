<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Zasilanie uczenia spam/ham (rspamc, sa-learn lub folder dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Sterownik uczenia',
	'plugin.spam-learn.f.driver.help'      => 'Jak nauczone wiadomości są przekazywane do silnika antyspamowego',
	'plugin.spam-learn.f.driver.exec'      => 'Polecenie (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Folder IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Polecenie uczenia spamu',
	'plugin.spam-learn.f.command_ham'      => 'Polecenie uczenia hamu',
	'plugin.spam-learn.f.command.help'     => 'Tylko binarka + argumenty; .eml jest przekazywany na stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Folder uczenia spamu',
	'plugin.spam-learn.f.folder_ham'       => 'Folder uczenia hamu',
	'plugin.spam-learn.f.folder.help'      => 'Tworzony automatycznie, gdy brak',
	'plugin.spam-learn.f.max_bytes'        => 'Maks. rozmiar przekazywany (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Wiadomość jest obcinana powyżej tego rozmiaru',
	'plugin.spam-learn.label'           => 'Uczenie spamu',
];
