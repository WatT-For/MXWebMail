import { t } from '../i18n.js';

/** Échappe & < > " ' pour insertion en HTML (texte ou attribut). */
export function escapeHtml(s) {
	return String(s ?? '').replace(/[&<>"']/g,
		c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

/** Texte brut > HTML affichable : échappé, retours à la ligne > <br>. */
export function textToHtml(s) {
	return escapeHtml(s).replace(/\r?\n/g, '<br>');
}

/** Liste d'adresses {name,email} > "Nom <a@b>, …" pour l'affichage.
 *  (chaîne plate acceptée ; ne quote pas - contrairement au payload RFC
 *  d'envoi qui exige "Nom" <a@b>, cf. compose.js) */
export function formatAddressList(v) {
	if (!v) { return ''; }
	if (Array.isArray(v)) {
		return v.map(e => e.name ? `${e.name} <${e.email}>` : (e.email || e)).join(', ');
	}
	return String(v);
}

/** Timestamp unix (s) > date+heure localisée ; `unknown` quand vide. */
export function formatTs(ts, lang, unknown = '-') {
	return ts ? new Date(Number(ts) * 1000).toLocaleString(lang) : unknown;
}

/** Timestamp unix (s) > date seule localisée ; `unknown` quand vide. */
export function formatDate(ts, lang, unknown = '-') {
	return ts ? new Date(Number(ts) * 1000).toLocaleDateString(lang) : unknown;
}

/** Entier borné : NaN ou hors [min,max] > def (réglages de largeur de panneau). */
export function clampInt(v, min, max, def) {
	v = parseInt(v, 10);
	return (v >= min && v <= max) ? v : def;
}

/** Taille lisible (octets > o/Ko/Mo/Go, unités binaires) ; `unknown` quand
 *  l'entrée n'est pas un nombre positif ('0 o' par défaut, '' ou '-' selon
 *  le contexte d'affichage). */
export function formatSize(bytes, unknown = null) {
	// null/undefined/'' ne sont pas des nombres - Number() les ferait passer
	// pour 0 ('0 o' affiché au lieu de l'indicateur "inconnu").
	const b = (bytes === null || bytes === undefined || bytes === '') ? NaN : Number(bytes);
	if (!Number.isFinite(b) || b < 0) { return unknown ?? '0 ' + t('unit.b'); }
	if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
	if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
	if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
	return b + ' ' + t('unit.b');
}
