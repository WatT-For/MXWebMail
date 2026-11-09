<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Avvisi di accesso',
	'plugin.login-notify.description' => 'Invia un avviso email all\'account al login da un IP sconosciuto (stile Gmail) - consegnata inosservata nella casella',
	'plugin.login-notify.tab' => 'Avvisi di accesso',
	'plugin.login-notify.subject' => 'Nuovo accesso alla tua casella',
	'plugin.login-notify.enabled' => 'Inviami un\'email al login da un indirizzo sconosciuto',
	'plugin.login-notify.hint' => 'Un messaggio viene depositato nella tua casella quando una connessione proviene da un IP mai visto per il tuo account.',
	'plugin.login-notify.f.folder' => 'Cartella di consegna',
	'plugin.login-notify.f.folder.help' => 'Cartella IMAP che riceve i messaggi di avviso (deve esistere)',
	'plugin.login-notify.f.ignore_private' => 'Ignora IP privati',
	'plugin.login-notify.f.ignore_private.help' => 'Gli indirizzi LAN/VPN non attivano mai un avviso',
	'plugin.login-notify.f.mask_ip' => 'Maschera l\'IP nel messaggio',
	'plugin.login-notify.f.mask_ip.help' => 'L\'IP memorizzato resta completo (necessario per il rilevamento) - solo il testo è mascherato (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Includi il client (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Identificazione del browser/client nel corpo dell\'avviso',
	'plugin.login-notify.f.user_control' => 'Disattivazione per utente',
	'plugin.login-notify.f.user_control.help' => 'Aggiunge una scheda impostazioni che permette agli utenti di disattivare i propri avvisi',
	'plugin.login-notify.body' => 'Ciao,  La tua casella è stata appena aperta da un indirizzo mai visto prima.  Indirizzo IP: {ip} Data: {date} Client: {ua}  Se sei stato tu, ignora questo messaggio. Altrimenti cambia subito la password e contatta l\'amministratore.',
];
