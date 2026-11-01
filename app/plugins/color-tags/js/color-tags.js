/**
 * Onglet Paramètres du plugin color-tags : deux sous-onglets (palette de
 * couleurs / règles automatiques — sujet / expéditeur / domaine, wildcard
 * « * ») avec deep-link #/@settings/color-tags/{palette|rules}.
 * Chargé dynamiquement par le binding pluginMount.
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.color-tags.';
	const FIELDS = ['from', 'domain', 'subject'];
	let palette = [];
	let rules = [];
	let sub = 'palette';

	el.innerHTML = `
		<div style="max-width:720px">
			<div class="alert alert-warning small" id="ct-manual" style="display:none"></div>
			<ul class="nav nav-tabs mb-3">
				<li class="nav-item"><a class="nav-link" id="ct-tab-palette" href="#/@settings/color-tags/palette">${t(K + 'palette')}</a></li>
				<li class="nav-item"><a class="nav-link" id="ct-tab-rules" href="#/@settings/color-tags/rules">${t(K + 'rules')}</a></li>
			</ul>
			<div id="ct-pane-palette">
				<table class="table table-sm align-middle">
					<thead class="table-light"><tr>
						<th style="width:46px"></th>
						<th>${t(K + 'col_name')}</th>
						<th style="width:150px">${t(K + 'col_id')}</th>
						<th style="width:40px"></th>
					</tr></thead>
					<tbody id="ct-palette"></tbody>
				</table>
				<button class="btn btn-sm btn-outline-secondary" id="ct-add-pal"><i class="fa-solid fa-plus"></i> ${t('common.add')}</button>
			</div>
			<div id="ct-pane-rules">
				<p class="text-muted small">${t(K + 'rules_help')}</p>
				<table class="table table-sm align-middle" id="ct-rules-table">
					<thead class="table-light"><tr>
						<th style="width:150px"></th>
						<th>${t(K + 'pattern')}</th>
						<th style="width:170px">${t(K + 'tag')}</th>
						<th style="width:96px"></th>
					</tr></thead>
					<tbody id="ct-rules"></tbody>
				</table>
				<button class="btn btn-sm btn-outline-secondary" id="ct-add-rule"><i class="fa-solid fa-plus"></i> ${t('common.add')}</button>
			</div>
			<div class="mt-3">
				<button class="btn btn-sm btn-primary" id="ct-save">${t('common.save')}</button>
			</div>
			<p class="text-muted small mt-3">${t(K + 'help')}</p>
		</div>`;

	const $ = id => el.querySelector('#' + id);

	const subFromHash = () =>
		location.hash === '#/@settings/color-tags/rules' ? 'rules' : 'palette';

	function renderTabs() {
		$('ct-tab-palette').classList.toggle('active', sub === 'palette');
		$('ct-tab-rules').classList.toggle('active', sub === 'rules');
		$('ct-pane-palette').style.display = sub === 'palette' ? '' : 'none';
		$('ct-pane-rules').style.display = sub === 'rules' ? '' : 'none';
	}

	// Les <a> font la navigation hash native ; activate() côté cœur conserve
	// tab==='color-tags' et ignore le détail — la synchro du sous-onglet se
	// fait ici. Auto-nettoyage : le plugin est remonté à chaque entrée dans
	// Paramètres (foreach pluginTabs), le listener d'un div détaché se retire.
	const onHash = () => {
		if (!el.isConnected) {
			window.removeEventListener('hashchange', onHash);
			return;
		}
		if (!location.hash.startsWith('#/@settings/color-tags')) { return; }
		const s = subFromHash();
		if (s !== sub) { sub = s; renderTabs(); }
	};
	window.addEventListener('hashchange', onHash);

	sub = subFromHash();
	renderTabs();
	const call = (a, b) => ctx.api.post(`/plugin/color-tags/${a}`, b || {});
	const msg = (m, ok) => { if (m) { (ok ? ctx.toast.success : ctx.toast.error)(m); } };

	const slug = s => String(s).toLowerCase().normalize('NFD')
		.replace(/[̀-ͯ]/g, '')
		.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 32) || 'tag';

	function mkBtn(icon, fn, cls = 'btn-outline-secondary') {
		const b = document.createElement('button');
		b.type = 'button';
		b.className = `btn btn-sm btn-icon ${cls}`;
		const i = document.createElement('i');
		i.className = `fa-solid ${icon}`;
		b.append(i);
		b.addEventListener('click', fn);
		return b;
	}

	function renderPalette() {
		const tb = $('ct-palette');
		tb.replaceChildren();
		palette.forEach((p, i) => {
			const tr = document.createElement('tr');

			const tdC = document.createElement('td');
			const inp = document.createElement('input');
			inp.type = 'color';
			inp.className = 'form-control form-control-color form-control-sm';
			inp.value = p.color;
			inp.addEventListener('input', () => { p.color = inp.value; });
			tdC.append(inp);

			const tdL = document.createElement('td');
			const nl = document.createElement('input');
			nl.className = 'form-control form-control-sm';
			nl.value = p.label;
			nl.addEventListener('input', () => { p.label = nl.value; });
			tdL.append(nl);

			const tdI = document.createElement('td');
			const ni = document.createElement('input');
			ni.className = 'form-control form-control-sm font-monospace';
			ni.value = p.id;
			// Au blur : slugifie et reporte le nouvel id sur les règles liées
			ni.addEventListener('change', () => {
				const old = p.id;
				p.id = slug(ni.value);
				ni.value = p.id;
				rules.forEach(r => { if (r.tag === old) { r.tag = p.id; } });
				renderRules();
			});
			tdI.append(ni);

			const tdD = document.createElement('td');
			tdD.className = 'text-end';
			tdD.append(mkBtn('fa-trash', () => {
				palette.splice(i, 1);
				renderPalette();
				renderRules();
			}, 'btn-outline-danger'));

			tr.append(tdC, tdL, tdI, tdD);
			tb.append(tr);
		});
	}

	function renderRules() {
		const tb = $('ct-rules');
		tb.replaceChildren();
		rules.forEach((r, i) => {
			const tr = document.createElement('tr');

			const tdF = document.createElement('td');
			const fs = document.createElement('select');
			fs.className = 'form-select form-select-sm';
			for (const f of FIELDS) {
				const o = document.createElement('option');
				o.value = f;
				o.textContent = t(K + 'field_' + f);
				if (r.field === f) { o.selected = true; }
				fs.append(o);
			}
			fs.addEventListener('change', () => { r.field = fs.value; });
			tdF.append(fs);

			const tdP = document.createElement('td');
			const pi = document.createElement('input');
			pi.className = 'form-control form-control-sm';
			pi.value = r.pattern;
			pi.placeholder = '*@exemple.com';
			pi.addEventListener('input', () => { r.pattern = pi.value; });
			tdP.append(pi);

			const tdT = document.createElement('td');
			const ts = document.createElement('select');
			ts.className = 'form-select form-select-sm';
			for (const p of palette) {
				const o = document.createElement('option');
				o.value = p.id;
				o.textContent = p.label;
				if (r.tag === p.id) { o.selected = true; }
				ts.append(o);
			}
			ts.addEventListener('change', () => { r.tag = ts.value; });
			tdT.append(ts);

			const tdA = document.createElement('td');
			tdA.className = 'text-end text-nowrap';
			tdA.append(
				mkBtn('fa-arrow-up', () => {
					if (i > 0) { [rules[i - 1], rules[i]] = [rules[i], rules[i - 1]]; renderRules(); }
				}),
				mkBtn('fa-arrow-down', () => {
					if (i < rules.length - 1) { [rules[i + 1], rules[i]] = [rules[i], rules[i + 1]]; renderRules(); }
				}),
				mkBtn('fa-trash', () => { rules.splice(i, 1); renderRules(); }, 'btn-outline-danger'));

			tr.append(tdF, tdP, tdT, tdA);
			tb.append(tr);
		});
	}

	$('ct-add-pal').addEventListener('click', () => {
		palette.push({ id: slug(t(K + 'new_tag')), label: t(K + 'new_tag'), color: '#607d8b' });
		renderPalette();
		renderRules();
	});
	$('ct-add-rule').addEventListener('click', () => {
		rules.push({ field: 'from', pattern: '', tag: palette[0]?.id || '' });
		renderRules();
	});
	$('ct-save').addEventListener('click', async () => {
		msg('');
		try {
			const r = await call('save', { palette, rules });
			palette = r.palette;
			rules = r.rules;
			renderPalette();
			renderRules();
			msg(t('settings.saved'), true);
		} catch (e) {
			msg(e.message, false);
		}
	});

	call('state').then(s => {
		palette = s.palette;
		rules = s.rules;
		if (s.manual === false) {
			const a = $('ct-manual');
			a.textContent = t(K + 'manual_unsupported');
			a.style.display = '';
		}
		renderPalette();
		renderRules();
	}).catch(e => msg(e.message, false));
}
