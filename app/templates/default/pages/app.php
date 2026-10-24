<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" <?= $themeHtmlAttrs ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($app->config()->get('app_name')) ?></title>
	<link rel="icon" href="<?= $faviconUrl ?>">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= $assets ?>/vendor/fontawesome/css/all.min.css">
	<link id="theme-css" rel="stylesheet" href="<?= $base ?>/assets/theme/<?= $theme ?>/app.css?v=<?= $themeVer ?>">
	<?= $themeHead ?>
	<?= $themeScript ?>
	<script src="<?= $assets ?>/vendor/openpgp/openpgp.min.js" defer></script>
	<?= $pluginHead ?>
</head>
<body>
<div class="app-shell">
	<!-- Barre supérieure -->
	<nav class="navbar navbar-dark px-3">
		<span class="navbar-brand mb-0"><?= htmlspecialchars($app->config()->get('app_name')) ?></span>
		<div class="d-flex align-items-center gap-3">
			<div class="dropdown">
				<a class="text-white-50 small text-decoration-none dropdown-toggle" role="button" data-bs-toggle="dropdown"
					data-bind="text: user() && user().email"></a>
				<ul class="dropdown-menu dropdown-menu-end">
					<!-- ko foreach: accounts -->
					<li><a class="dropdown-item" href="#" data-bind="click: $parent.switchAccount">
						<i class="fa-solid fa-check me-1" data-bind="visible: active" style="display:none"></i>
						<span data-bind="text: email"></span>
						<span class="badge text-bg-info ms-1" data-bind="visible: auth && auth !== 'password', text: auth"></span>
					</a></li>
					<!-- /ko -->
					<!-- ko if: features().accounts !== false -->
					<li><hr class="dropdown-divider"></li>
					<li><a class="dropdown-item" href="#" data-bind="click: addAccount">
						<i class="fa-solid fa-plus me-1"></i><?= $t('app.add_account') ?>
					</a></li>
					<li><a class="dropdown-item" href="#/@settings/accounts">
						<i class="fa-solid fa-envelopes-bulk me-1"></i><?= $t('app.manage_accounts') ?>
					</a></li>
					<!-- /ko -->
				</ul>
			</div>
			<button class="btn btn-sm btn-icon btn-outline-light" data-bind="click: openSettings, tooltip: 'app.settings'">
				<i class="fa-solid fa-gear"></i>
			</button>
			<button class="btn btn-sm btn-icon btn-outline-light" data-bind="click: logout, tooltip: 'app.logout'">
				<i class="fa-solid fa-right-from-bracket"></i>
			</button>
		</div>
	</nav>

	<div class="app-main" data-bind="with: $root, visible: view() === 'mail',
		style: gridTemplate, css: { 'layout-vertical': layout() === 'vertical' }" style="display:none">
		<!-- Colonne dossiers -->
		<aside class="folder-pane bg-light" data-bind="with: folderList">
			<div class="d-flex justify-content-between align-items-center gap-2 p-2 border-bottom">
				<button class="btn btn-sm btn-primary text-truncate" style="min-width:0"
					data-bind="click: $root.compose.openNew.bind($root.compose)">
					<i class="fa-solid fa-pen"></i> <?= $t('app.new_message') ?>
				</button>
				<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: createFolder, tooltip: 'app.new_folder'">
					<i class="fa-solid fa-plus"></i>
				</button>
			</div>
			<div class="list-group list-group-flush" data-bind="foreach: folders">
				<div class="list-group-item list-group-item-action" role="button"
					data-bind="click: (d, e) => { if (!e.target.closest('.dropdown')) { $parent.select(d); } },
						css: { active: $parent.isActive($data), 'folder-sep': $data.sep },
						style: { paddingLeft: (8 + $data.depth * 14) + 'px' }">
					<span>
						<i class="fa-solid" data-bind="css: $parent.iconFor($data)"></i>
						<span data-bind="text: label"></span>
					</span>
					<span class="d-flex align-items-center">
						<span class="badge text-bg-primary" data-bind="visible: unread() > 0, text: unread" style="display:none"></span>
						<span class="dropdown">
							<span class="text-muted px-1 folder-menu-toggle" role="button" data-bs-toggle="dropdown"
								data-bs-popper-config='{"strategy":"fixed"}'
								data-bind="visible: !isSystem || ($root.features().dangerous_actions !== false && !isInbox)">
								<i class="fa-solid fa-ellipsis-vertical"></i>
							</span>
							<ul class="dropdown-menu dropdown-menu-end">
								<!-- ko ifnot: isSystem -->
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.renameFolder"><i class="fa-solid fa-pen me-2"></i><?= $t('app.rename') ?></a></li>
								<!-- /ko -->
								<!-- ko if: $root.features().dangerous_actions !== false && !isInbox -->
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.clearFolder"><i class="fa-solid fa-broom me-2"></i><?= $t('app.empty_folder') ?></a></li>
								<!-- /ko -->
								<!-- ko ifnot: isSystem -->
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.editAcl"><i class="fa-solid fa-user-lock me-2"></i>ACL</a></li>
								<!-- /ko -->
								<!-- ko if: $root.features().dangerous_actions !== false && !isSystem -->
								<li><hr class="dropdown-divider"></li>
								<li><a class="dropdown-item text-danger" href="#" data-bind="click: $parent.deleteFolder"><i class="fa-solid fa-trash me-2"></i><?= $t('common.delete') ?></a></li>
								<!-- /ko -->
							</ul>
						</span>
					</span>
				</div>
			</div>

			<!-- Quota -->
			<div class="pane-footer p-2 border-top" data-bind="with: $root.quota, visible: $root.quota.limit() > 0" style="display:none">
				<div class="progress quota-progress">
					<span class="quota-label" data-bind="text: label"></span>
					<div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
						data-bind="style: { width: pct() + '%' },
							css: { 'bg-danger': pct() >= 90, 'bg-warning': pct() >= 75 && pct() < 90 },
							attr: { 'aria-valuenow': pct() }">
						<span class="quota-label" aria-hidden="true"
							data-bind="text: label, style: { width: labelWidth }"></span>
					</div>
				</div>
			</div>
		</aside>

		<div class="pane-resizer" role="separator" aria-orientation="vertical"
			data-bind="paneResize: { size: folderPaneWidth, axis: 'x', min: 140, max: 600, save: savePaneSizes }"></div>

		<!-- Colonne messages -->
		<section class="message-pane" data-bind="with: messageList">
			<div class="p-2 border-bottom d-flex gap-2">
				<div class="input-group input-group-sm">
					<input type="search" class="form-control" placeholder="<?= $t('app.search') ?>"
						data-bind="value: search, valueUpdate: 'input', event: { keydown: function(d,e){ if(e.key==='Enter'){ searchNow(); return false; } return true; } }">
					<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: searchNow"><i class="fa-solid fa-magnifying-glass"></i></button>
					<button class="btn btn-sm btn-icon btn-outline-secondary position-relative"
						data-bind="click: openSearch, tooltip: 'app.search.advanced'">
						<i class="fa-solid fa-filter"></i>
						<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-primary"
							style="display:none" data-bind="visible: hasSearchFilters, text: appliedFilterCount"></span>
					</button>
				</div>
				<button class="btn btn-sm btn-icon btn-outline-secondary"
					data-bind="click: refresh, disable: loading(), tooltip: 'common.refresh'">
					<i class="fa-solid fa-rotate" data-bind="css: { 'fa-spin': loading }"></i>
				</button>
				<div class="dropdown">
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bs-toggle="dropdown"
						data-bind="attr: { 'aria-label': sortTooltip }, css: { active: hasCustomSort }">
						<i class="fa-solid fa-arrow-down-wide-short" data-bind="tooltip: sortTooltip"></i>
					</button>
					<ul class="dropdown-menu dropdown-menu-end">
						<!-- ko foreach: sortOptions -->
						<li><a class="dropdown-item" href="#" data-bind="click: $parent.setSort">
							<i class="fa-solid fa-check fa-fw me-1" data-bind="visible: $parent.sort() === $data.key" style="display:none"></i>
							<span data-bind="text: $root.t($data.label)"></span>
						</a></li>
						<!-- /ko -->
					</ul>
				</div>
			</div>

			<div class="alert alert-danger m-2 mb-0" data-bind="visible: error, text: error" style="display:none"></div>

			<!-- Barre d'actions groupées -->
			<div class="p-1 border-bottom bg-light d-flex align-items-center gap-2">
				<input type="checkbox" class="form-check-input mt-0 flex-shrink-0 ms-1"
					data-bind="checked: allChecked, indeterminate: someChecked, enable: messages().length > 0,
						tooltip: 'app.select_all'">
				<div class="btn-group btn-group-sm">
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: () => bulkFlag('\\Seen', !checkedAllSeen()), disable: !hasChecked(),
							tooltip: checkedAllSeen() ? 'app.mark_unread' : 'app.mark_read'">
						<i data-bind="css: checkedAllSeen() ? 'fa-regular fa-envelope' : 'fa-regular fa-envelope-open'"></i>
					</button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: () => bulkFlag('\\Flagged', !checkedAllFlagged()), disable: !hasChecked(),
							tooltip: checkedAllFlagged() ? 'app.mark_unflagged' : 'app.mark_flagged'">
						<i class="fa-star" data-bind="css: checkedAllFlagged() ? 'fa-solid text-warning' : 'fa-regular'"></i>
					</button>
					<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: () => bulkSpam(!$root.folderList.isJunkFolder(folder())), disable: !hasChecked(), tooltip: $root.folderList.isJunkFolder(folder()) ? 'app.mark_not_spam' : 'app.mark_spam'"><i class="fa-solid fa-shield-halved"></i></button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: moveWithPicker, disable: !hasChecked(), tooltip: 'app.move_to'"><i class="fa-solid fa-file-export"></i></button>
					<!-- ko if: $root.features().dangerous_actions !== false -->
					<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: bulkDelete, disable: !hasChecked(), tooltip: 'common.delete'"><i class="fa-solid fa-trash text-danger"></i></button>
					<!-- /ko -->
					<!-- ko foreach: $root.messageToolbar -->
					<span class="btn-group" data-bind="pluginMount: $data"></span>
					<!-- /ko -->
				</div>
			</div>

			<div class="flex-grow-1 overflow-auto">
				<table class="table table-hover table-sm align-middle mb-0">
					<colgroup><col style="width:6px"><col style="width:28px"><col><col style="width:110px"></colgroup>
					<tbody data-bind="foreach: messages">
						<tr data-bind="click: $parent.select.bind($parent),
							css: { 'table-active': isSelected, 'message-unread': !isSeen() }">
							<td class="p-0 msg-tag-cell"
								data-bind="style: { backgroundColor: colorTag() || 'transparent' },
									attr: { title: colorTagLabel }"></td>
							<td>
								<input type="checkbox" class="form-check-input" data-bind="checked: isChecked, click: () => true, clickBubble: false">
							</td>
							<td>
								<div class="text-truncate" data-bind="text: fromLabel()"></div>
								<div class="text-truncate" data-bind="text: subject"></div>
							</td>
							<td class="text-end text-nowrap text-muted small">
								<div>
									<span data-bind="text: dateLabel()"></span>
									<i class="fa-star msg-flag" data-bind="css: isFlagged() ? 'fa-solid text-warning' : 'fa-regular',
										click: $parent.toggleFlag, clickBubble: false,
										tooltip: isFlagged() ? 'app.mark_unflagged' : 'app.mark_flagged'"></i>
								</div>
								<div>
									<span data-bind="text: sizeLabel()"></span>
									<i class="fa-solid fa-paperclip ms-1" data-bind="visible: hasAttach" style="display:none"></i>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
				<div class="text-center text-muted p-3 small" data-bind="visible: !loading() && messages().length === 0" style="display:none">
					<?= $t('app.no_messages') ?>
				</div>
			</div>

			<div class="pane-footer p-2 border-top justify-content-between small">
				<span class="text-muted" data-bind="text: $root.t('app.message_count', { n: total() })"></span>
				<div class="btn-group btn-group-sm">
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: prevPage, disable: page() <= 1, tooltip: 'app.page_prev',
							attr: { 'aria-label': $root.t('app.page_prev') }">
						<i class="fa-solid fa-chevron-left"></i>
					</button>
					<span class="btn btn-outline-secondary disabled" data-bind="text: page() + ' / ' + totalPages()"></span>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: nextPage, disable: page() >= totalPages(), tooltip: 'app.page_next',
							attr: { 'aria-label': $root.t('app.page_next') }">
						<i class="fa-solid fa-chevron-right"></i>
					</button>
				</div>
			</div>
		</section>

		<div class="pane-resizer" role="separator"
			data-bind="paneResize: { size: messagePaneSize, axis: listPaneAxis, min: listPaneMin, max: listPaneMax, save: savePaneSizes },
				attr: { 'aria-orientation': layout() === 'vertical' ? 'horizontal' : 'vertical' }"></div>

		<!-- Colonne lecture -->
		<section class="reader-pane bg-white" data-bind="with: messageView">
			<div class="text-center text-muted p-5" data-bind="visible: !message() && !loading()" style="display:none">
				<i class="fa-regular fa-envelope-open fa-3x mb-3"></i>
				<p><?= $t('app.select_message') ?></p>
			</div>
			<div class="text-center p-5" data-bind="visible: loading" style="display:none">
				<span class="spinner-border"></span>
			</div>
			<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>

			<div data-bind="visible: message() && !loading(), with: message" style="display:none">
				<div class="d-flex justify-content-between align-items-start mb-3">
					<div>
						<h5 class="mb-1" data-bind="text: subject"></h5>
						<div class="small text-muted">
							<div class="d-flex align-items-center gap-2">
								<img data-bind="visible: $parent.avatarSrc, attr: { src: $parent.avatarSrc }"
									style="width:32px;height:32px;border-radius:6px" alt="" width="32" height="32">
								<span><?= $t('app.from') ?> <span data-bind="text: $parent.headerText('from')"></span></span>
							</div>
							<div><?= $t('app.to') ?> <span data-bind="text: $parent.headerText('to')"></span></div>
							<div data-bind="visible: cc && cc.length" style="display:none"><?= $t('app.cc') ?> <span data-bind="text: $parent.headerText('cc')"></span></div>
							<div data-bind="text: new Date(date * 1000).toLocaleString($root.lang)"></div>
						</div>
					</div>
					<div class="btn-group btn-group-sm">
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: () => $root.compose.openReply($data,
								($root.userSettings?.msg_default_action || 'reply') === 'reply_all'),
								tooltip: 'app.reply'">
							<i class="fa-solid" data-bind="css: ($root.userSettings?.msg_default_action || 'reply') === 'reply_all' ? 'fa-reply-all' : 'fa-reply'"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: () => $root.compose.openReply($data, true), tooltip: 'app.reply_all'">
							<i class="fa-solid fa-reply-all"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: () => $root.compose.openForward($data), tooltip: 'app.forward'">
							<i class="fa-solid fa-share"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: $parent.moveWithPicker, tooltip: 'app.move_to'"><i class="fa-solid fa-file-export"></i></button>
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: $parent.toggleFlag,
								tooltip: isFlagged() ? 'app.mark_unflagged' : 'app.mark_flagged'">
							<i class="fa-star" data-bind="css: isFlagged() ? 'fa-solid text-warning' : 'fa-regular'"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.markUnread, tooltip: 'app.mark_unread'">
							<i class="fa-regular fa-envelope"></i>
						</button>
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.toggleSpam, tooltip: $parent.spamLabel()"><i class="fa-solid fa-shield-halved"></i></button>
						<!-- ko if: $root.features().dangerous_actions !== false -->
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.deleteMessage, tooltip: 'common.delete'">
							<i class="fa-solid fa-trash text-danger"></i>
						</button>
						<!-- /ko -->
						<button class="btn btn-sm btn-icon btn-outline-secondary"
							data-bind="click: $parent.printMessage, tooltip: 'app.print'">
							<i class="fa-solid fa-print"></i>
						</button>
						<a class="btn btn-sm btn-icon btn-outline-secondary" data-bind="attr: { href: $parent.rawUrl() }, tooltip: 'app.download_eml'">
							<i class="fa-solid fa-download"></i>
						</a>
						<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.showSource, tooltip: 'app.view_source'">
							<i class="fa-solid fa-code"></i>
						</button>
					</div>
				</div>

				<div class="alert mb-2" style="display:none"
					data-bind="visible: $parent.securityAlerts().length, css: $parent.securityCss">
					<i class="fa-solid fa-triangle-exclamation me-1"></i>
					<strong data-bind="text: $root.t('plugin.anti-spoofing.title')"></strong>
					<ul class="mb-0 small ps-3" data-bind="foreach: $parent.securityAlerts">
						<li data-bind="text: $data.text"></li>
					</ul>
				</div>

				<ul class="nav nav-tabs mb-2" data-bind="visible: attachments && attachments.length" style="display:none">
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $parent.tab()==='message'}, click: () => $parent.tab('message')"><?= $t('app.tab_message') ?></a></li>
					<li class="nav-item"><a class="nav-link" href="#"
						data-bind="css:{active: $parent.tab()==='attachments'}, click: () => $parent.tab('attachments')">
						<i class="fa-solid fa-paperclip"></i> <?= $t('app.attachments') ?>
						<span class="badge text-bg-secondary ms-1" data-bind="text: attachments.length"></span></a></li>
				</ul>

				<div class="mb-2" data-bind="visible: $parent.tab()==='attachments'" style="display:none">
					<div class="d-flex flex-wrap gap-1" data-bind="foreach: attachments">
						<a class="btn btn-sm btn-outline-secondary" data-bind="attr: { href: $parents[1].attachmentUrl($data) },
							text: name + (size ? ' (' + $parents[1].formatSize(size) + ')' : '')"></a>
						<!-- ko if: $root.features().ics_viewer
							&& (mime === 'text/calendar' || /\\.ics$/i.test(name || '')) -->
						<button class="btn btn-sm btn-icon btn-outline-primary"
							data-bind="click: () => $parents[1].previewIcs($data)">
							<i class="fa-solid fa-calendar-days"></i>
						</button>
						<!-- /ko -->
					</div>
				</div>

				<div data-bind="visible: $parent.tab()==='message'" style="display:none">
					<div class="alert alert-info py-1 px-2 mb-2 small ko-flex justify-content-between align-items-center"
						data-bind="visible: $parent.imagesBlocked" style="display:none">
						<span><i class="fa-solid fa-image"></i> <?= $t('app.images_blocked') ?></span>
						<button class="btn btn-sm btn-outline-primary" style="display:none"
							data-bind="visible: $root.features().image_proxy, click: $parent.showRemoteImages"><?= $t('app.show') ?></button>
					</div>
					<button class="btn btn-sm btn-outline-primary mb-2" data-bind="visible: $parent.isPgp(), click: $parent.decryptPgp" style="display:none">
						<i class="fa-solid fa-key"></i> <?= $t('app.decrypt_pgp') ?>
					</button>
					<pre class="border rounded p-3 bg-white" style="white-space:pre-wrap;display:none"
						data-bind="visible: $parent.decryptedText, text: $parent.decryptedText"></pre>
					<div id="mail-body" class="mail-body w-100 border rounded p-2" style="min-height:400px"
						data-bind="visible: !$parent.decryptedText()"></div>
				</div>
			</div>
		</section>
	</div>

	<!-- Vue paramètres -->
	<div class="container-fluid py-3" data-bind="visible: view() === 'settings', with: settings" style="display:none">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h4 class="mb-0"><?= $t('app.settings') ?></h4>
			<button class="btn btn-sm btn-outline-secondary" data-bind="click: () => location.hash = '#/INBOX'">
				<i class="fa-solid fa-arrow-left"></i> <?= $t('common.back') ?>
			</button>
		</div>

		<div class="row">
		<div class="col-12 col-md-3 col-lg-2 mb-3">
			<div class="list-group side-menu sticky-top" style="top:.5rem">
				<div class="side-menu-group"><?= $t('settings.group_prefs') ?></div>
				<a class="list-group-item list-group-item-action" href="#/@settings/general"
					data-bind="css:{active: tab()==='general'}">
					<i class="fa-solid fa-gear fa-fw me-2"></i><?= $t('common.general') ?></a>
				<div class="side-menu-group"><?= $t('settings.group_account') ?></div>
				<!-- ko if: $root.features().accounts !== false -->
				<a class="list-group-item list-group-item-action" href="#/@settings/accounts"
					data-bind="css:{active: tab()==='accounts'}">
					<i class="fa-solid fa-envelopes-bulk fa-fw me-2"></i><?= $t('settings.accounts') ?></a>
				<!-- /ko -->
				<a class="list-group-item list-group-item-action" href="#/@settings/identities"
					data-bind="css:{active: tab()==='identities'}">
					<i class="fa-solid fa-id-card fa-fw me-2"></i><?= $t('settings.identities') ?></a>
				<!-- ko if: $root.features().contacts !== false -->
				<a class="list-group-item list-group-item-action" href="#/@settings/contacts"
					data-bind="css:{active: tab()==='contacts'}">
					<i class="fa-solid fa-address-book fa-fw me-2"></i><?= $t('settings.contacts') ?></a>
				<!-- /ko -->
				<a class="list-group-item list-group-item-action" href="#/@settings/folders"
					data-bind="css:{active: tab()==='folders'}">
					<i class="fa-solid fa-folder-tree fa-fw me-2"></i><?= $t('settings.folders') ?></a>
				<!-- ko if: $root.features().accounts !== false -->
				<a class="list-group-item list-group-item-action" href="#/@settings/oauthapps"
					data-bind="css:{active: tab()==='oauthapps'}">
					<i class="fa-solid fa-fingerprint fa-fw me-2"></i><?= $t('settings.oauth_apps') ?></a>
				<!-- /ko -->
				<div class="side-menu-group"><?= $t('settings.group_messaging') ?></div>
				<a class="list-group-item list-group-item-action" href="#/@settings/filters"
					data-bind="css:{active: tab()==='filters'}">
					<i class="fa-solid fa-filter fa-fw me-2"></i><?= $t('settings.filters') ?></a>
				<a class="list-group-item list-group-item-action" href="#/@settings/pgp"
					data-bind="css:{active: tab()==='pgp'}">
					<i class="fa-solid fa-key fa-fw me-2"></i>OpenPGP</a>
				<!-- ko if: pluginTabs().length -->
				<div class="side-menu-group"><?= $t('settings.group_plugins') ?></div>
				<!-- /ko -->
				<!-- ko foreach: pluginTabs -->
				<a class="list-group-item list-group-item-action"
					data-bind="attr:{href:'#/@settings/'+$data.id}, css:{active:$parent.tab()===$data.id}">
					<i class="fa-solid fa-fw me-2" data-bind="css: $data.icon || 'fa-puzzle-piece'"></i>
					<span data-bind="text: $root.t($data.label)"></span></a>
				<!-- /ko -->
			</div>
		</div>
		<div class="col-12 col-md-9 col-lg-10">

		<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
		<div class="alert alert-success" data-bind="visible: saved" style="display:none"><?= $t('settings.saved') ?></div>

		<!-- Général -->
		<div data-bind="visible: tab()==='general'" style="display:none">
			<h5><?= $t('common.general') ?></h5>
			<div class="card"><div class="card-body">
				<div class="row">
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.messages_per_page') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="value: values().messages_per_page">
									<option value="25">25</option><option value="50">50</option>
									<option value="100">100</option><option value="200">200</option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.layout') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="value: values().layout">
									<option value="horizontal"><?= $t('settings.layout.horizontal') ?></option>
									<option value="vertical"><?= $t('settings.layout.vertical') ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.view_images') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.view_images_help'"></i></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="value: values().view_images">
									<option value="ask"><?= $t('settings.view_images.ask') ?></option>
									<option value="match"><?= $t('settings.view_images.match') ?></option>
									<option value="always"><?= $t('settings.view_images.always') ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.reply_button') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="value: values().msg_default_action">
									<option value="reply"><?= $t('app.reply') ?></option>
									<option value="reply_all"><?= $t('app.reply_all') ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.mark_read_delay') ?></label>
							<div class="col-md-8">
								<input type="number" min="0" max="300" class="form-control form-control-sm" data-bind="value: values().mark_read_delay">
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-autosave"><?= $t('settings.contacts_autosave') ?></label>
							<div class="col-md-8">
								<div class="form-check d-flex align-items-center">
									<input class="form-check-input" type="checkbox" id="st-autosave"
										data-bind="checked: values().contacts_autosave">
									<i class="fa-regular fa-circle-question help-icon ms-2" tabindex="0"
										data-bind="tooltip: 'settings.contacts_autosave_help'"></i>
								</div>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.autologout') ?></label>
							<div class="col-md-8">
								<input type="number" min="0" max="1440" class="form-control form-control-sm" data-bind="value: values().autologout">
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.list_refresh') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="options: refreshOptions,
									optionsText: 'label', optionsValue: 'value', value: values().list_refresh"></select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-notify"><?= $t('settings.notify_new_mail') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.notify_new_mail_help'"></i></label>
							<div class="col-md-8">
								<div class="form-check d-flex align-items-center">
									<input class="form-check-input" type="checkbox" id="st-notify"
										data-bind="checked: values().notify_new_mail, event: { change: toggleNotify }">
								</div>
								<div class="text-danger small" data-bind="visible: notifyPermission()==='denied'" style="display:none"><?= $t('settings.notify_denied') ?></div>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.theme') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="options: $root.themeOptions, optionsText: (i) => $root.t(i.label), optionsValue: 'name', value: values().theme"></select>
							</div>
						</div>
					</div>
				</div>
				<button class="btn btn-primary btn-sm" data-bind="click: saveSettings"><?= $t('common.save') ?></button>
			</div></div>
		</div>

		<!-- Comptes -->
		<div data-bind="visible: tab()==='accounts'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.accounts') ?></h5>
				<button class="btn btn-sm btn-primary" data-bind="click: $root.addAccount"><i class="fa-solid fa-plus"></i> <?= $t('app.add_account') ?></button>
			</div>
			<p class="text-muted small"><?= $t('settings.accounts_help') ?></p>
			<table class="table table-hover table-sm align-middle">
				<thead class="table-light"><tr><th>Email</th><th></th><th></th></tr></thead>
				<tbody data-bind="foreach: $root.accounts">
					<tr>
						<td>
							<span data-bind="text: email"></span>
							<span class="badge text-bg-info ms-1" data-bind="visible: auth && auth !== 'password', text: auth" style="display:none"></span>
						</td>
						<td>
							<span class="badge text-bg-primary" data-bind="visible: Number(is_default)" style="display:none"><?= $t('settings.account_main') ?></span>
							<span class="badge text-bg-success ms-1" data-bind="visible: active" style="display:none"><?= $t('settings.account_active') ?></span>
						</td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="visible: !active, click: $root.switchAccount, tooltip: 'settings.account_switch'" style="display:none"><i class="fa-solid fa-arrow-right-arrow-left"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editAccount, tooltip: 'settings.account_edit'"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="visible: !Number(is_default), click: $parent.defaultAccount, tooltip: 'settings.account_set_default'" style="display:none"><i class="fa-solid fa-star"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="visible: !Number(is_default) && !active, click: $parent.deleteAccount, tooltip: 'common.delete'" style="display:none"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Identités -->
		<div data-bind="visible: tab()==='identities'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.identities_title') ?></h5>
				<button class="btn btn-sm btn-primary" data-bind="click: () => editIdentity(null),
					visible: $root.features().identities !== false"><i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
			</div>
			<table class="table table-hover table-sm align-middle">
				<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th>Email</th><th>Reply-To</th><th><?= $t('settings.default') ?></th><th></th></tr></thead>
				<tbody data-bind="foreach: identities">
					<tr>
						<td data-bind="text: name"></td>
						<td data-bind="text: email"></td>
						<td data-bind="text: reply_to"></td>
						<td><i class="fa-solid fa-check text-success" data-bind="visible: is_default" style="display:none"></i></td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.defaultIdentity, tooltip: 'settings.default'"><i class="fa-solid fa-star"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editIdentity"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteIdentity"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="text-center py-4" data-bind="visible: identitiesLoading" style="display:none">
				<span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
			</div>
		</div>

		<!-- Dossiers système -->
		<div data-bind="visible: tab()==='folders'" style="display:none">
			<h5><?= $t('settings.folders') ?></h5>
			<ul class="nav nav-tabs mb-3">
				<li class="nav-item"><a class="nav-link" href="#/@settings/folders/roles"
					data-bind="css:{active: foldersTab()==='roles'}"><?= $t('settings.folders_roles') ?></a></li>
				<li class="nav-item"><a class="nav-link" href="#/@settings/folders/notify"
					data-bind="css:{active: foldersTab()==='notify'}"><?= $t('settings.folders_notify') ?></a></li>
			</ul>
			<div data-bind="visible: foldersTab()==='roles'" style="display:none">
				<div class="card"><div class="card-body">
					<p class="text-muted small"><?= $t('settings.folders_help') ?></p>
					<!-- ko foreach: folderRoles -->
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm" data-bind="text: $root.t($data.label)"></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" data-bind="options: $parent.roleOptions($data),
								optionsText: 'label', optionsValue: 'value', value: $data.value"></select>
						</div>
					</div>
					<!-- /ko -->
					<button class="btn btn-primary btn-sm" data-bind="click: saveFolderRoles"><?= $t('common.save') ?></button>
				</div></div>
			</div>
			<div data-bind="visible: foldersTab()==='notify'" style="display:none">
				<div class="card"><div class="card-body">
					<p class="text-muted small"><?= $t('settings.folders_notify_help') ?></p>
					<!-- ko foreach: notifyChecks -->
					<div class="form-check">
						<input class="form-check-input" type="checkbox" data-bind="checked: checked, disable: isInbox, attr: { id: 'nf-' + $index() }">
						<label class="form-check-label" data-bind="text: label, attr: { for: 'nf-' + $index() }, style: { paddingLeft: (depth * 1.25) + 'rem' }"></label>
						<!-- ko if: isInbox --><span class="text-muted small ms-1"><?= $t('settings.folders_notify_inbox') ?></span><!-- /ko -->
					</div>
					<!-- /ko -->
					<button class="btn btn-primary btn-sm mt-2" data-bind="click: saveNotifyFolders"><?= $t('common.save') ?></button>
				</div></div>
			</div>
		</div>

		<!-- Applications OAuth2 (identifiants d'app par utilisateur) -->
		<div data-bind="visible: tab()==='oauthapps'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.oauth_apps') ?></h5>
				<button class="btn btn-sm btn-primary" data-bind="click: addOauthApp,
					visible: oauthProviders().length" style="display:none">
					<i class="fa-solid fa-plus"></i> <?= $t('settings.oauth_add') ?></button>
			</div>
			<p class="text-muted small"><?= $t('settings.oauth_apps_help') ?></p>
			<div class="alert alert-info" data-bind="visible: !oauthProviders().length" style="display:none"><?= $t('settings.oauth_apps_none') ?></div>
			<div class="mb-2 small" data-bind="visible: oauthProviders().length" style="display:none">
				<?= $t('settings.oauth_redirect_uri') ?> : <code data-bind="text: oauthRedirectUri"></code>
			</div>
			<table class="table table-hover table-sm align-middle" data-bind="visible: oauthApps().length" style="display:none">
				<thead class="table-light"><tr>
					<th>Provider</th><th>Client ID</th><th><?= $t('settings.oauth_tenant') ?></th>
					<th><?= $t('settings.oauth_accounts') ?></th><th></th>
				</tr></thead>
				<tbody data-bind="foreach: oauthApps">
					<tr>
						<td data-bind="text: $parent.oauthProviderLabel(provider)"></td>
						<td class="font-monospace" data-bind="text: client_id"></td>
						<td data-bind="text: tenant"></td>
						<td><span class="badge text-bg-secondary" data-bind="text: accounts"></span></td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editOauthApp"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteOauthApp"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Onglets plugins (settings.tabs.*) -->
		<!-- ko foreach: pluginTabs -->
		<div data-bind="visible: $parent.tab() === $data.id, pluginMount: $data" style="display:none"></div>
		<!-- /ko -->

		<!-- Contacts -->
		<div data-bind="visible: tab()==='contacts'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.contacts') ?></h5>
				<button class="btn btn-sm btn-primary" data-bind="click: () => editContact(null)"><i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
			</div>
			<table class="table table-hover table-sm align-middle">
				<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th>Emails</th><th></th></tr></thead>
				<tbody data-bind="foreach: contacts">
					<tr>
						<td data-bind="text: name"></td>
						<td data-bind="text: emails"></td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editContact"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteContact"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="text-center py-4" data-bind="visible: contactsLoading" style="display:none">
				<span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
			</div>
			<div class="card" data-bind="visible: editingContact">
				<div class="card-body" data-bind="with: editingContact">
					<div class="row g-2">
						<div class="col-md-4"><input class="form-control" placeholder="<?= $t('common.name') ?>" data-bind="value: name"></div>
						<div class="col-md-8"><input class="form-control" placeholder="<?= $t('settings.emails_csv') ?>" data-bind="value: emails"></div>
					</div>
					<div class="mt-3">
						<button class="btn btn-primary btn-sm" data-bind="click: $parent.saveContact"><?= $t('common.save') ?></button>
						<button class="btn btn-link btn-sm" data-bind="click: () => $parent.editingContact(null)"><?= $t('common.cancel') ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Filtres Sieve -->
		<div data-bind="visible: tab()==='filters'" style="display:none">
			<div class="alert alert-info" data-bind="visible: $root.features().sieve === false" style="display:none"><?= $t('settings.sieve_unavailable') ?></div>
			<div data-bind="visible: filtersError, text: filtersError" class="alert alert-warning" style="display:none"></div>
			<div data-bind="visible: $root.features().sieve !== false" style="display:none">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<h5 class="mb-0"><?= $t('settings.scripts') ?></h5>
					<div class="btn-group btn-group-sm">
						<button class="btn btn-primary" data-bind="click: () => newFilter('empty')">
							<i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
						<button class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="visually-hidden"><?= $t('settings.filters.templates') ?></span>
						</button>
						<ul class="dropdown-menu dropdown-menu-end" data-bind="foreach: filterTemplates">
							<li><a class="dropdown-item" href="#" data-bind="click: () => $parent.newFilter(value), text: $root.t(key)"></a></li>
						</ul>
					</div>
				</div>
				<table class="table table-hover table-sm align-middle">
					<thead class="table-light"><tr>
						<th><?= $t('common.name') ?></th><th><?= $t('settings.status') ?></th><th></th>
					</tr></thead>
					<tbody data-bind="foreach: filters">
						<tr>
							<td data-bind="text: name"></td>
							<td><span class="badge text-bg-success" data-bind="visible: active" style="display:none"><?= $t('settings.active') ?></span></td>
							<td class="text-end text-nowrap">
								<button class="btn btn-sm btn-icon btn-outline-success" data-bind="click: $parent.activateFilter, visible: !active" style="display:none">
									<i class="fa-solid fa-circle-play" data-bind="tooltip: 'settings.activate'"></i></button>
								<button class="btn btn-sm btn-icon btn-outline-warning" data-bind="click: $parent.deactivateFilter, visible: active" style="display:none">
									<i class="fa-solid fa-circle-stop" data-bind="tooltip: 'settings.deactivate'"></i></button>
								<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editFilter"><i class="fa-solid fa-pen"></i></button>
								<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteFilter, visible: !active" style="display:none"><i class="fa-solid fa-trash"></i></button>
								<!-- ko if: active -->
								<span class="d-inline-block" tabindex="0" data-bind="tooltip: 'settings.filters.delete_active_hint'">
									<button class="btn btn-sm btn-icon btn-outline-danger" disabled><i class="fa-solid fa-trash"></i></button>
								</span>
								<!-- /ko -->
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- OpenPGP : keyring utilisateur -->
		<div data-bind="visible: tab()==='pgp'" style="display:none">
			<h5>OpenPGP</h5>
			<p class="text-muted small"><?= $t('settings.pgp_help') ?></p>
			<ul class="nav nav-tabs mb-3">
				<li class="nav-item"><a class="nav-link" href="#/@settings/pgp/keys"
					data-bind="css:{active: pgpTab()==='keys'}"><?= $t('settings.pgp_keys') ?></a></li>
				<li class="nav-item"><a class="nav-link" href="#/@settings/pgp/import"
					data-bind="css:{active: pgpTab()==='import'}"><?= $t('settings.import_key') ?></a></li>
			</ul>
			<div data-bind="visible: pgpTab()==='keys'" style="display:none">
				<table class="table table-hover table-sm align-middle">
					<thead class="table-light"><tr><th>Email</th><th><?= $t('settings.type') ?></th><th></th></tr></thead>
					<tbody data-bind="foreach: pgpKeys">
						<tr>
							<td data-bind="text: email"></td>
							<td data-bind="text: Number(is_private) ? $root.t('settings.key_private') : $root.t('settings.key_public')"></td>
							<td class="text-end">
								<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deletePgpKey"><i class="fa-solid fa-trash"></i></button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div data-bind="visible: pgpTab()==='import'" style="display:none">
				<div class="card"><div class="card-body">
					<div class="row g-2 mb-2">
						<div class="col-md-8"><input class="form-control form-control-sm" placeholder="<?= $t('settings.key_email') ?>" data-bind="value: pgpEmail"></div>
						<div class="col-md-4 d-flex align-items-center">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="pgp-priv" data-bind="checked: pgpPrivate">
								<label class="form-check-label" for="pgp-priv"><?= $t('settings.key_private') ?></label>
							</div>
						</div>
					</div>
					<textarea class="form-control font-monospace" rows="6" spellcheck="false"
						placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----"
						data-bind="value: pgpArmored"></textarea>
					<button class="btn btn-primary btn-sm mt-2" data-bind="click: savePgpKey"><?= $t('settings.import') ?></button>
				</div></div>
			</div>
		</div>
		</div>
		</div>
	</div>

	<!-- Modale de composition -->
	<div class="modal fade" id="compose-modal" tabindex="-1" data-bind="with: compose">
		<div class="modal-dialog modal-lg"
			data-bind="style: { width: composeW() ? composeW() + 'px' : '',
				maxWidth: composeW() ? 'calc(100vw - 32px)' : '' }">
			<div class="modal-content"
				data-bind="style: { height: composeH() ? composeH() + 'px' : '' }">
				<div class="modal-header">
					<h5 class="modal-title"><?= $t('app.new_message') ?></h5>
					<div class="ms-auto me-2 d-flex align-items-center gap-2">
						<select class="form-select form-select-sm w-auto" style="display:none"
							data-bind="options: identities, optionsText: 'email', optionsValue: 'email',
								value: identity, visible: identities().length > 1"></select>
						<div class="dropdown">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="dropdown"
								data-bs-auto-close="outside" aria-label="<?= $t('compose.options') ?>">
								<i class="fa-solid fa-ellipsis" data-bind="tooltip: 'compose.options'"></i>
							</button>
							<ul class="dropdown-menu dropdown-menu-end">
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: showCc, enable: !cc()"> <?= $t('compose.cc') ?></label></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: showBcc, enable: !bcc()"> <?= $t('compose.bcc') ?></label></li>
								<li><hr class="dropdown-divider"></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: pgpEncrypt"> <i class="fa-solid fa-lock me-1"></i><?= $t('compose.encrypt') ?><i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
									data-bind="tooltip: 'compose.encrypt_help'"></i></label></li>
							</ul>
						</div>
					</div>
					<button type="button" class="btn-close ms-0" data-bind="click: hide"></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>

					<div class="mb-2 position-relative">
						<input class="form-control form-control-sm" placeholder="<?= $t('compose.to') ?>" data-bind="value: to, event: { input: () => suggest('to') }">
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" data-bind="foreach: suggestions, visible: suggestField()==='to'">
							<a class="list-group-item list-group-item-action py-1 small" href="#"
								data-bind="click: () => $parent.applySuggestion('to', $data), text: (name ? name + ' — ' : '') + email"></a>
						</div>
					</div>
					<div class="mb-2 position-relative" style="display:none" data-bind="visible: showCc() || !!cc()">
						<input class="form-control form-control-sm" placeholder="<?= $t('compose.cc') ?>" data-bind="value: cc, event: { input: () => suggest('cc') }">
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" data-bind="foreach: suggestions, visible: suggestField()==='cc'">
							<a class="list-group-item list-group-item-action py-1 small" href="#"
								data-bind="click: () => $parent.applySuggestion('cc', $data), text: (name ? name + ' — ' : '') + email"></a>
						</div>
					</div>
					<div class="mb-2 position-relative" style="display:none" data-bind="visible: showBcc() || !!bcc()">
						<input class="form-control form-control-sm" placeholder="<?= $t('compose.bcc') ?>" data-bind="value: bcc, event: { input: () => suggest('bcc') }">
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" data-bind="foreach: suggestions, visible: suggestField()==='bcc'">
							<a class="list-group-item list-group-item-action py-1 small" href="#"
								data-bind="click: () => $parent.applySuggestion('bcc', $data), text: (name ? name + ' — ' : '') + email"></a>
						</div>
					</div>
					<div class="mb-2">
						<input class="form-control form-control-sm" placeholder="<?= $t('compose.subject') ?>" data-bind="value: subject">
					</div>
					<ul class="nav nav-tabs">
						<li class="nav-item"><a class="nav-link" href="#"
							data-bind="css:{active: tab()==='message'}, click: () => tab('message')"><?= $t('app.tab_message') ?></a></li>
						<li class="nav-item"><a class="nav-link" href="#"
							data-bind="css:{active: tab()==='attachments'}, click: () => tab('attachments')">
							<i class="fa-solid fa-paperclip"></i> <?= $t('app.attachments') ?>
							<span class="badge text-bg-secondary ms-1" style="display:none"
								data-bind="visible: attachments().length, text: attachments().length"></span></a></li>
						<li class="ms-auto d-flex align-items-end pb-1">
							<label class="btn btn-sm btn-outline-secondary mb-0">
								<i class="fa-solid fa-paperclip"></i> <?= $t('compose.attach') ?>
								<input type="file" multiple hidden data-bind="event: { change: (d,e) => { const f = e.target, fs = [...f.files]; f.value = ''; upload(fs); } }">
							</label>
						</li>
					</ul>

					<div class="compose-tab compose-tab-msg" data-bind="visible: tab()==='message'" style="display:none">
						<textarea id="compose-body" data-bind="editor: html"></textarea>
					</div>

					<div class="compose-tab compose-tab-pj pt-2" data-bind="visible: tab()==='attachments'" style="display:none">
						<ul class="list-group list-group-flush" data-bind="foreach: attachments">
							<li class="list-group-item px-0">
								<div class="d-flex justify-content-between align-items-center">
									<span class="text-truncate">
										<i class="fa-solid fa-paperclip me-2"></i><span data-bind="text: name"></span>
										<span class="text-muted small ms-2" style="display:none"
											data-bind="visible: size, text: $parent.formatSize(size)"></span>
									</span>
									<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.removeAttachment">
										<i class="fa-solid fa-xmark"></i>
									</button>
								</div>
								<div class="progress mt-1" role="progressbar" style="display:none;height:0.9rem"
									aria-valuemin="0" aria-valuemax="100"
									data-bind="visible: uploading, attr: { 'aria-valuenow': progress, 'aria-label': name }">
									<div class="progress-bar" data-bind="style: { width: progress() + '%' }, text: progress() + '%'"></div>
								</div>
							</li>
						</ul>
						<div class="text-muted small py-3 text-center" style="display:none"
							data-bind="visible: !attachments().length"><?= $t('compose.no_attachments') ?></div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-sm btn-outline-secondary" data-bind="click: saveDraft, disable: sending() || uploadPending()"><?= $t('compose.draft') ?></button>
					<button class="btn btn-sm btn-primary" data-bind="click: send, disable: sending() || uploadPending()">
						<span data-bind="visible: !sending()"><i class="fa-solid fa-paper-plane"></i> <?= $t('compose.send') ?></span>
						<span data-bind="visible: sending" style="display:none"><span class="spinner-border spinner-border-sm"></span> <?= $t('compose.sending') ?></span>
					</button>
				</div>
				<div class="modal-resize-grip" aria-hidden="true"
					data-bind="modalResize:
						{ w: composeW, h: composeH, minW: 480, minH: 360, save: saveComposeSize },
						tooltip: 'app.resize'"></div>
			</div>
		</div>
	</div>

	<!-- Modale source du message -->
	<div class="modal fade" id="source-modal" tabindex="-1" data-bind="with: messageView">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><?= $t('app.view_source') ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<pre class="font-monospace small mb-0" style="max-height:70vh;overflow:auto"
						data-bind="text: sourceText"></pre>
				</div>
			</div>
		</div>
	</div>

	<!-- Modale des options de recherche : champs combinés en ET avec le texte libre.
	     Le brouillon vit dans les observables → conservé à la fermeture. -->
	<div class="modal fade" id="search-modal" tabindex="-1" data-bind="with: messageList">
		<div class="modal-dialog">
			<form class="modal-content" data-bind="submit: applySearch">
				<div class="modal-header">
					<h5 class="modal-title"><?= $t('app.search.advanced') ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="row g-2 mb-3">
						<div class="col-6">
							<label class="form-label form-label-sm mb-1" for="mx-search-from"><?= $t('app.search.from') ?></label>
							<input type="text" class="form-control form-control-sm" id="mx-search-from" data-bind="value: searchFrom">
						</div>
						<div class="col-6">
							<label class="form-label form-label-sm mb-1" for="mx-search-to"><?= $t('app.search.to') ?></label>
							<input type="text" class="form-control form-control-sm" id="mx-search-to" data-bind="value: searchTo">
						</div>
						<div class="col-6">
							<label class="form-label form-label-sm mb-1" for="mx-search-subject"><?= $t('app.search.subject') ?></label>
							<input type="text" class="form-control form-control-sm" id="mx-search-subject" data-bind="value: searchSubject">
						</div>
						<div class="col-6">
							<label class="form-label form-label-sm mb-1" for="mx-search-body"><?= $t('app.search.body') ?></label>
							<input type="text" class="form-control form-control-sm" id="mx-search-body" data-bind="value: searchBody">
						</div>
					</div>
					<div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-2 mb-3">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="mx-search-attach" data-bind="checked: searchAttach">
							<label class="form-check-label" for="mx-search-attach"><?= $t('app.search.attachment') ?></label>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="mx-search-unseen" data-bind="checked: searchUnseen">
							<label class="form-check-label" for="mx-search-unseen"><?= $t('app.search.unseen') ?></label>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="mx-search-flagged" data-bind="checked: searchFlagged">
							<label class="form-check-label" for="mx-search-flagged"><?= $t('app.search.flagged') ?></label>
						</div>
					</div>
					<div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-2">
						<label class="d-flex align-items-center gap-1 mb-0 small" for="mx-search-since">
							<?= $t('app.search.since') ?>
							<input type="date" class="form-control form-control-sm w-auto" id="mx-search-since" data-bind="value: searchSince">
						</label>
						<label class="d-flex align-items-center gap-1 mb-0 small" for="mx-search-before">
							<?= $t('app.search.before') ?>
							<input type="date" class="form-control form-control-sm w-auto" id="mx-search-before" data-bind="value: searchBefore">
						</label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-secondary me-auto"
						data-bind="click: resetSearch"><?= $t('app.search.reset') ?></button>
					<button type="submit" class="btn btn-sm btn-primary"><?= $t('app.search.apply') ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modale script Sieve (création/édition) -->
	<div class="modal fade" id="filter-modal" tabindex="-1" data-bind="with: settings">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content" data-bind="with: editingFilter">
				<div class="modal-header">
					<h5 class="modal-title" data-bind="text: originalName()
						? $root.t('settings.filters.edit_script', { name: originalName() })
						: $root.t('settings.filters.new_script')"></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-danger" data-bind="visible: error, text: error" style="display:none"></div>
					<div class="d-flex gap-2 align-items-end mb-3">
						<div class="flex-grow-1">
							<label class="form-label form-label-sm mb-1"><?= $t('settings.script_name') ?></label>
							<input class="form-control form-control-sm" data-bind="value: name">
						</div>
						<div class="btn-group btn-group-sm text-nowrap" data-bind="visible: managed" style="display:none">
							<button class="btn btn-outline-secondary" data-bind="click: () => $parent.filterMode('assistant'), css: { active: $parent.filterMode() === 'assistant' }"><?= $t('settings.filters.mode_assistant') ?></button>
							<button class="btn btn-outline-secondary" data-bind="click: () => $parent.filterMode('source'), css: { active: $parent.filterMode() === 'source' }"><?= $t('settings.filters.mode_source') ?></button>
						</div>
					</div>

					<!-- Assistant : règles compilées en Sieve par le serveur -->
					<div data-bind="visible: managed && $parent.filterMode() === 'assistant'" style="display:none">
						<div data-bind="foreach: { data: rules, as: 'rule' }">
							<div class="card mb-2"><div class="card-body py-2 px-3">
								<div class="d-flex align-items-center gap-2 mb-2">
									<span class="text-muted small"><?= $t('settings.filters.if_label') ?></span>
									<select class="form-select form-select-sm w-auto" data-bind="value: rule.match">
										<option value="all"><?= $t('settings.filters.match_all') ?></option>
										<option value="any"><?= $t('settings.filters.match_any') ?></option>
									</select>
									<button class="btn btn-sm btn-icon btn-outline-danger ms-auto" data-bind="click: () => $parent.rules.remove(rule)"><i class="fa-solid fa-trash"></i></button>
								</div>
								<div data-bind="foreach: { data: rule.conditions, as: 'cond' }">
									<div class="d-flex flex-wrap gap-1 mb-1 align-items-center">
										<select class="form-select form-select-sm w-auto" data-bind="value: cond.field, options: $parents[2].filterFields, optionsValue: 'value', optionsText: o => $root.t(o.key)"></select>
										<input class="form-control form-control-sm" style="width:9rem" placeholder="<?= $t('settings.filters.header_name') ?>" data-bind="value: cond.header, visible: cond.field() === 'header'">
										<select class="form-select form-select-sm w-auto" data-bind="value: cond.op, options: $parents[2].filterOps, optionsValue: 'value', optionsText: o => $root.t(o.key)"></select>
										<input class="form-control form-control-sm flex-grow-1" style="min-width:8rem" placeholder="<?= $t('settings.filters.cond_value') ?>" data-bind="value: cond.value">
										<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: () => rule.conditions.remove(cond)"><i class="fa-solid fa-xmark"></i></button>
									</div>
								</div>
								<div class="text-muted small my-1"><?= $t('settings.filters.then_label') ?></div>
								<div data-bind="foreach: { data: rule.actions, as: 'act' }">
									<div class="d-flex flex-wrap gap-1 mb-1 align-items-center">
										<select class="form-select form-select-sm w-auto" data-bind="value: act.type, options: $parents[2].filterActions, optionsValue: 'value', optionsText: o => $root.t(o.key)"></select>
										<!-- ko if: $parents[2].filterArgKind(act.type()) === 'folder' -->
										<select class="form-select form-select-sm flex-grow-1" style="min-width:8rem" data-bind="value: act.arg, options: $parents[2].filterFolders, optionsValue: 'value', optionsText: 'label'"></select>
										<!-- /ko -->
										<!-- ko if: $parents[2].filterArgKind(act.type()) === 'email' -->
										<input class="form-control form-control-sm flex-grow-1" style="min-width:8rem" placeholder="<?= $t('settings.filters.act_email') ?>" data-bind="value: act.arg">
										<!-- /ko -->
										<!-- ko if: $parents[2].filterArgKind(act.type()) === 'text' -->
										<input class="form-control form-control-sm flex-grow-1" style="min-width:8rem" placeholder="<?= $t('settings.filters.act_message') ?>" data-bind="value: act.arg">
										<!-- /ko -->
										<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: () => rule.actions.remove(act)"><i class="fa-solid fa-xmark"></i></button>
									</div>
								</div>
								<div class="d-flex gap-1 mt-1">
									<button class="btn btn-sm btn-outline-secondary" data-bind="click: () => $parents[1].addFilterCond(rule)"><?= $t('settings.filters.add_condition') ?></button>
									<button class="btn btn-sm btn-outline-secondary" data-bind="click: () => $parents[1].addFilterAction(rule)"><?= $t('settings.filters.add_action') ?></button>
								</div>
							</div></div>
						</div>
						<button class="btn btn-sm btn-outline-secondary" data-bind="click: $parent.addFilterRule"><?= $t('settings.filters.add_rule') ?></button>
						<button class="btn btn-sm btn-outline-secondary ms-1" data-bind="click: $parent.previewFilter"><?= $t('settings.filters.view_source') ?></button>
						<textarea class="form-control font-monospace mt-2" rows="10" readonly spellcheck="false"
							data-bind="value: $parent.compiledSource, visible: $parent.compiledSource" style="display:none"></textarea>
					</div>

					<!-- Source brute (script externe ou mode source) -->
					<textarea class="form-control font-monospace" rows="14" spellcheck="false"
						placeholder="require [&quot;fileinto&quot;];&#10;if header :contains &quot;Subject&quot; &quot;SPAM&quot; { fileinto &quot;Junk&quot;; }"
						data-bind="value: source, visible: $parent.filterMode() === 'source' || !managed"></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-danger me-auto"
						data-bind="click: $parent.deleteFilter, visible: originalName() && !active()" style="display:none"><?= $t('common.delete') ?></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.cancel') ?></button>
					<button type="button" class="btn btn-sm btn-primary" data-bind="click: $parent.saveFilter"><?= $t('common.save') ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modale identité (redimensionnable via la poignée bas-droite) -->
	<div class="modal fade" id="identity-modal" tabindex="-1" data-bind="with: settings">
		<div class="modal-dialog"
			data-bind="style: { width: identW() ? identW() + 'px' : '',
				maxWidth: identW() ? 'calc(100vw - 32px)' : '' }">
			<div class="modal-content" data-bind="with: editingIdentity,
				style: { height: identH() ? identH() + 'px' : '' }">
				<div class="modal-header">
					<h5 class="modal-title" data-bind="text: id
						? $root.t('settings.identity_edit')
						: $root.t('settings.identity_new')"></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="row g-2 mb-2">
						<div class="col-md-4"><input id="identity-name" class="form-control" placeholder="<?= $t('settings.display_name') ?>" data-bind="value: name"></div>
						<div class="col-md-4"><input class="form-control" placeholder="Email" data-bind="value: email"></div>
						<div class="col-md-4"><input class="form-control" placeholder="<?= $t('settings.reply_to_opt') ?>" data-bind="value: reply_to"></div>
					</div>
					<label class="form-label small mb-1"><?= $t('settings.signature') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
						data-bind="tooltip: 'settings.signature_caret_help'"></i></label>
					<textarea id="signature-editor" data-bind="signatureEditor: signature"></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.cancel') ?></button>
					<button type="button" class="btn btn-sm btn-primary" data-bind="click: $parent.saveIdentity"><?= $t('common.save') ?></button>
				</div>
				<div class="modal-resize-grip" aria-hidden="true"
					data-bind="modalResize:
						{ w: $parent.identW, h: $parent.identH, minW: 480, minH: 400 },
						tooltip: 'app.resize'"></div>
			</div>
		</div>
	</div>
</div>

<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $assets ?>/vendor/knockout/knockout.js"></script>
<script src="<?= $assets ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= $assets ?>/vendor/tinymce/tinymce.min.js"></script>
<script type="module" src="<?= $assets ?>/js/app.js"></script>
</body>
</html>
