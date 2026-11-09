/** Onglet Parametres du plugin login-notify : opt-out de l'alerte. */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.login-notify.';
	const call = (a, b) => ctx.api.post(`/plugin/login-notify/${a}`, b || {});
	el.innerHTML =
		`<h5>${t(K + 'tab')}</h5><div class="card"><div class="card-body">
		<div class="form-check"><input class="form-check-input" type="checkbox" id="ln-on">
		<label class="form-check-label" for="ln-on">${t(K + 'enabled')}</label></div>
		<p class="text-muted small mt-2 mb-0">${t(K + 'hint')}</p>
		</div></div>`;
	const cb = el.querySelector('#ln-on');
	ctx.api.get('/plugin/login-notify/status')
		.then(r => { cb.checked = !!r.enabled; });
	cb.addEventListener('change', () => {
		call('set', { enabled: cb.checked })
			.then(() => ctx.toast.success(t('settings.saved')))
			.catch(e => { cb.checked = !cb.checked; ctx.toast.error(e.message); });
	});
}
