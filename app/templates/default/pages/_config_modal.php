<?php
/**
 * Modale d'édition de config par schéma (config.schema.json) - partagée
 * par les plugins (admin.php) et les thèmes. $cm = spec injectée par
 * l'appelant : {id, obs, title, name (fragment HTML du libellé), save}.
 */
?>
<div class="modal fade" id="<?= $cm['id'] ?>" tabindex="-1" data-bind="with: <?= $cm['obs'] ?>">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?= $t($cm['title']) ?> <?= $cm['name'] ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->
				<div class="text-muted small" data-bind="visible: !fields().length" style="display:none">
					<?= $t('admin.no_schema') ?>
				</div>
				<div class="row" data-bind="template: {name: 'fields-tpl', foreach: fields}"></div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.close') ?></button>
				<button class="btn btn-sm btn-primary" data-bind="click: $parent.<?= $cm['save'] ?>, visible: fields().length"><?= $t('common.save') ?></button>
			</div>
		</div>
	</div>
</div>
