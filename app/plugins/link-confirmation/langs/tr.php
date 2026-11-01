<?php
declare(strict_types=1);

/** link-confirmation eklentisinin Türkçe sözlüğü. */
return [
	'plugin.link-confirmation.description' => 'İletilerdeki bağlantılar açılmadan önce onay penceresi (oltalama karşıtı)',
	'plugin.link-confirmation.modal.title' => 'Bu bağlantı açılsın mı?',
	'plugin.link-confirmation.modal.body'  => 'Webmail’den ayrılıp harici bir siteye gidiyorsunuz. Hedef adresi kontrol edin:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Bağlantı metni, gerçek hedeften farklı bir adres gösteriyor',
	'plugin.link-confirmation.modal.reason_ip'            => 'Hedef bir alan adı değil, IP adresi',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Alan adı uluslararasılaştırılmış karakterler içeriyor (punycode) — olası taklit',
	'plugin.link-confirmation.modal.reason_creds'         => 'URL bir kullanıcı adı/parola içeriyor',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Olağan dışı bağlantı türü',

	'plugin.link-confirmation.modal.continue' => 'Devam',
	'plugin.link-confirmation.modal.cancel'   => 'İptal',
	'plugin.link-confirmation.modal.remember' => 'Bu alan adı için bir daha sorma',

	'plugin.link-confirmation.f.mode'            => 'Tetikleme',
	'plugin.link-confirmation.f.mode.all'        => 'Tüm harici bağlantılar',
	'plugin.link-confirmation.f.mode.suspicious' => 'Yalnızca şüpheli bağlantılar',
	'plugin.link-confirmation.f.mode.help'       => '«Yalnızca şüpheli» yalnızca hedefi gizli veya şüpheli görünen bağlantıları onaylatır',
	'plugin.link-confirmation.f.whitelist'       => 'Güvenilir alan adları',
	'plugin.link-confirmation.f.whitelist.help'  => 'Satır başına bir alan adı (veya boşluk, virgül, noktalı virgül ile ayrılmış) — alt alan adları dahil',
	'plugin.link-confirmation.f.warn_mailto'     => 'mailto: ve tel: için de onayla',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Varsayılan olarak bu bağlantılar doğrudan ilişkili uygulamada açılır',
	'plugin.link-confirmation.f.remember'        => '«Bu alan adı için bir daha sorma» seçeneği sun',
	'plugin.link-confirmation.f.remember.help'   => 'Kullanıcının tarayıcısında saklanır (localStorage)',
];
