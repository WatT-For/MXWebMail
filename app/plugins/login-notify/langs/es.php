<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Alertas de acceso',
	'plugin.login-notify.description' => 'Envía una alerta por correo al acceder desde una IP desconocida (estilo Gmail) - entregada sin ser vista en el buzón',
	'plugin.login-notify.tab' => 'Alertas de acceso',
	'plugin.login-notify.subject' => 'Nuevo acceso a tu buzón',
	'plugin.login-notify.enabled' => 'Avísame por correo de un acceso desde una dirección desconocida',
	'plugin.login-notify.hint' => 'Se deposita un mensaje en tu buzón cuando una conexión proviene de una IP nunca vista para tu cuenta.',
	'plugin.login-notify.f.folder' => 'Carpeta de entrega',
	'plugin.login-notify.f.folder.help' => 'Carpeta IMAP que recibe los mensajes de alerta (debe existir)',
	'plugin.login-notify.f.ignore_private' => 'Ignorar IPs privadas',
	'plugin.login-notify.f.ignore_private.help' => 'Las direcciones LAN/VPN nunca disparan una alerta',
	'plugin.login-notify.f.mask_ip' => 'Enmascarar la IP en el mensaje',
	'plugin.login-notify.f.mask_ip.help' => 'La IP almacenada sigue completa (necesaria para la detección) - solo el texto se enmascara (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Incluir el cliente (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Identificación del navegador/cliente en el cuerpo de la alerta',
	'plugin.login-notify.f.user_control' => 'Desactivación por usuario',
	'plugin.login-notify.f.user_control.help' => 'Añade una pestaña de ajustes que permite a los usuarios desactivar sus alertas',
	'plugin.login-notify.body' => 'Hola,  Se acaba de acceder a tu buzón desde una dirección no vista antes.  Dirección IP: {ip} Fecha: {date} Cliente: {ua}  Si fuiste tú, ignora este mensaje. Si no, cambia tu contraseña inmediatamente y contacta con tu administrador.',
];
