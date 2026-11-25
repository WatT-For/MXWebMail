/**
 * debounce(fn, ms) > fonction : chaque appel ré-arme le timer, fn ne part
 * qu'au calme. .cancel() annule le tir en attente (fermeture, désarmement).
 * Contrairement aux timers one-shot annulables (clearTimeout/setTimeout
 * isolés), réservé aux déclenchements répétitifs - frappe, clics, events.
 */
export function debounce(fn, ms) {
	let t = null;
	const d = (...args) => {
		clearTimeout(t);
		t = setTimeout(() => { t = null; fn(...args); }, ms);
	};
	d.cancel = () => { clearTimeout(t); t = null; };
	return d;
}
