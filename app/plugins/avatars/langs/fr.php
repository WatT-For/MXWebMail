<?php
declare(strict_types=1);

/** Dictionnaire français du plugin avatars. */
return [
	'plugin.avatars.description'        => "Avatar de l'expéditeur dans le lecteur (icônes domaines connus, Gravatar, favicon, identicon)",
	'plugin.avatars.email_invalid'      => 'Email invalide',
	'plugin.avatars.not_found'          => 'Aucun avatar',
	'plugin.avatars.f.gravatar'         => 'Gravatar',
	'plugin.avatars.f.gravatar.help'    => "Interroge gravatar.com (hash MD5 de l'email - considération vie privée)",
	'plugin.avatars.f.favicon'          => 'Favicon du domaine',
	'plugin.avatars.f.favicon.help'     => "Récupère le favicon du domaine de l'expéditeur",
	'plugin.avatars.f.service'          => 'Icônes de services connus',
	'plugin.avatars.f.service.help'     => 'Icônes embarquées des domaines courants (Google, PayPal…)',
	'plugin.avatars.f.identicon'        => 'Identicon de repli',
	'plugin.avatars.f.identicon.help'   => "Image générée localement quand aucune source externe ne répond",
	'plugin.avatars.f.identicon.initials' => 'Initiales colorées',
];
