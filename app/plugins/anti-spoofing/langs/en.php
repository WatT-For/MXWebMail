<?php
declare(strict_types=1);

/** English dictionary for the anti-spoofing plugin. */
return [
	'plugin.anti-spoofing.description'        => 'Email address and domain spoofing detection in the message reader',
	'plugin.anti-spoofing.title'              => 'Warning — possible spoofing',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => 'The sending address ({return_path}) does not match the displayed sender ({from}).',
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Replies will be sent to {reply_to} instead of the displayed sender ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'This message was sent by {sender} on behalf of {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'The display name "{name}" impersonates a known sender, but the real address is {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'The domain {domain} imitates the legitimate domain {protected}.',

	'plugin.anti-spoofing.f.protected_domains'      => 'Protected domains',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Legitimate domains to protect, in addition to your own domain and the server domains (protected automatically).',
	'plugin.anti-spoofing.f.brands'                 => 'Brands / keywords',
	'plugin.anti-spoofing.f.brands.help'            => 'Keywords detected in the display name (the default list applies if empty).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Check envelope (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Check Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Check Sender header',
	'plugin.anti-spoofing.f.check_display'          => 'Detect display-name spoofing',
	'plugin.anti-spoofing.f.check_lookalike'        => 'Detect lookalike domains',
];