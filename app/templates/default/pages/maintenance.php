<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" <?= $themeHtmlAttrs ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($app->config()->get('app_name')) ?> - <?= $t('maintenance.title') ?></title>
	<link rel="icon" href="<?= $faviconUrl ?>">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/fontawesome/css/all.min.css">
	<link id="theme-css" rel="stylesheet" href="<?= $base ?>/assets/theme/<?= $theme ?>/app.css?v=<?= $themeVer ?>">
	<?= $themeHead ?>
	<?= $themeScript ?>
	<?= $pluginHead ?>
</head>
<body class="login-page">
<div class="card login-card shadow-sm">
	<div class="card-body p-4 text-center">
		<i class="fa-solid fa-screwdriver-wrench fa-2x text-warning mb-2"></i>
		<div class="brand-logo"><?= htmlspecialchars($app->config()->get('app_name')) ?></div>
		<h1 class="h5 mt-3 mb-3"><?= $t('maintenance.title') ?></h1>
		<p class="text-body-secondary mb-2"><?= $t('maintenance.message') ?></p>
		<p class="small text-body-secondary mb-0">
			<i class="fa-solid fa-rotate"></i> <?= $t('maintenance.retry_hint') ?>
		</p>
	</div>
</div>

<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $i18nUrl ?>"></script>
<script nonce="<?= $nonce ?>">
	// Sonde /api/ping : la page se recharge dès que la maintenance se termine.
	// Erreur réseau > on retente au prochain tour (jamais de reload en boucle).
	(function () {
		const poll = async () => {
			try {
				const res = await fetch(window.MXWM.base + '/api/ping');
				const json = await res.json();
				if (json.ok && !json.data.maintenance) {
					location.reload();
				}
			} catch (e) { /* serveur injoignable : nouvel essai au prochain tick */ }
		};
		setInterval(poll, 15000);
	})();
</script>
</body>
</html>
