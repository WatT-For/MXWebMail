<?php
declare(strict_types=1);

/** English dictionary of the link-confirmation plugin. */
return [
	'plugin.link-confirmation.description' => 'Confirmation modal before opening links inside messages (anti-phishing)',
	'plugin.link-confirmation.modal.title' => 'Open this link?',
	'plugin.link-confirmation.modal.body'  => 'You are about to leave the webmail for an external site. Check the destination address:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'The link text shows an address different from its real destination',
	'plugin.link-confirmation.modal.reason_ip'            => 'The destination is an IP address, not a domain name',
	'plugin.link-confirmation.modal.reason_punycode'      => 'The domain name contains internationalized characters (punycode) - possible lookalike',
	'plugin.link-confirmation.modal.reason_creds'         => 'The URL embeds a username/password',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Unusual link type',

	'plugin.link-confirmation.modal.continue' => 'Continue',
	'plugin.link-confirmation.modal.cancel'   => 'Cancel',
	'plugin.link-confirmation.modal.remember' => 'Don’t ask again for this domain',

	'plugin.link-confirmation.f.mode'            => 'Trigger',
	'plugin.link-confirmation.f.mode.all'        => 'All external links',
	'plugin.link-confirmation.f.mode.suspicious' => 'Suspicious links only',
	'plugin.link-confirmation.f.mode.help'       => '“Suspicious only” only confirms links whose destination looks hidden or dubious',
	'plugin.link-confirmation.f.whitelist'       => 'Trusted domains',
	'plugin.link-confirmation.f.whitelist.help'  => 'One domain per line (or separated by space, comma, semicolon) - subdomains are included',
	'plugin.link-confirmation.f.warn_mailto'     => 'Also confirm mailto: and tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'By default these links open directly in the associated application',
	'plugin.link-confirmation.f.remember'        => 'Offer “don’t ask again for this domain”',
	'plugin.link-confirmation.f.remember.help'   => 'Stored in the user’s browser (localStorage)',
	'plugin.link-confirmation.label'                     => 'Link confirmation',
];
