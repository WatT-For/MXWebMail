<?php
declare(strict_types=1);

/** Dictionnaire italien du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Autenticazione a due fattori TOTP (Google Authenticator, Aegis, ecc.)',
	'plugin.two-factor-auth.tab'                => 'Autenticazione a due fattori',
	'plugin.two-factor-auth.issuer'             => 'Nome del servizio mostrato nell\'app TOTP',
	'plugin.two-factor-auth.label'              => 'Nome dell\'account nell\'app TOTP',
	'plugin.two-factor-auth.label.help'         => '«Indirizzo completo» mantiene l\'email; «Parte locale» rimuove il @dominio — alcune app scelgono l\'icona in base al dominio.',
	'plugin.two-factor-auth.label.email'        => 'Indirizzo completo',
	'plugin.two-factor-auth.label.local'        => 'Parte locale (prima di @)',
	'plugin.two-factor-auth.icon_url'           => 'URL dell\'icona (parametro image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Mostrata da Aegis/andOTP/FreeOTP tramite il parametro non standard image — ignorata da Authy. Vuoto = favicon dell\'istanza.',
	'plugin.two-factor-auth.title'              => 'Autenticazione a due fattori (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Segreto:',
	'plugin.two-factor-auth.code_placeholder'   => 'Codice a 6 cifre',
	'plugin.two-factor-auth.verify'             => 'Verifica',
	'plugin.two-factor-auth.generate'           => 'Genera un segreto',
	'plugin.two-factor-auth.disable'            => 'Disabilita',
	'plugin.two-factor-auth.status_on'          => 'L\'autenticazione a due fattori è attiva su questo account.',
	'plugin.two-factor-auth.status_off'         => 'L\'autenticazione a due fattori è disattivata.',
	'plugin.two-factor-auth.disable_hint'       => 'Inserisci il codice attuale della tua app e fai clic su «Verifica» per disattivare.',
	'plugin.two-factor-auth.no_pending'         => 'Nessuna attivazione in corso',
	'plugin.two-factor-auth.code_invalid'       => 'Codice non valido',
	'plugin.two-factor-auth.code_invalid_disable' => 'Codice non valido — inserisci il codice attuale per disattivare',
	'plugin.two-factor-auth.admin_title'          => 'Autenticazione a due fattori amministratore (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Si applica all\'account amministratore locale (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Nessun account amministratore locale configurato (admin_user/admin_password_hash) — impostazione senza effetto.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php non è scrivibile — attivazione impossibile.',
	'plugin.two-factor-auth.recovery_title'   => 'Codici di recupero',
	'plugin.two-factor-auth.recovery_regen'   => 'Rigenera codici',
	'plugin.two-factor-auth.recovery_left'    => '{v} codice/i di recupero rimasto/i — monouso, da inserire nel campo TOTP.',
];
