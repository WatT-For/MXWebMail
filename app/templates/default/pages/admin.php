<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" <?= $themeHtmlAttrs ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($app->config()->get('app_name')) ?> — <?= $t('admin.administration') ?></title>
	<link rel="icon" href="<?= $faviconUrl ?>">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/fontawesome/css/all.min.css">
	<link id="theme-css" rel="stylesheet" href="<?= $base ?>/assets/theme/<?= $theme ?>/app.css?v=<?= $themeVer ?>">
	<?= $themeHead ?>
	<?= $themeScript ?>
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-3">
	<span class="navbar-brand mb-0"><?= htmlspecialchars($app->config()->get('app_name')) ?> — <?= $t('admin.administration') ?></span>
	<div>
		<a class="btn btn-sm btn-outline-light me-2" href="<?= $base ?>/"><i class="fa-solid fa-envelope"></i> <?= $t('admin.webmail') ?></a>
		<button class="btn btn-sm btn-outline-light" data-bind="click: logout"><i class="fa-solid fa-right-from-bracket"></i></button>
	</div>
</nav>

<div class="container-fluid py-3">
<div class="row">
	<div class="col-12 col-md-3 col-lg-2 mb-3">
		<div class="list-group side-menu sticky-top" style="top:.5rem">
			<div class="side-menu-group"><?= $t('admin.group_config') ?></div>
			<a class="list-group-item list-group-item-action" href="#/general"
				data-bind="css:{active: tab()==='general'}">
				<i class="fa-solid fa-gear fa-fw me-2"></i><?= $t('common.general') ?></a>
			<a class="list-group-item list-group-item-action" href="#/database"
				data-bind="css:{active: tab()==='database'}">
				<i class="fa-solid fa-database fa-fw me-2"></i><?= $t('admin.database') ?></a>
			<a class="list-group-item list-group-item-action" href="#/features"
				data-bind="css:{active: tab()==='features'}">
				<i class="fa-solid fa-wand-magic-sparkles fa-fw me-2"></i><?= $t('admin.features') ?></a>
			<a class="list-group-item list-group-item-action" href="#/security"
				data-bind="css:{active: tab()==='security'}">
				<i class="fa-solid fa-shield-halved fa-fw me-2"></i><?= $t('admin.security') ?></a>
			<a class="list-group-item list-group-item-action" href="#/logs"
				data-bind="css:{active: tab()==='logs'}">
				<i class="fa-solid fa-list fa-fw me-2"></i><?= $t('admin.logs') ?></a>
			<a class="list-group-item list-group-item-action" href="#/performance"
				data-bind="css:{active: tab()==='performance'}">
				<i class="fa-solid fa-gauge-high fa-fw me-2"></i><?= $t('admin.performance') ?></a>
			<div class="side-menu-group"><?= $t('admin.group_data') ?></div>
			<a class="list-group-item list-group-item-action" href="#/domains"
				data-bind="css:{active: tab()==='domains'}">
				<i class="fa-solid fa-globe fa-fw me-2"></i><?= $t('admin.domains') ?></a>
			<a class="list-group-item list-group-item-action" href="#/users"
				data-bind="css:{active: tab()==='users'}">
				<i class="fa-solid fa-users fa-fw me-2"></i><?= $t('admin.users') ?></a>
			<a class="list-group-item list-group-item-action" href="#/plugins"
				data-bind="css:{active: tab()==='plugins'}">
				<i class="fa-solid fa-puzzle-piece fa-fw me-2"></i><?= $t('admin.plugins') ?></a>
			<a class="list-group-item list-group-item-action" href="#/themes"
				data-bind="css:{active: tab()==='themes'}">
				<i class="fa-solid fa-palette fa-fw me-2"></i><?= $t('admin.themes') ?></a>
			<a class="list-group-item list-group-item-action" href="#/about"
				data-bind="css:{active: tab()==='about'}">
				<i class="fa-solid fa-circle-info fa-fw me-2"></i><?= $t('admin.about') ?></a>
		</div>
	</div>
	<div class="col-12 col-md-9 col-lg-10">

	<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
	<div class="alert alert-success" data-bind="visible: notice, text: notice" style="display:none"></div>
	<div class="alert alert-warning" data-bind="visible: maintenance" style="display:none">
		<i class="fa-solid fa-triangle-exclamation"></i> <?= $t('admin.maintenance_active') ?>
	</div>
	<div class="alert alert-warning" data-bind="visible: settings() && settings().writable === false" style="display:none">
		<code>config/config.php</code> <?= $t('admin.config_readonly') ?>
	</div>

	<!-- Chargement initial (bootstrap) -->
	<div class="text-center py-5" data-bind="visible: !settings() && !error()" style="display:none">
		<div class="spinner-border text-secondary" role="status"></div>
	</div>

	<!-- Général / Serveur / Base / Fonctionnalités / Sécurité : formulaires pilotés par le schéma backend -->
	<div data-bind="visible: tab()==='general'" style="display:none">
		<h5><?= $t('common.general') ?></h5>
		<div class="card"><div class="card-body">
			<div class="row">
				<!-- ko template: {name:'fields-tpl', foreach: formSections.general} --><!-- /ko -->
				<div class="col-12 col-xxl-6">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm">
							<?= $t('admin.favicon') ?>
							<i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
								data-bind="tooltip: 'admin.favicon_help'"></i>
						</label>
						<div class="col-md-8">
							<div class="d-flex align-items-center gap-2 flex-wrap">
								<img data-bind="attr: { src: faviconUrl }" width="32" height="32" alt="">
								<input id="favicon-input" type="file" class="form-control form-control-sm w-auto"
									accept=".ico,.png,.svg,.jpg,.jpeg,.webp"
									data-bind="event: { change: (d, e) => $root.faviconFile(e.target.files[0] || null) }">
								<button class="btn btn-sm btn-primary"
									data-bind="click: uploadFavicon, enable: faviconFile"><?= $t('admin.favicon_upload') ?></button>
								<button class="btn btn-sm btn-outline-danger" style="display:none"
									data-bind="click: deleteFavicon, visible: favicon() !== ''"><?= $t('admin.favicon_reset') ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('general')"><?= $t('common.save') ?></button>
		</div></div>
	</div>

	<div data-bind="visible: tab()==='database'" style="display:none">
		<h5><?= $t('admin.db_title') ?></h5>
		<div class="card"><div class="card-body">
			<div class="alert alert-info small"><?= $t('admin.db_notice') ?></div>
			<div class="row" data-bind="template: {name:'fields-tpl', foreach: formSections.database}"></div>
			<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('database')"><?= $t('common.save') ?></button>
			<button class="btn btn-outline-secondary btn-sm" data-bind="click: testDb"><?= $t('admin.test_connection') ?></button>
		</div></div>
	</div>

	<div data-bind="visible: tab()==='features'" style="display:none">
		<h5><?= $t('admin.features') ?></h5>
		<ul class="nav nav-tabs mb-3">
			<li class="nav-item"><a class="nav-link" href="#/features/features"
				data-bind="css:{active: subtabs.features()==='features'}"><?= $t('admin.subtab.features') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/features/defaults"
				data-bind="css:{active: subtabs.features()==='defaults'}"><?= $t('admin.subtab.defaults') ?></a></li>
		</ul>
		<div data-bind="visible: subtabs.features()==='features'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('features','features')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('features')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
		<div data-bind="visible: subtabs.features()==='defaults'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('features','defaults')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('features')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
	</div>

	<!-- Domaines -->
	<div data-bind="visible: tab()==='domains'" style="display:none">
		<div class="d-flex justify-content-between mb-2">
			<h5><?= $t('admin.domains') ?></h5>
			<button class="btn btn-sm btn-primary" data-bind="click: newDomain, visible: subtabs.domains()==='list'"><i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
		</div>
		<ul class="nav nav-tabs mb-3">
			<li class="nav-item"><a class="nav-link" href="#/domains/list"
				data-bind="css:{active: subtabs.domains()==='list'}"><?= $t('admin.domains') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/domains/params"
				data-bind="css:{active: subtabs.domains()==='params'}"><?= $t('admin.subtab.params') ?></a></li>
		</ul>
		<div data-bind="visible: subtabs.domains()==='list'" style="display:none">
			<table class="table table-hover table-sm align-middle">
				<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th>IMAP</th><th>SMTP</th><th><?= $t('admin.status') ?></th><th></th></tr></thead>
				<tbody data-bind="foreach: domains">
					<tr>
						<td>
							<span data-bind="text: name"></span>
							<span class="badge text-bg-info" data-bind="visible: name === '*'"><?= $t('admin.domain_wildcard') ?></span>
							<span class="badge text-bg-secondary" data-bind="visible: detected"><?= $t('admin.domain_auto') ?></span>
						</td>
						<td><span data-bind="text: imap_host"></span>:<span data-bind="text: imap_port"></span> <span class="badge text-bg-secondary" data-bind="text: imap_encryption"></span></td>
						<td><span data-bind="text: smtp_host"></span>:<span data-bind="text: smtp_port"></span> <span class="badge text-bg-secondary" data-bind="text: smtp_encryption"></span></td>
						<td>
							<i class="fa-solid" data-bind="css: Number(active) ? 'fa-check text-success' : 'fa-xmark text-danger'"></i>
							<i class="fa-solid fa-user-tag text-warning ms-1" tabindex="0" style="display:none"
								data-bind="visible: secondary_only, tooltip: 'admin.secondary_only'"></i>
						</td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editDomain"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteDomain"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div data-bind="visible: subtabs.domains()==='params'" style="display:none">
			<div class="card"><div class="card-body">
				<h6 class="mb-2"><?= $t('admin.resolution') ?></h6>
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('domains','')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('domains')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
	</div>

	<!-- Utilisateurs -->
	<div data-bind="visible: tab()==='users'" style="display:none">
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h5><?= $t('admin.users') ?></h5>
			<div class="d-flex align-items-center gap-2">
				<input class="form-control form-control-sm" style="max-width:260px"
					placeholder="<?= $t('admin.search_users') ?>"
					data-bind="value: userSearch, valueUpdate: 'input'">
				<button class="btn btn-sm btn-icon btn-outline-secondary" type="button"
					data-bind="click: loadUsers, disable: usersBusy, tooltip: 'common.refresh'">
					<i class="fa-solid fa-rotate" data-bind="css:{'fa-spin': usersBusy}"></i>
				</button>
			</div>
		</div>
		<table class="table table-hover table-sm align-middle">
			<thead class="table-light"><tr>
				<th>Email</th><th><?= $t('common.name') ?></th>
				<th><?= $t('admin.user_accounts') ?></th><th>Admin</th>
				<th><?= $t('admin.status') ?></th>
				<th><?= $t('admin.last_login') ?></th><th><?= $t('admin.user_created') ?></th><th></th>
			</tr></thead>
			<tbody data-bind="foreach: filteredUsers">
				<tr>
					<td data-bind="text: username"></td>
					<td data-bind="text: display_name"></td>
					<td><span class="badge text-bg-secondary" data-bind="text: accounts_count || 0"></span></td>
					<td><i class="fa-solid" data-bind="css: Number(is_admin) ? 'fa-check text-success' : 'fa-xmark text-muted'"></i></td>
					<td><span class="badge text-bg-danger" data-bind="visible: Number(disabled), text: $root.t('admin.user_disabled')" style="display:none"></span></td>
					<td data-bind="text: last_login_at ? new Date(last_login_at * 1000).toLocaleString($root.lang) : '—'"></td>
					<td data-bind="text: created_at ? new Date(created_at * 1000).toLocaleDateString($root.lang) : '—'"></td>
					<td class="text-end">
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editUser, tooltip: 'admin.user_edit'"><i class="fa-solid fa-pen"></i></button>
						<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteUser, tooltip: 'common.delete'"><i class="fa-solid fa-trash"></i></button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Extensions -->
	<div data-bind="visible: tab()==='plugins'" style="display:none">
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h5 class="mb-0"><?= $t('admin.plugins') ?></h5>
			<button class="btn btn-sm btn-icon btn-outline-secondary" type="button"
				data-bind="click: function(){ refreshPlugins(); checkPluginsCatalog(true) }, disable: catalogBusy, tooltip: 'common.refresh'">
				<i class="fa-solid fa-rotate" data-bind="css:{'fa-spin': catalogBusy}"></i>
			</button>
		</div>
		<div class="alert alert-danger py-2" data-bind="visible: pluginsErr, text: pluginsErr" style="display:none"></div>
		<table class="table table-hover table-sm align-middle">
			<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th><?= $t('admin.description') ?></th><th>Version</th><th><?= $t('admin.enabled') ?></th><th><?= $t('admin.plugin_user_allowed') ?></th><th></th></tr></thead>
			<tbody>
				<!-- ko foreach: plugins -->
				<tr>
					<td data-bind="text: name"></td>
					<td data-bind="text: $root.t(description)"></td>
					<td>
						<span data-bind="text: version"></span>
						<!-- ko if: $root.catalogFor(name)?.compatible === false -->
						<span class="badge text-bg-warning ms-1" data-bind="text: $root.t('admin.plugins.incompatible')"></span>
						<!-- /ko -->
					</td>
					<td>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" data-bind="checked: enabled, event: { change: $parent.togglePlugin }">
						</div>
					</td>
					<td>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox"
								data-bind="checked: user_allowed, event: { change: $parent.togglePluginAllowed }, enable: enabled()">
						</div>
					</td>
					<td class="text-end text-nowrap">
						<!-- ko if: $root.catalogFor(name)?.update_available -->
						<button class="btn btn-sm btn-icon btn-outline-primary"
							data-bind="click: $parent.updatePlugin, disable: $parent.pluginBusy() === name, tooltip: 'admin.plugins.update'">
							<i class="fa-solid fa-download"></i>
						</button>
						<!-- /ko -->
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editPluginConfig, enable: has_schema, tooltip: 'admin.configure'">
							<i class="fa-solid fa-gear"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-danger"
							data-bind="click: $parent.removePlugin, disable: $parent.pluginBusy() === name, tooltip: 'admin.plugins.remove'">
							<i class="fa-solid fa-trash-can"></i>
						</button>
					</td>
				</tr>
				<!-- /ko -->
				<!-- Catalogue GitHub (PluginPackageService) : entrées non installées -->
				<!-- ko foreach: catalogAvailable -->
				<tr>
					<td data-bind="text: name"></td>
					<td data-bind="text: $root.t(description)"></td>
					<td>
						<span class="badge text-bg-secondary" data-bind="text: 'v' + latest"></span>
						<!-- ko if: compatible === false -->
						<span class="badge text-bg-warning ms-1" data-bind="text: $root.t('admin.plugins.incompatible')"></span>
						<!-- /ko -->
					</td>
					<td></td>
					<td></td>
					<td class="text-end">
						<button class="btn btn-sm btn-primary" type="button"
							data-bind="click: $root.installPlugin, disable: $root.pluginBusy() === name || compatible === false">
							<i class="fa-solid fa-download"></i>
							<span data-bind="text: $root.pluginBusy() === name ? $root.t('admin.plugins.installing') : $root.t('admin.plugins.install')"></span>
						</button>
					</td>
				</tr>
				<!-- /ko -->
			</tbody>
		</table>
		<div class="text-muted small" data-bind="visible: plugins().length === 0 && catalogAvailable().length === 0 && !catalogBusy()" style="display:none">
			<?= $t('admin.no_plugins') ?>
		</div>
		<!-- ko if: pluginCatalog() && pluginCatalog().configured === false -->
		<div class="text-muted small"><?= $t('admin.plugins.not_configured') ?></div>
		<!-- /ko -->
	</div>

	<!-- Templates -->
	<div data-bind="visible: tab()==='themes'" style="display:none">
		<h5><?= $t('admin.themes') ?></h5>
		<table class="table table-hover table-sm align-middle">
			<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th><?= $t('common.label') ?></th><th>Version</th><th><?= $t('admin.default_theme') ?></th><th><?= $t('admin.theme_user_allowed') ?></th><th></th></tr></thead>
			<tbody data-bind="foreach: themes">
				<tr>
					<td>
						<span data-bind="text: name"></span>
						<div class="text-muted small" data-bind="visible: $data.extends, text: $root.t('admin.theme_inherits', {name: $data.extends})"></div>
					</td>
					<td data-bind="text: $root.t(label)"></td>
					<td data-bind="text: version"></td>
					<td>
						<button class="btn btn-sm btn-icon btn-link p-0 border-0"
							data-bind="click: $root.setThemeDefault, enable: name !== $root.themeDefault(), tooltip: 'admin.theme_set_default'">
							<i data-bind="css: name === $root.themeDefault() ? 'fa-solid fa-star text-warning' : 'fa-regular fa-star text-muted'"></i>
						</button>
					</td>
					<td>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox"
								data-bind="checked: allowed, event: {change: $parent.toggleThemeAllowed}, enable: name !== $root.themeDefault()">
						</div>
					</td>
					<td class="text-end">
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editThemeConfig, tooltip: 'admin.configure'">
							<i class="fa-solid fa-gear"></i>
						</button>
					</td>
				</tr>
			</tbody>
		</table>

	</div>

	<!-- Sécurité -->
	<div data-bind="visible: tab()==='security'" style="display:none">
		<h5><?= $t('admin.security') ?></h5>
		<ul class="nav nav-tabs mb-3">
			<li class="nav-item"><a class="nav-link" href="#/security/account"
				data-bind="css:{active: subtabs.security()==='account'}"><?= $t('admin.subtab.account') ?></a></li>
			<!-- Sous-onglets déclarés par les plugins (hook admin.mounts + « tab ») -->
			<!-- ko foreach: adminTabsIn('security') -->
			<li class="nav-item"><a class="nav-link"
				data-bind="attr: { href: '#/security/' + $data.tab },
					css: { active: $root.subtabs.security() === $data.tab },
					text: $root.t($data.label || $data.tab)"></a></li>
			<!-- /ko -->
			<li class="nav-item"><a class="nav-link" href="#/security/access"
				data-bind="css:{active: subtabs.security()==='access'}"><?= $t('admin.subtab.access') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/security/headers"
				data-bind="css:{active: subtabs.security()==='headers'}"><?= $t('admin.subtab.headers') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/security/tls"
				data-bind="css:{active: subtabs.security()==='tls'}"><?= $t('admin.subtab.tls') ?></a></li>
		</ul>

		<div data-bind="visible: subtabs.security()==='account'" style="display:none">
			<div class="card mb-3"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('security','account')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: saveAccount"><?= $t('common.save') ?></button>
				<hr>
				<h6><?= $t('admin.change_password') ?></h6>
				<div class="row g-2 align-items-center">
					<div class="col-md-4"><input class="form-control form-control-sm" type="password" placeholder="<?= $t('admin.new_password') ?>" autocomplete="new-password" data-bind="value: newPass"></div>
					<div class="col-md-4"><input class="form-control form-control-sm" type="password" placeholder="<?= $t('admin.password_confirm') ?>" autocomplete="new-password" data-bind="value: newPass2"></div>
				</div>
				<div class="form-text mt-1"><?= $t('admin.change_password_hint') ?></div>
			</div></div>
			<!-- Blocs inline déclarés par les plugins (hook admin.mounts, sans « tab ») -->
			<!-- ko foreach: adminMountsIn('security-account') -->
				<!-- ko if: $root.pluginEnabled($data.plugin) -->
				<div data-bind="pluginMount: { js: $data.js, plugin: $data.plugin, admin: true }"></div>
				<!-- /ko -->
			<!-- /ko -->
		</div>

		<!-- Contenu des sous-onglets déclarés par les plugins -->
		<!-- ko foreach: adminTabsIn('security') -->
		<div data-bind="visible: $root.subtabs.security() === $data.tab" style="display:none">
			<div data-bind="pluginMount: { js: $data.js, plugin: $data.plugin, admin: true }"></div>
		</div>
		<!-- /ko -->

		<div data-bind="visible: subtabs.security()==='access'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row align-items-center mb-2">
					<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.your_ip') ?></label>
					<div class="col-md-8">
						<code data-bind="text: settings() && settings().ip"></code>
						<button class="btn btn-link btn-sm" data-bind="click: addMyIp"><?= $t('admin.add_to_whitelist') ?></button>
					</div>
				</div>
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('security','access')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('security')"><?= $t('common.save') ?></button>
			</div></div>
		</div>

		<div data-bind="visible: subtabs.security()==='headers'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('security','headers')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('security')"><?= $t('common.save') ?></button>
			</div></div>
		</div>

		<div data-bind="visible: subtabs.security()==='tls'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('security','tls')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('security')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
	</div>

	<!-- Logs -->
	<div data-bind="visible: tab()==='logs'" style="display:none">
		<h5><?= $t('admin.logs') ?></h5>
		<ul class="nav nav-tabs mb-3">
			<li class="nav-item"><a class="nav-link" href="#/logs/viewer"
				data-bind="css:{active: subtabs.logs()==='viewer'}"><?= $t('admin.subtab.viewer') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/logs/params"
				data-bind="css:{active: subtabs.logs()==='params'}"><?= $t('admin.subtab.params') ?></a></li>
		</ul>
		<div data-bind="visible: subtabs.logs()==='viewer'" style="display:none">
			<div class="d-flex align-items-center gap-2 mb-2">
				<select class="form-select form-select-sm" style="width:auto" data-bind="value: logFile, event: {change: loadLogs}">
					<option value="app"><?= $t('admin.log_app') ?></option>
					<option value="mail"><?= $t('admin.log_mail') ?></option>
					<option value="auth"><?= $t('admin.log_auth') ?></option>
				</select>
				<button class="btn btn-sm btn-icon btn-outline-secondary" type="button"
					data-bind="click: loadLogs, tooltip: 'common.refresh'">
					<i class="fa-solid fa-rotate"></i>
				</button>
			</div>
			<pre class="bg-dark text-light p-3 rounded small" style="max-height:60vh;overflow:auto" data-bind="text: logs().join('\n')"></pre>
		</div>
		<div data-bind="visible: subtabs.logs()==='params'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: formSections.logs}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('logs')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
	</div>

	<!-- Performance : compression HTTP + caches -->
	<div data-bind="visible: tab()==='performance'" style="display:none">
		<h5><?= $t('admin.performance') ?></h5>
		<ul class="nav nav-tabs mb-3">
			<li class="nav-item"><a class="nav-link" href="#/performance/http"
				data-bind="css:{active: subtabs.performance()==='http'}"><?= $t('admin.subtab.http') ?></a></li>
			<li class="nav-item"><a class="nav-link" href="#/performance/cache"
				data-bind="css:{active: subtabs.performance()==='cache'}"><?= $t('admin.subtab.cache') ?></a></li>
		</ul>
		<div data-bind="visible: subtabs.performance()==='http'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('performance','http')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('performance')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
		<div data-bind="visible: subtabs.performance()==='cache'" style="display:none">
			<div class="card"><div class="card-body">
				<div class="row" data-bind="template: {name:'fields-tpl', foreach: fieldsIn('performance','cache')}"></div>
				<button class="btn btn-primary btn-sm" data-bind="click: () => saveSection('performance')"><?= $t('common.save') ?></button>
			</div></div>
		</div>
	</div>

	<!-- À propos -->
	<div data-bind="visible: tab()==='about'" style="display:none">
		<h5><?= $t('admin.about') ?></h5>
		<!-- ko with: about -->
		<div class="card mb-3">
			<div class="card-body d-flex align-items-center gap-3">
				<i class="fa-solid fa-envelope fa-2x text-primary"></i>
				<div class="flex-grow-1">
					<div class="fs-5" data-bind="text: app_name"></div>
					<div class="text-muted small"><?= $t('admin.about.tagline') ?></div>
				</div>
				<span class="badge text-bg-primary fs-6">v<span data-bind="text: version"></span></span>
			</div>
		</div>
		<!-- /ko -->

		<div class="row g-3">
		<div class="col-12 col-xl-7">
		<!-- ko with: about -->
		<div class="card h-100"><div class="card-body">
			<h6 class="card-title mb-3"><i class="fa-solid fa-server me-1"></i><?= $t('admin.about.system') ?></h6>
			<table class="table table-sm table-borderless mb-0">
				<tbody>
					<tr><th class="text-muted fw-normal" style="width:40%">PHP</th>
						<td><span data-bind="text: php"></span> <span class="text-muted small">(<span data-bind="text: php_sapi"></span>)</span></td></tr>
					<tr><th class="text-muted fw-normal"><?= $t('admin.about.db_driver') ?></th>
						<td><code data-bind="text: db_driver || '—'"></code></td></tr>
					<tr><th class="text-muted fw-normal">memory_limit</th>
						<td><code data-bind="text: memory_limit"></code></td></tr>
					<tr><th class="text-muted fw-normal">upload_max_filesize</th>
						<td><code data-bind="text: upload_max_filesize"></code></td></tr>
					<tr><th class="text-muted fw-normal">post_max_size</th>
						<td><code data-bind="text: post_max_size"></code></td></tr>
					<tr><th class="text-muted fw-normal"><?= $t('admin.about.db_size') ?></th>
						<td data-bind="text: db_size === null ? $root.t('admin.about.unavailable') : $root.formatSize(db_size)"></td></tr>
					<tr><th class="text-muted fw-normal"><?= $t('admin.about.config_writable') ?></th>
						<td><span class="badge" data-bind="css: config_writable ? 'text-bg-success' : 'text-bg-warning',
							text: $root.t(config_writable ? 'admin.about.writable' : 'admin.about.readonly')"></span></td></tr>
				</tbody>
			</table>
		</div></div>
		<!-- /ko -->
		</div>
		<div class="col-12 col-xl-5">

		<!-- Mise à jour -->
		<div class="card h-100">
			<div class="card-body">
				<h6 class="card-title mb-3"><?= $t('admin.update.title') ?></h6>
				<!-- ko if: !updateInfo() || !updateInfo().configured -->
				<p class="text-muted small mb-0"><?= $t('admin.update.not_configured') ?></p>
				<!-- /ko -->
				<!-- ko if: updateInfo() && updateInfo().configured -->
				<div class="alert alert-danger py-2" data-bind="visible: updateErr, text: updateErr" style="display:none"></div>
				<div class="d-flex align-items-center gap-2 flex-wrap">
					<span class="small"><?= $t('admin.update.current') ?> <strong>v<span data-bind="text: updateInfo().current"></span></strong></span>
					<button class="btn btn-sm btn-outline-primary" type="button"
						data-bind="click: checkUpdate, disable: !!updateBusy()">
						<i class="fa-solid fa-rotate" data-bind="css:{'fa-spin': updateBusy()==='check'}"></i>
						<?= $t('admin.update.check') ?>
					</button>
				</div>

				<!-- ko if: updateResult() -->
				<div class="mt-3">
					<!-- ko if: updateResult().up_to_date -->
					<span class="badge text-bg-success"><?= $t('admin.update.up_to_date') ?></span>
					<!-- /ko -->
					<!-- ko if: !updateResult().up_to_date -->
					<div class="alert alert-info py-2 mb-2">
						<span class="badge text-bg-primary">v<span data-bind="text: updateResult().latest"></span></span>
						<strong class="ms-1" data-bind="text: updateResult().name || updateResult().tag"></strong>
						<span class="text-muted small ms-1"
							data-bind="text: updateResult().published_at ? updateResult().published_at.slice(0,10) : ''"></span>
						<!-- ko if: updateResult().html_url -->
						<a data-bind="attr:{href: updateResult().html_url}" target="_blank" rel="noopener" class="ms-1">
							<i class="fa-solid fa-arrow-up-right-from-square"></i></a>
						<!-- /ko -->
					</div>
					<!-- ko if: updateResult().notes -->
					<div class="small text-muted mb-1"><?= $t('admin.update.release_notes') ?></div>
					<pre class="small border rounded p-2 mb-2" style="max-height:180px;overflow:auto;white-space:pre-wrap"
						data-bind="text: updateResult().notes"></pre>
					<!-- /ko -->
					<!-- /ko -->
				</div>
				<!-- /ko -->

				<!-- ko if: updateStep()==='downloaded' || updateStep()==='extracted' -->
				<div class="alert alert-warning py-2 mt-3 mb-0 small">
					<i class="fa-solid fa-box-open me-1"></i>
					<span data-bind="text: $root.t('admin.update.pending', {ver: updateInfo().state.version})"></span>
					<span class="badge ms-1" data-bind="text: updateInfo().state.verified ? $root.t('admin.update.verified') : $root.t('admin.update.unverified'),
						css: updateInfo().state.verified ? 'text-bg-success' : 'text-bg-secondary'"></span>
				</div>
				<!-- /ko -->
				<!-- ko if: updateStep()==='applied' -->
				<div class="alert alert-success py-2 mt-3 mb-0 small">
					<i class="fa-solid fa-check me-1"></i>
					<span data-bind="text: $root.t('admin.update.applied_state', {ver: updateInfo().state.version})"></span>
				</div>
				<!-- /ko -->

				<div class="d-flex gap-2 mt-3 flex-wrap">
					<!-- ko if: (updateResult() && !updateResult().up_to_date && updateResult().has_asset) || updateStep()==='downloaded' -->
					<button class="btn btn-sm btn-primary" type="button"
						data-bind="click: downloadUpdate, disable: !!updateBusy()">
						<i class="fa-solid fa-download" data-bind="css:{'fa-spin': updateBusy()==='download'}"></i>
						<span data-bind="text: updateBusy()==='download' ? $root.t('admin.update.downloading') : $root.t('admin.update.download')"></span>
					</button>
					<!-- /ko -->
					<!-- ko if: updateStep()==='extracted' -->
					<button class="btn btn-sm btn-danger" type="button"
						data-bind="click: applyUpdate, disable: !!updateBusy()">
						<i class="fa-solid fa-triangle-exclamation" data-bind="css:{'fa-beat': updateBusy()==='apply'}"></i>
						<span data-bind="text: updateBusy()==='apply' ? $root.t('admin.update.applying') : $root.t('admin.update.apply')"></span>
					</button>
					<!-- /ko -->
					<!-- ko if: updateStep()==='downloaded' || updateStep()==='extracted' -->
					<button class="btn btn-sm btn-outline-secondary" type="button"
						data-bind="click: resetUpdate, disable: !!updateBusy()">
						<?= $t('admin.update.reset') ?>
					</button>
					<!-- /ko -->
				</div>
				<!-- /ko -->
			</div>
		</div>
		</div>
		</div>

		<!-- ko with: about -->
		<div class="card mt-3"><div class="card-body">
			<h6 class="card-title mb-3"><i class="fa-solid fa-puzzle-piece me-1"></i><?= $t('admin.about.extensions') ?>
				<span class="badge text-bg-warning fw-normal ms-1" style="display:none"
					data-bind="visible: $root.aboutMissingCount() > 0, text: $root.t('admin.about.ext_missing', {count: $root.aboutMissingCount()})"></span>
			</h6>
			<!-- ko foreach: $root.aboutExtensions -->
			<span class="badge fw-normal me-1 mb-1" data-bind="css: ok ? 'text-bg-success' : 'text-bg-secondary'">
				<i class="fa-solid fa-fw" data-bind="css: {'fa-check': ok, 'fa-xmark': !ok}"></i>
				<span data-bind="text: name"></span>
			</span>
			<!-- /ko -->
		</div></div>
		<div class="card mt-3"><div class="card-body">
			<h6 class="card-title mb-3"><i class="fa-solid fa-folder-tree me-1"></i><?= $t('admin.about.paths') ?></h6>
			<div class="row mb-1">
				<div class="col-md-3 text-muted small"><?= $t('admin.about.root_dir') ?></div>
				<div class="col"><code class="small text-break" data-bind="text: paths.root"></code></div>
			</div>
			<div class="row mb-1">
				<div class="col-md-3 text-muted small"><?= $t('admin.about.install_dir') ?></div>
				<div class="col"><code class="small text-break" data-bind="text: paths.install"></code></div>
			</div>
			<div class="row">
				<div class="col-md-3 text-muted small"><?= $t('admin.about.storage_dir') ?></div>
				<div class="col"><code class="small text-break" data-bind="text: paths.storage"></code></div>
			</div>
		</div></div>
		<!-- /ko -->
	</div>
	</div>
</div>
</div>

<!-- Modale config plugin (schéma : plugins/<name>/config.schema.json) -->
<!-- Hors du conteneur visible:tab() : sinon changer d'onglet masquerait la modale mais garderait le backdrop -->
<div class="modal fade" id="plugin-config-modal" tabindex="-1" data-bind="with: editingPlugin">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?= $t('admin.plugin_config') ?> <span data-bind="text: name"></span></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
				<div class="text-muted small" data-bind="visible: !fields().length" style="display:none">
					<?= $t('admin.no_schema') ?>
				</div>
				<div class="row" data-bind="foreach: fields">
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-4 col-form-label col-form-label-sm">
								<span data-bind="text: $root.t(label)"></span>
								<i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
									data-bind="visible: help, tooltip: help"></i>
							</label>
							<div class="col-8">
								<!-- ko if: type === 'string' || type === 'integer' || type === 'number' -->
								<input class="form-control form-control-sm"
									data-bind="value: value, attr: { type: type === 'integer' || type === 'number' ? 'number' : 'text' }">
								<!-- /ko -->
								<!-- ko if: type === 'text' -->
								<textarea class="form-control form-control-sm" rows="3" data-bind="value: value"></textarea>
								<!-- /ko -->
								<!-- ko if: type === 'password' -->
								<input class="form-control form-control-sm" type="password" autocomplete="new-password"
									data-bind="value: value">
								<!-- /ko -->
								<!-- ko if: type === 'enum' -->
								<select class="form-select form-select-sm" data-bind="value: value, valueAllowUnset: true, foreach: optionsList">
									<option data-bind="value: $data[0], text: $root.t($data[1])"></option>
								</select>
								<!-- /ko -->
								<!-- ko if: type === 'bool' -->
								<div class="form-check">
									<input class="form-check-input" type="checkbox" data-bind="checked: value">
								</div>
								<!-- /ko -->
								<!-- ko if: type === 'list' -->
								<div class="border rounded p-2">
									<div data-bind="foreach: value">
										<div class="d-flex gap-2 mb-1">
											<input class="form-control form-control-sm" data-bind="value: $data">
											<button class="btn btn-sm btn-icon btn-outline-danger" type="button"
												data-bind="click: () => $parent.removeItem($data)">
												<i class="fa-solid fa-xmark"></i>
											</button>
										</div>
									</div>
									<button class="btn btn-sm btn-outline-secondary" type="button" data-bind="click: addItem">
										<i class="fa-solid fa-plus"></i> <span data-bind="text: $root.t('common.add')"></span>
									</button>
								</div>
								<!-- /ko -->
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.close') ?></button>
				<button class="btn btn-sm btn-primary" data-bind="click: $parent.savePluginConfig, visible: fields().length"><?= $t('common.save') ?></button>
			</div>
		</div>
	</div>
</div>

<!-- Modale config template (schéma : templates/<name>/config.schema.json) -->
<div class="modal fade" id="theme-config-modal" tabindex="-1" data-bind="with: editingTheme">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?= $t('admin.theme_config') ?> <span data-bind="text: name"></span></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
				<div class="text-muted small" data-bind="visible: !fields().length" style="display:none">
					<?= $t('admin.no_schema') ?>
				</div>
				<div class="row" data-bind="foreach: fields">
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-4 col-form-label col-form-label-sm">
								<span data-bind="text: $root.t(label)"></span>
								<i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
									data-bind="visible: help, tooltip: help"></i>
							</label>
							<div class="col-8">
								<!-- ko if: type === 'string' || type === 'integer' || type === 'number' -->
								<input class="form-control form-control-sm"
									data-bind="value: value, attr: { type: type === 'integer' || type === 'number' ? 'number' : 'text' }">
								<!-- /ko -->
								<!-- ko if: type === 'text' -->
								<textarea class="form-control form-control-sm" rows="3" data-bind="value: value"></textarea>
								<!-- /ko -->
								<!-- ko if: type === 'enum' -->
								<select class="form-select form-select-sm" data-bind="value: value, valueAllowUnset: true, foreach: optionsList">
									<option data-bind="value: $data[0], text: $root.t($data[1])"></option>
								</select>
								<!-- /ko -->
								<!-- ko if: type === 'color' -->
								<div class="ko-flex gap-2">
									<input type="color" class="form-control form-control-color" data-bind="value: value">
									<input class="form-control form-control-sm" placeholder="#RRGGBB" data-bind="value: value">
								</div>
								<!-- /ko -->
								<!-- ko if: type === 'bool' -->
								<div class="form-check">
									<input class="form-check-input" type="checkbox" data-bind="checked: value">
								</div>
								<!-- /ko -->
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.close') ?></button>
				<button class="btn btn-sm btn-primary" data-bind="click: $parent.saveThemeConfig, visible: fields().length"><?= $t('common.save') ?></button>
			</div>
		</div>
	</div>
</div>

<!-- Modale gestion utilisateur (détail : GET /api/admin/users/{id}) -->
<div class="modal fade" id="user-modal" tabindex="-1" data-bind="with: editingUser">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?= $t('admin.user_edit') ?> <code data-bind="text: username"></code></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
				<ul class="nav nav-tabs mb-3">
					<li class="nav-item"><a class="nav-link" href="#" data-bind="css:{active: $root.userModalTab()==='profile'}, click: () => $root.userModalTab('profile')"><?= $t('admin.user_tab_profile') ?></a></li>
					<li class="nav-item"><a class="nav-link" href="#" data-bind="css:{active: $root.userModalTab()==='settings'}, click: () => $root.userModalTab('settings')"><?= $t('admin.user_tab_settings') ?></a></li>
					<li class="nav-item"><a class="nav-link" href="#" data-bind="css:{active: $root.userModalTab()==='accounts'}, click: () => $root.userModalTab('accounts')"><?= $t('admin.user_accounts') ?></a></li>
				</ul>

				<!-- Profil -->
				<div data-bind="visible: $root.userModalTab()==='profile'" style="display:none">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.display_name') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" data-bind="value: display_name"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm">Admin <i class="fa-regular fa-circle-question help-icon" tabindex="0"
							data-bind="tooltip: 'admin.user_is_admin_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: is_admin"></div>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.user_disabled') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
							data-bind="tooltip: 'admin.user_disabled_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: disabled"></div>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.user_created') ?></label>
						<div class="col-md-8"><span class="text-muted small" data-bind="text: created_at ? new Date(created_at * 1000).toLocaleString($root.lang) : '—'"></span></div>
					</div>
					<div class="row align-items-center mb-3">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.last_login') ?></label>
						<div class="col-md-8"><span class="text-muted small" data-bind="text: last_login_at ? new Date(last_login_at * 1000).toLocaleString($root.lang) : '—'"></span></div>
					</div>
					<button class="btn btn-primary btn-sm" data-bind="click: $root.saveUser"><?= $t('common.save') ?></button>
					<hr>
					<button class="btn btn-outline-danger btn-sm" data-bind="click: () => $root.deleteUser($data)">
						<i class="fa-solid fa-trash"></i> <?= $t('admin.user_delete') ?></button>
				</div>

				<!-- Paramètres utilisateur (user_settings — schéma partagé user_defaults) -->
				<div data-bind="visible: $root.userModalTab()==='settings'" style="display:none">
					<div class="row" data-bind="template: {name:'fields-tpl', foreach: settingsFields}"></div>
					<button class="btn btn-primary btn-sm" data-bind="click: $root.saveUserSettings"><?= $t('common.save') ?></button>
					<button class="btn btn-outline-warning btn-sm" data-bind="click: $root.resetUserSettings"><?= $t('admin.user_settings_reset') ?></button>
				</div>

				<!-- Comptes IMAP liés + identités d'envoi -->
				<div data-bind="visible: $root.userModalTab()==='accounts'" style="display:none">
					<div class="text-muted small" data-bind="visible: !accounts.length" style="display:none">
						<?= $t('admin.user_no_accounts') ?></div>
					<div data-bind="foreach: accounts">
						<div class="card mb-3"><div class="card-body">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<div>
									<strong data-bind="text: email"></strong>
									<span class="badge text-bg-primary ms-1" data-bind="visible: Number(is_default)" style="display:none"><?= $t('settings.default') ?></span>
								</div>
								<button class="btn btn-sm btn-icon btn-outline-danger"
									data-bind="visible: !Number(is_default), click: $root.deleteUserAccount, tooltip: 'common.delete'" style="display:none">
									<i class="fa-solid fa-trash"></i></button>
							</div>
							<table class="table table-sm align-middle mb-2">
								<thead><tr><th><?= $t('common.name') ?></th><th>Email</th><th>Reply-To</th><th><?= $t('settings.default') ?></th><th></th></tr></thead>
								<tbody data-bind="foreach: $root.identitiesFor($data)">
									<tr>
										<td data-bind="text: name"></td>
										<td data-bind="text: email"></td>
										<td data-bind="text: reply_to"></td>
										<td><i class="fa-solid fa-check text-success" data-bind="visible: Number(is_default)" style="display:none"></i></td>
										<td class="text-end">
											<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $root.defaultUserIdentity, tooltip: 'settings.default'"><i class="fa-solid fa-star"></i></button>
											<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: (i) => $root.editUserIdentity($parent, i), tooltip: 'admin.user_edit'"><i class="fa-solid fa-pen"></i></button>
											<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $root.deleteUserIdentity, tooltip: 'common.delete'"><i class="fa-solid fa-trash"></i></button>
										</td>
									</tr>
								</tbody>
							</table>
							<button class="btn btn-sm btn-outline-secondary" data-bind="click: (a) => $root.editUserIdentity(a, null)">
								<i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
						</div></div>
					</div>
					<!-- Formulaire identité (création/édition, HTML source pour la signature) -->
					<div class="card" data-bind="visible: $root.editingUserIdentity" style="display:none">
						<div class="card-body" data-bind="with: $root.editingUserIdentity">
							<h6><?= $t('settings.identities') ?></h6>
							<div class="row g-2">
								<div class="col-md-4"><input class="form-control form-control-sm" placeholder="<?= $t('settings.display_name') ?>" data-bind="value: name"></div>
								<div class="col-md-4"><input class="form-control form-control-sm" placeholder="Email" data-bind="value: email"></div>
								<div class="col-md-4"><input class="form-control form-control-sm" placeholder="<?= $t('settings.reply_to_opt') ?>" data-bind="value: reply_to"></div>
								<div class="col-md-4"><input class="form-control form-control-sm" placeholder="BCC" data-bind="value: bcc"></div>
								<div class="col-12">
									<label class="form-label small mb-1"><?= $t('settings.signature') ?></label>
									<textarea class="form-control form-control-sm font-monospace" rows="3" spellcheck="false" data-bind="value: signature"></textarea>
								</div>
							</div>
							<div class="mt-2">
								<button class="btn btn-primary btn-sm" data-bind="click: $root.saveUserIdentity"><?= $t('common.save') ?></button>
								<button class="btn btn-link btn-sm" data-bind="click: () => $root.editingUserIdentity(null)"><?= $t('common.cancel') ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.close') ?></button>
			</div>
		</div>
	</div>
</div>

<!-- Modale domaine (création/édition) -->
<div class="modal fade" id="domain-modal" tabindex="-1" data-bind="with: editingDomain">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" data-bind="text: id ? $root.t('admin.edit_domain', { name: name }) : $root.t('admin.new_domain')"></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
				<ul class="nav nav-tabs mb-3">
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $root.domainModalTab()==='general'}, click: () => $root.domainModalTab('general')"><?= $t('common.general') ?></a></li>
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $root.domainModalTab()==='imap'}, click: () => $root.domainModalTab('imap')">IMAP</a></li>
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $root.domainModalTab()==='smtp'}, click: () => $root.domainModalTab('smtp')">SMTP</a></li>
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $root.domainModalTab()==='sieve'}, click: () => $root.domainModalTab('sieve')">Sieve</a></li>
				</ul>

				<!-- Général -->
				<div data-bind="visible: $root.domainModalTab()==='general'" style="display:none">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('common.name') ?></label>
						<div class="col-md-8">
							<input class="form-control form-control-sm" placeholder="<?= $t('admin.domain_placeholder') ?>" data-bind="value: name">
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.active') ?></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: active"></div>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.whitelist_login') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.whitelist_login_help'"></i></label>
						<div class="col-md-8">
							<input class="form-control form-control-sm" placeholder="*@domain.tld admin@x.tld" data-bind="value: opt_whitelist">
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.secondary_only') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.secondary_only_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: opt_secondary_only"></div>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.oauth_provider') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.oauth_provider_help'"></i></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" data-bind="value: opt_oauth">
								<option value=""><?= $t('common.none') ?></option>
								<option value="google">Google (Gmail)</option>
								<option value="microsoft">Microsoft (Outlook)</option>
								<option value="yahoo">Yahoo</option>
							</select>
						</div>
					</div>
				</div>

				<!-- IMAP -->
				<div data-bind="visible: $root.domainModalTab()==='imap'" style="display:none">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.imap_host') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" data-bind="value: imap_host"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.port') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" type="number" min="1" max="65535" data-bind="value: imap_port"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.encryption') ?></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" data-bind="value: imap_encryption">
								<option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none"><?= $t('common.none') ?></option>
							</select>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.imap_timeout') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.imap_timeout_help'"></i></label>
						<div class="col-md-8"><input class="form-control form-control-sm" type="number" min="0" data-bind="value: opt_imap_timeout"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.imap_disabled_caps') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.imap_disabled_caps_help'"></i></label>
						<div class="col-md-8"><input class="form-control form-control-sm" placeholder="SORT, THREAD, METADATA" data-bind="value: opt_imap_disabled_capabilities"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.imap_short_login') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.imap_short_login_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: opt_imap_shortLogin"></div>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.imap_lower_login') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.imap_lower_login_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: opt_imap_lowerLogin"></div>
						</div>
					</div>
				</div>

				<!-- SMTP -->
				<div data-bind="visible: $root.domainModalTab()==='smtp'" style="display:none">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.smtp_host') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" data-bind="value: smtp_host"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.port') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" type="number" min="1" max="65535" data-bind="value: smtp_port"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.encryption') ?></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" data-bind="value: smtp_encryption">
								<option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none"><?= $t('common.none') ?></option>
							</select>
						</div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.smtp_timeout') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.smtp_timeout_help'"></i></label>
						<div class="col-md-8"><input class="form-control form-control-sm" type="number" min="0" data-bind="value: opt_smtp_timeout"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.smtp_auth') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.smtp_auth_help'"></i></label>
						<div class="col-md-8">
							<div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-bind="checked: opt_smtp_useAuth"></div>
						</div>
					</div>
				</div>

				<!-- Sieve -->
				<div data-bind="visible: $root.domainModalTab()==='sieve'" style="display:none">
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.sieve_host') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" data-bind="value: sieve_host"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.port') ?></label>
						<div class="col-md-8"><input class="form-control form-control-sm" type="number" min="1" max="65535" data-bind="value: sieve_port"></div>
					</div>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('admin.encryption') ?>
							<i class="fa-regular fa-circle-question help-icon" tabindex="0" data-bind="tooltip: 'admin.sieve_encryption_help'"></i></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" data-bind="value: opt_sieve_encryption">
								<option value=""><?= $t('admin.sieve_auto') ?></option>
								<option value="ssl"><?= $t('admin.sieve_ssl') ?></option>
								<option value="tls"><?= $t('admin.sieve_tls') ?></option>
								<option value="none"><?= $t('admin.sieve_none') ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-sm btn-outline-secondary" data-bind="click: $root.detectDomain"><?= $t('admin.detect') ?></button>
				<button class="btn btn-sm btn-outline-secondary" data-bind="click: $root.testDomain"><?= $t('admin.test_connection') ?></button>
				<button class="btn btn-sm btn-outline-secondary ms-auto" data-bs-dismiss="modal"><?= $t('common.cancel') ?></button>
				<button class="btn btn-sm btn-primary" data-bind="click: $root.saveDomain"><?= $t('common.save') ?></button>
			</div>
		</div>
	</div>
</div>

<!-- Template générique de champ de réglage (piloté par settings-schema.php) -->
<script type="text/html" id="fields-tpl">
	<div class="col-12 col-xxl-6">
		<div class="row align-items-center mb-2">
			<label class="col-md-4 col-form-label col-form-label-sm">
				<span data-bind="text: $root.t(label)"></span>
				<i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
					data-bind="visible: help, tooltip: help"></i>
			</label>
			<div class="col-md-8">
				<!-- ko if: type === 'string' || type === 'integer' -->
				<input class="form-control form-control-sm"
					data-bind="value: value, attr: { type: type === 'integer' ? 'number' : 'text' }">
				<!-- /ko -->
				<!-- ko if: type === 'password' -->
				<input class="form-control form-control-sm" type="password" autocomplete="new-password"
					data-bind="value: value">
				<!-- /ko -->
				<!-- ko if: type === 'enum' -->
				<select class="form-select form-select-sm" data-bind="value: value, valueAllowUnset: true, foreach: optionsList">
					<option data-bind="value: $data[0], text: $root.t($data[1])"></option>
				</select>
				<!-- /ko -->
				<!-- ko if: type === 'bool' -->
				<div class="form-check">
					<input class="form-check-input" type="checkbox" data-bind="checked: value">
				</div>
				<!-- /ko -->
			</div>
		</div>
	</div>
</script>

<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $assets ?>/vendor/knockout/knockout.js"></script>
<script src="<?= $assets ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script type="module" src="<?= $assets ?>/js/admin.js"></script>
</body>
</html>
