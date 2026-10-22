/**
 * Traductions côté client — dictionnaire injecté dans window.MXWM.i18n
 * par PageController ({lang, dict}). t() retourne la clé si absente
 * (pass-through : les textes non catalogués s'affichent tels quels).
 */
const i18n = window.MXWM?.i18n || { lang: 'en', dict: {} };

export const lang = i18n.lang || 'en';

export function t(key, params = null) {
	let s = i18n.dict[key] ?? key;
	if (params) {
		for (const [k, v] of Object.entries(params)) {
			s = s.replaceAll('{' + k + '}', String(v));
		}
	}
	return s;
}

// Secours pour les modules de plugins montés hors contexte Knockout.
if (window.MXWM) {
	window.MXWM.t = t;
	window.MXWM.lang = lang;
}
