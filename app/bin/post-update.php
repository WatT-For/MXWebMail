<?php
declare(strict_types=1);

/**
 * Migrations post-update — CLI uniquement.
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
$storage = $app->storageDir();
$dir = $storage . '/update';
is_dir($dir) || mkdir($dir, 0700, true);

$result = ['ok' => true, 'at' => date('c'), 'version' => APP_VERSION, 'errors' => []];

try {
	$app->db()->migrate();
	try {
		$v = $app->db()->meta('schema_version');
		$result['schema_version'] = $v === null ? null : (int) $v;
	} catch (\Throwable) {
		$result['schema_version'] = null;
	}
} catch (\Throwable $e) {
	$result['ok'] = false;
	$result['errors'][] = 'globale : ' . $e->getMessage();
}

foreach (glob($storage . '/db/users/*.sqlite') ?: [] as $f) {
	try {
		MXWebMail\Repository\Database::sqliteFile($f)->migrate();
	} catch (\Throwable $e) {
		$result['ok'] = false;
		$result['errors'][] = basename($f) . ' : ' . $e->getMessage();
	}
}

@unlink($storage . '/cache/classmap.php');

file_put_contents("$dir/post-update.json",
	json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

foreach ($result['errors'] as $e) {
	fwrite(STDERR, "ERREUR $e\n");
}
echo ($result['ok'] ? 'ok' : 'échec')
	. ' — v' . APP_VERSION . ', schéma ' . ($result['schema_version'] ?? '?') . "\n";
exit($result['ok'] ? 0 : 1);
