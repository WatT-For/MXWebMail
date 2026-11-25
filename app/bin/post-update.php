<?php
declare(strict_types=1);

/**
 * Migrations post-update - CLI uniquement.
 *
 * Usage : php app/bin/post-update.php [chemin-vers-app]
 *
 * Lance les migrations du code actuellement installé : base globale
 * (SQLite ou MariaDB selon config.php) puis chaque base contacts par
 * utilisateur (storage/db/users/*.sqlite). Purge la classmap pour forcer
 * le rescan des classes MailSo/Lib au prochain hit.
 *
 * Utile après une mise à jour manuelle (upload/FTP) ou pour rejouer les
 * migrations ; l'updater intégré utilise /api/admin/update/finalize.
 * Résultat consigné dans storage/update/post-update.json.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$appDir = isset($argv[1]) ? rtrim((string) $argv[1], '/\\') : dirname(__DIR__);
$boot = $appDir . '/src/bootstrap.php';
if (!is_file($boot)) {
	fwrite(STDERR, "bootstrap introuvable : $boot\n");
	exit(1);
}
require $boot;

$app = new MXWebMail\Application\App($appDir, dirname($appDir));
$dir = $app->storageDir() . '/update';
is_dir($dir) || mkdir($dir, 0700, true);

// Logique partagée avec UpdateService::finalize : migrations globale +
// bases contacts, purge classmap/opcache, nettoyage public/setup.
$result = $app->updater()->postUpdateMaintenance()
	+ ['at' => date('c'), 'version' => APP_VERSION];

file_put_contents("$dir/post-update.json",
	json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

foreach ($result['errors'] as $e) {
	fwrite(STDERR, "ERREUR $e\n");
}
echo ($result['ok'] ? 'ok' : 'échec')
	. ' - v' . APP_VERSION . ', schéma ' . ($result['schema_version'] ?? '?') . "\n";
exit($result['ok'] ? 0 : 1);
