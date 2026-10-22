<?php
declare(strict_types=1);

/** Dictionnaire espagnol du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Autenticación de dos factores TOTP (Google Authenticator, Aegis, etc.)',
	'plugin.two-factor-auth.tab'                => 'Autenticación de dos factores',
	'plugin.two-factor-auth.issuer'             => 'Nombre del servicio mostrado en la app TOTP',
	'plugin.two-factor-auth.label'              => 'Nombre de la cuenta en la app TOTP',
	'plugin.two-factor-auth.label.help'         => '«Dirección completa» conserva el email; «Parte local» quita el @dominio — algunas apps eligen el icono según el dominio.',
	'plugin.two-factor-auth.label.email'        => 'Dirección completa',
	'plugin.two-factor-auth.label.local'        => 'Parte local (antes de @)',
	'plugin.two-factor-auth.icon_url'           => 'URL del icono (parámetro image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Mostrado por Aegis/andOTP/FreeOTP mediante el parámetro no estándar image — ignorado por Authy. Vacío = favicon de la instancia.',
	'plugin.two-factor-auth.title'              => 'Autenticación de dos factores (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Secreto:',
	'plugin.two-factor-auth.code_placeholder'   => 'Código de 6 dígitos',
	'plugin.two-factor-auth.verify'             => 'Verificar',
	'plugin.two-factor-auth.generate'           => 'Generar un secreto',
	'plugin.two-factor-auth.disable'            => 'Desactivar',
	'plugin.two-factor-auth.status_on'          => 'La autenticación de dos factores está activada en esta cuenta.',
	'plugin.two-factor-auth.status_off'         => 'La autenticación de dos factores está desactivada.',
	'plugin.two-factor-auth.disable_hint'       => 'Introduce el código actual de tu app y haz clic en «Verificar» para desactivar.',
	'plugin.two-factor-auth.no_pending'         => 'No hay activación en curso',
	'plugin.two-factor-auth.code_invalid'       => 'Código no válido',
	'plugin.two-factor-auth.code_invalid_disable' => 'Código no válido — introduce el código actual para desactivar',
	'plugin.two-factor-auth.admin_title'          => 'Autenticación de dos factores del administrador (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Se aplica a la cuenta de administrador local (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Sin cuenta de administrador local (admin_user/admin_password_hash) — el ajuste no tendrá efecto.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php no tiene permisos de escritura — no se puede activar.',
];
