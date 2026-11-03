import { t } from '../i18n.js';

/**
 * Binding : tooltip Bootstrap sur l'élément (icône d'aide, bouton…).
 * Usage : data-bind="tooltip: 'cle.i18n'" - clé résolue via t() à chaque
 * affichage ; texte brut accepté (pass-through) ; expression/observable OK.
 * Forme objet : { text, placement?, pre? } - text accepte observable/computed ;
 * pre: true ajoute la classe mxwm-tooltip-pre (retours à la ligne préservés).
 */
ko.bindingHandlers.tooltip = {
	init(element, valueAccessor) {
		const v = ko.unwrap(valueAccessor());
		const conf = (v && typeof v === 'object') ? v : { text: v };
		const tip = new bootstrap.Tooltip(element, {
			title: () => t(ko.unwrap(conf.text)),
			container: 'body',       // z-index tooltip > modale ; évite le clipping overflow
			trigger: 'hover focus',
			placement: conf.placement || 'top',
			customClass: conf.pre ? 'mxwm-tooltip-pre' : '',
		});
		ko.utils.domNodeDisposal.addDisposeCallback(element, () => tip.dispose());
	},
};
