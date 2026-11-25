<?php
$headTitle = ' - ' . $t('login.administration');
$headPlugin = false; // vues admin : pas d'injection app.head
require $partial('_head');
$cardIcon = 'fa-shield-halved';
$cardBrand = $t('login.administration');
ob_start();
?>
<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>

<form data-bind="submit: submit">
	<div class="mb-3">
		<label class="form-label" for="user"><?= $t('login.admin_user') ?></label>
		<input id="user" type="text" class="form-control" autocomplete="username"
			data-bind="value: user, disable: loading" required autofocus>
	</div>
	<div class="mb-3">
		<label class="form-label" for="password"><?= $t('login.password') ?></label>
		<input id="password" type="password" class="form-control" autocomplete="current-password"
			data-bind="value: password, disable: loading" required>
	</div>
	<div class="mb-3" data-bind="visible: showTotp" style="display:none">
		<label class="form-label" for="totp"><?= $t('login.totp') ?></label>
		<input id="totp" type="text" class="form-control" autocomplete="one-time-code"
			inputmode="numeric" maxlength="6" data-bind="value: totp, disable: loading">
	</div>
	<div class="d-grid">
		<button type="submit" class="btn btn-sm btn-primary" data-bind="disable: loading">
			<?= $t('login.sign_in') ?>
		</button>
	</div>
</form>
<div class="text-center mt-3">
	<a href="<?= $base ?>/" class="small text-decoration-none"><?= $t('login.back_to_webmail') ?></a>
</div>
<?php
$cardContent = ob_get_clean();
require $partial('_login_card');
$scriptsKo = true;
require $partial('_scripts');
?>
<script type="module" nonce="<?= $nonce ?>">
	import { api } from '<?= $assets ?>/js/dist/api.js';
	class VM {
		constructor() {
			this.user = ko.observable('');
			this.password = ko.observable('');
			this.totp = ko.observable('');
			this.showTotp = ko.observable(<?= !empty($totpEnabled) ? 'true' : 'false' ?>);
			this.loading = ko.observable(false);
			this.error = ko.observable('');
		}
		async submit() {
			this.error(''); this.loading(true);
			try {
				await api.post('/admin/login', { user: this.user(), password: this.password(), totp: this.totp() });
				location.href = window.MXWM.base + '/<?= rawurlencode((string) $app->config()->get('admin_path')) ?>';
			} catch (e) {
				// Code d'erreur stable - jamais de test sur le message localisé
				if (e.code === 'totp_required') { this.showTotp(true); }
				this.error(e.message);
			} finally { this.loading(false); }
		}
	}
	ko.applyBindings(new VM());
</script>
</body>
</html>
