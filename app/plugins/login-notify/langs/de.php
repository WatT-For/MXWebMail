<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Anmelde-Benachrichtigungen',
	'plugin.login-notify.description' => 'Sendet eine E-Mail-Benachrichtigung bei Anmeldung von unbekannter IP (Gmail-Stil) - unbemerkt im Postfach abgelegt',
	'plugin.login-notify.tab' => 'Anmelde-Benachrichtigungen',
	'plugin.login-notify.subject' => 'Neue Anmeldung in Ihrem Postfach',
	'plugin.login-notify.enabled' => 'Bei Anmeldung von unbekannter Adresse per E-Mail benachrichtigen',
	'plugin.login-notify.hint' => 'Eine Nachricht wird in Ihrem Postfach abgelegt, wenn eine Verbindung von einer für Ihr Konto nie gesehenen IP kommt.',
	'plugin.login-notify.f.folder' => 'Zustellordner',
	'plugin.login-notify.f.folder.help' => 'IMAP-Ordner für die Benachrichtigungen (muss existieren)',
	'plugin.login-notify.f.ignore_private' => 'Private IPs ignorieren',
	'plugin.login-notify.f.ignore_private.help' => 'LAN/VPN-Adressen lösen nie eine Benachrichtigung aus',
	'plugin.login-notify.f.mask_ip' => 'IP in der Nachricht maskieren',
	'plugin.login-notify.f.mask_ip.help' => 'Die gespeicherte IP bleibt vollständig (für die Erkennung nötig) - nur der Text wird maskiert (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Client (User-Agent) einbeziehen',
	'plugin.login-notify.f.include_ua.help' => 'Browser-/Client-Kennung im Benachrichtigungstext',
	'plugin.login-notify.f.user_control' => 'Opt-out pro Benutzer',
	'plugin.login-notify.f.user_control.help' => 'Fügt einen Einstellungs-Tab hinzu, über den Benutzer ihre Benachrichtigungen deaktivieren können',
	'plugin.login-notify.body' => 'Hallo,  Ihr Postfach wurde soeben von einer bisher unbekannten Adresse geöffnet.  IP-Adresse: {ip} Datum: {date} Client: {ua}  Wenn Sie das waren, ignorieren Sie diese Nachricht. Andernfalls ändern Sie sofort Ihr Passwort und kontaktieren Sie Ihren Administrator.',
];
