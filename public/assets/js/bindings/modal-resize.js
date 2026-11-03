/**
 * Binding : poignée de redimensionnement (coin bas-droit) d'une modale.
 * Usage : <div class="modal-resize-grip" data-bind="modalResize:
 *   { w: composeW, h: composeH, minW: 480, minH: 360, save: saveComposeSize }"></div>
 * w/h : observables en px ; 0 = taille Bootstrap par défaut (pas d'inline).
 * Double-clic sur la poignée = retour à la taille par défaut (détecté via
 * e.detail === 2 : preventDefault supprime les mouse events, dont dblclick).
 * Pendant le drag, <body> reçoit la classe "modal-resizing" (neutralise les
 * iframes - TinyMCE - et la sélection de texte).
 */
ko.bindingHandlers.modalResize = {
	init(element, valueAccessor) {
		const opts = valueAccessor();
		const dialog = element.closest('.modal-dialog');
		const content = element.closest('.modal-content');
		element.addEventListener('pointerdown', (e) => {
			e.preventDefault();
			if (e.detail === 2) {
				opts.w(0); opts.h(0); opts.save?.();
				return;
			}
			element.setPointerCapture(e.pointerId);
			const startX = e.clientX, startY = e.clientY;
			const startW = dialog.offsetWidth, startH = content.offsetHeight;
			const move = (ev) => {
				opts.w(Math.round(Math.min(window.innerWidth - 32,
					Math.max(opts.minW, startW + ev.clientX - startX))));
				opts.h(Math.round(Math.min(window.innerHeight - 56,
					Math.max(opts.minH, startH + ev.clientY - startY))));
			};
			const up = () => {
				document.removeEventListener('pointermove', move);
				document.removeEventListener('pointerup', up);
				document.removeEventListener('pointercancel', up);
				element.classList.remove('dragging');
				document.body.classList.remove('modal-resizing');
				opts.save?.();
			};
			element.classList.add('dragging');
			document.body.classList.add('modal-resizing');
			document.addEventListener('pointermove', move);
			document.addEventListener('pointerup', up);
			document.addEventListener('pointercancel', up);
		});
	},
};
