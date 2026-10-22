<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin avatars. */
return [
	'plugin.avatars.description'        => 'Аватар отправителя в просмотрщике (значки известных доменов, Gravatar, favicon, identicon)',
	'plugin.avatars.email_invalid'      => 'Недопустимый адрес',
	'plugin.avatars.not_found'          => 'Нет аватара',
	'plugin.avatars.f.gravatar'         => 'Gravatar',
	'plugin.avatars.f.gravatar.help'    => 'Обращается к gravatar.com (MD5-хеш адреса — вопрос конфиденциальности)',
	'plugin.avatars.f.favicon'          => 'Favicon домена',
	'plugin.avatars.f.favicon.help'     => 'Загружает favicon домена отправителя',
	'plugin.avatars.f.service'          => 'Значки известных сервисов',
	'plugin.avatars.f.service.help'     => 'Встроенные значки для популярных доменов (Google, PayPal…)',
	'plugin.avatars.f.identicon'        => 'Запасной identicon',
	'plugin.avatars.f.identicon.help'   => 'Локально создаваемое изображение, когда внешний источник не отвечает',
	'plugin.avatars.f.identicon.initials' => 'Цветные инициалы',
];