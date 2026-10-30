<?php
declare(strict_types=1);

/** Dictionnaire français du plugin color-tags. */
return [
	'plugin.color-tags.description'         => 'Étiquettes couleur sur les mails (manuel + règles automatiques)',
	'plugin.color-tags.tab'                 => 'Étiquettes',
	'plugin.color-tags.palette'             => 'Palette de couleurs',
	'plugin.color-tags.col_name'            => 'Nom',
	'plugin.color-tags.col_id'              => 'Identifiant',
	'plugin.color-tags.rules'               => 'Règles automatiques',
	'plugin.color-tags.rules_help'          => 'La première règle correspondante est appliquée (l’ordre compte). « * » = suite quelconque ; plusieurs motifs possibles séparés par espace ou virgule.',
	'plugin.color-tags.field_from'          => 'De',
	'plugin.color-tags.field_domain'        => 'Domaine',
	'plugin.color-tags.field_subject'       => 'Objet',
	'plugin.color-tags.pattern'             => 'Motif',
	'plugin.color-tags.tag'                 => 'Étiquette',
	'plugin.color-tags.action'              => 'Étiquettes',
	'plugin.color-tags.none'                => 'Aucune',
	'plugin.color-tags.manual_unsupported'  => 'Le serveur IMAP ne permet pas les étiquettes persistantes — seules les règles automatiques s’appliqueront.',
	'plugin.color-tags.error_params'        => 'Paramètres invalides',
	'plugin.color-tags.error_save'          => 'Données invalides — vérifiez les identifiants, les couleurs et les règles',
	'plugin.color-tags.new_tag'             => 'Nouvelle étiquette',
	'plugin.color-tags.help'                => 'Une étiquette posée manuellement est prioritaire sur les règles automatiques.',
	'plugin.color-tags.defaults.urgent'     => 'Urgent',
	'plugin.color-tags.defaults.important'  => 'Important',
	'plugin.color-tags.defaults.work'       => 'Travail',
	'plugin.color-tags.defaults.personal'   => 'Personnel',
	'plugin.color-tags.defaults.follow-up'  => 'À suivre',
];
