/**
 * Binding : état "indéterminé" d'une checkbox (sélection partielle).
 * Usage : <input type="checkbox" data-bind="checked: all, indeterminate: some">
 * update seul suffit : KO l'appelle aussi à l'initialisation du binding.
 */
ko.bindingHandlers.indeterminate = {
	update(element, valueAccessor) {
		element.indeterminate = !!ko.unwrap(valueAccessor());
	},
};
