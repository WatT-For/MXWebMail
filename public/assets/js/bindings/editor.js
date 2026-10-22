import { t, lang } from '../i18n.js';

// Langue TinyMCE = langue de l'interface. Seuls les codes dont le pack est
// vendoré sous vendor/tinymce/langs/ figurent ici : un fichier langs/<code>.js
// absent empêcherait l'éditeur de démarrer. 'en' = défaut intégré (pas de
// fichier), l'option n'est alors pas fixée du tout.
const TINY_LANGS = {
	de: 'de', es: 'es', fr: 'fr_FR', it: 'it', nl: 'nl',
	pl: 'pl', pt: 'pt_PT', ru: 'ru', tr: 'tr',
};

/**
 * Bindings Knockout pour TinyMCE (chargé depuis /assets/vendor/tinymce/).
 * - <textarea data-bind="editor: htmlObservable"></textarea> : éditeur standard
 * - <textarea data-bind="signatureEditor: sigObservable"></textarea> : signature
 *   (bouton « position du curseur » insérant un marqueur .mxwm-sig-caret)
 */
function initTiny(element, accessor, extra = {}) {
	const promise = tinymce.init({
		target: element,
		base_url: (window.MXWM?.base || '') + '/assets/vendor/tinymce',
		suffix: '.min',
		license_key: 'gpl',
		...(TINY_LANGS[lang] ? { language: TINY_LANGS[lang] } : {}),
		menubar: false,
		statusbar: false,
		height: 320,
		branding: false,
		// CSP du document iframe : bloque tout chargement distant (images de
		// tracking, fonts, médias) dans les contenus collés/transférés — les
		// src originaux restent intacts dans le HTML envoyé.
		content_security_policy:
			"default-src 'none'; img-src 'self' data: cid: blob:; style-src 'unsafe-inline'; font-src data:",
		plugins: 'autolink link lists image',
		toolbar: 'undo redo | bold italic underline | forecolor | bullist numlist | link | removeformat',
		...extra,
		setup(editor) {
			editor.on('change keyup undo redo', () => {
				if (ko.isObservable(accessor)) {
					accessor(editor.getContent());
				}
			});
			// tinymce.init est asynchrone : KO appelle update() une fois juste
			// après l'init du binding, souvent avant que l'éditeur existe.
			// Sans ce sync, un observable déjà rempli ne serait jamais affiché.
			editor.on('init', () => {
				const v = ko.unwrap(accessor) || '';
				if (editor.getContent() !== v) {
					editor.setContent(v);
				}
			});
			extra.setup?.(editor);
		},
	});
	// Si le DOM est détruit avant la fin de l'init async (ex: with:), l'éditeur
	// fuirait attaché à un nœud détaché.
	promise.then(editors => {
		if (!element.isConnected) {
			editors.forEach(ed => ed.remove());
		}
	});
}

function syncFromObservable(element, valueAccessor) {
	const ed = tinymce.get(element.id);
	const value = ko.unwrap(valueAccessor()) || '';
	// ed.initialized : getContent() lève une erreur avant la fin de l'init
	if (ed?.initialized && ed.getContent() !== value) {
		ed.setContent(value);
	}
}

function addDispose(element) {
	ko.utils.domNodeDisposal.addDisposeCallback(element, () => {
		const ed = tinymce.get(element.id);
		if (ed) { ed.remove(); }
	});
}

ko.bindingHandlers.editor = {
	init(element, valueAccessor) {
		initTiny(element, valueAccessor());
		addDispose(element);
	},
	update: syncFromObservable,
};

ko.bindingHandlers.signatureEditor = {
	init(element, valueAccessor) {
		initTiny(element, valueAccessor(), {
			height: 200,
			toolbar: 'undo redo | bold italic underline | forecolor | bullist numlist | link | sigcaret | removeformat',
			setup(editor) {
				editor.ui.registry.addButton('sigcaret', {
					icon: 'bookmark',
					tooltip: t('settings.signature_caret'),
					onAction() {
						// Un seul marqueur : retirer l'existant
						editor.dom.select('span.mxwm-sig-caret')
							.forEach(n => editor.dom.remove(n));
						editor.insertContent(
							'<span class="mxwm-sig-caret">&#8982;</span>&nbsp;');
					},
				});
			},
		});
		addDispose(element);
	},
	update: syncFromObservable,
};
