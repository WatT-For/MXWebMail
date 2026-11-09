<?php
declare(strict_types=1);
/** English dictionary of the login-notify plugin. */
return [
	'plugin.login-notify.label' => 'Alertas de início de sessão',
	'plugin.login-notify.description' => 'Envia um alerta por e-mail ao iniciar sessão de um IP desconhecido (estilo Gmail) - entregue sem ser visto na caixa',
	'plugin.login-notify.tab' => 'Alertas de início de sessão',
	'plugin.login-notify.subject' => 'Novo acesso à sua caixa de correio',
	'plugin.login-notify.enabled' => 'Notificar-me por e-mail de um início de sessão a partir de um endereço desconhecido',
	'plugin.login-notify.hint' => 'Uma mensagem é depositada na sua caixa quando uma ligação vem de um IP nunca visto para a sua conta.',
	'plugin.login-notify.f.folder' => 'Pasta de entrega',
	'plugin.login-notify.f.folder.help' => 'Pasta IMAP que recebe as mensagens de alerta (deve existir)',
	'plugin.login-notify.f.ignore_private' => 'Ignorar IPs privados',
	'plugin.login-notify.f.ignore_private.help' => 'Os endereços LAN/VPN nunca desencadeiam um alerta',
	'plugin.login-notify.f.mask_ip' => 'Mascarar o IP na mensagem',
	'plugin.login-notify.f.mask_ip.help' => 'O IP guardado permanece completo (necessário para deteção) - apenas o texto é mascarado (1.2.3.x)',
	'plugin.login-notify.f.include_ua' => 'Incluir o cliente (User-Agent)',
	'plugin.login-notify.f.include_ua.help' => 'Identificação do navegador/cliente no corpo do alerta',
	'plugin.login-notify.f.user_control' => 'Desativação por utilizador',
	'plugin.login-notify.f.user_control.help' => 'Adiciona um separador de definições que permite aos utilizadores desativar os seus alertas',
	'plugin.login-notify.body' => 'Olá,  A sua caixa de correio foi acedida a partir de um endereço nunca visto antes.  Endereço IP: {ip} Data: {date} Cliente: {ua}  Se foi você, ignore esta mensagem. Caso contrário, altere imediatamente a sua palavra-passe e contacte o administrador.',
];
