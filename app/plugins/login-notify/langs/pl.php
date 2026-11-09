<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Alerty logowania',
	'plugin.login-notify.description' => 'Wysyła alert e-mail przy logowaniu z nieznanego IP (styl Gmail) - dostarczany niepostrzeżenie do skrzynki',
	'plugin.login-notify.tab' => 'Alerty logowania',
	'plugin.login-notify.subject' => 'Nowe logowanie do Twojej skrzynki',
	'plugin.login-notify.enabled' => 'Powiadom mnie e-mailem o logowaniu z nieznanego adresu',
	'plugin.login-notify.hint' => 'Wiadomość trafia do Twojej skrzynki, gdy połączenie pochodzi z IP nigdy wcześniej nie widzianego dla Twojego konta.',
	'plugin.login-notify.f.folder' => 'Folder dostarczania',
	'plugin.login-notify.f.folder.help' => 'Folder IMAP odbierający alerty (musi istnieć)',
	'plugin.login-notify.f.ignore_private' => 'Ignoruj prywatne IP',
	'plugin.login-notify.f.ignore_private.help' => 'Adresy LAN/VPN nigdy nie wywołują alertu',
	'plugin.login-notify.f.mask_ip' => 'Maskuj IP w wiadomości',
	'plugin.login-notify.f.mask_ip.help' => 'Zapisane IP pozostaje kompletne (potrzebne do detekcji) - maskowany jest tylko tekst (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Dołącz klienta (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Identyfikacja przeglądarki/klienta w treści alertu',
	'plugin.login-notify.f.user_control' => 'Rezygnacja per użytkownik',
	'plugin.login-notify.f.user_control.help' => 'Dodaje kartę ustawień pozwalającą użytkownikom wyłączyć własne alerty',
	'plugin.login-notify.body' => 'Witaj,  Do Twojej skrzynki właśnie zalogowano się z adresu, którego wcześniej nie widziano.  Adres IP: {ip} Data: {date} Klient: {ua}  Jeśli to Ty, zignoruj tę wiadomość. Jeśli nie, natychmiast zmień hasło i skontaktuj się z administratorem.',
];
