<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'SSO por cabeçalho de proxy',
	'plugin.sso-header.description' => 'Início de sessão automático através de um reverse proxy de autenticação (Authelia, authentik, oauth2-proxy) que confia num cabeçalho de utilizador - requer uma palavra-passe mestra IMAP e um webmail acessível APENAS atrás do proxy',
	'plugin.sso-header.denied' => 'Autenticação SSO falhada ou expirada - tente novamente ou use o formulário manual',
	'plugin.sso-header.f.header' => 'Cabeçalho de confiança',
	'plugin.sso-header.f.header.help' => 'Cabeçalho colocado pelo proxy de autenticação (predefinição X-Forwarded-User ; REMOTE_USER para a variável Apache)',
	'plugin.sso-header.f.trusted_ips' => 'IPs de proxy de confiança (obrigatório)',
	'plugin.sso-header.f.trusted_ips.help' => 'Lista CIDR, uma por linha - o par direto deve corresponder. VAZIO = plugin desativado (fail-closed). A instância NÃO deve ser acessível contornando o proxy',
	'plugin.sso-header.f.master_pass' => 'Palavra-passe mestra IMAP (obrigatória)',
	'plugin.sso-header.f.master_pass.help' => 'Guardada na config do plugin como outros segredos. Modo "same" (Dovecot passdb master=yes): funciona como a palavra-passe de cada utilizador',
	'plugin.sso-header.f.master_user' => 'Utilizador mestre (opcional)',
	'plugin.sso-header.f.master_user.help' => 'Se definido, o início de sessão IMAP/SMTP/Sieve torna-se user*master (modo "separate") - apenas para a sessão SSO primária',
	'plugin.sso-header.f.allowed_domains' => 'Domínios permitidos',
	'plugin.sso-header.f.allowed_domains.help' => 'Wildcards, uma por linha (ex. *@domain.tld). Vazio = todos',
	'plugin.sso-header.f.hide_form' => 'Recolher o formulário de palavra-passe',
	'plugin.sso-header.f.hide_form.help' => 'O início de sessão manual permanece disponível atrás de um link - nunca removido (recurso)',
	'plugin.sso-header.manual_login' => 'Início de sessão manual',
];
