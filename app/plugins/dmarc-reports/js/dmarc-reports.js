/** Page admin du plugin dmarc-reports (admin.mounts, section "page"). */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.dmarc-reports.';
	const call = (a, b) => ctx.api.post(`/admin/plugin/dmarc-reports/${a}`, b || {});
	const esc = s => String(s ?? '').replace(/[&<>"]/g,
		c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
	el.innerHTML = `<h5>${t(K + 'tab')}</h5>
		<div class="card mb-3"><div class="card-body">
			<p class="text-muted small">${t(K + 'intro')}</p>
			<div id="dm-status" class="small"></div>
			<div class="d-flex gap-2 mt-2">
				<button id="dm-fetch" class="btn btn-sm btn-primary">${t(K + 'fetch')}</button>
				<button id="dm-reset" class="btn btn-sm btn-outline-danger">${t(K + 'reset')}</button>
			</div>
			<div id="dm-fetch-info" class="small text-muted mt-2"></div>
		</div></div>
		<div class="card"><div class="card-body">
			<div class="btn-group btn-group-sm mb-2">
				<button class="btn btn-outline-secondary active" data-g="domain">${t(K + 'by_domain')}</button>
				<button class="btn btn-outline-secondary" data-g="source_ip">${t(K + 'by_ip')}</button>
			</div>
			<table class="table table-sm table-hover mb-0"><thead><tr>
				<th id="dm-th"></th><th class="text-end">${t(K + 'col_count')}</th>
				<th class="text-end">${t(K + 'col_records')}</th><th class="text-end">${t(K + 'col_last')}</th>
			</tr></thead><tbody id="dm-rows"></tbody></table>
		</div></div>`;
	const $ = id => el.querySelector('#' + id);
	const info = async () => {
		const s = await call('status');
		$('dm-status').textContent = s.configured
			? t(K + 'status_ok', { r: s.reports, n: s.rows })
			: t(K + 'status_unconfigured');
	};
	const load = async g => {
		const r = await ctx.api.get(`/admin/plugin/dmarc-reports/list?group=${g}`);
		$('dm-th').textContent = g === 'domain' ? t(K + 'by_domain') : t(K + 'by_ip');
		$('dm-rows').innerHTML = (r.rows || []).map(row =>
			`<tr><td>${esc(row.k)}</td><td class="text-end">${row.c}</td>` +
			`<td class="text-end">${row.n}</td>` +
			`<td class="text-end">${row.last ? new Date(row.last * 1000).toLocaleDateString() : ''}</td></tr>`
		).join('') || `<tr><td colspan="4" class="text-muted">${t(K + 'empty')}</td></tr>`;
	};
	$('dm-fetch').addEventListener('click', async () => {
		let total = 0, skipped = 0;
		try {
			// Borne : jamais de boucle infinie, meme si un lot se repete
			// (ceinture - le backend recherche UNSEEN desormais).
			for (let i = 0; i < 100; i++) {
				const r = await call('fetch');
				total += r.fetched;
				skipped += r.skipped;
				$('dm-fetch-info').textContent = t(K + 'fetched', { n: total, s: skipped });
				if (!r.fetched) { break; }
			}
		} catch (e) { ctx.toast.error(e.message); }
		info(); load('domain');
	});
	$('dm-reset').addEventListener('click', async () => {
		if (await ctx.dialog.confirm(t(K + 'reset_confirm'), { variant: 'danger' })) {
			await call('reset'); info(); load('domain');
		}
	});
	el.querySelectorAll('[data-g]').forEach(b => b.addEventListener('click', () => {
		el.querySelectorAll('[data-g]').forEach(x => x.classList.toggle('active', x === b));
		load(b.dataset.g);
	}));
	info(); load('domain');
}
