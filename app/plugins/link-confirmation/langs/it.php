<?php
declare(strict_types=1);

/** Dizionario italiano del plugin link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Finestra di conferma prima di aprire i link contenuti nei messaggi (antiphishing)',
	'plugin.link-confirmation.modal.title' => 'Aprire questo link?',
	'plugin.link-confirmation.modal.body'  => 'Stai per lasciare il webmail verso un sito esterno. Controlla l’indirizzo di destinazione:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Il testo del link mostra un indirizzo diverso dalla destinazione reale',
	'plugin.link-confirmation.modal.reason_ip'            => 'La destinazione è un indirizzo IP, non un nome di dominio',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Il nome di dominio contiene caratteri internazionalizzati (punycode) — possibile imitazione',
	'plugin.link-confirmation.modal.reason_creds'         => 'L’URL contiene un nome utente/una password',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Tipo di collegamento insolito',

	'plugin.link-confirmation.modal.continue' => 'Continua',
	'plugin.link-confirmation.modal.cancel'   => 'Annulla',
	'plugin.link-confirmation.modal.remember' => 'Non chiedere più per questo dominio',

	'plugin.link-confirmation.f.mode'            => 'Attivazione',
	'plugin.link-confirmation.f.mode.all'        => 'Tutti i link esterni',
	'plugin.link-confirmation.f.mode.suspicious' => 'Solo link sospetti',
	'plugin.link-confirmation.f.mode.help'       => '«Solo sospetti» conferma unicamente i link la cui destinazione appare nascosta o dubbia',
	'plugin.link-confirmation.f.whitelist'       => 'Domini attendibili',
	'plugin.link-confirmation.f.whitelist.help'  => 'Un dominio per riga (o separati da spazio, virgola, punto e virgola) — i sottodomini sono inclusi',
	'plugin.link-confirmation.f.warn_mailto'     => 'Conferma anche mailto: e tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Per impostazione predefinita questi link si aprono direttamente nell’applicazione associata',
	'plugin.link-confirmation.f.remember'        => 'Proponi «non chiedere più per questo dominio»',
	'plugin.link-confirmation.f.remember.help'   => 'Memorizzato nel browser dell’utente (localStorage)',
];
