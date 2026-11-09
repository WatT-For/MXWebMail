<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Aanmeldingenmeldingen',
	'plugin.login-notify.description' => 'Stuurt een e-mailmelding bij aanmelding vanaf een onbekend IP (Gmail-stijl) - ongezien in de mailbox afgeleverd',
	'plugin.login-notify.tab' => 'Aanmeldingsmeldingen',
	'plugin.login-notify.subject' => 'Nieuwe aanmelding op je mailbox',
	'plugin.login-notify.enabled' => 'E-mail mij bij aanmelding vanaf een onbekend adres',
	'plugin.login-notify.hint' => 'Een bericht wordt in je mailbox geplaatst wanneer een verbinding afkomstig is van een IP dat nooit eerder voor je account is gezien.',
	'plugin.login-notify.f.folder' => 'Aflevermap',
	'plugin.login-notify.f.folder.help' => 'IMAP-map die de meldingsberichten ontvangt (moet bestaan)',
	'plugin.login-notify.f.ignore_private' => 'Privé-IP\'s negeren',
	'plugin.login-notify.f.ignore_private.help' => 'LAN/VPN-adressen triggeren nooit een melding',
	'plugin.login-notify.f.mask_ip' => 'IP in het bericht maskeren',
	'plugin.login-notify.f.mask_ip.help' => 'Het opgeslagen IP blijft compleet (nodig voor detectie) - alleen de tekst wordt gemaskeerd (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Client (User-Agent) opnemen',
	'plugin.login-notify.f.include_ua.help' => 'Browser-/clientidentificatie in de meldingstekst',
	'plugin.login-notify.f.user_control' => 'Opt-out per gebruiker',
	'plugin.login-notify.f.user_control.help' => 'Voegt een instellingentab toe waarmee gebruikers hun meldingen kunnen uitschakelen',
	'plugin.login-notify.body' => 'Hallo,  Je mailbox is zojuist geopend vanaf een adres dat nog niet eerder is gezien.  IP-adres: {ip} Datum: {date} Client: {ua}  Als jij dit was, negeer dit bericht. Zo niet, wijzig dan onmiddellijk je wachtwoord en neem contact op met je beheerder.',
];
