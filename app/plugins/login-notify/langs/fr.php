<?php
declare(strict_types=1);
/** Dictionnaire français du plugin login-notify. */
return [
	'plugin.login-notify.label' => 'Alertes de connexion',
	'plugin.login-notify.description' => 'Envoie un mail d\'alerte au compte lors d\'une connexion depuis une IP inconnue (modèle Gmail) - délivré non lu en boîte',
	'plugin.login-notify.tab' => 'Alertes de connexion',
	'plugin.login-notify.subject' => 'Nouvelle connexion à votre messagerie',
	'plugin.login-notify.body' => "Bonjour,\r\n\r\nVotre messagerie vient d'être consultée depuis une adresse jamais vue.\r\n\r\nAdresse IP : {ip}\r\nDate : {date}\r\nClient : {ua}\r\n\r\nSi c'était vous, ignorez ce message. Sinon, changez immédiatement votre mot de passe et contactez votre administrateur.",
	'plugin.login-notify.enabled' => 'M\'alerter par mail lors d\'une connexion depuis une adresse inconnue',
	'plugin.login-notify.hint' => 'Un message est déposé dans votre boîte quand une connexion provient d\'une IP jamais vue pour votre compte.',
	'plugin.login-notify.f.folder' => 'Dossier de livraison',
	'plugin.login-notify.f.folder.help' => 'Dossier IMAP recevant les alertes (doit exister)',
	'plugin.login-notify.f.ignore_private' => 'Ignorer les IP privées',
	'plugin.login-notify.f.ignore_private.help' => 'Les adresses LAN/VPN ne déclenchent jamais d\'alerte',
	'plugin.login-notify.f.mask_ip' => 'Masquer l\'IP dans le message',
	'plugin.login-notify.f.mask_ip.help' => 'L\'IP stockée reste complète (nécessaire à la détection) - seul le texte est masqué (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Inclure le client (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Identification du navigateur/client dans le corps de l\'alerte',
	'plugin.login-notify.f.user_control' => 'Opt-out par utilisateur',
	'plugin.login-notify.f.user_control.help' => 'Ajoute un onglet Paramètres pour désactiver ses propres alertes',
];
