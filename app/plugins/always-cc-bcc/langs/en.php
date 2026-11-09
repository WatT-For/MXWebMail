<?php
declare(strict_types=1);
/** English dictionary of the always-cc-bcc plugin. */
return [
	'plugin.always-cc-bcc.label' => 'Automatic CC/BCC',
	'plugin.always-cc-bcc.description' => 'Adds systematic Cc or Bcc recipients to every outgoing message (compliance archive, tracking address)',
	'plugin.always-cc-bcc.bad_addresses' => 'The automatic recipients list contains invalid addresses (fix the plugin configuration)',
	'plugin.always-cc-bcc.f.mode' => 'Mode',
	'plugin.always-cc-bcc.f.mode.bcc' => 'Bcc (invisible)',
	'plugin.always-cc-bcc.f.mode.cc' => 'Cc (visible)',
	'plugin.always-cc-bcc.f.mode.help' => 'Bcc: the recipient gets the mail without being shown to others; Cc: shown in copy to everyone',
	'plugin.always-cc-bcc.f.addresses' => 'Automatic recipients',
	'plugin.always-cc-bcc.f.addresses.help' => 'One or more addresses separated by comma or space. Empty = plugin inactive',
	'plugin.always-cc-bcc.f.senders' => 'Affected accounts',
	'plugin.always-cc-bcc.f.senders.help' => 'Sender wildcards, one per line (e.g. *@domain.tld, user*@x.tld) - empty or * = all',
];
