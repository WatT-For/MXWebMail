/**
 * Onglet Paramètres du plugin carddav-contacts.
 * Chargé dynamiquement par le binding pluginMount.
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.carddav-contacts.';
	el.innerHTML = `
		<div class="card" style="max-width:560px">
			<div class="card-body">
				<h6>${t(K + 'title')}</h6>
				<p class="text-muted small">${t(K + 'help')}</p>
				<div class="mb-2">
					<label class="form-label small">${t(K + 'url')} <span class="text-muted">${t(K + 'url_hint')}</span></label>
					<input id="cd-url" class="form-control form-control-sm" placeholder="https://…">
				</div>
				<div class="row g-2 mb-2">
					<div class="col">
						<label class="form-label small">${t(K + 'user')}</label>
						<input id="cd-user" class="form-control form-control-sm">
					</div>
					<div class="col">
						<label class="form-label small">${t(K + 'password')} <span class="text-muted">${t(K + 'password_hint')}</span></label>
						<input id="cd-pass" type="password" class="form-control form-control-sm" autocomplete="new-password">
					</div>
				</div>
				<div class="d-flex gap-2 align-items-center">
					<button id="cd-save" class="btn btn-sm btn-primary">${t('common.save')}</button>
					<button id="cd-test" class="btn btn-sm btn-outline-secondary">${t('common.test')}</button>
					<button id="cd-sync" class="btn btn-sm btn-outline-success">${t(K + 'sync')}</button>
					<button id="cd-clear" class="btn btn-sm btn-outline-danger ms-auto" style="display:none">${t(K + 'pass_clear')}</button>
				</div>
				<div id="cd-status" class="text-muted small mt-2"></div>
				<div id="cd-error" class="text-danger small mt-1"></div>
			</div>
		</div>`;

	const $ = (id) => el.querySelector('#' + id);
	const call = (action, body) => ctx.api.post(`/plugin/carddav-contacts/${action}`, body || {});
	const err = (m) => { $('cd-error').textContent = m || ''; };
	const status = (m) => { $('cd-status').textContent = m || ''; };

	async function load() {
		const c = await call('config').catch(e => { err(e.message); return null; });
		if (!c) { return; }
		$('cd-url').value = c.url || '';
		$('cd-user').value = c.user || '';
		$('cd-clear').style.display = c.hasPass ? '' : 'none';
		status(c.lastSync
			? t(K + 'last_sync', { date: new Date(c.lastSync * 1000).toLocaleString() })
			: t(K + 'never_synced'));
	}

	$('cd-save').onclick = async () => {
		err('');
		await call('save', {
			url: $('cd-url').value, user: $('cd-user').value, password: $('cd-pass').value,
		}).then(() => status(t(K + 'saved'))).catch(e => err(e.message));
	};

	$('cd-clear').onclick = async () => {
		err('');
		const r = await call('save', {
			url: $('cd-url').value, user: $('cd-user').value, password_reset: true,
		}).then(() => true).catch(e => { err(e.message); return false; });
		if (r) { $('cd-clear').style.display = 'none'; status(t(K + 'saved')); }
	};

	$('cd-test').onclick = async () => {
		err(''); status(t(K + 'testing'));
		const r = await call('test').catch(e => { status(''); err(e.message); return null; });
		if (r) { status(t(K + 'test_ok', { book: r.addressbook, n: r.cards })); }
	};

	$('cd-sync').onclick = async () => {
		err(''); status(t(K + 'syncing'));
		const r = await call('sync').catch(e => { status(''); err(e.message); return null; });
		if (r) { status(t(K + 'imported', { n: r.synced })); }
	};

	load();
}
