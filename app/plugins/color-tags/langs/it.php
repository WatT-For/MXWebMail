<?php
declare(strict_types=1);

/** Dizionario italiano del plugin color-tags. */
return [
	'plugin.color-tags.description'         => 'Etichette colorate sui messaggi (manuale + regole automatiche)',
	'plugin.color-tags.tab'                 => 'Etichette',
	'plugin.color-tags.palette'             => 'Tavolozza colori',
	'plugin.color-tags.col_name'            => 'Nome',
	'plugin.color-tags.col_id'              => 'Identificativo',
	'plugin.color-tags.rules'               => 'Regole automatiche',
	'plugin.color-tags.rules_help'          => 'Viene applicata la prima regola corrispondente (l’ordine conta). «*» = sequenza qualsiasi; più modelli separati da spazio o virgola.',
	'plugin.color-tags.field_from'          => 'Da',
	'plugin.color-tags.field_domain'        => 'Dominio',
	'plugin.color-tags.field_subject'       => 'Oggetto',
	'plugin.color-tags.pattern'             => 'Modello',
	'plugin.color-tags.tag'                 => 'Etichetta',
	'plugin.color-tags.action'              => 'Etichette',
	'plugin.color-tags.none'                => 'Nessuna',
	'plugin.color-tags.manual_unsupported'  => 'Il server IMAP non consente etichette persistenti — verranno applicate solo le regole automatiche.',
	'plugin.color-tags.error_params'        => 'Parametri non validi',
	'plugin.color-tags.error_save'          => 'Dati non validi — controlla identificativi, colori e regole',
	'plugin.color-tags.new_tag'             => 'Nuova etichetta',
	'plugin.color-tags.help'                => 'Un’etichetta applicata manualmente ha la precedenza sulle regole automatiche.',
	'plugin.color-tags.defaults.urgent'     => 'Urgente',
	'plugin.color-tags.defaults.important'  => 'Importante',
	'plugin.color-tags.defaults.work'       => 'Lavoro',
	'plugin.color-tags.defaults.personal'   => 'Personale',
	'plugin.color-tags.defaults.follow-up'  => 'Da seguire',
];
