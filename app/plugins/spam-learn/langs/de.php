<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Spam-/Ham-Lern-Feed (rspamc, sa-learn oder dovecot-antispam-Ordner)',
	'plugin.spam-learn.f.driver'           => 'Lern-Treiber',
	'plugin.spam-learn.f.driver.help'      => 'Wie gelernte Nachrichten an die Antispam-Engine übergeben werden',
	'plugin.spam-learn.f.driver.exec'      => 'Befehl (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'IMAP-Ordner (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Spam-Lernbefehl',
	'plugin.spam-learn.f.command_ham'      => 'Ham-Lernbefehl',
	'plugin.spam-learn.f.command.help'     => 'Nur Binärdatei + Argumente; die .eml wird auf stdin gepipet',
	'plugin.spam-learn.f.folder_spam'      => 'Spam-Lernordner',
	'plugin.spam-learn.f.folder_ham'       => 'Ham-Lernordner',
	'plugin.spam-learn.f.folder.help'      => 'Wird bei Bedarf automatisch erstellt',
	'plugin.spam-learn.f.max_bytes'        => 'Max. übertragene Größe (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Nachricht wird über dieser Größe abgeschnitten',
	'plugin.spam-learn.label'           => 'Spam-Lernen',
];
