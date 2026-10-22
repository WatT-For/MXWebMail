<?php
declare(strict_types=1);

/** Dictionnaire polonais du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Uwierzytelnianie dwuskładnikowe TOTP (Google Authenticator, Aegis itd.)',
	'plugin.two-factor-auth.tab'                => 'Uwierzytelnianie dwuskładnikowe',
	'plugin.two-factor-auth.issuer'             => 'Nazwa usługi wyświetlana w aplikacji TOTP',
	'plugin.two-factor-auth.label'              => 'Nazwa konta w aplikacji TOTP',
	'plugin.two-factor-auth.label.help'         => '„Pełny adres” zachowuje e-mail; „Część lokalna” usuwa @domenę — niektóre aplikacje dobierają ikonę według domeny.',
	'plugin.two-factor-auth.label.email'        => 'Pełny adres',
	'plugin.two-factor-auth.label.local'        => 'Część lokalna (przed @)',
	'plugin.two-factor-auth.icon_url'           => 'URL ikony (parametr image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Wyświetlana przez Aegis/andOTP/FreeOTP przez niestandardowy parametr image — ignorowana przez Authy. Puste = favicon instancji.',
	'plugin.two-factor-auth.title'              => 'Uwierzytelnianie dwuskładnikowe (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Tajny klucz:',
	'plugin.two-factor-auth.code_placeholder'   => 'Kod 6-cyfrowy',
	'plugin.two-factor-auth.verify'             => 'Zweryfikuj',
	'plugin.two-factor-auth.generate'           => 'Generuj tajny klucz',
	'plugin.two-factor-auth.disable'            => 'Wyłącz',
	'plugin.two-factor-auth.status_on'          => 'Uwierzytelnianie dwuskładnikowe jest włączone na tym koncie.',
	'plugin.two-factor-auth.status_off'         => 'Uwierzytelnianie dwuskładnikowe jest wyłączone.',
	'plugin.two-factor-auth.disable_hint'       => 'Wpisz bieżący kod z aplikacji i kliknij «Zweryfikuj», aby wyłączyć.',
	'plugin.two-factor-auth.no_pending'         => 'Brak trwającej aktywacji',
	'plugin.two-factor-auth.code_invalid'       => 'Nieprawidłowy kod',
	'plugin.two-factor-auth.code_invalid_disable' => 'Nieprawidłowy kod — wpisz bieżący kod, aby wyłączyć',
	'plugin.two-factor-auth.admin_title'          => 'Uwierzytelnianie dwuskładnikowe administratora (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Dotyczy lokalnego konta administratora (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Brak lokalnego konta administratora (admin_user/admin_password_hash) — ustawienie bez efektu.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php nie jest zapisywalny — włączenie niemożliwe.',
];
