<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO tramite header proxy',
	'plugin.sso-header.description' => 'Login automatico tramite un reverse proxy di autenticazione (Authelia, authentik, oauth2-proxy) che pone un header utente - richiede una password master IMAP e un webmail raggiungibile SOLO dietro il proxy',
	'plugin.sso-header.denied' => 'Autenticazione SSO fallita o scaduta - riprova o usa il modulo manuale',
	'plugin.sso-header.f.header' => 'Header fidato',
	'plugin.sso-header.f.header.help' => 'Header posto dal proxy di autenticazione (default X-Forwarded-User ; REMOTE_USER per la variabile Apache)',
	'plugin.sso-header.f.trusted_ips' => 'IP proxy fidati (obbligatorio)',
	'plugin.sso-header.f.trusted_ips.help' => 'Lista CIDR, una per riga - il peer diretto deve corrispondere. VUOTO = plugin disattivato (fail-closed). L\'istanza NON deve essere raggiungibile aggirando il proxy',
	'plugin.sso-header.f.master_pass' => 'Password master IMAP (obbligatoria)',
	'plugin.sso-header.f.master_pass.help' => 'Memorizzata nella config del plugin come gli altri segreti. Modalità "same" (Dovecot passdb master=yes): funziona come password di ogni utente',
	'plugin.sso-header.f.master_user' => 'Utente master (opzionale)',
	'plugin.sso-header.f.master_user.help' => 'Se impostato, il login IMAP/SMTP/Sieve diventa user*master (modalità "separate") - solo per la sessione SSO primaria',
	'plugin.sso-header.f.allowed_domains' => 'Domini consentiti',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcard, una per riga (es. *@domain.tld). Vuoto = tutti',
	'plugin.sso-header.f.hide_form' => 'Comprimi il modulo password',
	'plugin.sso-header.f.hide_form.help' => 'Il login manuale resta disponibile dietro un link - mai rimosso (ripiego)',
	'plugin.sso-header.manual_login' => 'Accesso manuale',
];
