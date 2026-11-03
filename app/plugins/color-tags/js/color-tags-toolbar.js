/**
 * Bouton "Étiquettes" de la barre d'actions groupées (plugin color-tags).
 * Monté via pluginMount dans le slot messageToolbar. La liste des couleurs
 * (noms définis par l'utilisateur) vient de /api/plugin/color-tags/state.
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.color-tags.';
	let state = null; // cache apiState

	const btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'btn btn-sm btn-icon btn-outline-secondary';
	btn.dataset.bsToggle = 'dropdown';
	btn.title = t(K + 'action');
	const icon = document.createElement('i');
	icon.className = 'fa-solid fa-tag';
	btn.append(icon);
	const menu = document.createElement('ul');
	menu.className = 'dropdown-menu dropdown-menu-end';
	el.append(btn, menu);

	// Désactivé tant qu'aucun mail coché (isChecked observables > réactif)
	if (window.ko && ctx.list) {
		const c = ko.computed(() => ctx.list.checked().length === 0);
		c.subscribe(v => { btn.disabled = v; });
		btn.disabled = c();
	}

	const call = (action, body) => ctx.api.post(`/plugin/color-tags/${action}`, body || {});

	function item(text, color, onClick) {
		const li = document.createElement('li');
		const a = document.createElement('a');
		a.className = 'dropdown-item';
		a.href = '#';
		if (color) {
			const s = document.createElement('span');
			s.className = 'me-2 d-inline-block border rounded-1';
			s.style.cssText = 'width:.8em;height:.8em;background:' + color;
			a.append(s);
		}
		// textContent : le nom est une donnée utilisateur, jamais de HTML
		a.append(document.createTextNode(text));
		if (onClick) {
			a.addEventListener('click', e => { e.preventDefault(); onClick(); });
		}
		li.append(a);
		return li;
	}

	async function buildMenu() {
		menu.replaceChildren();
		if (!state) {
			state = await call('state');
		}
		if (state.manual === false) {
			const li = document.createElement('li');
			const span = document.createElement('span');
			span.className = 'dropdown-item-text text-muted small';
			span.textContent = t(K + 'manual_unsupported');
			li.append(span);
			menu.append(li);
			return;
		}
		for (const p of state.palette) {
			menu.append(item(p.label, p.color, () => apply(p.id)));
		}
		const div = document.createElement('li');
		const hr = document.createElement('hr');
		hr.className = 'dropdown-divider';
		div.append(hr);
		menu.append(div);
		menu.append(item(t(K + 'none'), null, () => apply(null)));
	}

	async function apply(tag) {
		const checked = ctx.list ? ctx.list.checked() : [];
		if (!checked.length) { return; }
		try {
			const r = await call('tag', {
				folder: ctx.list.folder(),
				uids: checked.map(m => m.uid),
				tag,
			});
			for (const m of checked) {
				m.colorTag(r.color || null);
				m.colorTagId = r.tag;
				m.colorTagLabel = r.label || null;
			}
		} catch (e) {
			await ctx.dialog.alert(e.message, { title: t(K + 'action'), variant: 'danger' });
		}
	}

	btn.addEventListener('show.bs.dropdown', () => { buildMenu().catch(() => {}); });
}
