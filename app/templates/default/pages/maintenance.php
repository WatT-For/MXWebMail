<?php
$headTitle = ' - ' . $t('maintenance.title');
$headPlugin = true;
require $partial('_head');
$cardIcon = 'fa-screwdriver-wrench';
$cardIconClass = 'text-warning';
$cardBrand = htmlspecialchars($app->config()->get('app_name'));
$cardBodyClass = 'text-center';
$cardHeadClass = 'text-center';
ob_start();
?>
<h1 class="h5 mt-3 mb-3"><?= $t('maintenance.title') ?></h1>
<p class="text-body-secondary mb-2"><?= $t('maintenance.message') ?></p>
<p class="small text-body-secondary mb-0">
	<i class="fa-solid fa-rotate"></i> <?= $t('maintenance.retry_hint') ?>
</p>
<?php
$cardContent = ob_get_clean();
require $partial('_login_card');
require $partial('_scripts');
?>
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
