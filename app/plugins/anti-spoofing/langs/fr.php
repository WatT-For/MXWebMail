<?php
declare(strict_types=1);

/** Dictionnaire français du plugin anti-spoofing. */
return [
	'plugin.anti-spoofing.description'        => "Détection d'usurpation d'adresse et de domaine dans le lecteur de message",
	'plugin.anti-spoofing.title'              => 'Attention - usurpation possible',
	'plugin.anti-spoofing.clean'              => 'Aucune usurpation détectée',

	'plugin.anti-spoofing.alert.envelope_mismatch'  => "L'adresse d'expédition ({return_path}) ne correspond pas à l'expéditeur affiché ({from}).",
	'plugin.anti-spoofing.alert.reply_to_mismatch'  => 'Les réponses seront envoyées à {reply_to} au lieu de l\'expéditeur affiché ({from}).',
	'plugin.anti-spoofing.alert.sender_mismatch'    => 'Le message a été envoyé par {sender} pour le compte de {from}.',
	'plugin.anti-spoofing.alert.display_name_spoof' => 'Le nom affiché "{name}" imite un expéditeur connu, mais l\'adresse réelle est {email}.',
	'plugin.anti-spoofing.alert.display_name_homoglyph' => 'Le nom affiché "{name}" utilise des caractères trompeurs pour imiter un expéditeur connu ; l\'adresse réelle est {email}.',
	'plugin.anti-spoofing.alert.lookalike_domain'   => 'Le domaine {domain} imite le domaine légitime {protected}.',
	'plugin.anti-spoofing.alert.lookalike_subdomain'    => 'Le domaine {domain} fait passer {protected} pour un sous-domaine.',
	'plugin.anti-spoofing.alert.lookalike_tld'          => 'Le domaine {domain} reprend abusivement le nom "{protected}".',

	'plugin.anti-spoofing.f.protected_domains'      => 'Domaines protégés',
	'plugin.anti-spoofing.f.protected_domains.help' => 'Domaines légitimes à protéger, en plus de votre domaine et de ceux du serveur (protégés automatiquement).',
	'plugin.anti-spoofing.f.brands'                 => 'Marques / mots-clés',
	'plugin.anti-spoofing.f.brands.help'            => 'Mots-clés détectés dans le nom d\'affichage (la liste par défaut s\'applique si vide).',
	'plugin.anti-spoofing.f.check_envelope'         => 'Vérifier l\'enveloppe (Return-Path)',
	'plugin.anti-spoofing.f.check_reply_to'         => 'Vérifier le Reply-To',
	'plugin.anti-spoofing.f.check_sender'           => 'Vérifier le champ Sender',
	'plugin.anti-spoofing.f.check_display'          => "Détecter l'usurpation de nom d'affichage",
	'plugin.anti-spoofing.f.check_lookalike'        => 'Détecter les domaines leurres',
	'plugin.anti-spoofing.f.check_auth_results'   => 'Vérifier SPF/DKIM/DMARC (Authentication-Results)',
	'plugin.anti-spoofing.alert.auth_spf_fail'     => 'Échec de la vérification SPF pour cet expéditeur',
	'plugin.anti-spoofing.alert.auth_dkim_fail'    => 'Échec de la vérification de la signature DKIM',
	'plugin.anti-spoofing.alert.auth_dmarc_fail'   => 'Échec de la vérification DMARC',
	'plugin.anti-spoofing.alert.auth_spf_softfail' => 'SPF softfail (expéditeur non autorisé)',
	'plugin.anti-spoofing.alert.auth_dkim_fail_thirdparty' => 'Signature DKIM tierce invalide ({domain}) - probable relais.',
	'plugin.anti-spoofing.alert.auth_pass'         => 'Authentification vérifiée : {mechs}',
];
