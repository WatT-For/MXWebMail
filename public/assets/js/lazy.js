/**
 * Chargement paresseux des libs lourdes vendorées : openpgp (~540 K) et
 * tinymce (~500 K) ne servent qu'à la crypto PGP et à l'éditeur riche —
 * hors du chemin critique du boot (elles étaient chargées par <script>
 * systématique dans app.php). Promesse mutualisée par URL : appels
 * concurrents dédupliqués, échec → retry possible au prochain appel.
 */
const pending = {};

function loadScript(src) {
	return pending[src] ??= new Promise((resolve, reject) => {
		const s = document.createElement('script');
		s.src = src;
		s.onload = resolve;
		s.onerror = () => {
			delete pending[src];
			reject(new Error('Chargement impossible : ' + src));
		};
		document.head.appendChild(s);
	});
}

const assets = () => (window.MXWM?.base || '') + '/assets/v' + (window.MXWM?.ver || '');

export function loadOpenPgp() {
	return loadScript(assets() + '/vendor/openpgp/openpgp.min.js')
		.then(() => window.openpgp);
}

export function loadTinyMce() {
	return loadScript(assets() + '/vendor/tinymce/tinymce.min.js')
		.then(() => window.tinymce);
}
