<?php
declare(strict_types=1);
/** English dictionary of the send-rate-limit plugin. */
return [
	'plugin.send-rate-limit.label' => 'Gönderim sınırı',
	'plugin.send-rate-limit.description' => 'Hesap başına gönderim kotası: saat/gün başına ileti ve gün başına alıcıyı sınırlar - hesap ele geçirilirse zararı kısıtlar (yalnızca webmail)',
	'plugin.send-rate-limit.exceeded_hour' => 'Saatlik gönderim sınırına ulaşıldı ({n} ileti/saat) - daha sonra tekrar deneyin',
	'plugin.send-rate-limit.exceeded_day' => 'Günlük gönderim sınırına ulaşıldı ({n} ileti/gün) - yarın tekrar deneyin',
	'plugin.send-rate-limit.exceeded_rcpts' => 'Günlük alıcı sınırına ulaşıldı ({n} alıcı/gün)',
	'plugin.send-rate-limit.f.max_msgs_hour' => 'Maks. ileti / saat',
	'plugin.send-rate-limit.f.max_msgs_hour.help' => 'Hesap başına. 0 = sınırsız',
	'plugin.send-rate-limit.f.max_msgs_day' => 'Maks. ileti / gün',
	'plugin.send-rate-limit.f.max_msgs_day.help' => 'Hesap başına. 0 = sınırsız',
	'plugin.send-rate-limit.f.max_rcpts_day' => 'Maks. alıcı / gün',
	'plugin.send-rate-limit.f.max_rcpts_day.help' => 'Hesap ve gün başına toplam To+Cc+Bcc. 0 = sınırsız',
	'plugin.send-rate-limit.f.exempt' => 'Muaf hesaplar',
	'plugin.send-rate-limit.f.exempt.help' => 'Gönderen jokerleri, satır başına bir tane (örn. admin@domain.tld, postmaster@*) - boş = muaf yok',
];
