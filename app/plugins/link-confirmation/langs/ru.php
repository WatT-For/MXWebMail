<?php
declare(strict_types=1);

/** Русский словарь плагина link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Окно подтверждения перед открытием ссылок в письмах (антифишинг)',
	'plugin.link-confirmation.modal.title' => 'Открыть эту ссылку?',
	'plugin.link-confirmation.modal.body'  => 'Вы покидаете веб-почту и переходите на внешний сайт. Проверьте адрес назначения:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Текст ссылки указывает адрес, отличный от реального назначения',
	'plugin.link-confirmation.modal.reason_ip'            => 'Назначение - IP-адрес, а не доменное имя',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Доменное имя содержит интернационализированные символы (punycode) - возможная подделка',
	'plugin.link-confirmation.modal.reason_creds'         => 'URL содержит имя пользователя/пароль',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Необычный тип ссылки',

	'plugin.link-confirmation.modal.continue' => 'Продолжить',
	'plugin.link-confirmation.modal.cancel'   => 'Отмена',
	'plugin.link-confirmation.modal.remember' => 'Больше не спрашивать для этого домена',

	'plugin.link-confirmation.f.mode'            => 'Срабатывание',
	'plugin.link-confirmation.f.mode.all'        => 'Все внешние ссылки',
	'plugin.link-confirmation.f.mode.suspicious' => 'Только подозрительные ссылки',
	'plugin.link-confirmation.f.mode.help'       => '"Только подозрительные" подтверждает лишь ссылки, чьё назначение выглядит скрытым или сомнительным',
	'plugin.link-confirmation.f.whitelist'       => 'Доверенные домены',
	'plugin.link-confirmation.f.whitelist.help'  => 'Один домен на строку (или через пробел, запятую, точку с запятой) - поддомены включены',
	'plugin.link-confirmation.f.warn_mailto'     => 'Также подтверждать mailto: и tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'По умолчанию эти ссылки открываются напрямую в связанном приложении',
	'plugin.link-confirmation.f.remember'        => 'Предлагать "больше не спрашивать для этого домена"',
	'plugin.link-confirmation.f.remember.help'   => 'Хранится в браузере пользователя (localStorage)',
];
