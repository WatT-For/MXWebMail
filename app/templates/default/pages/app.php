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
	<!-- openpgp (~540K) et tinymce (~500K) chargés à la demande via js/lazy.js -->
	<?= $pluginHead ?>
	<style>
	/* Splash de démarrage : HTML/CSS pur affiché dès le 1er paint (avant
	   knockout.js), retiré en fondu par app.js une fois l'interface prête.
	   z-index au-dessus du plus haut niveau Bootstrap (toast = 1090). */
	#app-splash {
		position: fixed; inset: 0; z-index: 2000;
		display: flex; flex-direction: column;
		align-items: center; justify-content: center; gap: 1rem;
		background: var(--bs-body-bg); color: var(--bs-body-color);
		/* visibility flip discret en fin de fondu : le compositeur ne peut
		   pas rejouer une frame à pleine opacité (flash fantôme) au remove(). */
		transition: opacity .25s ease, visibility 0s linear .25s;
	}
	#app-splash.done { opacity: 0; visibility: hidden; pointer-events: none; }
	@media (prefers-reduced-motion: reduce) {
		#app-splash { transition: none; }
	}
	</style>
</head>
<body>
<div id="app-splash" role="status">
	<div class="brand-logo"><?= htmlspecialchars($app->config()->get('app_name')) ?></div>
	<div class="spinner-border text-primary" aria-hidden="true"></div>
	<div class="text-body-secondary small"><?= $t('common.loading') ?></div>
</div>
<noscript><style>#app-splash{display:none}</style></noscript>
<div class="app-shell">
	<!-- Barre supérieure -->
	<nav class="navbar navbar-dark px-3">
		<span class="navbar-brand mb-0"><?= htmlspecialchars($app->config()->get('app_name')) ?></span>
		<div class="d-flex align-items-center gap-3">
			<div class="dropdown">
				<button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
					<i class="fa-solid fa-circle-user me-1"></i><span data-bind="text: user() && user().email"></span>
				</button>
				<ul class="dropdown-menu dropdown-menu-end">
					<!-- ko foreach: accounts -->
					<li><a class="dropdown-item" href="#"
						data-bind="click: $parent.switchAccount, css: { active: active }, attr: { 'aria-current': active ? 'true' : null }">
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
			<button class="btn btn-sm btn-icon btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#shortcuts-modal">
				<i class="fa-solid fa-keyboard" data-bind="tooltip: 'app.shortcuts'"></i>
			</button>
			<button class="btn btn-sm btn-icon btn-outline-light" data-bind="click: logout, tooltip: 'app.logout'">
				<i class="fa-solid fa-right-from-bracket"></i>
			</button>
		</div>
	</nav>

	<div class="app-main" data-bind="with: $root, visible: view() === 'mail',
		style: gridTemplate, css: { 'layout-vertical': layout() === 'vertical' }" style="display:none">
		<!-- Colonne dossiers -->
		<aside class="folder-pane" data-bind="with: folderList">
			<div class="d-flex justify-content-between align-items-center gap-2 p-2 border-bottom">
				<button class="btn btn-sm btn-primary text-truncate" style="min-width:0"
					data-bind="click: $root.compose.openNew.bind($root.compose),
						event: { dragover: $root.compose.mailBtnDragOver.bind($root.compose),
							dragleave: $root.compose.mailBtnDragLeave.bind($root.compose),
							drop: $root.compose.mailBtnDrop.bind($root.compose) }">
					<i class="fa-solid fa-pen"></i> <?= $t('app.new_message') ?>
				</button>
				<span class="d-flex gap-1">
					<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: createFolder, tooltip: 'app.new_folder'">
						<i class="fa-solid fa-plus"></i>
					</button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: markAllReadCurrent, tooltip: 'app.mark_all_read',
							disable: !currentFolder() || currentFolder().unread() === 0">
						<i class="fa-regular fa-envelope-open"></i>
					</button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: clearCurrent, tooltip: 'app.empty_folder',
							visible: $root.features().dangerous_actions !== false,
							disable: !currentFolder() || currentFolder().isInbox" style="display:none">
						<i class="fa-solid fa-broom"></i>
					</button>
				</span>
			</div>
			<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->
			<div class="list-group list-group-flush" data-bind="foreach: folders">
				<div class="list-group-item list-group-item-action" role="button"
					data-bind="click: $parent.select, visible: !hidden(),
						css: { active: $parent.isActive($data), 'folder-sep': $data.fullName === $parent.firstBottomName() },
						style: { paddingLeft: (8 + $data.depth * 14) + 'px' },
						event: { dragenter: $parent.folderDragOver, dragover: $parent.folderDragOver,
							dragleave: $parent.folderDragLeave, drop: $parent.folderDrop }">
					<span>
						<i class="fa-solid folder-caret" data-bind="
							css: { 'fa-chevron-down': !collapsed(), 'fa-chevron-right': collapsed(), invisible: !hasChildren },
							click: $parent.toggleCollapse, clickBubble: false,
							tooltip: { text: caretTip, placement: 'right' },
							attr: { 'aria-expanded': hasChildren ? String(!collapsed()) : null }"></i>
						<i class="fa-solid" data-bind="css: $parent.iconFor($data)"></i>
						<span data-bind="text: label"></span>
					</span>
					<span class="d-flex align-items-center">
						<span class="badge text-bg-primary" data-bind="visible: unread() > 0, text: unread" style="display:none"></span>
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
						data-bind="value: search, valueUpdate: 'input', event: { keydown: function(d,e){ if(e.key==='Enter'){ searchNow(); return false; } return true; }, search: clearSearch }">
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
						<li><hr class="dropdown-divider"></li>
						<li><a class="dropdown-item" href="#" data-bind="click: toggleThreads, visible: threadsSupported" style="display:none">
							<i class="fa-solid fa-check fa-fw me-1" data-bind="visible: useThreads" style="display:none"></i>
							<?= $t('app.threads') ?>
						</a></li>
					</ul>
				</div>
			</div>

			<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->

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
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: copyWithPicker, disable: !hasChecked(), tooltip: 'app.copy_to'"><i class="fa-solid fa-copy"></i></button>
					<button class="btn btn-sm btn-icon btn-outline-secondary" style="display:none"
						data-bind="visible: $root.folderList.archiveFolder() && folder() !== $root.folderList.archiveFolder().fullName,
							click: bulkArchive, disable: !hasChecked(), tooltip: 'app.archive'"><i class="fa-solid fa-box-archive"></i></button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: bulkForwardAttach, disable: !hasChecked(), tooltip: 'app.forward_attach'"><i class="fa-solid fa-share"></i></button>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: downloadEmlZip, disable: !hasChecked(), tooltip: 'app.download_eml_zip'"><i class="fa-solid fa-file-zipper"></i></button>
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
						<tr data-bind="click: $parent.rowClick,
							css: { 'table-active': isSelected, 'message-unread': !isSeen() },
							attr: { draggable: true },
							event: { dragstart: $parent.messageDragStart, dragend: $parent.messageDragEnd }">
							<td class="p-0 msg-tag-cell"
								data-bind="style: { backgroundColor: colorTag() || 'transparent' },
									attr: { title: colorTagLabel }"></td>
							<td>
								<input type="checkbox" class="form-check-input" data-bind="checked: isChecked, click: $parent.checkClick, clickBubble: false">
							</td>
							<td>
								<div class="text-truncate" data-bind="text: fromLabel(), css: { 'ps-3': isChild }"></div>
								<div class="text-truncate">
									<i class="fa-solid fa-caret-right me-1" style="display:none;cursor:pointer"
										data-bind="visible: !isChild && threads && threads.length > 1,
											click: $parent.toggleThread, clickBubble: false,
											css: { 'fa-rotate-90': _threadOpen }"></i>
									<i class="fa-solid fa-reply me-1 text-muted" style="display:none"
										data-bind="visible: isAnswered, tooltip: 'app.answered'"></i>
									<i class="fa-solid fa-share me-1 text-muted" style="display:none"
										data-bind="visible: isForwarded, tooltip: 'app.forwarded'"></i>
									<span data-bind="text: subject"></span>
									<span class="badge text-bg-secondary ms-1" style="display:none"
										data-bind="visible: !isChild && threads && threads.length > 1, text: threads.length"></span>
									<span class="badge text-bg-info ms-1" style="display:none"
										data-bind="visible: $parent.searchScope() === 'all' && $parent.searchQuery() !== '', text: folder"></span>
								</div>
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

			<!-- Voile de chargement centré sur la colonne -->
			<div class="msglist-loading" role="status" style="display:none"
				data-bind="visible: showLoader">
				<span class="spinner-border text-primary" aria-hidden="true"></span>
				<span class="visually-hidden"><?= $t('common.loading') ?></span>
			</div>
		</section>

		<div class="pane-resizer" role="separator"
			data-bind="paneResize: { size: messagePaneSize, axis: listPaneAxis, min: listPaneMin, max: listPaneMax, save: savePaneSizes },
				attr: { 'aria-orientation': layout() === 'vertical' ? 'horizontal' : 'vertical' }"></div>

		<!-- Colonne lecture -->
		<section class="reader-pane bg-white" data-bind="with: messageView">
			<div class="text-center text-muted p-5" data-bind="visible: !message() && !loading() && $root.messageList.checkedCount() <= 1" style="display:none">
				<i class="fa-regular fa-envelope-open fa-3x mb-3"></i>
				<p><?= $t('app.select_message') ?></p>
			</div>
			<div class="text-center text-muted p-5" data-bind="visible: $root.messageList.checkedCount() > 1 && !loading()" style="display:none">
				<i class="fa-regular fa-square-check fa-3x mb-3"></i>
				<p data-bind="text: $root.t('app.selected_count', { n: $root.messageList.checkedCount() })"></p>
			</div>
			<div class="text-center p-5" data-bind="visible: loading" style="display:none">
				<span class="spinner-border"></span>
			</div>
			<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->

			<div data-bind="visible: message() && !loading() && $root.messageList.checkedCount() <= 1, with: message" style="display:none">
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
					<div class="d-flex flex-column align-items-end align-self-stretch gap-1">
						<div class="btn-group btn-group-sm">
							<button class="btn btn-sm btn-icon btn-outline-secondary"
								data-bind="click: $parent.prevMessage, disable: !$parent.hasPrev()">
								<i class="fa-solid fa-chevron-left" data-bind="tooltip: 'app.msg_prev'"></i>
							</button>
							<button class="btn btn-sm btn-icon btn-outline-secondary"
								data-bind="click: $parent.nextMessage, disable: !$parent.hasNext()">
								<i class="fa-solid fa-chevron-right" data-bind="tooltip: 'app.msg_next'"></i>
							</button>
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
								data-bind="click: $parent.toggleFlag,
									tooltip: isFlagged() ? 'app.mark_unflagged' : 'app.mark_flagged'">
								<i class="fa-star" data-bind="css: isFlagged() ? 'fa-solid text-warning' : 'fa-regular'"></i>
							</button>
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.toggleSpam, tooltip: $parent.spamLabel()"><i class="fa-solid fa-shield-halved"></i></button>
							<!-- ko if: $root.features().dangerous_actions !== false -->
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.deleteMessage, tooltip: 'common.delete'">
								<i class="fa-solid fa-trash text-danger"></i>
							</button>
							<!-- /ko -->
							<button class="btn btn-sm btn-icon btn-outline-secondary" style="display:none"
								data-bind="visible: !!unsubscribe, click: $parent.unsubscribe, tooltip: 'app.unsubscribe'">
								<i class="fa-solid fa-user-xmark"></i>
							</button>
							<button class="btn btn-sm btn-icon btn-outline-secondary"
								data-bs-toggle="dropdown"
								data-bs-popper-config='{"strategy":"fixed"}'
								aria-label="<?= $t('app.more_actions') ?>">
								<i class="fa-solid fa-ellipsis" data-bind="tooltip: 'app.more_actions'"></i>
							</button>
							<ul class="dropdown-menu dropdown-menu-end">
								<li><a class="dropdown-item" href="#" data-bind="click: () => $root.compose.openForward($data, true)"><i class="fa-solid fa-paperclip me-2"></i><?= $t('app.forward_attach') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: () => $root.compose.openAsNew($data)"><i class="fa-solid fa-pen-to-square me-2"></i><?= $t('app.edit_as_new') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.moveWithPicker"><i class="fa-solid fa-file-export me-2"></i><?= $t('app.move_to') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.copyWithPicker"><i class="fa-solid fa-copy me-2"></i><?= $t('app.copy_to') ?></a></li>
								<li style="display:none" data-bind="visible: $root.features().sieve"><a class="dropdown-item" href="#" data-bind="click: $parent.createFilter"><i class="fa-solid fa-filter me-2"></i><?= $t('app.create_filter') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.bounce"><i class="fa-solid fa-share-from-square me-2"></i><?= $t('app.bounce') ?></a></li>
								<li style="display:none"
									data-bind="visible: $root.folderList.archiveFolder() && folder !== $root.folderList.archiveFolder().fullName"><a class="dropdown-item" href="#"
									data-bind="click: $parent.archive"><i class="fa-solid fa-box-archive me-2"></i><?= $t('app.archive') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.markUnread"><i class="fa-regular fa-envelope me-2"></i><?= $t('app.mark_unread') ?></a></li>
								<li><hr class="dropdown-divider"></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.printMessage"><i class="fa-solid fa-print me-2"></i><?= $t('app.print') ?></a></li>
								<li><a class="dropdown-item" data-bind="attr: { href: $parent.rawUrl() }"><i class="fa-solid fa-download me-2"></i><?= $t('app.download_eml') ?></a></li>
								<li><a class="dropdown-item" href="#" data-bind="click: $parent.showSource"><i class="fa-solid fa-code me-2"></i><?= $t('app.view_source') ?></a></li>
							</ul>
						</div>
						<!-- Verdict anti-spoofing : bouclier coloré par niveau, détail en tooltip -->
						<i class="fa-solid fa-shield-halved fa-2x mt-auto" tabindex="0" style="display:none"
							data-bind="visible: !!$parent.security(), css: $parent.securityIconCss,
								attr: { 'aria-label': $parent.securityTip },
								tooltip: { text: $parent.securityTip, placement: 'left', pre: true }"></i>
					</div>
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
					<a class="btn btn-sm btn-outline-primary mb-2" style="display:none"
						data-bind="visible: attachments && attachments.length > 1,
							attr: { href: $parent.attachmentsZipUrl() }">
						<i class="fa-solid fa-file-zipper"></i> <?= $t('app.download_all') ?></a>
					<div class="table-responsive">
					<table class="table table-hover table-sm align-middle mb-0" style="table-layout:fixed">
						<thead class="table-light"><tr>
							<th><?= $t('common.name') ?></th>
							<th class="text-end" style="width:5.5rem"><?= $t('common.size') ?></th>
							<th class="text-end" style="width:6.5rem"><?= $t('common.actions') ?></th>
						</tr></thead>
						<tbody data-bind="foreach: attachments">
							<tr>
								<td class="text-truncate" data-bind="attr: { title: name }, text: name"></td>
								<td class="text-end text-muted"
									data-bind="text: size ? $parents[1].formatSize(size) : ''"></td>
								<td><div class="d-flex gap-1 justify-content-end">
									<!-- ko if: $root.features().ics_viewer
										&& (mime === 'text/calendar' || /\.ics$/i.test(name || '')) -->
									<button class="btn btn-sm btn-icon btn-outline-primary"
										data-bind="click: () => $parents[1].previewIcs($data), tooltip: 'plugin.ics-viewer.title'">
										<i class="fa-solid fa-calendar-days"></i>
									</button>
									<!-- /ko -->
									<!-- ko if: $root.features().vcf_viewer
										&& (mime === 'text/vcard' || mime === 'text/x-vcard' || /\.vcf$/i.test(name || '')) -->
									<button class="btn btn-sm btn-icon btn-outline-primary"
										data-bind="click: () => $parents[1].previewVcf($data), tooltip: 'plugin.vcf-viewer.title'">
										<i class="fa-solid fa-address-card"></i>
									</button>
									<!-- /ko -->
									<!-- ko if: $parents[1].isPreviewable($data) -->
									<button class="btn btn-sm btn-icon btn-outline-primary"
										data-bind="click: () => $parents[1].preview($data), tooltip: 'app.preview'">
										<i class="fa-solid fa-magnifying-glass"></i>
									</button>
									<!-- /ko -->
									<a class="btn btn-sm btn-icon btn-outline-secondary"
										data-bind="attr: { href: $parents[1].attachmentUrl($data) },
											tooltip: 'common.download'">
										<i class="fa-solid fa-download"></i>
									</a>
								</div></td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>

				<div data-bind="visible: $parent.tab()==='message'" style="display:none">
					<div class="alert alert-info py-1 px-2 mb-2 small ko-flex justify-content-between align-items-center"
						data-bind="visible: $parent.imagesBlocked" style="display:none">
						<span><i class="fa-solid fa-image"></i> <?= $t('app.images_blocked') ?></span>
						<button class="btn btn-sm btn-outline-primary" style="display:none"
							data-bind="visible: $root.features().image_proxy, click: $parent.showRemoteImages"><?= $t('app.show') ?></button>
					</div>
					<div class="alert alert-warning py-1 px-2 mb-2 small ko-flex justify-content-between align-items-center"
						data-bind="visible: $parent.mdnBanner" style="display:none">
						<span><i class="fa-solid fa-receipt"></i> <?= $t('app.mdn_requested') ?></span>
						<span class="d-flex gap-1">
							<button class="btn btn-sm btn-outline-primary" data-bind="click: $parent.sendMdn"><?= $t('app.mdn_send') ?></button>
							<button class="btn btn-sm btn-outline-secondary" data-bind="click: $parent.dismissMdn"><?= $t('app.mdn_ignore') ?></button>
						</span>
					</div>
					<button class="btn btn-sm btn-outline-primary mb-2" data-bind="visible: $parent.isPgp(), click: $parent.decryptPgp" style="display:none">
						<i class="fa-solid fa-key"></i> <span data-bind="text: $parent.isPgpSigned() ? $root.t('app.verify_pgp') : $root.t('app.decrypt_pgp')"></span>
					</button>
					<button class="btn btn-sm btn-outline-secondary mb-2" data-bind="visible: $parent.message() && $parent.message().smimeSigned, click: $parent.verifySmime" style="display:none">
						<i class="fa-solid fa-certificate"></i> <span data-bind="text: $parent.smimeLabel"></span>
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
			<div class="d-flex gap-2">
				<?php if ($sponsorUrl !== ''): ?>
				<a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($sponsorUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-heart"></i> <?= $t('common.sponsor') ?></a>
				<?php endif; ?>
				<button class="btn btn-sm btn-outline-secondary" data-bind="click: () => location.hash = '#/INBOX'">
					<i class="fa-solid fa-arrow-left"></i> <?= $t('common.back') ?>
				</button>
			</div>
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
				<a class="list-group-item list-group-item-action" href="#/@settings/responses"
					data-bind="css:{active:tab()==='responses'}"><i class="fa-solid fa-message fa-fw me-2"></i><?= $t('settings.responses') ?></a>
				<a class="list-group-item list-group-item-action" href="#/@settings/filters"
					data-bind="css:{active: tab()==='filters'}">
					<i class="fa-solid fa-filter fa-fw me-2"></i><?= $t('settings.filters') ?></a>
				<a class="list-group-item list-group-item-action" href="#/@settings/pgp"
					data-bind="css:{active: tab()==='pgp'}">
					<i class="fa-solid fa-key fa-fw me-2"></i>OpenPGP</a>
				<a class="list-group-item list-group-item-action" href="#/@settings/sessions"
					data-bind="css:{active: tab()==='sessions'}">
					<i class="fa-solid fa-laptop fa-fw me-2"></i><?= $t('settings.sessions') ?></a>
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

		<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->
		<!-- ko toast: { value: saved, variant: 'success', text: 'settings.saved' } --><!-- /ko -->

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
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.after_delete') ?></label>
							<div class="col-md-8">
								<select class="form-select form-select-sm" data-bind="value: values().after_delete">
									<option value="next"><?= $t('settings.after_delete.next') ?></option>
									<option value="prev"><?= $t('settings.after_delete.prev') ?></option>
									<option value="close"><?= $t('settings.after_delete.close') ?></option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-keys"><?= $t('settings.keyboard_shortcuts') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.keyboard_shortcuts_help'"></i></label>
							<div class="col-md-8"><div class="form-check">
								<input class="form-check-input" type="checkbox" id="st-keys" data-bind="checked: values().keyboard_shortcuts">
							</div></div>
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
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-autosave"><?= $t('settings.contacts_autosave') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.contacts_autosave_help'"></i></label>
							<div class="col-md-8"><div class="form-check">
								<input class="form-check-input" type="checkbox" id="st-autosave" data-bind="checked: values().contacts_autosave">
							</div></div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm"><?= $t('settings.compose_autosave') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.compose_autosave_help'"></i></label>
							<div class="col-md-8">
								<input type="number" min="0" max="300" class="form-control form-control-sm" data-bind="value: values().compose_autosave">
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
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="st-notify"
										data-bind="checked: values().notify_new_mail, event: { change: toggleNotify }">
								</div>
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
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-threads"><?= $t('settings.list_threads') ?> <i class="fa-regular fa-circle-question help-icon" tabindex="0"
								data-bind="tooltip: 'settings.list_threads_help'"></i></label>
							<div class="col-md-8"><div class="form-check">
								<input class="form-check-input" type="checkbox" id="st-threads" data-bind="checked: values().list_threads">
							</div>
							<div class="form-text" data-bind="visible: $root.messageList.threadsSupported() === false" style="display:none"><?= $t('settings.list_threads_unsupported') ?></div></div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-empty-trash"><?= $t('settings.empty_trash_logout') ?></label>
							<div class="col-md-8"><div class="form-check">
								<input class="form-check-input" type="checkbox" id="st-empty-trash" data-bind="checked: values().empty_trash_on_logout">
							</div></div>
						</div>
					</div>
					<div class="col-12 col-xxl-6">
						<div class="row align-items-center mb-2">
							<label class="col-md-4 col-form-label col-form-label-sm" for="st-empty-junk"><?= $t('settings.empty_junk_logout') ?></label>
							<div class="col-md-8"><div class="form-check">
								<input class="form-check-input" type="checkbox" id="st-empty-junk" data-bind="checked: values().empty_junk_on_logout">
							</div></div>
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
				<thead class="table-light"><tr><th></th><th>Email</th><th></th><th></th></tr></thead>
				<tbody data-bind="foreach: $root.accounts">
					<tr data-bind="attr: {draggable: !Number(is_default)}, css: {'mx-drag': !Number(is_default)}, event: {dragstart: $parent.accountDragStart, dragover: $parent.accountDragOver, drop: $parent.accountDrop, dragend: $parent.accountDragEnd}">
						<td class="mx-grip"><i class="fa-solid fa-grip-vertical" data-bind="visible: !Number(is_default), tooltip: 'settings.account_reorder'" style="display:none"></i></td>
						<td>
							<span data-bind="text: email"></span>
							<span class="badge text-bg-info ms-1" data-bind="visible: auth && auth !== 'password', text: auth" style="display:none"></span>
						</td>
						<td>
							<span class="badge text-bg-primary" data-bind="visible: Number(is_default)" style="display:none"><?= $t('settings.account_main') ?></span>
							<span class="badge text-bg-success ms-1" data-bind="visible: active" style="display:none"><?= $t('settings.account_active') ?></span>
						</td>
						<td class="text-end">
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

		<!-- Dossiers -->
		<div data-bind="visible: tab()==='folders'" style="display:none">
			<h5><?= $t('settings.folders') ?></h5>
			<ul class="nav nav-tabs mb-3">
				<li class="nav-item"><a class="nav-link" href="#/@settings/folders/manage"
					data-bind="css:{active: foldersTab()==='manage'}"><?= $t('settings.folders_manage') ?></a></li>
				<li class="nav-item"><a class="nav-link" href="#/@settings/folders/roles"
					data-bind="css:{active: foldersTab()==='roles'}"><?= $t('settings.folders_roles') ?></a></li>
				<li class="nav-item"><a class="nav-link" href="#/@settings/folders/import"
					data-bind="css:{active: foldersTab()==='import'}"><?= $t('app.import_eml') ?></a></li>
			</ul>
			<div data-bind="visible: foldersTab()==='manage'" style="display:none">
				<div class="card"><div class="card-body">
					<p class="text-muted small"><?= $t('settings.folders_notify_help') ?></p>
					<table class="table table-hover table-sm align-middle settings-folders">
						<thead class="table-light"><tr>
							<th class="text-center text-nowrap" style="width:1%"><?= $t('settings.folders_display') ?></th>
							<th class="text-center text-nowrap" style="width:1%"><?= $t('settings.folders_watch') ?></th>
							<th><?= $t('settings.folders') ?></th>
							<th class="text-end text-nowrap" style="width:1%; display:none"
								data-bind="visible: hasFolderSize"><?= $t('common.size') ?></th>
							<th style="width:1%"></th>
						</tr></thead>
						<tbody data-bind="foreach: notifyChecks">
							<tr>
								<td class="text-center">
									<div class="form-check form-switch m-0 d-inline-block">
										<input class="form-check-input" type="checkbox" role="switch"
											data-bind="checked: folder.shown, disable: isSystem,
												event: { change: $parent.persistFolderVisibility },
												attr: { id: 'df-' + $index() }">
									</div>
								</td>
								<td class="text-center">
									<input class="form-check-input" type="checkbox"
										data-bind="checked: checked, disable: isInbox, attr: { id: 'nf-' + $index() }">
								</td>
								<td>
									<label data-bind="text: label, attr: { for: 'nf-' + $index() },
										style: { paddingLeft: (depth * 1.25) + 'rem' }"></label>
									<!-- ko if: isInbox --><span class="text-muted small ms-1"><?= $t('settings.folders_notify_inbox') ?></span><!-- /ko -->
								</td>
								<td class="text-end text-nowrap" style="display:none"
									data-bind="visible: $parent.hasFolderSize, text: folder.sizeLabel"></td>
								<td class="text-end text-nowrap">
									<button class="btn btn-sm btn-icon btn-outline-secondary" style="display:none"
										data-bind="visible: !isSystem, click: () => $root.folderList.renameFolder($data.folder), tooltip: 'app.rename'"><i class="fa-solid fa-pen"></i></button>
									<button class="btn btn-sm btn-icon btn-outline-secondary" style="display:none"
										data-bind="visible: !isSystem, click: () => $root.folderList.editAcl($data.folder), tooltip: 'folders.acl_title'"><i class="fa-solid fa-user-lock"></i></button>
									<button class="btn btn-sm btn-icon btn-outline-danger" style="display:none"
										data-bind="visible: !isSystem && $root.features().dangerous_actions !== false, click: () => $root.folderList.deleteFolder($data.folder), tooltip: 'common.delete'"><i class="fa-solid fa-trash"></i></button>
								</td>
							</tr>
						</tbody>
					</table>
					<button class="btn btn-primary btn-sm" data-bind="click: saveNotifyFolders"><?= $t('common.save') ?></button>
				</div></div>
			</div>
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
			<div data-bind="visible: foldersTab()==='import'" style="display:none">
				<div class="card"><div class="card-body">
					<p class="text-muted small"><?= $t('settings.import_eml_help') ?></p>
					<div class="row align-items-center mb-2">
						<label class="col-md-4 col-form-label col-form-label-sm" for="eml-target"><?= $t('settings.import_folder') ?></label>
						<div class="col-md-8">
							<select class="form-select form-select-sm" id="eml-target"
								data-bind="options: $root.folderList.folders, optionsText: 'label',
									optionsValue: 'fullName', value: importFolder, disable: importBusy"></select>
						</div>
					</div>
					<label class="btn btn-sm btn-primary mb-0" data-bind="css: { disabled: importBusy }">
						<i class="fa-solid fa-file-import"></i> <?= $t('app.import_eml') ?>
						<input type="file" accept=".eml,.mbox,message/rfc822" multiple class="d-none"
							data-bind="event: { change: importEmlFiles }, disable: importBusy">
					</label>
					<span class="small text-muted ms-2" style="display:none"
						data-bind="visible: importProgress, text: importProgress"></span>
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

		<!-- Réponses types -->
		<div data-bind="visible: tab()==='responses'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.responses') ?></h5>
				<button class="btn btn-sm btn-primary" data-bind="click: () => editResponse(null)"><i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
			</div>
			<p class="text-muted small"><?= $t('settings.responses_help') ?></p>
			<table class="table table-hover table-sm align-middle">
				<tbody data-bind="foreach: responses">
					<tr>
						<td data-bind="text: name"></td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editResponse"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteResponse"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="card" data-bind="visible: editingResponse">
				<div class="card-body" data-bind="with: editingResponse">
					<input class="form-control form-control-sm mb-2" maxlength="100"
						data-bind="value: name, attr:{placeholder:'<?= $t('settings.responses_name') ?>'}">
					<textarea class="form-control" data-bind="signatureEditor: body"></textarea>
					<div class="mt-2">
						<button class="btn btn-primary btn-sm" data-bind="click: $parent.saveResponse"><?= $t('common.save') ?></button>
						<button class="btn btn-link btn-sm" data-bind="click: () => $parent.editingResponse(null)"><?= $t('common.cancel') ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Onglets plugins (settings.tabs.*) -->
		<!-- ko foreach: pluginTabs -->
		<div data-bind="visible: $parent.tab() === $data.id, pluginMount: $data" style="display:none"></div>
		<!-- /ko -->

		<!-- Contacts -->
		<div data-bind="visible: tab()==='contacts'" style="display:none">
			<div class="d-flex justify-content-between mb-2">
				<h5><?= $t('settings.contacts') ?></h5>
				<div class="d-flex align-items-center gap-1">
					<input type="search" class="form-control form-control-sm w-auto" style="display:none"
						data-bind="value: contactSearch, valueUpdate: 'input', visible: contacts().length > 0"
						placeholder="<?= $t('settings.contact_search') ?>">
					<button class="btn btn-sm btn-outline-secondary" data-bind="click: exportContacts"><i class="fa-solid fa-file-export"></i> <?= $t('settings.export') ?></button>
					<label class="btn btn-sm btn-outline-secondary mb-0"><i class="fa-solid fa-file-import"></i> <?= $t('settings.import') ?><input type="file" accept=".vcf,.csv,.sqlite,.sqlite3,.db,text/vcard,text/csv,application/vnd.sqlite3,application/x-sqlite3" class="d-none" data-bind="event: {change: importContacts}"></label>
					<button class="btn btn-sm btn-primary" data-bind="click: () => editContact(null)"><i class="fa-solid fa-plus"></i> <?= $t('common.add') ?></button>
				</div>
			</div>
			<p class="text-muted small"><?= $t('settings.contacts_import_help') ?></p>
			<table class="table table-hover table-sm align-middle">
				<thead class="table-light"><tr><th><?= $t('common.name') ?></th><th>Emails</th><th><?= $t('settings.contact_groups') ?></th><th></th></tr></thead>
				<tbody data-bind="foreach: pagedContacts">
					<tr>
						<td data-bind="text: name"></td>
						<td data-bind="text: emails"></td>
						<td><!-- ko foreach: (properties && properties.categories) || [] -->
							<span class="badge text-bg-secondary me-1" data-bind="text: $data"></span>
						<!-- /ko --></td>
						<td class="text-end">
							<button class="btn btn-sm btn-icon btn-outline-secondary"
								data-bind="click: $parent.composeToContact, tooltip: 'settings.contact_compose',
									attr: { 'aria-label': $root.t('settings.contact_compose') }"><i class="fa-solid fa-pen-to-square"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.editContact"><i class="fa-solid fa-pen"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteContact"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="d-flex justify-content-end mt-1" data-bind="visible: contactsTotalPages() > 1" style="display:none">
				<div class="btn-group btn-group-sm">
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: contactsPrevPage, disable: contactsPageEff() <= 1,
							tooltip: 'app.page_prev', attr: { 'aria-label': $root.t('app.page_prev') }">
						<i class="fa-solid fa-chevron-left"></i>
					</button>
					<span class="btn btn-outline-secondary disabled" data-bind="text: contactsPageEff() + ' / ' + contactsTotalPages()"></span>
					<button class="btn btn-sm btn-icon btn-outline-secondary"
						data-bind="click: contactsNextPage, disable: contactsPageEff() >= contactsTotalPages(),
							tooltip: 'app.page_next', attr: { 'aria-label': $root.t('app.page_next') }">
						<i class="fa-solid fa-chevron-right"></i>
					</button>
				</div>
			</div>
			<div class="text-center py-4" data-bind="visible: contactsLoading" style="display:none">
				<span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
			</div>
		</div>

		<!-- Filtres Sieve -->
		<div data-bind="visible: tab()==='filters'" style="display:none">
			<div class="alert alert-info" data-bind="visible: $root.features().sieve === false" style="display:none"><?= $t('settings.sieve_unavailable') ?></div>
			<!-- ko toast: { value: filtersError, variant: 'warning' } --><!-- /ko -->
			<div data-bind="visible: $root.features().sieve !== false" style="display:none">
				<ul class="nav nav-tabs mb-3">
					<li class="nav-item"><a class="nav-link" href="#/@settings/filters/list"
						data-bind="css:{active: filtersTab()==='list'}"><?= $t('settings.filters') ?></a></li>
					<li class="nav-item"><a class="nav-link" href="#/@settings/filters/vacation"
						data-bind="css:{active: filtersTab()==='vacation'}"><?= $t('settings.vacation') ?></a></li>
				</ul>
				<div data-bind="visible: filtersTab()==='list'" style="display:none">
					<div class="d-flex justify-content-end mb-2">
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
				<div data-bind="visible: filtersTab()==='vacation'" style="display:none">
					<div class="card mb-3" data-bind="visible: vacation" style="display:none">
						<div class="card-body" data-bind="with: vacation">
							<div class="alert alert-warning py-1 small" style="display:none"
								data-bind="visible: !$parent.vacationManaged()"><?= $t('settings.vacation_unmanaged') ?></div>
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" id="vac-on" data-bind="checked: enabled">
								<label class="form-check-label" for="vac-on"><?= $t('settings.vacation_enabled') ?></label>
							</div>
							<div class="row g-2 mb-2">
								<div class="col-6 col-md-3"><label class="form-label small"><?= $t('settings.vacation_from') ?></label>
									<input type="date" class="form-control form-control-sm" data-bind="value: from"></div>
								<div class="col-6 col-md-3"><label class="form-label small"><?= $t('settings.vacation_to') ?></label>
									<input type="date" class="form-control form-control-sm" data-bind="value: to"></div>
								<div class="col-6 col-md-2"><label class="form-label small"><?= $t('settings.vacation_days') ?></label>
									<input type="number" min="1" max="30" class="form-control form-control-sm" data-bind="value: days"></div>
								<div class="col-6 col-md-4"><label class="form-label small"><?= $t('settings.vacation_subject') ?></label>
									<input type="text" class="form-control form-control-sm" data-bind="value: subject"></div>
							</div>
							<div class="mb-2"><label class="form-label small"><?= $t('settings.vacation_message') ?></label>
								<textarea class="form-control form-control-sm" rows="4" data-bind="value: message"></textarea></div>
							<div class="mb-2">
								<label class="form-label small"><?= $t('settings.vacation_addresses') ?>
									<i class="fa-regular fa-circle-question help-icon" tabindex="0"
										data-bind="tooltip: 'settings.vacation_addresses_help'"></i></label>
								<div class="dropdown">
									<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
										data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
										data-bind="text: $root.t('settings.vacation_n_addresses', { n: addresses().length })"></button>
									<ul class="dropdown-menu" data-bind="foreach: $parent.vacationAddressChoices">
										<li><label class="dropdown-item d-flex align-items-center gap-2">
											<input class="form-check-input m-0" type="checkbox"
												data-bind="checkedValue: $data, checked: $parent.addresses">
											<span data-bind="text: $data"></span>
										</label></li>
									</ul>
								</div>
							</div>
							<button class="btn btn-primary btn-sm" data-bind="click: $parent.saveVacation, enable: $parent.vacationManaged"><?= $t('common.save') ?></button>
						</div>
					</div>
				</div>
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

		<!-- Onglet Sessions (jetons "se souvenir de moi") -->
		<div data-bind="visible: tab()==='sessions'" style="display:none">
			<h5><?= $t('settings.sessions') ?></h5>
			<p class="text-muted small"><?= $t('settings.sessions_help') ?></p>
			<table class="table table-hover table-sm align-middle" data-bind="visible: sessions().length" style="display:none">
				<thead class="table-light"><tr>
					<th><?= $t('settings.session_agent') ?></th>
					<th><?= $t('settings.session_ip') ?></th>
					<th><?= $t('settings.session_created') ?></th>
					<th><?= $t('settings.session_last_used') ?></th>
					<th></th>
				</tr></thead>
				<tbody data-bind="foreach: sessions">
					<tr>
						<td class="text-truncate" style="max-width:280px">
							<span data-bind="text: agent || '-'"></span>
							<span class="badge text-bg-primary ms-1" style="display:none" data-bind="visible: current"><?= $t('settings.session_current') ?></span>
						</td>
						<td class="small text-nowrap" data-bind="text: last_ip || ip || '-'"></td>
						<td class="small" data-bind="text: $parent.fmtTs(created_at)"></td>
						<td class="small" data-bind="text: last_used_at ? $parent.fmtTs(last_used_at) : '-'"></td>
						<td class="text-end text-nowrap">
							<button class="btn btn-sm btn-icon btn-outline-secondary" data-bind="click: $parent.showSession, tooltip: 'settings.session_details'"><i class="fa-solid fa-circle-info"></i></button>
							<button class="btn btn-sm btn-icon btn-outline-danger" data-bind="click: $parent.deleteSession, tooltip: 'common.delete'"><i class="fa-solid fa-trash"></i></button>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="text-muted small" data-bind="visible: !sessions().length" style="display:none"><?= $t('settings.sessions_none') ?></p>
			<button class="btn btn-sm btn-outline-danger" style="display:none" data-bind="click: revokeOtherSessions, visible: sessions().length > 1"><?= $t('settings.sessions_revoke_others') ?></button>
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
				data-bind="style: { height: composeH() ? composeH() + 'px' : '' },
					css: { 'mx-dropzone': dropActive },
					event: { dragenter: onDragEnter, dragover: onDragOver, dragleave: onDragLeave, drop: onDrop, paste: onPaste }">
				<div class="compose-drop-hint" data-bind="visible: dropActive" style="display:none">
					<i class="fa-solid fa-paperclip fa-2x mb-2"></i>
					<span><?= $t('compose.drop_hint') ?></span>
				</div>
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
									data-bind="checked: showCc, enable: !cc().length && !ccText()"> <?= $t('compose.cc') ?></label></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: showBcc, enable: !bcc().length && !bccText()"> <?= $t('compose.bcc') ?></label></li>
								<li><hr class="dropdown-divider"></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: priority"> <i class="fa-solid fa-exclamation me-1"></i><?= $t('compose.priority') ?></label></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-1"
									data-bind="checked: mdn"> <i class="fa-solid fa-receipt me-1"></i><?= $t('compose.mdn') ?></label></li>
								<li><hr class="dropdown-divider"></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: pgpEncrypt"> <i class="fa-solid fa-lock me-1"></i><?= $t('compose.encrypt') ?><i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
									data-bind="tooltip: 'compose.encrypt_help'"></i></label></li>
								<li><label class="dropdown-item"><input type="checkbox" class="form-check-input me-2"
									data-bind="checked: pgpSign"> <i class="fa-solid fa-pen-fancy me-1"></i><?= $t('compose.sign') ?><i class="fa-regular fa-circle-question help-icon ms-1" tabindex="0"
									data-bind="tooltip: 'compose.sign_help'"></i></label></li>
								<li><hr class="dropdown-divider"></li>
								<li class="dropdown-submenu">
									<!-- click noop : neutralise href="#" (sinon le routeur hash réagit) -->
									<a class="dropdown-item d-flex justify-content-between align-items-center" href="#"
										data-bind="click: () => {}">
										<span><i class="fa-solid fa-message me-2"></i><?= $t('compose.responses') ?></span>
										<i class="fa-solid fa-caret-left ms-3"></i>
									</a>
									<ul class="dropdown-menu">
										<!-- ko ifnot: $root.settings.responses().length -->
										<li><span class="dropdown-item-text text-muted"><?= $t('compose.responses_empty') ?></span></li>
										<!-- /ko -->
										<!-- ko foreach: $root.settings.responses -->
										<li><a class="dropdown-item" href="#" data-bind="click: $parent.applyResponse, text: name"></a></li>
										<!-- /ko -->
									</ul>
								</li>
							</ul>
						</div>
					</div>
					<button type="button" class="btn-close ms-0" data-bind="click: hide"></button>
				</div>
				<div class="modal-body">
					<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->

					<div class="mb-2 position-relative">
						<div class="form-control form-control-sm addr-field d-flex flex-wrap align-items-center gap-1"
							data-bind="click: () => document.getElementById('compose-to-input').focus()">
							<!-- ko foreach: to -->
							<span class="addr-chip" data-bind="css:{'addr-chip-invalid':!!$data.invalid}, attr:{title:email}">
								<span data-bind="text: name || email"></span>
								<button type="button" class="addr-chip-x" title="<?= $t('common.delete') ?>"
									data-bind="click: () => $parent.removeChip('to', $data)"><i class="fa-solid fa-xmark"></i></button>
							</span>
							<!-- /ko -->
							<input id="compose-to-input" class="addr-input" type="text" autocomplete="off"
								placeholder="<?= $t('compose.to') ?>"
								data-bind="value: toText, valueUpdate: 'input', event: {
									input: () => onAddrInput('to'),
									keydown: (d,e) => onAddrKey('to', e),
									blur: onAddrBlur }">
						</div>
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" role="listbox"
							data-bind="foreach: suggestions, visible: suggestField()==='to' && suggestions().length">
							<a class="list-group-item list-group-item-action py-1 small" href="#" role="option"
								data-bind="event:{mousedown:(d,e)=>{e.preventDefault();$parent.applySuggestion('to',$data);}},
									css:{active:$index()===$parent.suggestIndex()},
									text: $data.group ? $root.t('compose.group', {name: $data.group, n: $data.count}) : ($data.name ? $data.name + ' - ' : '') + $data.email"></a>
						</div>
					</div>
					<div class="mb-2 position-relative" style="display:none" data-bind="visible: showCc() || !!cc().length || !!ccText()">
						<div class="form-control form-control-sm addr-field d-flex flex-wrap align-items-center gap-1"
							data-bind="click: () => document.getElementById('compose-cc-input').focus()">
							<!-- ko foreach: cc -->
							<span class="addr-chip" data-bind="css:{'addr-chip-invalid':!!$data.invalid}, attr:{title:email}">
								<span data-bind="text: name || email"></span>
								<button type="button" class="addr-chip-x" title="<?= $t('common.delete') ?>"
									data-bind="click: () => $parent.removeChip('cc', $data)"><i class="fa-solid fa-xmark"></i></button>
							</span>
							<!-- /ko -->
							<input id="compose-cc-input" class="addr-input" type="text" autocomplete="off"
								placeholder="<?= $t('compose.cc') ?>"
								data-bind="value: ccText, valueUpdate: 'input', event: {
									input: () => onAddrInput('cc'),
									keydown: (d,e) => onAddrKey('cc', e),
									blur: onAddrBlur }">
						</div>
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" role="listbox"
							data-bind="foreach: suggestions, visible: suggestField()==='cc' && suggestions().length">
							<a class="list-group-item list-group-item-action py-1 small" href="#" role="option"
								data-bind="event:{mousedown:(d,e)=>{e.preventDefault();$parent.applySuggestion('cc',$data);}},
									css:{active:$index()===$parent.suggestIndex()},
									text: $data.group ? $root.t('compose.group', {name: $data.group, n: $data.count}) : ($data.name ? $data.name + ' - ' : '') + $data.email"></a>
						</div>
					</div>
					<div class="mb-2 position-relative" style="display:none" data-bind="visible: showBcc() || !!bcc().length || !!bccText()">
						<div class="form-control form-control-sm addr-field d-flex flex-wrap align-items-center gap-1"
							data-bind="click: () => document.getElementById('compose-bcc-input').focus()">
							<!-- ko foreach: bcc -->
							<span class="addr-chip" data-bind="css:{'addr-chip-invalid':!!$data.invalid}, attr:{title:email}">
								<span data-bind="text: name || email"></span>
								<button type="button" class="addr-chip-x" title="<?= $t('common.delete') ?>"
									data-bind="click: () => $parent.removeChip('bcc', $data)"><i class="fa-solid fa-xmark"></i></button>
							</span>
							<!-- /ko -->
							<input id="compose-bcc-input" class="addr-input" type="text" autocomplete="off"
								placeholder="<?= $t('compose.bcc') ?>"
								data-bind="value: bccText, valueUpdate: 'input', event: {
									input: () => onAddrInput('bcc'),
									keydown: (d,e) => onAddrKey('bcc', e),
									blur: onAddrBlur }">
						</div>
						<div class="list-group compose-suggestions position-absolute w-100 shadow" style="z-index:1055" role="listbox"
							data-bind="foreach: suggestions, visible: suggestField()==='bcc' && suggestions().length">
							<a class="list-group-item list-group-item-action py-1 small" href="#" role="option"
								data-bind="event:{mousedown:(d,e)=>{e.preventDefault();$parent.applySuggestion('bcc',$data);}},
									css:{active:$index()===$parent.suggestIndex()},
									text: $data.group ? $root.t('compose.group', {name: $data.group, n: $data.count}) : ($data.name ? $data.name + ' - ' : '') + $data.email"></a>
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
						<li class="ms-auto d-flex align-items-end pb-1 gap-1">
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
					<span class="small text-muted me-auto" style="display:none"
						data-bind="visible: savedAt, text: savedAt() ? $root.t('compose.autosaved_at', { v: savedAt().toLocaleTimeString().slice(0,5) }) : ''"></span>
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

	<!-- Modale d'aperçu d'une pièce jointe (image, PDF, texte). -->
	<div class="modal fade" id="preview-modal" tabindex="-1" data-bind="with: messageView">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" data-bind="text: previewName"></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body text-center">
					<img class="img-fluid" style="max-height:75vh;display:none" alt=""
						data-bind="visible: previewType() === 'image', attr: { src: previewType() === 'image' ? previewUrl() : '' }">
					<iframe style="width:100%;height:75vh;border:0;display:none" title=""
						data-bind="visible: previewType() === 'pdf', attr: { src: previewType() === 'pdf' ? previewUrl() : '' }"></iframe>
					<pre class="font-monospace small mb-0 text-start" style="max-height:75vh;overflow:auto;display:none"
						data-bind="visible: previewType() === 'text', text: previewText"></pre>
					<!-- Aperçu .eml : en-têtes + corps sanitisé + PJ imbriquées. -->
					<div class="text-start" style="display:none"
						data-bind="visible: previewType() === 'eml'">
						<!-- ko if: previewEml() -->
						<div class="small text-muted border-bottom pb-1 mb-2" data-bind="with: previewEml">
							<div data-bind="visible: from && from.length">
								<strong class="text-body"><?= $t('app.from') ?></strong>
								<span data-bind="text: $parent.addrText(from)"></span></div>
							<div data-bind="visible: to && to.length">
								<strong class="text-body"><?= $t('app.to') ?></strong>
								<span data-bind="text: $parent.addrText(to)"></span></div>
							<div data-bind="visible: cc && cc.length">
								<strong class="text-body"><?= $t('app.cc') ?></strong>
								<span data-bind="text: $parent.addrText(cc)"></span></div>
							<div data-bind="visible: date">
								<span data-bind="text: new Date(date * 1000).toLocaleString($parent.app.lang)"></span></div>
						</div>
						<!-- /ko -->
						<div id="eml-preview-body" class="mail-body" style="max-height:60vh;overflow:auto"></div>
						<!-- ko if: previewEml() && previewEml().attachments.length -->
						<div class="d-flex flex-wrap gap-1 mt-2" data-bind="foreach: previewEml().attachments">
							<a class="btn btn-sm btn-outline-secondary"
								data-bind="attr: { href: $parent.emlPartUrl(n, 1) },
									text: name + (size ? ' (' + $parent.formatSize(size) + ')' : '')"></a>
							<!-- ko if: eml -->
							<button class="btn btn-sm btn-icon btn-outline-primary"
								data-bind="click: () => $parent.loadEml(sub), tooltip: 'app.preview'">
								<i class="fa-solid fa-magnifying-glass"></i>
							</button>
							<!-- /ko -->
						</div>
						<!-- /ko -->
					</div>
				</div>
				<div class="modal-footer">
					<a class="btn btn-sm btn-outline-primary" download
						data-bind="attr: { href: previewUrl() }"><i class="fa-solid fa-download"></i> <?= $t('common.download') ?></a>
				</div>
			</div>
		</div>
	</div>

	<!-- Modale des options de recherche : champs combinés en ET avec le texte libre.
	     Le brouillon vit dans les observables > conservé à la fermeture. -->
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
						<label class="d-flex align-items-center gap-1 mb-0 small" for="mx-search-scope">
							<?= $t('app.search.scope') ?>
							<select class="form-select form-select-sm w-auto" id="mx-search-scope" data-bind="value: searchScope">
								<option value="folder"><?= $t('app.search.scope_folder') ?></option>
								<option value="all"><?= $t('app.search.scope_all') ?></option>
							</select>
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
					<!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->
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

	<!-- Modale détails d'une session "se souvenir de moi" -->
	<div class="modal fade" id="session-modal" tabindex="-1" data-bind="with: settings">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content" data-bind="with: sessionDetail">
				<div class="modal-header">
					<h5 class="modal-title"><?= $t('settings.session_details') ?>
						<span class="badge text-bg-primary ms-1" style="display:none" data-bind="visible: current"><?= $t('settings.session_current') ?></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<dl class="row mb-0">
						<dt class="col-sm-4"><?= $t('settings.session_agent') ?></dt>
						<dd class="col-sm-8" data-bind="text: $parent.agentInfo(agent)"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_user_agent') ?></dt>
						<dd class="col-sm-8 small font-monospace text-break" data-bind="text: agent || '-'"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_created_ip') ?></dt>
						<dd class="col-sm-8" data-bind="text: ip || '-'"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_last_ip') ?></dt>
						<dd class="col-sm-8" data-bind="text: last_ip || ip || '-'"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_created') ?></dt>
						<dd class="col-sm-8" data-bind="text: $parent.fmtTs(created_at)"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_last_used') ?></dt>
						<dd class="col-sm-8" data-bind="text: last_used_at ? $parent.fmtTs(last_used_at) : '-'"></dd>
						<dt class="col-sm-4"><?= $t('settings.session_expires') ?></dt>
						<dd class="col-sm-8" data-bind="text: $parent.fmtTs(expires_at)"></dd>
					</dl>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-danger me-auto"
						data-bind="click: $parent.deleteSession"><?= $t('settings.session_revoke') ?></button>
					<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.close') ?></button>
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
						data-bind="tooltip: { text: 'settings.signature_caret_help', icon: 'fa-regular fa-bookmark' }"></i></label>
					<textarea id="signature-editor" data-bind="signatureEditor: signature"></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.cancel') ?></button>
					<button type="button" class="btn btn-sm btn-primary" data-bind="click: $parent.saveIdentity"><?= $t('common.save') ?></button>
				</div>
				<div class="modal-resize-grip" aria-hidden="true"
					data-bind="modalResize:
						{ w: $parent.identW, h: $parent.identH, minW: 480, minH: 400,
							save: $parent.saveIdentitySize },
						tooltip: 'app.resize'"></div>
			</div>
		</div>
	</div>

	<!-- Modale contact (ajout/édition) -->
	<div class="modal fade" id="contact-modal" tabindex="-1" data-bind="with: settings">
		<div class="modal-dialog">
			<div class="modal-content" data-bind="with: editingContact">
				<div class="modal-header">
					<h5 class="modal-title" data-bind="text: id
						? $root.t('settings.contact_edit')
						: $root.t('settings.contact_new')"></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-2"><input id="contact-name" class="form-control" placeholder="<?= $t('common.name') ?>" data-bind="value: name"></div>
					<div class="mb-2"><input class="form-control" placeholder="<?= $t('settings.emails_csv') ?>" data-bind="value: emails"></div>
					<div class="mb-2"><input class="form-control" placeholder="<?= $t('settings.groups_csv') ?>" data-bind="value: categories"></div>
					<div class="row g-2 mb-2">
						<div class="col-6"><input type="tel" class="form-control" placeholder="<?= $t('settings.contact_tel') ?>" data-bind="value: tel"></div>
						<div class="col-6"><input class="form-control" placeholder="<?= $t('settings.contact_org') ?>" data-bind="value: org"></div>
						<div class="col-6"><input class="form-control" placeholder="<?= $t('settings.contact_nick') ?>" data-bind="value: nick"></div>
						<div class="col-6"><input type="url" class="form-control" placeholder="<?= $t('settings.contact_url') ?>" data-bind="value: url"></div>
						<div class="col-6"><label class="form-label form-label-sm mb-1"><?= $t('settings.contact_bday') ?></label><input type="date" class="form-control" data-bind="value: bday"></div>
					</div>
					<div><textarea class="form-control" rows="2" placeholder="<?= $t('settings.contact_note') ?>" data-bind="value: note"></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= $t('common.cancel') ?></button>
					<button type="button" class="btn btn-sm btn-primary" data-bind="click: $parent.saveContact"><?= $t('common.save') ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- Aide raccourcis clavier (touche ?) -->
	<div class="modal fade" id="shortcuts-modal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><?= $t('app.shortcuts') ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<table class="table table-sm mb-0">
						<tbody>
							<tr><td class="text-nowrap"><kbd>↑</kbd> <kbd>↓</kbd> / <kbd>k</kbd> <kbd>j</kbd></td><td><?= $t('app.shortcuts.nav') ?></td></tr>
							<tr><td><kbd>x</kbd></td><td><?= $t('app.shortcuts.check') ?></td></tr>
							<tr><td><kbd><?= $t('app.shortcuts.key_delete') ?></kbd></td><td><?= $t('app.shortcuts.delete') ?></td></tr>
							<tr><td><kbd>r</kbd> / <kbd>a</kbd></td><td><?= $t('app.shortcuts.reply') ?></td></tr>
							<tr><td><kbd>f</kbd></td><td><?= $t('app.shortcuts.forward') ?></td></tr>
							<tr><td><kbd>e</kbd></td><td><?= $t('app.shortcuts.archive') ?></td></tr>
							<tr><td><kbd>!</kbd></td><td><?= $t('app.shortcuts.spam') ?></td></tr>
							<tr><td><kbd>s</kbd></td><td><?= $t('app.shortcuts.flag') ?></td></tr>
							<tr><td><kbd>u</kbd></td><td><?= $t('app.shortcuts.unread') ?></td></tr>
							<tr><td><kbd>m</kbd></td><td><?= $t('app.shortcuts.move') ?></td></tr>
							<tr><td><kbd>c</kbd></td><td><?= $t('app.shortcuts.compose') ?></td></tr>
							<tr><td><kbd>/</kbd></td><td><?= $t('app.shortcuts.search') ?></td></tr>
							<tr><td><kbd><?= $t('app.shortcuts.key_escape') ?></kbd></td><td><?= $t('app.shortcuts.escape') ?></td></tr>
							<tr><td><kbd>Ctrl</kbd>+<kbd><?= $t('app.shortcuts.key_enter') ?></kbd></td><td><?= $t('app.shortcuts.send') ?></td></tr>
							<tr><td><kbd>?</kbd></td><td><?= $t('app.shortcuts.help') ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script nonce="<?= $nonce ?>">
	window.MXWM = <?= $mxwmJson ?>;
</script>
<script src="<?= $i18nUrl ?>"></script>
<script src="<?= $assets ?>/vendor/knockout/knockout.js"></script>
<script src="<?= $assets ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script type="module" src="<?= $assets ?>/js/dist/app.js"></script>
</body>
</html>
