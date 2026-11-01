<?php
declare(strict_types=1);

return [
	'plugin.spam-learn.description'        => 'Spam/ham öğrenme beslemesi (rspamc, sa-learn veya dovecot-antispam klasörü)',
	'plugin.spam-learn.f.driver'           => 'Öğrenme sürücüsü',
	'plugin.spam-learn.f.driver.help'      => 'Öğrenilen iletilerin antispam motoruna nasıl beslendiği',
	'plugin.spam-learn.f.driver.exec'      => 'Komut (rspamc / sa-learn)',
	'plugin.spam-learn.f.driver.folder'    => 'IMAP klasörü (dovecot-antispam)',
	'plugin.spam-learn.f.command_spam'     => 'Spam öğrenme komutu',
	'plugin.spam-learn.f.command_ham'      => 'Ham öğrenme komutu',
	'plugin.spam-learn.f.command.help'     => 'Yalnızca ikili + argümanlar; .eml stdin\'e aktarılır',
	'plugin.spam-learn.f.folder_spam'      => 'Spam öğrenme klasörü',
	'plugin.spam-learn.f.folder_ham'       => 'Ham öğrenme klasörü',
	'plugin.spam-learn.f.folder.help'      => 'Yoksa otomatik oluşturulur',
	'plugin.spam-learn.f.max_bytes'        => 'Maks. beslenen boyut (KB)',
	'plugin.spam-learn.f.max_bytes.help'   => 'İleti bu boyutun üzerinde kırpılır',
];
