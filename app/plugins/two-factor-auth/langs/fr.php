<?php
declare(strict_types=1);

/** Dictionnaire français du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Double authentification TOTP (Google Authenticator, Aegis, etc.)',
	'plugin.two-factor-auth.tab'                => 'Double authentification',
	'plugin.two-factor-auth.issuer'             => "Nom du service affiché dans l'application TOTP",
	'plugin.two-factor-auth.label'              => "Nom du compte affiché dans l'application TOTP",
	'plugin.two-factor-auth.label.help'         => "« Adresse complète » conserve l'email ; « Partie locale » retire le @domaine — certaines applis choisissent l'icône selon le domaine.",
	'plugin.two-factor-auth.label.email'        => 'Adresse complète',
	'plugin.two-factor-auth.label.local'        => 'Partie locale (avant @)',
	'plugin.two-factor-auth.icon_url'           => "URL de l'icône (paramètre image=)",
	'plugin.two-factor-auth.icon_url.help'      => 'Affichée par Aegis/andOTP/FreeOTP via le paramètre image non standard — ignorée par Authy. Vide = favicon de l\'instance.',
	'plugin.two-factor-auth.title'              => 'Double authentification (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Secret :',
	'plugin.two-factor-auth.code_placeholder'   => 'Code à 6 chiffres',
	'plugin.two-factor-auth.verify'             => 'Vérifier',
	'plugin.two-factor-auth.generate'           => 'Générer un secret',
	'plugin.two-factor-auth.disable'            => 'Désactiver',
	'plugin.two-factor-auth.status_on'          => 'La double authentification est activée sur ce compte.',
	'plugin.two-factor-auth.status_off'         => 'La double authentification est désactivée.',
	'plugin.two-factor-auth.disable_hint'       => 'Entrez le code courant de votre application puis cliquez « Vérifier » pour désactiver.',
	'plugin.two-factor-auth.no_pending'         => 'Aucune activation en cours',
	'plugin.two-factor-auth.code_invalid'       => 'Code invalide',
	'plugin.two-factor-auth.code_invalid_disable' => 'Code invalide — entrez le code courant pour désactiver',
	'plugin.two-factor-auth.admin_title'          => 'Double authentification administrateur (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'S\'applique au compte admin local (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Aucun compte admin local configuré (admin_user/admin_password_hash) — réglage sans effet tant qu\'il n\'existe pas.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php n\'est pas accessible en écriture — activation impossible.',
	'plugin.two-factor-auth.recovery_title'   => 'Codes de secours',
	'plugin.two-factor-auth.recovery_regen'   => 'Régénérer les codes',
	'plugin.two-factor-auth.recovery_left'    => '{v} code(s) de secours restant(s) — à usage unique, saisissables dans le champ TOTP à la connexion.',
];
