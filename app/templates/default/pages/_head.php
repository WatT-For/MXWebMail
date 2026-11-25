<?php
/**
 * Partial <head> partagé par les 5 pages (login, admin-login, maintenance,
 * app, admin). Variables attendues (voir PageController::render) :
 * - $headTitle  : suffixe du <title> déjà échappé (sortie de $t), ex.
 *                 ' - ' . $t('...'), '' pour le nom d'app seul.
 * - $headPlugin : bool, true = injecte $pluginHead (hook app.head) - les
 *                 vues admin ne l'émettent pas par conception.
 * - $headExtra  : HTML additionnel avant </head> (ex. <style> du splash).
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" <?= $themeHtmlAttrs ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($app->config()->get('app_name')) ?><?= $headTitle ?? '' ?></title>
	<link rel="icon" href="<?= $faviconUrl ?>">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/fontawesome/css/all.min.css">
	<link id="theme-css" rel="stylesheet" href="<?= $base ?>/assets/theme/<?= $theme ?>/app.css?v=<?= $themeVer ?>">
	<?= $themeHead ?>
	<?= $themeScript ?>
	<?= ($headPlugin ?? false) ? $pluginHead : '' ?>
	<?= $headExtra ?? '' ?>
</head>
