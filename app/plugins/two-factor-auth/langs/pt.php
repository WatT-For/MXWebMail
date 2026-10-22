<?php
declare(strict_types=1);

/** Dictionnaire portugais du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'Autenticação de dois fatores TOTP (Google Authenticator, Aegis, etc.)',
	'plugin.two-factor-auth.tab'                => 'Autenticação de dois fatores',
	'plugin.two-factor-auth.issuer'             => 'Nome do serviço mostrado no app TOTP',
	'plugin.two-factor-auth.label'              => 'Nome da conta no app TOTP',
	'plugin.two-factor-auth.label.help'         => '«Endereço completo» mantém o email; «Parte local» remove o @domínio — alguns apps escolhem o ícone pelo domínio.',
	'plugin.two-factor-auth.label.email'        => 'Endereço completo',
	'plugin.two-factor-auth.label.local'        => 'Parte local (antes do @)',
	'plugin.two-factor-auth.icon_url'           => 'URL do ícone (parâmetro image=)',
	'plugin.two-factor-auth.icon_url.help'      => 'Exibido por Aegis/andOTP/FreeOTP via o parâmetro não padrão image — ignorado pelo Authy. Vazio = favicon da instância.',
	'plugin.two-factor-auth.title'              => 'Autenticação de dois fatores (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Segredo:',
	'plugin.two-factor-auth.code_placeholder'   => 'Código de 6 dígitos',
	'plugin.two-factor-auth.verify'             => 'Verificar',
	'plugin.two-factor-auth.generate'           => 'Gerar um segredo',
	'plugin.two-factor-auth.disable'            => 'Desativar',
	'plugin.two-factor-auth.status_on'          => 'A autenticação de dois fatores está ativada nesta conta.',
	'plugin.two-factor-auth.status_off'         => 'A autenticação de dois fatores está desativada.',
	'plugin.two-factor-auth.disable_hint'       => 'Digite o código atual do seu app e clique em «Verificar» para desativar.',
	'plugin.two-factor-auth.no_pending'         => 'Nenhuma ativação em andamento',
	'plugin.two-factor-auth.code_invalid'       => 'Código inválido',
	'plugin.two-factor-auth.code_invalid_disable' => 'Código inválido — digite o código atual para desativar',
	'plugin.two-factor-auth.admin_title'          => 'Autenticação de dois fatores do administrador (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Aplica-se à conta de administrador local (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Sem conta de administrador local (admin_user/admin_password_hash) — a configuração não terá efeito.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php sem permissão de escrita — ativação impossível.',
];
