<?php
declare(strict_types=1);
/** Dicionário português do plugin always-cc-bcc. */
return [
	'plugin.always-cc-bcc.label' => 'CC/BCC automático',
	'plugin.always-cc-bcc.description' => 'Adiciona destinatários Cc ou Bcc sistemáticos a cada envio (arquivo de conformidade, endereço de monitorização)',
	'plugin.always-cc-bcc.bad_addresses' => 'A lista de destinatários automáticos contém endereços inválidos (corrija a configuração do plugin)',
	'plugin.always-cc-bcc.f.mode' => 'Modo',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (invisível)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (visível)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: o destinatário recebe o e-mail sem aparecer aos outros; Cc: aparece em cópia para todos',
	'plugin.always-cc-bcc.f.addresses' => 'Destinatários automáticos',
	'plugin.always-cc-bcc.f.addresses.help' => 'Um ou mais endereços separados por vírgula ou espaço. Vazio = plugin inativo',
	'plugin.always-cc-bcc.f.senders' => 'Contas afetadas',
	'plugin.always-cc-bcc.f.senders.help' => 'Wildcards de remetente, uma por linha (ex. *@dominio.tld, user*@x.tld) - vazio ou * = todas',
];
