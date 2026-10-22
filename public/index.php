<?php
declare(strict_types=1);

/**
 * MXWebMail — front controller.
 * Toutes les requêtes non-statiques arrivent ici (rewrite dans .htaccess).
 */

// ob_start absorbe un éventuel BOM/espaces si l'admin édite define.php
ob_start();
require __DIR__ . '/define.php';
ob_end_clean();
$boot = MXWM_APP_DIR . '/src/bootstrap.php';
is_file($boot) || exit('MXWM_APP_DIR invalide dans define.php : ' . MXWM_APP_DIR);
require $boot;

$app = new MXWebMail\Application\App(MXWM_APP_DIR, dirname(__DIR__));
$app->run();
