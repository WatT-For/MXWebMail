import { t } from '../i18n.js';

/**
 * Binding : tooltip Bootstrap sur l'élément (icône d'aide, bouton…).
 * Usage : data-bind="tooltip: 'cle.i18n'" — clé résolue via t() à chaque
 * affichage ; texte brut accepté (pass-through) ; expression/observable OK.
 */
ko.bindingHandlers.tooltip = {
	init(element, valueAccessor) {
		const tip = new bootstrap.Tooltip(element, {
			title: () => t(ko.unwrap(valueAccessor())),
			container: 'body',       // z-index tooltip > modale ; évite le clipping overflow
			trigger: 'hover focus',
			placement: 'top',
		});
		ko.utils.domNodeDisposal.addDisposeCallback(element, () => tip.dispose());
	},
};
