<?php
declare(strict_types=1);
/** Русский словарь плагина always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'Автоматические CC/BCC',
	'plugin.always-cc-bcc.description' => 'Добавляет систематических получателей Cc или Bcc к каждому отправлению (архив соответствия, контрольный адрес)',
	'plugin.always-cc-bcc.bad_addresses' => 'Список автоматических получателей содержит недопустимые адреса (исправьте конфигурацию плагина)',
	'plugin.always-cc-bcc.f.mode' => 'Режим',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (невидимый)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (видимый)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: получатель получает письмо невидимо для остальных; Cc: виден в копии всем',
	'plugin.always-cc-bcc.f.addresses' => 'Автоматические получатели',
	'plugin.always-cc-bcc.f.addresses.help' => 'Один или несколько адресов через запятую или пробел. Пусто = плагин неактивен',
	'plugin.always-cc-bcc.f.senders' => 'Затронутые учётные записи',
	'plugin.always-cc-bcc.f.senders.help' => 'Шаблоны отправителей, по одному в строке (напр. *@domen.tld, user*@x.tld) - пусто или * = все',
];
