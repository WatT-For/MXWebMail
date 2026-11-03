<?php
declare(strict_types=1);

/** Dictionnaire anglais du plugin avatars. */
return [
	'plugin.avatars.description'        => 'Sender avatar in the reader (known-domain icons, Gravatar, favicon, identicon)',
	'plugin.avatars.email_invalid'      => 'Invalid email',
	'plugin.avatars.not_found'          => 'No avatar',
	'plugin.avatars.f.gravatar'         => 'Gravatar',
	'plugin.avatars.f.gravatar.help'    => 'Queries gravatar.com (email MD5 hash - privacy consideration)',
	'plugin.avatars.f.favicon'          => 'Domain favicon',
	'plugin.avatars.f.favicon.help'     => 'Fetches the favicon of the sender’s domain',
	'plugin.avatars.f.service'          => 'Known service icons',
	'plugin.avatars.f.service.help'     => 'Bundled icons for common domains (Google, PayPal…)',
	'plugin.avatars.f.identicon'        => 'Fallback identicon',
	'plugin.avatars.f.identicon.help'   => 'Locally generated image when no external source responds',
	'plugin.avatars.f.identicon.initials' => 'Colored initials',
];
