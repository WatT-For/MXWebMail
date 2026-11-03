/**
 * Binding : poignée de redimensionnement d'un panneau (horizontal ou vertical).
 * Usage : <div class="pane-resizer" data-bind="paneResize:
 *   { size: folderPaneWidth, axis: 'x', min: 140, max: 600, save: savePaneSizes }"></div>
 * `axis` ('x' | 'y'), `min` et `max` sont lus au pointerdown via ko.unwrap :
 * des observables/computed sont acceptés (l'axe change avec le layout).
 * Pointer events : souris + tactile. Pendant le drag, <body> reçoit la classe
 * "pane-resizing" (axe x) ou "pane-resizing-v" (axe y) - neutralise
 * l'iframe du lecteur et la sélection de texte.
 */
ko.bindingHandlers.paneResize = {
	init(element, valueAccessor) {
		const opts = valueAccessor();
		element.addEventListener('pointerdown', (e) => {
			e.preventDefault();
			const axis = ko.unwrap(opts.axis) || 'x';
			const min = ko.unwrap(opts.min);
			const max = ko.unwrap(opts.max);
			const pos = ev => axis === 'y' ? ev.clientY : ev.clientX;
			const start = pos(e);
			const startSize = opts.size();
			const cls = axis === 'y' ? 'pane-resizing-v' : 'pane-resizing';
			const move = (ev) => {
				opts.size(Math.min(max,
					Math.max(min, Math.round(startSize + pos(ev) - start))));
			};
			const up = () => {
				document.removeEventListener('pointermove', move);
				document.removeEventListener('pointerup', up);
				document.removeEventListener('pointercancel', up);
				element.classList.remove('dragging');
				document.body.classList.remove(cls);
				opts.save?.();
			};
			element.classList.add('dragging');
			document.body.classList.add(cls);
			document.addEventListener('pointermove', move);
			document.addEventListener('pointerup', up);
			document.addEventListener('pointercancel', up);
		});
	},
};
