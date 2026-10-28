<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'TOTP two-factor authentication (Google Authenticator, Aegis, etc.)',
	'plugin.two-factor-auth.tab'                => 'Two-factor authentication',
	'plugin.two-factor-auth.issuer'             => 'Service name shown in the TOTP app',
	'plugin.two-factor-auth.label'              => 'Account name shown in the TOTP app',
	'plugin.two-factor-auth.label.help'         => '“Full address” keeps the email; “Local part” drops the @domain — some apps pick the icon from the domain.',
	'plugin.two-factor-auth.label.email'        => 'Full address',
	'plugin.two-factor-auth.label.local'        => 'Local part (before @)',
	'plugin.two-factor-auth.icon_url'           => 'Icon URL (image= parameter)',
	'plugin.two-factor-auth.icon_url.help'      => 'Shown by Aegis/andOTP/FreeOTP via the non-standard image parameter — ignored by Authy. Empty = instance favicon.',
	'plugin.two-factor-auth.title'              => 'Two-factor authentication (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Secret:',
	'plugin.two-factor-auth.code_placeholder'   => '6-digit code',
	'plugin.two-factor-auth.verify'             => 'Verify',
	'plugin.two-factor-auth.generate'           => 'Generate a secret',
	'plugin.two-factor-auth.disable'            => 'Disable',
	'plugin.two-factor-auth.status_on'          => 'Two-factor authentication is enabled on this account.',
	'plugin.two-factor-auth.status_off'         => 'Two-factor authentication is disabled.',
	'plugin.two-factor-auth.disable_hint'       => 'Enter the current code from your app then click “Verify” to disable.',
	'plugin.two-factor-auth.no_pending'         => 'No activation in progress',
	'plugin.two-factor-auth.code_invalid'       => 'Invalid code',
	'plugin.two-factor-auth.code_invalid_disable' => 'Invalid code — enter the current code to disable',
	'plugin.two-factor-auth.admin_title'          => 'Admin two-factor authentication (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Applies to the local admin account (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'No local admin account configured (admin_user/admin_password_hash) — this setting has no effect until one exists.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php is not writable — cannot enable.',
	'plugin.two-factor-auth.recovery_title'   => 'Recovery codes',
	'plugin.two-factor-auth.recovery_regen'   => 'Regenerate codes',
	'plugin.two-factor-auth.recovery_left'    => '{v} recovery code(s) left — single-use, entered in the TOTP field at sign-in.',
];
