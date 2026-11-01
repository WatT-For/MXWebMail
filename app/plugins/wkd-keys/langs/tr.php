<?php
declare(strict_types=1);

return [
	'plugin.wkd-keys.description'              => 'Otomatik PGP anahtar keşfi (WKD + Autocrypt toplama)',
	'plugin.wkd-keys.f.keyserver_fallback'     => 'keys.openpgp.org yedeği',
	'plugin.wkd-keys.f.keyserver_fallback.help'=> 'WKD sonuç vermediğinde genel anahtar sunucusunu sorgula',
	'plugin.wkd-keys.f.autocrypt'              => 'Autocrypt başlıklarını topla',
	'plugin.wkd-keys.f.autocrypt.help'         => 'Yazışılan kişilerin Autocrypt başlığında gönderdiği anahtarları sakla',
	'plugin.wkd-keys.bad_email'                => 'Geçersiz e-posta adresi',
	'plugin.wkd-keys.import_confirm'           => '{email} için bir PGP anahtarı bulundu. İçe aktarılsın mı?',
	'plugin.wkd-keys.imported'                 => 'PGP anahtarı içe aktarıldı.',
];
