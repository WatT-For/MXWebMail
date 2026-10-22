<?php
declare(strict_types=1);

/** Dictionnaire néerlandais du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'TOTP-tweestapsverificatie (Google Authenticator, Aegis, enz.)',
	'plugin.two-factor-auth.tab'                => 'Tweestapsverificatie',
	'plugin.two-factor-auth.issuer'             => 'Dienstnaam getoond in de TOTP-app',
	'plugin.two-factor-auth.label'              => 'Accountnaam in de TOTP-app',
	'plugin.two-factor-auth.label.help'         => '«Volledig adres» behoudt de e-mail; «Lokaal deel» verwijdert het @domein — sommige apps kiezen het pictogram op basis van het domein.',
	'plugin.two-factor-auth.label.email'        => 'Volledig adres',
	'plugin.two-factor-auth.label.local'        => 'Lokaal deel (voor @)',
	'plugin.two-factor-auth.icon_url'           => 'Pictogram-URL (parameter image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Getoond door Aegis/andOTP/FreeOTP via de niet-standaard parameter image — genegeerd door Authy. Leeg = favicon van de installatie.',
	'plugin.two-factor-auth.title'              => 'Tweestapsverificatie (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Geheim:',
	'plugin.two-factor-auth.code_placeholder'   => '6-cijferige code',
	'plugin.two-factor-auth.verify'             => 'Verifiëren',
	'plugin.two-factor-auth.generate'           => 'Een geheim genereren',
	'plugin.two-factor-auth.disable'            => 'Uitschakelen',
	'plugin.two-factor-auth.status_on'          => 'Tweestapsverificatie is ingeschakeld voor dit account.',
	'plugin.two-factor-auth.status_off'         => 'Tweestapsverificatie is uitgeschakeld.',
	'plugin.two-factor-auth.disable_hint'       => 'Voer de huidige code uit uw app in en klik op «Verifiëren» om uit te schakelen.',
	'plugin.two-factor-auth.no_pending'         => 'Geen activering bezig',
	'plugin.two-factor-auth.code_invalid'       => 'Ongeldige code',
	'plugin.two-factor-auth.code_invalid_disable' => 'Ongeldige code — voer de huidige code in om uit te schakelen',
	'plugin.two-factor-auth.admin_title'          => 'Twee-factor-authenticatie beheerder (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Geldt voor het lokale beheerdersaccount (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Geen lokaal beheerdersaccount geconfigureerd (admin_user/admin_password_hash) — instelling heeft geen effect.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php is niet beschrijfbaar — activeren onmogelijk.',
];
