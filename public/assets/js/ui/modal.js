/**
 * bindModal(id, opts) - instancie une modale Bootstrap et câble les
 * hooks usuels en un seul endroit :
 *   onHidden : callback après fermeture complète (reset d'état -
 *     l'observable édité repart propre à la réouverture)
 *   onShown  : callback après ouverture complète
 *   focus    : id d'élément à focusser à chaque ouverture (le focus posé
 *     avant la fin de transition est volé par Bootstrap > shown.bs.modal)
 * Retourne l'instance bootstrap.Modal, ou null si l'élément est absent
 * (les modales optionnelles des hooks plugins ne sont pas toujours rendues).
 */
export function bindModal(id, { onHidden = null, onShown = null, focus = null } = {}) {
	const el = document.getElementById(id);
	if (!el) { return null; }
	const modal = new bootstrap.Modal(el);
	if (onHidden) { el.addEventListener('hidden.bs.modal', onHidden); }
	if (onShown)  { el.addEventListener('shown.bs.modal', onShown); }
	if (focus)    { el.addEventListener('shown.bs.modal',
		() => document.getElementById(focus)?.focus()); }
	return modal;
}
