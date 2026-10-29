/** Page admin « Statistiques » du plugin admin-stats. */
export default async function mount(el, ctx) {
	const esc = s => String(s).replace(/[&<>"]/g,
		c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
	const mb = b => (b / 1048576).toFixed(1) + ' Mo';
	const table = (rows, cols) =>
		'<table class="table table-sm"><thead><tr>' +
		cols.map(c => `<th>${esc(c)}</th>`).join('') + '</tr></thead><tbody>' +
		rows.map(r => '<tr>' + r.map(v => `<td>${esc(v)}</td>`).join('') + '</tr>').join('') +
		'</tbody></table>';
	try {
		const r = await ctx.api.post('/admin/plugin/admin-stats/stats');
		const t = k => ctx.t('plugin.admin-stats.' + k);
		el.innerHTML =
			`<h6>${t('env')}</h6>` + table(
				[['mxwebmail', r.env.app_version], ['PHP', r.env.php],
				 ['Base', r.env.db_driver]], [t('label'), t('value')]) +
			`<h6>${t('users')}</h6>` + table(
				[[t('total'), r.users.total], [t('active30'), r.users.active30],
				 [t('disabled'), r.users.disabled]], [t('label'), t('value')]) +
			`<h6>${t('storage')}</h6>` + table(
				r.storage.map(s => [s.name, mb(s.bytes)]), [t('dir'), t('size')]) +
			`<h6>${t('tables')}</h6>` + table(
				r.tables.map(s => [s.name, s.rows]), [t('table'), t('rows')]) +
			`<h6>${t('orphans')}</h6>` + table(
				r.orphans.map(s => [s.name, s.rows]), [t('table'), t('orphan_rows')]) +
			`<h6>${t('plugins')}</h6>` + table(
				r.plugins.map(p => [p.name, p.version,
					p.enabled ? '✓' : '—', p.user_allowed ? '✓' : '—']),
				[t('plugin'), t('version'), t('enabled'), t('user_allowed')]);
	} catch (e) {
		el.innerHTML = `<div class="alert alert-danger">${esc(e.message)}</div>`;
	}
}
