<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Sincronização de contatos de uma agenda CardDAV (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Agenda remota (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Sincronização CardDAV',
	'plugin.carddav-contacts.help'           => 'Importa contatos de uma agenda remota (Nextcloud, Radicale, iCloud…) para seus contatos locais. Os contatos importados são substituídos a cada sincronização; seus contatos locais não são alterados.',
	'plugin.carddav-contacts.url'            => 'URL do CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(ex. https://cloud.example.com ou /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Usuário',
	'plugin.carddav-contacts.password'       => 'Senha',
	'plugin.carddav-contacts.password_hint'  => '(vazio = inalterada)',
	'plugin.carddav-contacts.sync'           => 'Sincronizar',
	'plugin.carddav-contacts.last_sync'      => 'Última sincronização: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Nunca sincronizada.',
	'plugin.carddav-contacts.saved'          => 'Configurações salvas.',
	'plugin.carddav-contacts.testing'        => 'Testando…',
	'plugin.carddav-contacts.syncing'        => 'Sincronizando…',
	'plugin.carddav-contacts.test_ok'        => 'Conexão OK — agenda «{book}», {n} cartão(ões).',
	'plugin.carddav-contacts.imported'       => '{n} contato(s) importados.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV não configurado',
	'plugin.carddav-contacts.no_addressbook' => 'Nenhuma agenda CardDAV encontrada nesta URL',
	'plugin.carddav-contacts.bad_url'        => 'URL de CardDAV inválido (HTTPS e um host público são obrigatórios)',
	'plugin.carddav-contacts.pass_clear'  => 'Limpar palavra-passe',
];