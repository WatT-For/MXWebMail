<?php
declare(strict_types=1);

/** English dictionary of the color-tags plugin. */
return [
	'plugin.color-tags.description'         => 'Color labels on mails (manual + automatic rules)',
	'plugin.color-tags.tab'                 => 'Labels',
	'plugin.color-tags.palette'             => 'Color palette',
	'plugin.color-tags.col_name'            => 'Name',
	'plugin.color-tags.col_id'              => 'ID',
	'plugin.color-tags.rules'               => 'Automatic rules',
	'plugin.color-tags.rules_help'          => 'The first matching rule applies (order matters). "*" = any sequence; multiple patterns separated by space or comma.',
	'plugin.color-tags.field_from'          => 'From',
	'plugin.color-tags.field_domain'        => 'Domain',
	'plugin.color-tags.field_subject'       => 'Subject',
	'plugin.color-tags.pattern'             => 'Pattern',
	'plugin.color-tags.tag'                 => 'Label',
	'plugin.color-tags.action'              => 'Labels',
	'plugin.color-tags.none'                => 'None',
	'plugin.color-tags.manual_unsupported'  => 'The IMAP server does not allow persistent labels - only automatic rules will apply.',
	'plugin.color-tags.error_params'        => 'Invalid parameters',
	'plugin.color-tags.error_save'          => 'Invalid data - check IDs, colors and rules',
	'plugin.color-tags.new_tag'             => 'New label',
	'plugin.color-tags.help'                => 'A manually applied label takes precedence over automatic rules.',
	'plugin.color-tags.defaults.urgent'     => 'Urgent',
	'plugin.color-tags.defaults.important'  => 'Important',
	'plugin.color-tags.defaults.work'       => 'Work',
	'plugin.color-tags.defaults.personal'   => 'Personal',
	'plugin.color-tags.defaults.follow-up'  => 'Follow up',
];
