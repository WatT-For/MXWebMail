<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Giriş uyarıları',
	'plugin.login-notify.description' => 'Bilinmeyen bir IP\'den girişte hesaba e-posta uyarısı gönderir (Gmail tarzı) - posta kutusuna fark edilmeden bırakılır',
	'plugin.login-notify.tab' => 'Giriş uyarıları',
	'plugin.login-notify.subject' => 'Posta kutunuza yeni giriş',
	'plugin.login-notify.enabled' => 'Bilinmeyen bir adresten girişte bana e-posta gönder',
	'plugin.login-notify.hint' => 'Hesabınız için hiç görülmemiş bir IP\'den bağlantı geldiğinde posta kutunuza bir mesaj bırakılır.',
	'plugin.login-notify.f.folder' => 'Teslimat klasörü',
	'plugin.login-notify.f.folder.help' => 'Uyarı iletilerini alan IMAP klasörü (var olmalı)',
	'plugin.login-notify.f.ignore_private' => 'Özel IP\'leri yoksay',
	'plugin.login-notify.f.ignore_private.help' => 'LAN/VPN adresleri asla uyarı tetiklemez',
	'plugin.login-notify.f.mask_ip' => 'İletide IP\'yi maskele',
	'plugin.login-notify.f.mask_ip.help' => 'Kayıtlı IP eksiksiz kalır (algılama için gerekli) - yalnızca metin maskelenir (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'İstemciyi dahil et (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Uyarı gövdesinde tarayıcı/istemci tanımlaması',
	'plugin.login-notify.f.user_control' => 'Kullanıcı başına devre dışı bırakma',
	'plugin.login-notify.f.user_control.help' => 'Kullanıcıların kendi uyarılarını devre dışı bırakmasını sağlayan bir ayarlar sekmesi ekler',
	'plugin.login-notify.body' => 'Merhaba,  Posta kutunuza az önce daha önce görülmemiş bir adresten erişildi.  IP adresi: {ip} Tarih: {date} İstemci: {ua}  Bu sizseniz, bu mesajı yok sayın. Değilse, hemen parolanızı değiştirin ve yöneticinizle iletişime geçin.',
];
