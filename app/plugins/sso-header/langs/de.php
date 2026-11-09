<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO via Proxy-Header',
	'plugin.sso-header.description' => 'Auto-Login über einen authentifizierenden Reverse-Proxy (Authelia, authentik, oauth2-proxy), der einen Benutzer-Header setzt - erfordert ein IMAP-Master-Passwort und ein Webmail, das NUR hinter dem Proxy erreichbar ist',
	'plugin.sso-header.denied' => 'SSO-Authentifizierung fehlgeschlagen oder abgelaufen - erneut versuchen oder das manuelle Formular nutzen',
	'plugin.sso-header.f.header' => 'Vertrauenswürdiger Header',
	'plugin.sso-header.f.header.help' => 'Vom Auth-Proxy gesetzter Header (Standard X-Forwarded-User ; REMOTE_USER für die Apache-Umgebungsvariable)',
	'plugin.sso-header.f.trusted_ips' => 'Vertrauenswürdige Proxy-IPs (erforderlich)',
	'plugin.sso-header.f.trusted_ips.help' => 'CIDR-Liste, eine pro Zeile - der direkte Peer muss übereinstimmen. LEER = Plugin deaktiviert (fail-closed). Die Instanz darf NICHT am Proxy vorbei erreichbar sein',
	'plugin.sso-header.f.master_pass' => 'IMAP-Master-Passwort (erforderlich)',
	'plugin.sso-header.f.master_pass.help' => 'Wird wie andere Geheimnisse in der Plugin-Konfiguration gespeichert. "same"-Modus (Dovecot passdb master=yes): gilt als Passwort jedes Benutzers',
	'plugin.sso-header.f.master_user' => 'Master-Benutzer (optional)',
	'plugin.sso-header.f.master_user.help' => 'Wenn gesetzt, wird der IMAP/SMTP/Sieve-Login zu user*master ("separate"-Modus) - nur für die primäre SSO-Sitzung',
	'plugin.sso-header.f.allowed_domains' => 'Erlaubte Domains',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, eine pro Zeile (z. B. *@domain.tld). Leer = alle',
	'plugin.sso-header.f.hide_form' => 'Passwort-Formular einklappen',
	'plugin.sso-header.f.hide_form.help' => 'Der manuelle Login bleibt hinter einem Link verfügbar - nie entfernt (Rückfall)',
	'plugin.sso-header.manual_login' => 'Manuelle Anmeldung',
];
