/**
 * Raccourcis clavier de la vue mail (réglage keyboard_shortcuts, défaut on).
 * Inactifs dans un champ, une modale, un menu déroulant ouvert ou la vue
 * Paramètres ("?" affiche l'aide hors de ces contextes uniquement). L'iframe
 * TinyMCE ne remonte jamais ses frappes au document - l'éditeur est
 * naturellement exclu.
 */
export function initShortcuts(app) {
	let helpModal = null;

	document.addEventListener('keydown', e => {
		if (e.ctrlKey || e.altKey || e.metaKey) { return; }
		if (app.userSettings?.keyboard_shortcuts === false) { return; }
		const t2 = e.target;
		if (t2?.closest?.('input,textarea,select,[contenteditable]')) { return; }
		if (document.querySelector('.modal.show, .dropdown-menu.show')) { return; }

		const ml = app.messageList;
		const mv = app.messageView;
		const msg = mv.message();
		const checked = ml.checkedMessages();
		const hasChecked = checked.length > 0;
		const act = fn => { e.preventDefault(); fn(); };

		switch (e.key) {
		case '?':
			if (!helpModal) {
				helpModal = new bootstrap.Modal(document.getElementById('shortcuts-modal'));
			}
			act(() => helpModal.show());
			return;
		}
		if (app.view() !== 'mail') { return; }

		switch (e.key) {
		case 'ArrowDown': case 'j': act(() => ml.selectAdjacent(1)); break;
		case 'ArrowUp': case 'k': act(() => ml.selectAdjacent(-1)); break;
		case 'x': {
			const sel = ml.messages().find(m => m.isSelected());
			if (sel) { act(() => { sel.isChecked(!sel.isChecked()); ml._anchor = sel; }); }
			break;
		}
		case 'Delete': case 'Backspace':
			if (hasChecked) { act(() => ml.bulkDelete()); }
			else if (msg) { act(() => mv.deleteMessage()); }
			break;
		case 'r': if (msg) { act(() => app.compose.openReply(msg, false)); } break;
		case 'a': if (msg) { act(() => app.compose.openReply(msg, true)); } break;
		case 'f': if (msg) { act(() => app.compose.openForward(msg)); } break;
		case 'e': {
			if (!app.folderList.archiveFolder()) { break; }
			if (hasChecked) { act(() => ml.bulkArchive()); }
			else if (msg) { act(() => mv.archive()); }
			break;
		}
		case '!':
			// Miroir du bouton toolbar : dans le dossier Junk, "!" retire le
			// marquage (bulkSpam(true) sauterait toutes les cibles déjà junk).
			if (hasChecked) {
				act(() => ml.bulkSpam(!app.folderList.isJunkFolder(ml.folder())));
			}
			else if (msg) { act(() => mv.toggleSpam()); }
			break;
		case 's':
			if (hasChecked) { act(() => ml.bulkFlag('\\Flagged', !ml.checkedAllFlagged())); }
			else if (msg) { act(() => mv.toggleFlag()); }
			break;
		case 'u':
			if (hasChecked) { act(() => ml.bulkFlag('\\Seen', false)); }
			else if (msg) { act(() => mv.markUnread()); }
			break;
		case 'm':
			if (hasChecked) { act(() => ml.moveWithPicker()); }
			else if (msg) { act(() => mv.moveWithPicker()); }
			break;
		case 'c': act(() => app.compose.openNew()); break;
		case '/':
			act(() => document.querySelector('.message-pane input[type="search"]')?.focus());
			break;
		case 'Escape':
			if (msg) {
				act(() => {
					ml.messages().forEach(m => m.isSelected(false));
					mv.clear();
				});
			}
			break;
		}
	});
}
