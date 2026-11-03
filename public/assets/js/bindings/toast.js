import { toast } from '../ui/toast.js';
import { t } from '../i18n.js';

/**
 * Binding : émet un toast quand l'observable devient truthy.
 * Usage : <!-- ko toast: { value: error, variant: 'danger' } --><!-- /ko -->
 *         <!-- ko toast: { value: saved, variant: 'success',
 *               text: 'settings.saved' } --><!-- /ko -->
 * value : chaîne non vide > texte du toast ; true > t(opts.text).
 * La souscription est libérée à la destruction du nœud (modales with:).
 */
ko.bindingHandlers.toast = {
	init(element, valueAccessor) {
		const opts = valueAccessor() || {};
		if (!ko.isObservable(opts.value)) { return; }
		const sub = opts.value.subscribe(v => {
			if (typeof v === 'string' && v !== '') {
				toast.show(v, opts);
			} else if (v === true && opts.text) {
				toast.show(t(opts.text), opts);
			}
		});
		ko.utils.domNodeDisposal.addDisposeCallback(element, () => sub.dispose());
	},
};
ko.virtualElements.allowedBindings.toast = true;
