<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Синхронизация контактов из адресной книги CardDAV (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Удалённая адресная книга (CardDAV)',
	'plugin.carddav-contacts.title'          => 'Синхронизация CardDAV',
	'plugin.carddav-contacts.help'           => 'Импортирует контакты из удалённой адресной книги (Nextcloud, Radicale, iCloud…) в локальные контакты. Импортированные контакты заменяются при каждой синхронизации; локальные контакты не затрагиваются.',
	'plugin.carddav-contacts.url'            => 'URL CardDAV',
	'plugin.carddav-contacts.url_hint'       => '(напр. https://cloud.example.com или /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Пользователь',
	'plugin.carddav-contacts.password'       => 'Пароль',
	'plugin.carddav-contacts.password_hint'  => '(пусто = без изменений)',
	'plugin.carddav-contacts.sync'           => 'Синхронизировать',
	'plugin.carddav-contacts.last_sync'      => 'Последняя синхронизация: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Никогда не синхронизировалось.',
	'plugin.carddav-contacts.saved'          => 'Настройки сохранены.',
	'plugin.carddav-contacts.testing'        => 'Проверка…',
	'plugin.carddav-contacts.syncing'        => 'Синхронизация…',
	'plugin.carddav-contacts.test_ok'        => 'Подключение OK - адресная книга "{book}", карточек: {n}.',
	'plugin.carddav-contacts.imported'       => 'Импортировано контактов: {n}.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV не настроен',
	'plugin.carddav-contacts.no_addressbook' => 'По этому URL адресная книга CardDAV не найдена',
	'plugin.carddav-contacts.bad_url'        => 'Недопустимый URL CardDAV (требуется HTTPS и общедоступный хост)',
	'plugin.carddav-contacts.pass_clear'  => 'Очистить пароль',
	'plugin.carddav-contacts.label'         => 'Контакты CardDAV',
];