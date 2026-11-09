<?php
declare(strict_types=1);
/** Nederlandstalig woordenboek van de plugin always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'Automatische CC/BCC',
	'plugin.always-cc-bcc.description' => 'Voegt systematische Cc- of Bcc-ontvangers toe aan elk uitgaand bericht (compliance-archief, tracking-adres)',
	'plugin.always-cc-bcc.bad_addresses' => 'De lijst met automatische ontvangers bevat ongeldige adressen (corrigeer de pluginconfiguratie)',
	'plugin.always-cc-bcc.f.mode' => 'Modus',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (onzichtbaar)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (zichtbaar)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: de ontvanger krijgt de mail zonder voor anderen zichtbaar te zijn; Cc: voor iedereen zichtbaar in kopie',
	'plugin.always-cc-bcc.f.addresses' => 'Automatische ontvangers',
	'plugin.always-cc-bcc.f.addresses.help' => 'Een of meer adressen, gescheiden door komma of spatie. Leeg = plugin inactief',
	'plugin.always-cc-bcc.f.senders' => 'Betrokken accounts',
	'plugin.always-cc-bcc.f.senders.help' => 'Afzender-wildcards, een per regel (bijv. *@domein.tld, user*@x.tld) - leeg of * = alle',
];
