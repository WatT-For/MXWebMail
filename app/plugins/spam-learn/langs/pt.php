<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Alimentação de aprendizagem spam/ham (rspamc, sa-learn ou pasta dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Driver de aprendizagem',
	'plugin.spam-learn.f.driver.help'      => 'Como as mensagens aprendidas são enviadas ao motor antispam',
	'plugin.spam-learn.f.driver.exec'      => 'Comando (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Pasta IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Comando de aprendizagem de spam',
	'plugin.spam-learn.f.command_ham'      => 'Comando de aprendizagem de ham',
	'plugin.spam-learn.f.command.help'     => 'Apenas binário + argumentos; o .eml é enviado para o stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Pasta de aprendizagem de spam',
	'plugin.spam-learn.f.folder_ham'       => 'Pasta de aprendizagem de ham',
	'plugin.spam-learn.f.folder.help'      => 'Criada automaticamente se não existir',
	'plugin.spam-learn.f.max_bytes'        => 'Tamanho máx. enviado (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'A mensagem é truncada acima deste tamanho',
	'plugin.spam-learn.label'           => 'Aprendizagem de spam',
];
