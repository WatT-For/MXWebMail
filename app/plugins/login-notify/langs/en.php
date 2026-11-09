<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Login alerts',
	'plugin.login-notify.description' => 'Sends an email alert to the account on login from an unknown IP (Gmail-style) - delivered unseen in the mailbox',
	'plugin.login-notify.tab' => 'Login alerts',
	'plugin.login-notify.subject' => 'New login to your mailbox',
	'plugin.login-notify.body' => "Hello,\r\n\r\nYour mailbox was just accessed from an address not seen before.\r\n\r\nIP address: {ip}\r\nDate: {date}\r\nClient: {ua}\r\n\r\nIf this was you, ignore this message. If not, change your password immediately and contact your administrator.",
	'plugin.login-notify.enabled' => 'Email me on a login from an unknown address',
	'plugin.login-notify.hint' => 'A message is dropped in your mailbox when a connection comes from an IP address never seen for your account.',
	'plugin.login-notify.f.folder' => 'Delivery folder',
	'plugin.login-notify.f.folder.help' => 'IMAP folder receiving the alert messages (must exist)',
	'plugin.login-notify.f.ignore_private' => 'Ignore private IPs',
	'plugin.login-notify.f.ignore_private.help' => 'LAN/VPN addresses never trigger an alert',
	'plugin.login-notify.f.mask_ip' => 'Mask the IP in the message',
	'plugin.login-notify.f.mask_ip.help' => 'The stored IP stays complete (needed for detection) - only the text is masked (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Include the client (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Browser/client identification in the alert body',
	'plugin.login-notify.f.user_control' => 'Per-user opt-out',
	'plugin.login-notify.f.user_control.help' => 'Adds a settings tab letting users disable their own alerts',
];
