/**
 * Toasts Bootstrap — notifications éphémères (remplacement des alertes
 * inline). Conteneur position-fixed créé à la demande en haut à droite ;
 * chaque toast s'autodétruit à hidden.bs.toast.
 *
 * toast.show(message, opts) → void
 * opts : { variant: info|success|warning|danger, delay: 5000, title }
 *
 * Aucune référence à `bootstrap` au top-level : les tests jsdom n'ont
 * qu'un stub Modal — usage uniquement dans show().
 */
const ICONS = {
	info:    'fa-circle-info',
	success: 'fa-circle-check',
	warning: 'fa-triangle-exclamation',
	danger:  'fa-triangle-exclamation',
};

let container = null;

function holder() {
	if (!container || !container.isConnected) {
		container = document.createElement('div');
		container.className = 'toast-container position-fixed top-0 end-0 p-3';
		document.body.append(container);
	}
	return container;
}

export const toast = {
	show(message, { variant = 'info', delay = 5000, title = '' } = {}) {
		const el = document.createElement('div');
		el.className = `toast align-items-center text-bg-${variant} border-0`;
		el.setAttribute('role', 'status');
		const flex = document.createElement('div');
		flex.className = 'd-flex';
		const body = document.createElement('div');
		body.className = 'toast-body';
		body.style.whiteSpace = 'pre-wrap';
		const icon = document.createElement('i');
		icon.className = `fa-solid ${ICONS[variant] || ICONS.info} me-2`;
		body.append(icon);
		if (title) {
			const strong = document.createElement('strong');
			strong.className = 'me-1';
			strong.textContent = title;
			body.append(strong);
		}
		body.append(document.createTextNode(message));
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'btn-close btn-close-white me-2 m-auto';
		close.setAttribute('data-bs-dismiss', 'toast');
		close.setAttribute('aria-label', 'Close');
		flex.append(body, close);
		el.append(flex);
		holder().append(el);
		el.addEventListener('hidden.bs.toast', () => el.remove());
		new bootstrap.Toast(el, { delay }).show();
	},
	success: (m, o) => toast.show(m, { ...o, variant: 'success' }),
	info:    (m, o) => toast.show(m, { ...o, variant: 'info' }),
	warning: (m, o) => toast.show(m, { ...o, variant: 'warning' }),
	error:   (m, o) => toast.show(m, { ...o, variant: 'danger' }),
};
