<?php
declare(strict_types=1);

/** Dictionnaire français du plugin black-list. */
return [
	'plugin.black-list.description'      => 'Liste noire de connexion (wildcards et exceptions)',
	'plugin.black-list.account_denied'   => 'Compte non autorisé',
	'plugin.black-list.f.black_list'     => 'Liste noire',
	'plugin.black-list.f.black_list.help' => 'Emails bloqués, séparés par des espaces, wildcards supportés. Ex. : *@domain1.com user@domain2.com',
	'plugin.black-list.f.exceptions'     => 'Exceptions',
	'plugin.black-list.f.exceptions.help' => 'Emails de la liste noire qui restent autorisés. Ex. : admin@domain1.com',
	'plugin.black-list.f.auth_error'     => "Erreur d'authentification",
	'plugin.black-list.f.auth_error.help' => 'Renvoie une erreur d\'identifiants générique au lieu de "compte non autorisé"',
];
