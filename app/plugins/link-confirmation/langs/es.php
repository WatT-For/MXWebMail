<?php
declare(strict_types=1);

/** Diccionario español del plugin link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Modal de confirmación antes de abrir los enlaces de los mensajes (antiphishing)',
	'plugin.link-confirmation.modal.title' => '¿Abrir este enlace?',
	'plugin.link-confirmation.modal.body'  => 'Va a salir del webmail hacia un sitio externo. Compruebe la dirección de destino:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'El texto del enlace muestra una dirección distinta de su destino real',
	'plugin.link-confirmation.modal.reason_ip'            => 'El destino es una dirección IP, no un nombre de dominio',
	'plugin.link-confirmation.modal.reason_punycode'      => 'El nombre de dominio contiene caracteres internacionalizados (punycode) - posible suplantación',
	'plugin.link-confirmation.modal.reason_creds'         => 'La URL incluye un usuario/contraseña',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Tipo de enlace inusual',

	'plugin.link-confirmation.modal.continue' => 'Continuar',
	'plugin.link-confirmation.modal.cancel'   => 'Cancelar',
	'plugin.link-confirmation.modal.remember' => 'No volver a preguntar para este dominio',

	'plugin.link-confirmation.f.mode'            => 'Activación',
	'plugin.link-confirmation.f.mode.all'        => 'Todos los enlaces externos',
	'plugin.link-confirmation.f.mode.suspicious' => 'Solo enlaces sospechosos',
	'plugin.link-confirmation.f.mode.help'       => '"Solo sospechosos" confirma únicamente los enlaces cuyo destino parece oculto o dudoso',
	'plugin.link-confirmation.f.whitelist'       => 'Dominios de confianza',
	'plugin.link-confirmation.f.whitelist.help'  => 'Un dominio por línea (o separados por espacio, coma, punto y coma) - los subdominios están incluidos',
	'plugin.link-confirmation.f.warn_mailto'     => 'Confirmar también mailto: y tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Por defecto estos enlaces se abren directamente en la aplicación asociada',
	'plugin.link-confirmation.f.remember'        => 'Proponer "no volver a preguntar para este dominio"',
	'plugin.link-confirmation.f.remember.help'   => 'Guardado en el navegador del usuario (localStorage)',
	'plugin.link-confirmation.label'                     => 'Confirmación de enlaces',
];
