<?php
declare(strict_types=1);
/** Dizionario italiano del plugin always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'CC/BCC automatico',
	'plugin.always-cc-bcc.description' => 'Aggiunge destinatari Cc o Bcc sistematici a ogni invio (archivio conformità, indirizzo di monitoraggio)',
	'plugin.always-cc-bcc.bad_addresses' => 'L\'elenco dei destinatari automatici contiene indirizzi non validi (correggere la configurazione del plugin)',
	'plugin.always-cc-bcc.f.mode' => 'Modalità',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (invisibile)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (visibile)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: il destinatario riceve la mail senza apparire agli altri; Cc: appare in copia a tutti',
	'plugin.always-cc-bcc.f.addresses' => 'Destinatari automatici',
	'plugin.always-cc-bcc.f.addresses.help' => 'Uno o più indirizzi separati da virgola o spazio. Vuoto = plugin inattivo',
	'plugin.always-cc-bcc.f.senders' => 'Account interessati',
	'plugin.always-cc-bcc.f.senders.help' => 'Wildcard dei mittenti, una per riga (es. *@dominio.tld, user*@x.tld) - vuoto o * = tutti',
];
