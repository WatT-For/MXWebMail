/**
 * Onglet Paramètres du plugin two-factor-auth.
 * Chargé dynamiquement par le binding pluginMount.
 * ctx.admin = monté depuis le panneau admin (compte local, API
 * /api/admin/plugin/*) ; sinon Paramètres utilisateur (API /api/plugin/*).
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.two-factor-auth.';
	const admin = !!ctx.admin;
	el.innerHTML = `
		<div class="card" style="max-width:540px">
			<div class="card-body">
				<h6>${t(admin ? K + 'admin_title' : K + 'title')}</h6>
				<p class="text-muted small" id="tfa-status">…</p>
				<p class="text-warning small" id="tfa-note" style="display:none"></p>
				<div id="tfa-setup" style="display:none">
					<canvas id="tfa-qr" width="220" height="220" class="border"></canvas>
					<p class="small mt-2">${t(K + 'secret')} <code id="tfa-secret"></code></p>
				</div>
				<div class="input-group input-group-sm mt-2" style="max-width:260px">
					<input id="tfa-code" class="form-control" placeholder="${t(K + 'code_placeholder')}" maxlength="6" inputmode="numeric">
					<button id="tfa-verify" class="btn btn-sm btn-primary">${t(K + 'verify')}</button>
				</div>
				<div class="mt-3">
					<button id="tfa-enable" class="btn btn-sm btn-primary" style="display:none">${t(K + 'generate')}</button>
					<button id="tfa-disable" class="btn btn-sm btn-outline-danger" style="display:none">${t(K + 'disable')}</button>
				</div>
				<div id="tfa-recovery" class="mt-3" style="display:none">
					<h6>${t(K + 'recovery_title')}</h6>
					<p class="text-muted small" id="tfa-recovery-count"></p>
					<div id="tfa-recovery-codes" class="font-monospace small border rounded p-2 mb-2" style="display:none;column-count:2;max-width:300px"></div>
					<button id="tfa-regen" class="btn btn-sm btn-outline-secondary">${t(K + 'recovery_regen')}</button>
				</div>
				<div id="tfa-error" class="text-danger small mt-2"></div>
			</div>
		</div>`;

	const $ = (id) => el.querySelector('#' + id);
	const call = (action, body) => ctx.api.post(
		(admin ? '/admin/plugin/' : '/plugin/') + `two-factor-auth/${action}`, body || {});
	const err = (m) => { $('tfa-error').textContent = m || ''; };

	async function refresh() {
		const st = await call('status');
		$('tfa-status').textContent = st.enabled
			? t(K + 'status_on')
			: t(K + 'status_off');
		// Contexte admin : le TOTP ne s'applique qu'au compte admin local
		// (admin_user + admin_password_hash) et l'écriture exige config/ writable.
		const note = $('tfa-note');
		if (admin) {
			note.textContent = !st.writable ? t(K + 'admin_config_readonly')
				: !st.local ? t(K + 'admin_no_local')
				: t(K + 'admin_local_only');
			note.style.display = '';
		}
		$('tfa-enable').style.display = st.enabled ? 'none' : '';
		$('tfa-disable').style.display = st.enabled ? '' : 'none';
		$('tfa-setup').style.display = 'none';
		// Codes de secours : compte utilisateur uniquement (admin = secret
		// en config.php, pas de recovery) ; codes jamais re-lisibles.
		const recov = $('tfa-recovery');
		if (admin) {
			recov.style.display = 'none';
		} else {
			recov.style.display = st.enabled ? '' : 'none';
			$('tfa-recovery-count').textContent =
				t(K + 'recovery_left', { v: st.recovery || 0 });
			$('tfa-recovery-codes').style.display = 'none';
		}
	}

	function showRecovery(codes) {
		const box = $('tfa-recovery-codes');
		box.textContent = '';
		for (const c of codes || []) {
			const div = document.createElement('div');
			div.textContent = c;
			box.appendChild(div);
		}
		box.style.display = codes && codes.length ? '' : 'none';
		$('tfa-recovery').style.display = '';
	}

	function drawQr(matrix) {
		const canvas = $('tfa-qr'), ctx2 = canvas.getContext('2d');
		const n = matrix.length, scale = Math.floor(canvas.width / (n + 8));
		const offX = Math.floor((canvas.width - (n + 8) * scale) / 2);
		const offY = Math.floor((canvas.height - (n + 8) * scale) / 2);
		ctx2.fillStyle = '#fff'; ctx2.fillRect(0, 0, canvas.width, canvas.height);
		ctx2.fillStyle = '#000';
		matrix.forEach((row, r) => row.forEach((dark, c) => {
			if (dark) ctx2.fillRect(offX + (c + 4) * scale, offY + (r + 4) * scale, scale, scale);
		}));
	}

	$('tfa-enable').onclick = async () => {
		err('');
		const s = await call('setup').catch(e => { err(e.message); return null; });
		if (!s) return;
		$('tfa-secret').textContent = s.secret;
		$('tfa-setup').style.display = '';
		drawQr(s.qr);
	};

	$('tfa-verify').onclick = async () => {
		err('');
		const code = $('tfa-code').value.trim();
		if ($('tfa-setup').style.display !== 'none') {
			const r = await call('enable', { code }).catch(e => { err(e.message); return null; });
			if (r) { await refresh(); showRecovery(r.recovery); }
		} else {
			const r = await call('disable', { code }).catch(e => { err(e.message); return null; });
			if (r) { await refresh(); }
		}
	};

	$('tfa-regen').onclick = async () => {
		err('');
		const r = await call('recovery').catch(e => { err(e.message); return null; });
		if (r) { await refresh(); showRecovery(r.recovery); }
	};

	$('tfa-disable').onclick = () => {
		err(t(K + 'disable_hint'));
	};

	refresh().catch(e => err(e.message));
}
