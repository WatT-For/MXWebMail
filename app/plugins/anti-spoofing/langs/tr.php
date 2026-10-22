<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'İleti okuyucuda e-posta adresi ve alan adı taklidi algılama',
	'plugin.anti-spoofing.title'              => 'Uyarı — olası taklit',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Gönderim adresi ({return_path}) görüntülenen gönderenle ({from}) eşleşmiyor.',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Yanıtlar görüntülenen gönderen ({from}) yerine {reply_to} adresine gönderilecek.',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Bu ileti {from} adına {sender} tarafından gönderildi.',
	'plugin.anti-spoofing.alert.display_name_spoof' => '«{name}» görünen adı bilinen bir göndereni taklit ediyor, ancak gerçek adres {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => '{domain} alan adı meşru {protected} alan adını taklit ediyor.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Korunan alan adları',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Kendi alan adınıza ve sunucu alan adlarına (otomatik korunur) ek olarak korunacak meşru alan adları.',
	'plugin.anti-spoofing.f.brands'                 => 'Markalar / anahtar sözcükler',
	'plugin.anti-spoofing.f.brands.help'            => 'Görünen adda algılanan anahtar sözcükler (boşsa varsayılan liste geçerli).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Envelope\'u denetle (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Reply-To denetle',
	'plugin.anti-spoofing.f.check_sender'           => 'Sender başlığını denetle',
	'plugin.anti-spoofing.f.check_display'          => 'Görünen ad taklidini algıla',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Benzer alan adlarını algıla',
];