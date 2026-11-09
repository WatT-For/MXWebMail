<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO por cabecera de proxy',
	'plugin.sso-header.description' => 'Inicio de sesión automático a través de un proxy inverso de autenticación (Authelia, authentik, oauth2-proxy) que confía en una cabecera de usuario - requiere una contraseña maestra IMAP y un webmail accesible SOLO detrás del proxy',
	'plugin.sso-header.denied' => 'Autenticación SSO fallida o expirada - reintenta o usa el formulario manual',
	'plugin.sso-header.f.header' => 'Cabecera de confianza',
	'plugin.sso-header.f.header.help' => 'Cabecera puesta por el proxy de autenticación (por defecto X-Forwarded-User ; REMOTE_USER para la variable Apache)',
	'plugin.sso-header.f.trusted_ips' => 'IPs de proxy de confianza (obligatorio)',
	'plugin.sso-header.f.trusted_ips.help' => 'Lista CIDR, una por línea - el par directo debe coincidir. VACÍO = plugin desactivado (fail-closed). La instancia NO debe ser accesible evitando el proxy',
	'plugin.sso-header.f.master_pass' => 'Contraseña maestra IMAP (obligatoria)',
	'plugin.sso-header.f.master_pass.help' => 'Almacenada en la config del plugin como otros secretos. Modo "same" (Dovecot passdb master=yes): funciona como la contraseña de cada usuario',
	'plugin.sso-header.f.master_user' => 'Usuario maestro (opcional)',
	'plugin.sso-header.f.master_user.help' => 'Si se define, el login IMAP/SMTP/Sieve pasa a ser user*master (modo "separate") - solo para la sesión SSO primaria',
	'plugin.sso-header.f.allowed_domains' => 'Dominios permitidos',
	'plugin.sso-header.f.allowed_domains.help' => 'Comodines, uno por línea (ej. *@domain.tld). Vacío = todos',
	'plugin.sso-header.f.hide_form' => 'Plegar el formulario de contraseña',
	'plugin.sso-header.f.hide_form.help' => 'El login manual sigue disponible tras un enlace - nunca eliminado (respaldo)',
	'plugin.sso-header.manual_login' => 'Inicio de sesión manual',
];
