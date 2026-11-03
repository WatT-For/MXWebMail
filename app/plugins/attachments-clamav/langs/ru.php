<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Проверка ClamAV (clamd) загружаемых вложений',
	'plugin.attachments-clamav.f.socket'           => 'Сокет clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://host:port или unix:///путь/к/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Тайм-аут (секунды)',
	'plugin.attachments-clamav.f.on_error'         => 'Если clamd недоступен',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Разрешить загрузку (предупреждение в лог)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Отклонять загрузку (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny блокирует любую загрузку, пока clamd недоступен',
	'plugin.attachments-clamav.virus_found'        => 'Обнаружен вирус в "{name}"',
	'plugin.attachments-clamav.unavailable'        => 'Антивирус недоступен',
	'plugin.attachments-clamav.bad_response'       => 'Неожиданный ответ сканера: {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Не удаётся прочитать файл "{name}"',
];
