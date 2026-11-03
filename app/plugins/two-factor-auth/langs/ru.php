<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Двухфакторная аутентификация TOTP (Google Authenticator, Aegis и др.)',
	'plugin.two-factor-auth.tab'                => 'Двухфакторная аутентификация',
	'plugin.two-factor-auth.issuer'             => 'Название службы в приложении TOTP',
	'plugin.two-factor-auth.label'              => 'Имя учётной записи в приложении TOTP',
	'plugin.two-factor-auth.label.help'         => '"Полный адрес" сохраняет e-mail; "Локальная часть" убирает @домен - некоторые приложения выбирают значок по домену.',
	'plugin.two-factor-auth.label.email'        => 'Полный адрес',
	'plugin.two-factor-auth.label.local'        => 'Локальная часть (до @)',
	'plugin.two-factor-auth.icon_url'           => 'URL значка (параметр image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Показывается в Aegis/andOTP/FreeOTP через нестандартный параметр image - Authy игнорирует. Пусто = favicon экземпляра.',
	'plugin.two-factor-auth.title'              => 'Двухфакторная аутентификация (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Секрет:',
	'plugin.two-factor-auth.code_placeholder'   => '6-значный код',
	'plugin.two-factor-auth.verify'             => 'Проверить',
	'plugin.two-factor-auth.generate'           => 'Сгенерировать секрет',
	'plugin.two-factor-auth.disable'            => 'Отключить',
	'plugin.two-factor-auth.status_on'          => 'Двухфакторная аутентификация включена для этой учётной записи.',
	'plugin.two-factor-auth.status_off'         => 'Двухфакторная аутентификация отключена.',
	'plugin.two-factor-auth.disable_hint'       => 'Введите текущий код из приложения и нажмите "Проверить", чтобы отключить.',
	'plugin.two-factor-auth.no_pending'         => 'Нет текущей активации',
	'plugin.two-factor-auth.code_invalid'       => 'Неверный код',
	'plugin.two-factor-auth.code_invalid_disable' => 'Неверный код - введите текущий код, чтобы отключить',
	'plugin.two-factor-auth.admin_title'          => 'Двухфакторная аутентификация администратора (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Применяется к локальной учётной записи администратора (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Локальная учётная запись администратора не настроена (admin_user/admin_password_hash) - настройка не будет действовать.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php недоступен для записи - включение невозможно.',
	'plugin.two-factor-auth.recovery_title'   => 'Резервные коды',
	'plugin.two-factor-auth.recovery_regen'   => 'Сгенерировать коды заново',
	'plugin.two-factor-auth.recovery_left'    => 'Осталось кодов восстановления: {v} - одноразовые, вводятся в поле TOTP.',
];
