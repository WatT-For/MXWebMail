<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO via proxy-header',
	'plugin.sso-header.description' => 'Automatisch inloggen via een authenticerende reverse proxy (Authelia, authentik, oauth2-proxy) die een gebruikersheader zet - vereist een IMAP-masterwachtwoord en een webmail die ALLEEN achter de proxy bereikbaar is',
	'plugin.sso-header.denied' => 'SSO-authenticatie mislukt of verlopen - probeer opnieuw of gebruik het handmatige formulier',
	'plugin.sso-header.f.header' => 'Vertrouwde header',
	'plugin.sso-header.f.header.help' => 'Header gezet door de auth-proxy (standaard X-Forwarded-User ; REMOTE_USER voor de Apache-omgevingsvariabele)',
	'plugin.sso-header.f.trusted_ips' => 'Vertrouwde proxy-IP\'s (vereist)',
	'plugin.sso-header.f.trusted_ips.help' => 'CIDR-lijst, een per regel - de directe peer moet overeenkomen. LEEG = plug-in uitgeschakeld (fail-closed). De instantie mag NIET om de proxy heen bereikbaar zijn',
	'plugin.sso-header.f.master_pass' => 'IMAP-masterwachtwoord (vereist)',
	'plugin.sso-header.f.master_pass.help' => 'Opgeslagen in de plug-inconfig zoals andere geheimen. "same"-modus (Dovecot passdb master=yes): werkt als wachtwoord van elke gebruiker',
	'plugin.sso-header.f.master_user' => 'Mastergebruiker (optioneel)',
	'plugin.sso-header.f.master_user.help' => 'Indien ingesteld wordt de IMAP/SMTP/Sieve-login user*master ("separate"-modus) - alleen voor de primaire SSO-sessie',
	'plugin.sso-header.f.allowed_domains' => 'Toegestane domeinen',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, een per regel (bijv. *@domain.tld). Leeg = alle',
	'plugin.sso-header.f.hide_form' => 'Wachtwoordformulier inklappen',
	'plugin.sso-header.f.hide_form.help' => 'Handmatig inloggen blijft beschikbaar achter een link - nooit verwijderd (fallback)',
	'plugin.sso-header.manual_login' => 'Handmatige aanmelding',
];
