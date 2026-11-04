<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Подпитка обучения спам/не-спам (rspamc, sa-learn или папка dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Драйвер обучения',
	'plugin.spam-learn.f.driver.help'      => 'Как обученные сообщения передаются антиспам-движку',
	'plugin.spam-learn.f.driver.exec'      => 'Команда (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Папка IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Команда обучения спаму',
	'plugin.spam-learn.f.command_ham'      => 'Команда обучения не-спаму',
	'plugin.spam-learn.f.command.help'     => 'Только бинарник + аргументы; .eml передаётся на stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Папка обучения спаму',
	'plugin.spam-learn.f.folder_ham'       => 'Папка обучения не-спаму',
	'plugin.spam-learn.f.folder.help'      => 'Создаётся автоматически при отсутствии',
	'plugin.spam-learn.f.max_bytes'        => 'Макс. передаваемый размер (КБ)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Сообщение обрезается сверх этого размера',
	'plugin.spam-learn.label'           => 'Обучение спаму',
];
