<?php
declare(strict_types=1);

/** Dictionnaire allemand du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'TOTP-Zwei-Faktor-Authentifizierung (Google Authenticator, Aegis usw.)',
	'plugin.two-factor-auth.tab'                => 'Zwei-Faktor-Authentifizierung',
	'plugin.two-factor-auth.issuer'             => 'In der TOTP-App angezeigter Dienstname',
	'plugin.two-factor-auth.label'              => 'Kontoname in der TOTP-App',
	'plugin.two-factor-auth.label.help'         => '„Vollständige Adresse“ behält die E-Mail; „Lokaler Teil“ entfernt die @Domain — manche Apps wählen das Symbol anhand der Domain.',
	'plugin.two-factor-auth.label.email'        => 'Vollständige Adresse',
	'plugin.two-factor-auth.label.local'        => 'Lokaler Teil (vor @)',
	'plugin.two-factor-auth.icon_url'           => 'Icon-URL (Parameter image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Wird von Aegis/andOTP/FreeOTP über den nicht standardisierten Parameter image angezeigt — von Authy ignoriert. Leer = Favicon der Instanz.',
	'plugin.two-factor-auth.title'              => 'Zwei-Faktor-Authentifizierung (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Geheimnis:',
	'plugin.two-factor-auth.code_placeholder'   => '6-stelliger Code',
	'plugin.two-factor-auth.verify'             => 'Verifizieren',
	'plugin.two-factor-auth.generate'           => 'Geheimnis erzeugen',
	'plugin.two-factor-auth.disable'            => 'Deaktivieren',
	'plugin.two-factor-auth.status_on'          => 'Die Zwei-Faktor-Authentifizierung ist für dieses Konto aktiviert.',
	'plugin.two-factor-auth.status_off'         => 'Die Zwei-Faktor-Authentifizierung ist deaktiviert.',
	'plugin.two-factor-auth.disable_hint'       => 'Geben Sie den aktuellen Code aus Ihrer App ein und klicken Sie dann auf „Verifizieren", um zu deaktivieren.',
	'plugin.two-factor-auth.no_pending'         => 'Keine Aktivierung im Gange',
	'plugin.two-factor-auth.code_invalid'       => 'Ungültiger Code',
	'plugin.two-factor-auth.code_invalid_disable' => 'Ungültiger Code — geben Sie den aktuellen Code ein, um zu deaktivieren',
	'plugin.two-factor-auth.admin_title'          => 'Zwei-Faktor-Authentifizierung für Administrator (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Gilt für das lokale Admin-Konto (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Kein lokales Admin-Konto konfiguriert (admin_user/admin_password_hash) — die Einstellung bleibt wirkungslos.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php ist nicht beschreibbar — Aktivierung nicht möglich.',
];
