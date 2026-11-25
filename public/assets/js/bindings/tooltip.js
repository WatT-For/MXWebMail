import { t } from '../i18n.js';

// Instances vivantes : un trigger masqué (visible:) ne reçoit plus de
// mouseleave/focusout > son tip (container: body) resterait affiché.
// Purge globale au changement de page (hash) - couvre admin comme webmail.
const tips = new Set();
window.addEventListener('hashchange', () => {
	for (const el of tips) { bootstrap.Tooltip.getInstance(el)?.hide(); }
});

/**
 * Binding : tooltip Bootstrap sur l'élément (icône d'aide, bouton…).
 * Usage : data-bind="tooltip: 'cle.i18n'" - clé résolue via t() à chaque
 * affichage ; texte brut accepté (pass-through) ; expression/observable OK.
 * Forme objet : { text, placement?, pre?, icon? } - text accepte
 * observable/computed ; pre: true ajoute la classe mxwm-tooltip-pre (retours
 * à la ligne préservés) ; icon: 'fa-…' substitue {icon} dans le texte par
 * l'icône FontAwesome (rendu HTML activé dans ce cas).
 */
ko.bindingHandlers.tooltip = {
	init(element, valueAccessor) {
		const v = ko.unwrap(valueAccessor());
		const conf = (v && typeof v === 'object') ? v : { text: v };
		const tip = new bootstrap.Tooltip(element, {
			title: () => t(ko.unwrap(conf.text),
				conf.icon ? { icon: `<i class="${conf.icon}"></i>` } : null),
			html: !!conf.icon,
			container: 'body',       // z-index tooltip > modale ; évite le clipping overflow
			trigger: 'hover focus',
			placement: conf.placement || 'top',
			customClass: conf.pre ? 'mxwm-tooltip-pre' : '',
		});
		tips.add(element);
		ko.utils.domNodeDisposal.addDisposeCallback(element, () => {
			tips.delete(element);
			tip.dispose();
		});
	},
};
