<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Giriş beyaz listesi (jokerler ve istisnalar)',
	'plugin.white-list.account_denied'   => 'Hesaba izin verilmiyor',
	'plugin.white-list.f.white_list'     => 'Beyaz liste',
	'plugin.white-list.f.white_list.help' => 'İzin verilen e-postalar, boşlukla ayrılmış, jokerler desteklenir. Örn.: *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'İstisnalar',
	'plugin.white-list.f.exceptions.help' => 'Beyaz liste dışındaki e-postalardan yalnızca bunlar reddedilir (boş = tümü reddedilir). Örn.: demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => 'Kimlik doğrulama hatası',
	'plugin.white-list.f.auth_error.help' => '"Hesaba izin verilmiyor" yerine genel bir kimlik bilgisi hatası döndürür',
];