<?php
declare(strict_types=1);

/** Deutsches Wörterbuch des Plugins link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Bestätigungsdialog vor dem Öffnen von Links in Nachrichten (Anti-Phishing)',
	'plugin.link-confirmation.modal.title' => 'Diesen Link öffnen?',
	'plugin.link-confirmation.modal.body'  => 'Sie verlassen den Webmailer zu einer externen Website. Prüfen Sie die Zieladresse:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Der Linktext zeigt eine andere Adresse als das tatsächliche Ziel',
	'plugin.link-confirmation.modal.reason_ip'            => 'Das Ziel ist eine IP-Adresse, kein Domainname',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Der Domainname enthält internationalisierte Zeichen (Punycode) — mögliche Nachahmung',
	'plugin.link-confirmation.modal.reason_creds'         => 'Die URL enthält einen Benutzernamen/ein Passwort',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Ungewöhnlicher Linktyp',

	'plugin.link-confirmation.modal.continue' => 'Fortfahren',
	'plugin.link-confirmation.modal.cancel'   => 'Abbrechen',
	'plugin.link-confirmation.modal.remember' => 'Für diese Domain nicht mehr fragen',

	'plugin.link-confirmation.f.mode'            => 'Auslösung',
	'plugin.link-confirmation.f.mode.all'        => 'Alle externen Links',
	'plugin.link-confirmation.f.mode.suspicious' => 'Nur verdächtige Links',
	'plugin.link-confirmation.f.mode.help'       => '„Nur verdächtige“ bestätigt nur Links, deren Ziel verborgen oder zweifelhaft wirkt',
	'plugin.link-confirmation.f.whitelist'       => 'Vertrauenswürdige Domains',
	'plugin.link-confirmation.f.whitelist.help'  => 'Eine Domain pro Zeile (oder getrennt durch Leerzeichen, Komma, Semikolon) — Subdomains eingeschlossen',
	'plugin.link-confirmation.f.warn_mailto'     => 'Auch mailto: und tel: bestätigen',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Standardmäßig öffnen diese Links direkt die zugeordnete Anwendung',
	'plugin.link-confirmation.f.remember'        => '„Für diese Domain nicht mehr fragen“ anbieten',
	'plugin.link-confirmation.f.remember.help'   => 'Im Browser des Nutzers gespeichert (localStorage)',
];
