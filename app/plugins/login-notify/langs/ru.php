<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Оповещения о входе',
	'plugin.login-notify.description' => 'Отправляет оповещение на ящик при входе с неизвестного IP (в стиле Gmail) - доставляется незамеченным',
	'plugin.login-notify.tab' => 'Оповещения о входе',
	'plugin.login-notify.subject' => 'Новый вход в ваш почтовый ящик',
	'plugin.login-notify.enabled' => 'Уведомлять меня по почте о входе с неизвестного адреса',
	'plugin.login-notify.hint' => 'Сообщение помещается в ваш ящик, когда подключение происходит с IP, никогда ранее не виденного для вашей учётной записи.',
	'plugin.login-notify.f.folder' => 'Папка доставки',
	'plugin.login-notify.f.folder.help' => 'Папка IMAP для оповещений (должна существовать)',
	'plugin.login-notify.f.ignore_private' => 'Игнорировать частные IP',
	'plugin.login-notify.f.ignore_private.help' => 'Адреса LAN/VPN никогда не вызывают оповещение',
	'plugin.login-notify.f.mask_ip' => 'Маскировать IP в сообщении',
	'plugin.login-notify.f.mask_ip.help' => 'Сохранённый IP остаётся полным (нужен для детекции) - маскируется только текст (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Включать клиента (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Идентификация браузера/клиента в тексте оповещения',
	'plugin.login-notify.f.user_control' => 'Отказ по пользователю',
	'plugin.login-notify.f.user_control.help' => 'Добавляет вкладку настроек, позволяющую пользователям отключать свои оповещения',
	'plugin.login-notify.body' => 'Здравствуйте,  В ваш почтовый ящик только что вошли с адреса, который ранее не встречался.  IP-адрес: {ip} Дата: {date} Клиент: {ua}  Если это были вы, проигнорируйте это сообщение. Если нет - немедленно смените пароль и свяжитесь с администратором.',
];
