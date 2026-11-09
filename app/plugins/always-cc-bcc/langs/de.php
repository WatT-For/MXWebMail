<?php
declare(strict_types=1);
/** Deutsches Wörterbuch des Plugins always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'Automatische CC/BCC',
	'plugin.always-cc-bcc.description' => 'Fügt jedem ausgehenden Mail systematische Cc- oder Bcc-Empfänger hinzu (Compliance-Archiv, Tracking-Adresse)',
	'plugin.always-cc-bcc.bad_addresses' => 'Die Liste der automatischen Empfänger enthält ungültige Adressen (Plugin-Konfiguration korrigieren)',
	'plugin.always-cc-bcc.f.mode' => 'Modus',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (unsichtbar)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (sichtbar)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: Empfänger erhält die Mail ohne für andere sichtbar zu sein; Cc: für alle sichtbar in Kopie',
	'plugin.always-cc-bcc.f.addresses' => 'Automatische Empfänger',
	'plugin.always-cc-bcc.f.addresses.help' => 'Eine oder mehrere Adressen, getrennt durch Komma oder Leerzeichen. Leer = Plugin inaktiv',
	'plugin.always-cc-bcc.f.senders' => 'Betroffene Konten',
	'plugin.always-cc-bcc.f.senders.help' => 'Absender-Wildcards, eine pro Zeile (z. B. *@domain.tld, user*@x.tld) - leer oder * = alle',
];
