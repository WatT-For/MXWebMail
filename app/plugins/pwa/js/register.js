/**
 * PWA - enregistre le service worker sur le scope racine de l'application
 * et, quand la meta theme-color porte data-pwa-auto (champ vide), la
 * synchronise sur la couleur d'en-tête du thème affiché (clair/sombre).
 * Chargé en defer dans le <head> de la page app (manifest linké à côté).
 * window.MXWM.base est déjà défini à ce stade (script inline du template).
 */
(function () {
	var base = (window.MXWM && window.MXWM.base) || '';
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker
			.register(base + '/pwa/sw.js', { scope: base + '/' })
			.catch(function () {});
	}
	/* theme-color auto : suit --mxwm-header du thème affiché ; repli sur le
	   fond calculé de body (thèmes sans champ couleur), sinon content initial. */
	var meta = document.querySelector('meta[name="theme-color"][data-pwa-auto]');
	if (meta) {
		var cfg = document.getElementById('theme-config');
		var sync = function () {
			var v = getComputedStyle(document.documentElement)
				.getPropertyValue('--mxwm-header').trim();
			if (v === '') {
				v = getComputedStyle(document.body).backgroundColor;
			}
			if (v !== '' && v !== 'transparent' && v !== 'rgba(0, 0, 0, 0)') {
				meta.setAttribute('content', v);
			}
		};
		sync();
		new MutationObserver(sync).observe(document.documentElement,
			{ attributes: true, attributeFilter: ['data-bs-theme'] });
		if (cfg) {
			new MutationObserver(sync).observe(cfg,
				{ childList: true, characterData: true, subtree: true });
		}
	}
})();
