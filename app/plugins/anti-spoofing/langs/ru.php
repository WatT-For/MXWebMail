<?php
declare(strict_types=1);

/** Dictionnaire russe du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => 'Обнаружение подделки адресов и доменов в просмотрщике сообщений',
	'plugin.anti-spoofing.title'              => 'Предупреждение — возможная подделка',
	'plugin.anti-spoofing.clean'              => 'Подделка не обнаружена',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'Адрес отправки ({return_path}) не совпадает с отображаемым отправителем ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Ответы будут отправлены на {reply_to} вместо отображаемого отправителя ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Это сообщение отправлено {sender} от имени {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Отображаемое имя «{name}» выдаёт себя за известного отправителя, но реальный адрес — {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Домен {domain} имитирует легитимный домен {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Защищённые домены',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Легитимные домены для защиты, помимо вашего домена и доменов сервера (защищаются автоматически).',
	'plugin.anti-spoofing.f.brands'                 => 'Бренды / ключевые слова',
	'plugin.anti-spoofing.f.brands.help'            => 'Ключевые слова, обнаруживаемые в отображаемом имени (пусто = список по умолчанию).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Проверять envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Проверять Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Проверять заголовок Sender',
	'plugin.anti-spoofing.f.check_display'          => 'Обнаруживать подделку отображаемого имени',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Обнаруживать похожие домены',
	'plugin.anti-spoofing.f.check_auth_results'   => 'Проверять SPF/DKIM/DMARC (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'Проверка SPF для этого отправителя не пройдена',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'Проверка подписи DKIM не пройдена',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'Проверка DMARC не пройдена',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF softfail (отправитель не авторизован)',
	'plugin.anti-spoofing.alert.auth_pass_all'     => 'SPF, DKIM и DMARC проверены (pass)',
];