<?php
declare(strict_types=1);

/** Polski słownik wtyczki link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Okno potwierdzenia przed otwarciem linków w wiadomościach (antyphishing)',
	'plugin.link-confirmation.modal.title' => 'Otworzyć ten link?',
	'plugin.link-confirmation.modal.body'  => 'Zamierzasz opuścić webmail na zewnętrzną stronę. Sprawdź adres docelowy:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Tekst linku wskazuje inny adres niż rzeczywiste miejsce docelowe',
	'plugin.link-confirmation.modal.reason_ip'            => 'Miejsce docelowe to adres IP, a nie nazwa domeny',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Nazwa domeny zawiera znaki zinternacjonalizowane (punycode) - możliwe podszywanie się',
	'plugin.link-confirmation.modal.reason_creds'         => 'Adres URL zawiera nazwę użytkownika/hasło',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Nietypowy typ linku',

	'plugin.link-confirmation.modal.continue' => 'Kontynuuj',
	'plugin.link-confirmation.modal.cancel'   => 'Anuluj',
	'plugin.link-confirmation.modal.remember' => 'Nie pytaj ponownie dla tej domeny',

	'plugin.link-confirmation.f.mode'            => 'Wyzwalanie',
	'plugin.link-confirmation.f.mode.all'        => 'Wszystkie linki zewnętrzne',
	'plugin.link-confirmation.f.mode.suspicious' => 'Tylko podejrzane linki',
	'plugin.link-confirmation.f.mode.help'       => '„Tylko podejrzane" potwierdza wyłącznie linki, których cel wydaje się ukryty lub wątpliwy',
	'plugin.link-confirmation.f.whitelist'       => 'Zaufane domeny',
	'plugin.link-confirmation.f.whitelist.help'  => 'Jedna domena na wiersz (lub oddzielone spacją, przecinkiem, średnikiem) - subdomeny wliczone',
	'plugin.link-confirmation.f.warn_mailto'     => 'Potwierdzaj także mailto: i tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Domyślnie te linki otwierają się bezpośrednio w powiązanej aplikacji',
	'plugin.link-confirmation.f.remember'        => 'Proponuj „nie pytaj ponownie dla tej domeny"',
	'plugin.link-confirmation.f.remember.help'   => 'Zapamiętane w przeglądarce użytkownika (localStorage)',
	'plugin.link-confirmation.label'                     => 'Potwierdzanie linków',
];
