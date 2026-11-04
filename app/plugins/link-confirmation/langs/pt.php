<?php
declare(strict_types=1);

/** Dicionário em português do plugin link-confirmation. */
return [
	'plugin.link-confirmation.description' => 'Janela de confirmação antes de abrir as ligações das mensagens (antiphishing)',
	'plugin.link-confirmation.modal.title' => 'Abrir esta ligação?',
	'plugin.link-confirmation.modal.body'  => 'Está prestes a sair do webmail para um site externo. Verifique o endereço de destino:',

	'plugin.link-confirmation.modal.reason_text_mismatch' => 'O texto da ligação mostra um endereço diferente do destino real',
	'plugin.link-confirmation.modal.reason_ip'            => 'O destino é um endereço IP, não um nome de domínio',
	'plugin.link-confirmation.modal.reason_punycode'      => 'O nome de domínio contém caracteres internacionalizados (punycode) - possível imitação',
	'plugin.link-confirmation.modal.reason_creds'         => 'O URL contém um utilizador/palavra-passe',
	'plugin.link-confirmation.modal.reason_scheme'        => 'Tipo de ligação invulgar',

	'plugin.link-confirmation.modal.continue' => 'Continuar',
	'plugin.link-confirmation.modal.cancel'   => 'Cancelar',
	'plugin.link-confirmation.modal.remember' => 'Não voltar a perguntar para este domínio',

	'plugin.link-confirmation.f.mode'            => 'Ativação',
	'plugin.link-confirmation.f.mode.all'        => 'Todas as ligações externas',
	'plugin.link-confirmation.f.mode.suspicious' => 'Apenas ligações suspeitas',
	'plugin.link-confirmation.f.mode.help'       => '"Apenas suspeitas" confirma apenas ligações cujo destino parece oculto ou duvidoso',
	'plugin.link-confirmation.f.whitelist'       => 'Domínios de confiança',
	'plugin.link-confirmation.f.whitelist.help'  => 'Um domínio por linha (ou separados por espaço, vírgula, ponto e vírgula) - subdomínios incluídos',
	'plugin.link-confirmation.f.warn_mailto'     => 'Confirmar também mailto: e tel:',
	'plugin.link-confirmation.f.warn_mailto.help' => 'Por predefinição estas ligações abrem diretamente na aplicação associada',
	'plugin.link-confirmation.f.remember'        => 'Propor "não voltar a perguntar para este domínio"',
	'plugin.link-confirmation.f.remember.help'   => 'Guardado no navegador do utilizador (localStorage)',
	'plugin.link-confirmation.label'                     => 'Confirmação de links',
];
