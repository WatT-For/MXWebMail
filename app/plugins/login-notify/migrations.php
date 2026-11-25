<?php
declare(strict_types=1);

/**
 * Schéma de plugin_login_ips - voir PluginManager::migratePlugin.
 * Closure requise pour le dispatch driver : MySQL pur rejette
 * CREATE INDEX IF NOT EXISTS (erreur 1064) ; il est admis sous
 * SQLite et MariaDB (et alreadyApplied couvre le re-run MySQL).
 */
return function (\MXWebMail\Repository\Database $db): array {
	$idx = ($db->isSqlite() || $db->isMariaDb())
		? 'CREATE INDEX IF NOT EXISTS' : 'CREATE INDEX';
	return [
		1 => [
			'CREATE TABLE IF NOT EXISTS plugin_login_ips (
				user_id    INTEGER NOT NULL,
				ip         VARCHAR(45) NOT NULL,
				first_seen INTEGER NOT NULL,
				last_seen  INTEGER NOT NULL,
				hits       INTEGER NOT NULL DEFAULT 1)',
			"$idx idx_loginips_user ON plugin_login_ips (user_id, ip)",
		],
	];
};
