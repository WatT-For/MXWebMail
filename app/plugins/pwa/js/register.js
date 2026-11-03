/**
 * PWA - enregistre le service worker sur le scope racine de l'application.
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
})();
