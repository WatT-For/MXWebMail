<?php
declare(strict_types=1);

/** Dictionnaire français du plugin white-list. */
return [
	'plugin.white-list.description'      => 'Liste blanche de connexion (wildcards et exceptions)',
	'plugin.white-list.account_denied'   => 'Compte non autorisé',
	'plugin.white-list.f.white_list'     => 'Liste blanche',
	'plugin.white-list.f.white_list.help' => 'Emails autorisés, séparés par des espaces, wildcards supportés. Ex. : *@domain1.com user@domain2.com',
	'plugin.white-list.f.exceptions'     => 'Exceptions',
	'plugin.white-list.f.exceptions.help' => 'Parmi les emails hors liste blanche, seuls ceux-ci sont rejetés (vide = tous rejetés). Ex. : demo@domain1.com *@domain2.com admin@*',
	'plugin.white-list.f.auth_error'     => "Erreur d'authentification",
	'plugin.white-list.f.auth_error.help' => 'Renvoie une erreur d\'identifiants générique au lieu de « compte non autorisé »',
];
