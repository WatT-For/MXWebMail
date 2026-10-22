import { api, base } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { t } from '../i18n.js';

/**
 * Binding : monte le module JS d'un plugin dans l'élément.
 * Usage : <div data-bind="pluginMount: { js: 'two-factor.js', plugin: 'two-factor-auth' }"></div>
 * Le module reçoit (element, { api, base, user, dialog, t, list }).
 */
ko.bindingHandlers.pluginMount = {
	init(element, valueAccessor) {
		const opts = ko.unwrap(valueAccessor()) || {};
		const src = `${base()}/assets/plugin/${opts.plugin}/${opts.js}`;
		import(src)
			.then(mod => (mod.default || mod.mount)?.(element, {
				api, base: base(), user: opts.user || null, dialog, t,
				list: window.mxList || null,
				// Contexte admin : le module préfixe ses appels /admin/plugin/*
				admin: !!opts.admin,
			}))
			.catch(e => {
				const div = document.createElement('div');
				div.className = 'alert alert-warning';
				div.textContent = t('errors.plugin', { name: opts.plugin, msg: e.message });
				element.replaceChildren(div);
			});
	},
};
