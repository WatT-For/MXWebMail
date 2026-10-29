<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Envoyer les pièces jointes trop volumineuses via un lien de téléchargement expirable',
	'plugin.filelink.f.max_size'       => 'Taille max du fichier (Mo)',
	'plugin.filelink.f.max_size.help'  => 'Les fichiers plus gros sont rejetés',
	'plugin.filelink.f.ttl_days'       => 'Durée de vie du lien (jours)',
	'plugin.filelink.f.ttl_days.help'  => 'Les liens de téléchargement expirent après ce délai',
	'plugin.filelink.error_upload'     => 'Échec de l\'envoi filelink',
	'plugin.filelink.error_too_big'    => 'Le fichier dépasse {mb} Mo',
	'plugin.filelink.expired'          => 'Ce lien a expiré ou est invalide.',
	'plugin.filelink.attached'         => '{name} ({size}) : {url}',
];
