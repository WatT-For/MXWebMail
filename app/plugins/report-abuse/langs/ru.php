<?php
declare(strict_types=1);
/** Русский словарь плагина report-abuse. */
return [
	'plugin.report-abuse.label' => 'Жалоба на фишинг',
	'plugin.report-abuse.description' => 'Кнопка "Пожаловаться" на панели действий: пересылает отмеченные письма вложениями .eml на адрес abuse, заданный администратором',
	'plugin.report-abuse.action' => 'Пожаловаться',
	'plugin.report-abuse.confirm' => 'Пожаловаться на {n} письмо(а) по адресу abuse? Они будут пересланы вложениями .eml.',
	'plugin.report-abuse.done' => 'Отправлено жалоб: {n}',
	'plugin.report-abuse.done_partial' => '{n} отправлено, {f} не удалось',
	'plugin.report-abuse.not_configured' => 'Жалобы не настроены (нет адреса abuse)',
	'plugin.report-abuse.bad_items' => 'Неверный выбор',
	'plugin.report-abuse.failed' => 'Жалоба не отправлена',
	'plugin.report-abuse.body' => "Жалоба на фишинг.\r\n\r\nУчётная запись: {account}\r\nПапка: {folder}\r\nДата: {date}\r\n\r\nПожалованное письмо во вложении .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Адрес abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Получатель жалоб (напр. abuse@domen.tld или адрес SOC). Пусто = плагин неактивен',
	'plugin.report-abuse.f.subject_prefix' => 'Префикс темы',
	'plugin.report-abuse.f.subject_prefix.help' => 'Добавляется перед темой пожалованного письма',
	'plugin.report-abuse.f.mark_spam' => 'Также отметить как спам',
	'plugin.report-abuse.f.mark_spam.help' => 'После жалобы помечает письма как спам (перемещение/флаг по настройкам) - также питает обучение антиспама',
];
