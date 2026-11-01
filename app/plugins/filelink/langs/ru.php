<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Отправлять слишком большие вложения как временные ссылки для скачивания',
	'plugin.filelink.f.max_size'       => 'Макс. размер файла (МБ)',
	'plugin.filelink.f.max_size.help'  => 'Файлы большего размера отклоняются',
	'plugin.filelink.f.ttl_days'       => 'Срок жизни ссылки (дни)',
	'plugin.filelink.f.ttl_days.help'  => 'Ссылки для скачивания истекают по истечении этого срока',
	'plugin.filelink.error_upload'     => 'Не удалось загрузить filelink',
	'plugin.filelink.error_too_big'    => 'Файл превышает {mb} МБ',
	'plugin.filelink.expired'          => 'Эта ссылка истекла или недействительна.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
];
