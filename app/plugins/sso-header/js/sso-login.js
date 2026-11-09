/**
 * SSO via proxy (plugin sso-header) : interroge /sso-header/whoami puis
 * soumet le login avec le mot de passe sentinelle "sso-t:<ticket>".
 * Garde : sortie immediate si le formulaire de login est absent (page
 * maintenance ou autre ecran sans champ email/password).
 * TOTP : si le compte SSO exige la 2FA (reponse totp_required), un bloc de
 * saisie autonome est affiche et le flux SSO est rejoue avec un nouveau
 * ticket + le code - JAMAIS de location.reload() (boucle infinie).
 */
(function () {
	const form = document.querySelector('form');
	const email = document.querySelector('input[type="email"], input[name="email"]');
	const pass = document.querySelector('input[type="password"]');
	if (!form || !email || !pass || !window.MXWM) { return; }
	const base = MXWM.base || '';
	const cfg = window.MXWM_SSOHEAD || {};
	let det = null;
	if (cfg.hide_form && form.parentNode) {
		det = document.createElement('details');
		det.className = 'mt-2';
		const sum = document.createElement('summary');
		sum.textContent = form.getAttribute('data-manual-label') || cfg.manual_label || 'Manual login';
		det.append(sum);
		form.parentNode.insertBefore(det, form);
		det.append(form);
	}
	// Repli manuel : ouvre le <details> qui masque le formulaire.
	const revealForm = () => { if (det) { det.open = true; } };

	const post = (body) => fetch(base + '/api/login', {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-Token': MXWM.csrf || '',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: JSON.stringify(body),
	}).then(r => r.json());

	const whoami = () => fetch(base + '/sso-header/whoami', { credentials: 'same-origin' })
		.then(r => r.json()).then(j => j.data || j);

	// Flux SSO complet : ticket frais (consomme a chaque tentative, d'ou le
	// nouvel appel whoami) + login sentinelle + champ totp accepte par le
	// endpoint /api/login. {s: ok|totp|fail|off, m: message serveur}.
	async function attempt(totp) {
		const d = await whoami();
		if (!d || !d.enabled || !d.email || !d.ticket) { return { s: 'off' }; }
		const j = await post({
			email: d.email, password: 'sso-t:' + d.ticket, totp: totp || '' });
		if (j.ok) { location.href = base + '/'; return { s: 'ok' }; }
		return {
			s: j.error && j.error.code === 'totp_required' ? 'totp' : 'fail',
			m: (j.error && j.error.message) || '',
		};
	}

	// Bloc TOTP autonome (hors Knockout - le VM de login n'est pas expose).
	// Libelles repris du DOM : pas de dictionnaire i18n cote cette page.
	function showTotp(errMsg) {
		const box = document.createElement('div');
		box.className = 'mt-3';
		const lab = document.createElement('label');
		lab.className = 'form-label small';
		lab.textContent = (document.querySelector('label[for="totp"]') || {}).textContent || 'Code';
		const inp = document.createElement('input');
		inp.type = 'text';
		inp.className = 'form-control';
		inp.maxLength = 9;
		inp.inputMode = 'numeric';
		inp.autocomplete = 'one-time-code';
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'btn btn-sm btn-primary mt-2';
		btn.textContent = (form.querySelector('button[type="submit"] span') || {}).textContent || 'OK';
		const msg = document.createElement('div');
		msg.className = 'text-danger small mt-1';
		msg.textContent = errMsg || '';
		box.append(lab, inp, btn, msg);
		const anchor = det || form;
		anchor.parentNode.insertBefore(box, anchor.nextSibling);
		inp.focus();
		const go = () => {
			msg.textContent = '';
			btn.disabled = true;
			attempt(inp.value.trim()).then(r => {
				btn.disabled = false;
				if (r.s === 'totp') {
					msg.textContent = r.m;
					inp.select();
				} else if (r.s !== 'ok') {
					box.remove();
					revealForm();
				}
			}).catch(() => { box.remove(); revealForm(); });
		};
		btn.addEventListener('click', go);
		inp.addEventListener('keydown', e => {
			if (e.key === 'Enter') { e.preventDefault(); go(); }
		});
	}

	attempt('').then(r => {
		if (r.s === 'totp') { showTotp(r.m); }
		else if (r.s !== 'ok') { revealForm(); }
	}).catch(revealForm);
})();
