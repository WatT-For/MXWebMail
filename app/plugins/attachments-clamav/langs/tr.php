<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Yüklenen eklerin ClamAV (clamd) taraması',
	'plugin.attachments-clamav.f.socket'           => 'clamd soketi',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port veya unix:///dizin/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Zaman aşımı (saniye)',
	'plugin.attachments-clamav.f.on_error'         => 'clamd erişilemezken',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Yüklemeye izin ver (uyarıyı günlüğe yaz)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Yüklemeyi reddet (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny, clamd kapalıyken tüm yüklemeleri engeller',
	'plugin.attachments-clamav.virus_found'        => '“{name}” içinde virüs tespit edildi',
	'plugin.attachments-clamav.unavailable'        => 'Virüs tarayıcısına ulaşılamıyor',
	'plugin.attachments-clamav.bad_response'       => 'Beklenmeyen tarayıcı yanıtı: {msg}',
	'plugin.attachments-clamav.read_failed'         => '“{name}” dosyası okunamıyor',
	'plugin.attachments-clamav.label'           => 'ClamAV antivirüsü',
];
