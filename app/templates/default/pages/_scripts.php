<?php
/**
 * Partial pied de page : window.MXWM + dict i18n + scripts vendor partagés.
 * Variables attendues :
 * - $scriptsKo     : bool, true = knockout.js (pages KO)
 * - $scriptsBs     : bool, true = bootstrap.bundle (pages app/admin)
 * - $scriptsModule : chemin relatif du module ES de la page, ex.
 *                   'js/dist/app.js' (servi versionné via $assets)
 */
?>
<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $i18nUrl ?>"></script>
<?php if ($scriptsKo ?? false): ?>
<script src="<?= $assets ?>/vendor/knockout/knockout.js"></script>
<?php endif; ?>
<?php if ($scriptsBs ?? false): ?>
<script src="<?= $assets ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<?php if (($scriptsModule ?? '') !== ''): ?>
<script type="module" src="<?= $assets ?>/<?= $scriptsModule ?>"></script>
<?php endif; ?>
