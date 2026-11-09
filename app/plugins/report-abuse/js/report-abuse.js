/**
 * Bouton "Signaler" de la barre d'actions groupees (plugin report-abuse).
 * Transmet les mails coches {folder, uid} au endpoint qui les transfere
 * en .eml a l'adresse abuse configuree. Pas de data-bs-toggle : un seul
 * composant Bootstrap par element - titre natif, pas de binding tooltip.
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.report-abuse.';
	const btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'btn btn-sm btn-icon btn-outline-secondary';
	btn.title = t(K + 'action');
	const icon = document.createElement('i');
	icon.className = 'fa-solid fa-flag';
	btn.append(icon);
	el.append(btn);

	if (window.ko && ctx.list) {
		const c = ko.computed(() => ctx.list.checked().length === 0);
		c.subscribe(v => { btn.disabled = v; });
		btn.disabled = c();
	}

	btn.addEventListener('click', async () => {
		const items = (ctx.list.checked() || [])
			.map(m => ({ folder: m.folder, uid: m.uid }));
		if (!items.length) { return; }
		if (!await ctx.dialog.confirm(
			t(K + 'confirm', { n: items.length }),
			{ title: t(K + 'action'), variant: 'warning' })) {
			return;
		}
		try {
			const r = await ctx.api.post('/plugin/report-abuse/report', { items });
			ctx.toast.success(r.failed
				? t(K + 'done_partial', { n: r.reported, f: r.failed })
				: t(K + 'done', { n: r.reported }));
			ctx.list.reload();
		} catch (e) {
			ctx.toast.error(e.message);
		}
	});
}
