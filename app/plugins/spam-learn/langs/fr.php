<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Alimentation d\'apprentissage spam/ham (rspamc, sa-learn ou dossier dovecot-antispam)',
	'plugin.spam-learn.f.driver'           => 'Pilote d\'apprentissage',
	'plugin.spam-learn.f.driver.help'      => 'Comment les messages appris sont transmis au moteur antispam',
	'plugin.spam-learn.f.driver.exec'      => 'Commande (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'Dossier IMAP (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Commande d\'apprentissage spam',
	'plugin.spam-learn.f.command_ham'      => 'Commande d\'apprentissage ham',
	'plugin.spam-learn.f.command.help'     => 'Binaire + arguments uniquement ; le .eml est pipé sur stdin',
	'plugin.spam-learn.f.folder_spam'      => 'Dossier d\'apprentissage spam',
	'plugin.spam-learn.f.folder_ham'       => 'Dossier d\'apprentissage ham',
	'plugin.spam-learn.f.folder.help'      => 'Créé automatiquement si absent',
	'plugin.spam-learn.f.max_bytes'        => 'Taille max transmise (Ko)',
	'plugin.spam-learn.f.max_bytes.help'   => 'Le message est tronqué au-delà',
];
