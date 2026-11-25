<?php
declare(strict_types=1);

/**
 * Schéma de plugin_sendrate - voir PluginManager::migratePlugin.
 * Closure requise pour le dispatch driver : MySQL pur rejette
 * CREATE INDEX IF NOT EXISTS (erreur 1064) ; il est admis sous
 * SQLite et MariaDB (et alreadyApplied couvre le re-run MySQL).
 */
return function (\MXWebMail\Repository\Database $db): array {
	$idx = ($db->isSqlite() || $db->isMariaDb())
		? 'CREATE INDEX IF NOT EXISTS' : 'CREATE INDEX';
	return [
		1 => [
			'CREATE TABLE IF NOT EXISTS plugin_sendrate (
				user_id INTEGER NOT NULL,
				ts      INTEGER NOT NULL,
				rcpts   INTEGER NOT NULL)',
			"$idx idx_sendrate_user_ts ON plugin_sendrate (user_id, ts)",
		],
	];
};
