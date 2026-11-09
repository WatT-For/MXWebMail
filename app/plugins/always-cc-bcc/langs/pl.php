<?php
declare(strict_types=1);
/** Słownik polski wtyczki always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'Automatyczne CC/BCC',
	'plugin.always-cc-bcc.description' => 'Dodaje systematycznych odbiorców Cc lub Bcc do każdej wysyłki (archiwum zgodności, adres monitoringu)',
	'plugin.always-cc-bcc.bad_addresses' => 'Lista automatycznych odbiorców zawiera nieprawidłowe adresy (popraw konfigurację wtyczki)',
	'plugin.always-cc-bcc.f.mode' => 'Tryb',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (niewidoczny)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (widoczny)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: odbiorca otrzymuje wiadomość bez bycia widocznym dla innych; Cc: widoczny w kopii dla wszystkich',
	'plugin.always-cc-bcc.f.addresses' => 'Automatyczni odbiorcy',
	'plugin.always-cc-bcc.f.addresses.help' => 'Jeden lub więcej adresów oddzielonych przecinkiem lub spacją. Puste = wtyczka nieaktywna',
	'plugin.always-cc-bcc.f.senders' => 'Objęte konta',
	'plugin.always-cc-bcc.f.senders.help' => 'Wzorce nadawców, jeden na linię (np. *@domena.tld, user*@x.tld) - puste lub * = wszystkie',
];
