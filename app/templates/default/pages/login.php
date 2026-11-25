<?php
$headTitle = ' - ' . $t('login.sign_in');
$headPlugin = true;
require $partial('_head');
$cardIcon = 'fa-envelope';
$cardBrand = htmlspecialchars($app->config()->get('app_name'));
ob_start();
?>
<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>

<form data-bind="submit: submit">
	<div class="mb-3">
		<label class="form-label" for="email"><?= $t('login.email') ?></label>
		<input id="email" type="email" class="form-control" autocomplete="username"
			data-bind="value: email, disable: loading" required autofocus>
	</div>
	<div class="mb-3">
		<label class="form-label" for="password"><?= $t('login.password') ?></label>
		<input id="password" type="password" class="form-control" autocomplete="current-password"
			data-bind="value: password, disable: loading" required>
	</div>
	<div class="mb-3" data-bind="visible: showTotp" style="display:none">
		<label class="form-label" for="totp"><?= $t('login.totp') ?></label>
		<input id="totp" type="text" class="form-control" maxlength="9"
			autocomplete="one-time-code" data-bind="value: totp, disable: loading">
	</div>
	<div class="mb-3 form-check">
		<input id="remember" type="checkbox" class="form-check-input"
			data-bind="checked: remember, disable: loading">
		<label class="form-check-label" for="remember"><?= $t('login.remember') ?></label>
	</div>
	<div class="d-grid">
		<button type="submit" class="btn btn-sm btn-primary" data-bind="disable: loading">
			<span data-bind="visible: !loading()"><?= $t('login.sign_in') ?></span>
			<span data-bind="visible: loading" style="display:none">
				<span class="spinner-border spinner-border-sm"></span> <?= $t('login.signing_in') ?>
			</span>
		</button>
	</div>
</form>
<?php
$cardContent = ob_get_clean();
require $partial('_login_card');
$scriptsKo = true;
require $partial('_scripts');
?>
<script type="module" nonce="<?= $nonce ?>">
	import { api } from '<?= $assets ?>/js/dist/api.js';

	class LoginVM {
		constructor() {
			this.email = ko.observable('');
			this.password = ko.observable('');
			this.totp = ko.observable('');
			this.remember = ko.observable(false);
			this.showTotp = ko.observable(false);
			this.loading = ko.observable(false);
			this.error = ko.observable('');
		}
		async submit() {
			this.error('');
			this.loading(true);
			try {
				await api.post('/login', { email: this.email(), password: this.password(), totp: this.totp(), remember: this.remember() });
				// Préserver un ?mailto= à travers le login (clic mailto session fermée).
				const m = new URLSearchParams(location.search).get('mailto');
				location.href = window.MXWM.base + '/' + (m ? '?mailto=' + encodeURIComponent(m) : '');
			} catch (e) {
				// Code d'erreur stable - jamais de test sur le message localisé
				if (e.code === 'totp_required') { this.showTotp(true); }
				this.error(e.message);
			} finally {
				this.loading(false);
			}
		}
	}
	ko.applyBindings(new LoginVM());
</script>
</body>
</html>
