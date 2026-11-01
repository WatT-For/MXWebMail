<?php
declare(strict_types=1);

/** Dictionnaire français du plugin link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Modale de confirmation avant l’ouverture des liens contenus dans les messages (anti-hameçonnage)',
	'plugin.link-confirmation.modal.title' => 'Ouvrir ce lien ?',
	'plugin.link-confirmation.modal.body'  => 'Vous allez quitter le webmail vers un site externe. Vérifiez l’adresse de destination :',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'Le texte du lien indique une adresse différente de sa destination réelle',
	'plugin.link-confirmation.modal.reason_ip'            => 'La destination est une adresse IP, pas un nom de domaine',
	'plugin.link-confirmation.modal.reason_punycode'      => 'Le nom de domaine contient des caractères internationalisés (punycode) — imitation possible',
	'plugin.link-confirmation.modal.reason_creds'         => 'L’URL contient un identifiant/mot de passe intégré',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Type de lien inhabituel',

	'plugin.link-confirmation.modal.continue' => 'Continuer',
	'plugin.link-confirmation.modal.cancel'   => 'Annuler',
	'plugin.link-confirmation.modal.remember' => 'Ne plus demander pour ce domaine',

	'plugin.link-confirmation.f.mode'            => 'Déclenchement',
	'plugin.link-confirmation.f.mode.all'        => 'Tous les liens externes',
	'plugin.link-confirmation.f.mode.suspicious' => 'Liens suspects uniquement',
	'plugin.link-confirmation.f.mode.help'       => '« Suspects uniquement » ne confirme que les liens dont la destination semble masquée ou douteuse',
	'plugin.link-confirmation.f.whitelist'       => 'Domaines de confiance',
	'plugin.link-confirmation.f.whitelist.help'  => 'Un domaine par ligne (ou séparés par espace, virgule, point-virgule) — les sous-domaines sont inclus',
	'plugin.link-confirmation.f.warn_mailto'     => 'Confirmer aussi mailto: et tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Par défaut ces liens s’ouvrent directement dans l’application associée',
	'plugin.link-confirmation.f.remember'        => 'Proposer « ne plus demander pour ce domaine »',
	'plugin.link-confirmation.f.remember.help'   => 'Mémorisé dans le navigateur de l’utilisateur (localStorage)',
];
