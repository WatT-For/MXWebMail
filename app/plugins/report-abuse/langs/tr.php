<?php
declare(strict_types=1);
/** report-abuse eklentisinin Türkçe sözlüğü. */
return [
	'plugin.report-abuse.label' => 'Oltalama bildirimi',
	'plugin.report-abuse.description' => 'Eylem çubuğunda "Bildir" düğmesi: işaretli iletileri .eml ekleri olarak yönetici tarafından belirlenen abuse adresine iletir',
	'plugin.report-abuse.action' => 'Bildir',
	'plugin.report-abuse.confirm' => '{n} ileti abuse adresine bildirilsin mi? .eml ekleri olarak iletilecekler.',
	'plugin.report-abuse.done' => '{n} ileti bildirildi',
	'plugin.report-abuse.done_partial' => '{n} bildirildi, {f} başarısız',
	'plugin.report-abuse.not_configured' => 'Bildirim yapılandırılmamış (abuse adresi eksik)',
	'plugin.report-abuse.bad_items' => 'Geçersiz seçim',
	'plugin.report-abuse.failed' => 'Bildirim başarısız oldu',
	'plugin.report-abuse.body' => "Oltalama bildirimi.\r\n\r\nHesap: {account}\r\nKlasör: {folder}\r\nTarih: {date}\r\n\r\nBildirilen ileti .eml dosyası olarak ekte.",
	'plugin.report-abuse.f.abuse_address' => 'Abuse adresi',
	'plugin.report-abuse.f.abuse_address.help' => 'Bildirimlerin alıcısı (örn. abuse@alan.tld veya SOC adresi). Boş = eklenti etkisiz',
	'plugin.report-abuse.f.subject_prefix' => 'Konu öneki',
	'plugin.report-abuse.f.subject_prefix.help' => 'Bildirilen iletinin konusunun önüne eklenir',
	'plugin.report-abuse.f.mark_spam' => 'Ayrıca spam olarak işaretle',
	'plugin.report-abuse.f.mark_spam.help' => 'Bildirimden sonra iletileri spam olarak işaretler (hesap ayarına göre taşı/bayrak) - spam öğrenimini de besler',
];
