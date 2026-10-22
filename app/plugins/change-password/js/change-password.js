/**
 * Onglet Paramètres du plugin change-password.
 * Récupère les contraintes (longueur/force minimales) via l'API du plugin
 * et affiche une jauge de robustesse côté client.
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.change-password.';
	el.innerHTML = `
		<div class="card" style="max-width:480px">
			<div class="card-body">
				<h6>${t(K + 'title')}</h6>
				<div class="mb-2"><input id="pw-old" type="password" class="form-control form-control-sm" placeholder="${t(K + 'current')}" autocomplete="current-password"></div>
				<div class="mb-1"><input id="pw-new" type="password" class="form-control form-control-sm" placeholder="${t(K + 'new')}" autocomplete="new-password"></div>
				<div class="progress mb-2" style="height:4px"><div id="pw-strength" class="progress-bar" style="width:0%"></div></div>
				<div class="mb-3"><input id="pw-confirm" type="password" class="form-control form-control-sm" placeholder="${t(K + 'confirm')}" autocomplete="new-password"></div>
				<button id="pw-submit" class="btn btn-sm btn-primary">${t(K + 'change')}</button>
				<div id="pw-msg" class="small mt-2"></div>
			</div>
		</div>`;

	const $ = (id) => el.querySelector('#' + id);
	const msg = (m, ok) => {
		$('pw-msg').textContent = m || '';
		$('pw-msg').className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
	};

	// Contraintes dynamiques depuis la config du plugin
	ctx.api.post('/plugin/change-password/config', {}).then(cfg => {
		$('pw-new').placeholder = t(K + 'new_hint', { len: cfg.minLength, strength: cfg.minStrength });
	}).catch(() => {});

	// Jauge : même algorithme que le backend
	const strength = (p) => {
		let i = p.length, s = 0;
		const max = Math.min(100, i * 8);
		while (--i > 0) { s += (p[i] !== p[i - 1] ? 1 : -0.5); }
		let c = 0;
		for (const re of [/[^0-9A-Za-z]+/g, /[0-9]+/g, /[A-Z]+/g, /[a-z]+/g]) {
			const m = p.match(re);
			if (m) { ++c; for (const str of m) { if (str.length < 5) { ++s; } } }
		}
		return Math.max(0, Math.min(max, Math.round(s * c * 1.5)));
	};
	$('pw-new').oninput = () => {
		const v = strength($('pw-new').value);
		const bar = $('pw-strength');
		bar.style.width = v + '%';
		bar.className = 'progress-bar ' + (v >= 70 ? 'bg-success' : v >= 40 ? 'bg-warning' : 'bg-danger');
	};

	$('pw-submit').onclick = async () => {
		msg('');
		if ($('pw-new').value !== $('pw-confirm').value) {
			msg(t(K + 'mismatch'));
			return;
		}
		try {
			await ctx.api.post('/password/change', {
				old: $('pw-old').value, new: $('pw-new').value,
			});
			msg(t(K + 'changed'), true);
			$('pw-old').value = $('pw-new').value = $('pw-confirm').value = '';
			$('pw-strength').style.width = '0%';
		} catch (e) {
			msg(e.message);
		}
	};
}
