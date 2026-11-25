<?php
declare(strict_types=1);

/**
 * Schéma de plugin_dmarc_reports - voir PluginManager::migratePlugin.
 * Closure requise pour la clé primaire : INTEGER PRIMARY KEY (SQLite,
 * rowid auto-incrémenté) vs INTEGER AUTO_INCREMENT PRIMARY KEY.
 */
return function (\MXWebMail\Repository\Database $db): array {
	$pk = $db->isSqlite()
		? 'INTEGER PRIMARY KEY' : 'INTEGER AUTO_INCREMENT PRIMARY KEY';
	return [
		1 => [
			"CREATE TABLE IF NOT EXISTS plugin_dmarc_reports (
				id          $pk,
				org         VARCHAR(255) NOT NULL,
				report_id   VARCHAR(255) NOT NULL,
				domain      VARCHAR(255) NOT NULL,
				date_begin  INTEGER NOT NULL,
				date_end    INTEGER NOT NULL,
				source_ip   VARCHAR(45) NOT NULL,
				count       INTEGER NOT NULL,
				disposition VARCHAR(32) NOT NULL,
				dkim        VARCHAR(32) NOT NULL,
				spf         VARCHAR(32) NOT NULL,
				UNIQUE (org, report_id, source_ip, domain))",
		],
	];
};
