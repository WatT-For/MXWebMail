<?php
declare(strict_types=1);

/** Nederlandstalig woordenboek van de plugin link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Bevestigingsvenster vóór het openen van links in berichten (anti-phishing)',
	'plugin.link-confirmation.modal.title' => 'Deze link openen?',
	'plugin.link-confirmation.modal.body'  => 'U verlaat de webmail naar een externe site. Controleer het doeladres:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'De linktekst toont een ander adres dan de werkelijke bestemming',
	'plugin.link-confirmation.modal.reason_ip'            => 'De bestemming is een IP-adres, geen domeinnaam',
	'plugin.link-confirmation.modal.reason_punycode'      => 'De domeinnaam bevat geïnternationaliseerde tekens (punycode) - mogelijke nabootsing',
	'plugin.link-confirmation.modal.reason_creds'         => 'De URL bevat een gebruikersnaam/wachtwoord',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Ongewoon linktype',

	'plugin.link-confirmation.modal.continue' => 'Doorgaan',
	'plugin.link-confirmation.modal.cancel'   => 'Annuleren',
	'plugin.link-confirmation.modal.remember' => 'Niet meer vragen voor dit domein',

	'plugin.link-confirmation.f.mode'            => 'Activering',
	'plugin.link-confirmation.f.mode.all'        => 'Alle externe links',
	'plugin.link-confirmation.f.mode.suspicious' => 'Alleen verdachte links',
	'plugin.link-confirmation.f.mode.help'       => '"Alleen verdachte" bevestigt alleen links waarvan de bestemming verborgen of twijfelachtig lijkt',
	'plugin.link-confirmation.f.whitelist'       => 'Vertrouwde domeinen',
	'plugin.link-confirmation.f.whitelist.help'  => 'Eén domein per regel (of gescheiden door spatie, komma, puntkomma) - subdomeinen inbegrepen',
	'plugin.link-confirmation.f.warn_mailto'     => 'Ook mailto: en tel: bevestigen',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Standaard openen deze links rechtstreeks in de gekoppelde toepassing',
	'plugin.link-confirmation.f.remember'        => '"Niet meer vragen voor dit domein" aanbieden',
	'plugin.link-confirmation.f.remember.help'   => 'Opgeslagen in de browser van de gebruiker (localStorage)',
];
