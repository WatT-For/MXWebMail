<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin carddav-contacts. */
return [
	'plugin.carddav-contacts.description'    => 'Bir CardDAV adres defterinden kişi senkronizasyonu (Nextcloud, Radicale, iCloud…)',
	'plugin.carddav-contacts.tab'            => 'Uzak adres defteri (CardDAV)',
	'plugin.carddav-contacts.title'          => 'CardDAV senkronizasyonu',
	'plugin.carddav-contacts.help'           => 'Uzak bir adres defterinden (Nextcloud, Radicale, iCloud…) yerel kişilerinize kişi aktarır. İçe aktarılan kişiler her senkronizasyonda değiştirilir; yerel kişilerinize dokunulmaz.',
	'plugin.carddav-contacts.url'            => 'CardDAV URL',
	'plugin.carddav-contacts.url_hint'       => '(örn. https://cloud.example.com veya /remote.php/dav/)',
	'plugin.carddav-contacts.user'           => 'Kullanıcı',
	'plugin.carddav-contacts.password'       => 'Parola',
	'plugin.carddav-contacts.password_hint'  => '(boş = değişmez)',
	'plugin.carddav-contacts.sync'           => 'Senkronize et',
	'plugin.carddav-contacts.last_sync'      => 'Son senkronizasyon: {date}',
	'plugin.carddav-contacts.never_synced'   => 'Hiç senkronize edilmedi.',
	'plugin.carddav-contacts.saved'          => 'Ayarlar kaydedildi.',
	'plugin.carddav-contacts.testing'        => 'Test ediliyor…',
	'plugin.carddav-contacts.syncing'        => 'Senkronize ediliyor…',
	'plugin.carddav-contacts.test_ok'        => 'Bağlantı OK - "{book}" adres defteri, {n} kart.',
	'plugin.carddav-contacts.imported'       => '{n} kişi içe aktarıldı.',
	'plugin.carddav-contacts.not_configured' => 'CardDAV yapılandırılmamış',
	'plugin.carddav-contacts.no_addressbook' => 'Bu URL\'de CardDAV adres defteri bulunamadı',
	'plugin.carddav-contacts.bad_url'        => 'Geçersiz CardDAV URL\'si (HTTPS ve genel bir sunucu gerekli)',
	'plugin.carddav-contacts.pass_clear'  => 'Parolayı temizle',
];