<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO przez nagłówek proxy',
	'plugin.sso-header.description' => 'Automatyczne logowanie przez uwierzytelniający reverse proxy (Authelia, authentik, oauth2-proxy) ustawiający nagłówek użytkownika - wymaga hasła głównego IMAP i webmaila dostępnego TYLKO za proxy',
	'plugin.sso-header.denied' => 'Uwierzytelnienie SSO nieudane lub wygasłe - spróbuj ponownie lub użyj formularza ręcznego',
	'plugin.sso-header.f.header' => 'Zaufany nagłówek',
	'plugin.sso-header.f.header.help' => 'Nagłówek ustawiany przez proxy uwierzytelniające (domyślnie X-Forwarded-User ; REMOTE_USER dla zmiennej Apache)',
	'plugin.sso-header.f.trusted_ips' => 'Zaufane IP proxy (wymagane)',
	'plugin.sso-header.f.trusted_ips.help' => 'Lista CIDR, jedna na linię - bezpośredni peer musi pasować. PUSTE = wtyczka wyłączona (fail-closed). Instancja NIE może być dostępna z pominięciem proxy',
	'plugin.sso-header.f.master_pass' => 'Hasło główne IMAP (wymagane)',
	'plugin.sso-header.f.master_pass.help' => 'Przechowywane w konfiguracji wtyczki jak inne sekrety. Tryb "same" (Dovecot passdb master=yes): działa jako hasło każdego użytkownika',
	'plugin.sso-header.f.master_user' => 'Użytkownik główny (opcjonalny)',
	'plugin.sso-header.f.master_user.help' => 'Jeśli ustawiony, login IMAP/SMTP/Sieve staje się user*master (tryb "separate") - tylko dla głównej sesji SSO',
	'plugin.sso-header.f.allowed_domains' => 'Dozwolone domeny',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, jedna na linię (np. *@domain.tld). Puste = wszystkie',
	'plugin.sso-header.f.hide_form' => 'Zwiń formularz hasła',
	'plugin.sso-header.f.hide_form.help' => 'Logowanie ręczne pozostaje dostępne za linkiem - nigdy usuwane (awaryjne)',
	'plugin.sso-header.manual_login' => 'Logowanie ręczne',
];
