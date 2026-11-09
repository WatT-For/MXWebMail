<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO via proxy header',
	'plugin.sso-header.description' => 'Auto-login through an authenticating reverse proxy (Authelia, authentik, oauth2-proxy) trusting a user header - requires an IMAP master password and the webmail reachable ONLY behind the proxy',
	'plugin.sso-header.denied' => 'SSO authentication failed or expired - retry or use the manual form',
	'plugin.sso-header.f.header' => 'Trusted header',
	'plugin.sso-header.f.header.help' => 'Header set by the auth proxy (default X-Forwarded-User ; use REMOTE_USER for Apache env var)',
	'plugin.sso-header.f.trusted_ips' => 'Trusted proxy IPs (required)',
	'plugin.sso-header.f.trusted_ips.help' => 'CIDR list, one per line - the direct peer must match. EMPTY = plugin disabled (fail-closed). The instance must NOT be reachable bypassing the proxy',
	'plugin.sso-header.f.master_pass' => 'IMAP master password (required)',
	'plugin.sso-header.f.master_pass.help' => 'Stored in the plugin config like other secrets. "same" mode (Dovecot passdb master=yes): works as every user\'s password',
	'plugin.sso-header.f.master_user' => 'Master user (optional)',
	'plugin.sso-header.f.master_user.help' => 'If set, IMAP/SMTP/Sieve login becomes user*master ("separate" mode) - only for the primary SSO session',
	'plugin.sso-header.f.allowed_domains' => 'Allowed domains',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, one per line (e.g. *@domain.tld). Empty = all',
	'plugin.sso-header.f.hide_form' => 'Collapse the password form',
	'plugin.sso-header.f.hide_form.help' => 'Manual login stays available behind a link - never removed (fallback)',
	'plugin.sso-header.manual_login' => 'Manual login',
];
