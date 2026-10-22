<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" <?= $themeHtmlAttrs ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($app->config()->get('app_name')) ?> — <?= $t('login.sign_in') ?></title>
	<link rel="icon" href="<?= $faviconUrl ?>">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/fontawesome/css/all.min.css">
	<link id="theme-css" rel="stylesheet" href="<?= $base ?>/assets/theme/<?= $theme ?>/app.css?v=<?= $themeVer ?>">
	<?= $themeHead ?>
	<?= $themeScript ?>
</head>
<body class="login-page">
<div class="card login-card shadow-sm">
	<div class="card-body p-4">
		<div class="text-center mb-4">
			<i class="fa-solid fa-envelope fa-2x text-primary mb-2"></i>
			<div class="brand-logo"><?= htmlspecialchars($app->config()->get('app_name')) ?></div>
		</div>

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
				<input id="totp" type="text" class="form-control" inputmode="numeric" maxlength="6"
					autocomplete="one-time-code" data-bind="value: totp, disable: loading">
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
	</div>
</div>

<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $assets ?>/vendor/knockout/knockout.js"></script>
<script type="module" nonce="<?= $nonce ?>">
	import { api } from '<?= $assets ?>/js/api.js';

	class LoginVM {
		constructor() {
			this.email = ko.observable('');
			this.password = ko.observable('');
			this.totp = ko.observable('');
			this.showTotp = ko.observable(false);
			this.loading = ko.observable(false);
			this.error = ko.observable('');
		}
		async submit() {
			this.error('');
			this.loading(true);
			try {
				await api.post('/login', { email: this.email(), password: this.password(), totp: this.totp() });
				location.href = window.MXWM.base + '/';
			} catch (e) {
				// Code d'erreur stable — jamais de test sur le message localisé
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
