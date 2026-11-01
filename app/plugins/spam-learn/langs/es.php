<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Alimentación de aprendizaje spam/ham (rspamc, sa-learn o carpeta dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Controlador de aprendizaje',
	'plugin.spam-learn.f.driver.help'      => 'Cómo se envían los mensajes aprendidos al motor antispam',
	'plugin.spam-learn.f.driver.exec'      => 'Comando (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Carpeta IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Comando de aprendizaje de spam',
	'plugin.spam-learn.f.command_ham'      => 'Comando de aprendizaje de ham',
	'plugin.spam-learn.f.command.help'     => 'Solo binario + argumentos; el .eml se pasa por stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Carpeta de aprendizaje de spam',
	'plugin.spam-learn.f.folder_ham'       => 'Carpeta de aprendizaje de ham',
	'plugin.spam-learn.f.folder.help'      => 'Se crea automáticamente si falta',
	'plugin.spam-learn.f.max_bytes'        => 'Tamaño máx. enviado (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'El mensaje se trunca por encima de este tamaño',
];
