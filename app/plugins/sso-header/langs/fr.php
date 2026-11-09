<?php
declare(strict_types=1);
/** Dictionnaire français du plugin sso-header. */
return [
	'plugin.sso-header.label' => 'SSO par en-tête proxy',
	'plugin.sso-header.description' => 'Connexion automatique via un proxy d\'authentification (Authelia, authentik, oauth2-proxy) qui pose un en-tête utilisateur - exige un mot de passe maître IMAP et un webmail joignable UNIQUEMENT derrière le proxy',
	'plugin.sso-header.denied' => 'Authentification SSO échouée ou expirée - réessayez ou utilisez le formulaire',
	'plugin.sso-header.f.header' => 'En-tête de confiance',
	'plugin.sso-header.f.header.help' => 'En-tête posé par le proxy (défaut X-Forwarded-User ; REMOTE_USER pour la variable Apache)',
	'plugin.sso-header.f.trusted_ips' => 'IP des proxies de confiance (obligatoire)',
	'plugin.sso-header.f.trusted_ips.help' => 'CIDR, un par ligne - le pair direct doit correspondre. VIDE = plugin inactif (fail-closed). L\'instance ne doit PAS être joignable en contournant le proxy',
	'plugin.sso-header.f.master_pass' => 'Mot de passe maître IMAP (obligatoire)',
	'plugin.sso-header.f.master_pass.help' => 'Stocké dans la config du plugin comme les autres secrets. Mode "same" (Dovecot passdb master=yes) : vaut mot de passe de chaque utilisateur',
	'plugin.sso-header.f.master_user' => 'Utilisateur maître (optionnel)',
	'plugin.sso-header.f.master_user.help' => 'Si renseigné, le login IMAP/SMTP/Sieve devient user*master (mode "separate") - uniquement pour la session SSO primaire',
	'plugin.sso-header.f.allowed_domains' => 'Domaines autorisés',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, un par ligne (ex. *@domaine.tld). Vide = tous',
	'plugin.sso-header.f.hide_form' => 'Replier le formulaire mot de passe',
	'plugin.sso-header.f.hide_form.help' => 'Le login manuel reste disponible derrière un lien - jamais supprimé (secours)',
	'plugin.sso-header.manual_login' => 'Connexion manuelle',
];
