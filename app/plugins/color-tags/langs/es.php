<?php
declare(strict_types=1);

/** Diccionario español del plugin color-tags. */
return [
	'plugin.color-tags.description'         => 'Etiquetas de color en los correos (manual + reglas automáticas)',
	'plugin.color-tags.tab'                 => 'Etiquetas',
	'plugin.color-tags.palette'             => 'Paleta de colores',
	'plugin.color-tags.col_name'            => 'Nombre',
	'plugin.color-tags.col_id'              => 'Identificador',
	'plugin.color-tags.rules'               => 'Reglas automáticas',
	'plugin.color-tags.rules_help'          => 'Se aplica la primera regla coincidente (el orden importa). «*» = cualquier secuencia; varios patrones separados por espacio o coma.',
	'plugin.color-tags.field_from'          => 'De',
	'plugin.color-tags.field_domain'        => 'Dominio',
	'plugin.color-tags.field_subject'       => 'Asunto',
	'plugin.color-tags.pattern'             => 'Patrón',
	'plugin.color-tags.tag'                 => 'Etiqueta',
	'plugin.color-tags.action'              => 'Etiquetas',
	'plugin.color-tags.none'                => 'Ninguna',
	'plugin.color-tags.manual_unsupported'  => 'El servidor IMAP no permite etiquetas persistentes — solo se aplicarán las reglas automáticas.',
	'plugin.color-tags.error_params'        => 'Parámetros no válidos',
	'plugin.color-tags.error_save'          => 'Datos no válidos — revisa identificadores, colores y reglas',
	'plugin.color-tags.new_tag'             => 'Nueva etiqueta',
	'plugin.color-tags.help'                => 'Una etiqueta aplicada manualmente tiene prioridad sobre las reglas automáticas.',
	'plugin.color-tags.defaults.urgent'     => 'Urgente',
	'plugin.color-tags.defaults.important'  => 'Importante',
	'plugin.color-tags.defaults.work'       => 'Trabajo',
	'plugin.color-tags.defaults.personal'   => 'Personal',
	'plugin.color-tags.defaults.follow-up'  => 'Seguimiento',
];
