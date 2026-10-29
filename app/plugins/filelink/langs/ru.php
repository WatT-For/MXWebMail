<?php
declare(strict_types=1);

return [
	'plugin.filelink.description'      => 'Send oversized attachments as expiring download links',
	'plugin.filelink.f.max_size'       => 'Max file size (MB)',
	'plugin.filelink.f.max_size.help'  => 'Files larger than this are rejected',
	'plugin.filelink.f.ttl_days'       => 'Link lifetime (days)',
	'plugin.filelink.f.ttl_days.help'  => 'Download links expire after this delay',
	'plugin.filelink.error_upload'     => 'Filelink upload failed',
	'plugin.filelink.error_too_big'    => 'File exceeds {mb} MB',
	'plugin.filelink.expired'          => 'This link has expired or is invalid.',
	'plugin.filelink.attached'         => '{name} ({size}): {url}',
];
