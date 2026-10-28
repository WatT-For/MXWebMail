import { api, b64url, base } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { t } from '../i18n.js';
import { MessageModel } from '../models/message.js';

const DEFAULT_SORT = 'REVERSE DATE';

/**
 * ViewModel : liste des messages du dossier courant.
 * Tri/pagination/recherche côté serveur.
 */
export class MessageListVM {
	constructor(app) {
		this.app = app;
		this.messages = ko.observableArray([]);
		this.loading = ko.observable(false);
		this.error = ko.observable('');
		this.page = ko.observable(1);
		this.limit = 50;
		this.total = ko.observable(0);
		this.search = ko.observable('');
		// Recherche avancée : champs de la modale (brouillon). La requête n'est
		// commitée que dans searchNow() — sinon un load() ultérieur (dossier,
		// pagination, refresh) appliquerait des filtres jamais validés.
		this.searchFrom    = ko.observable('');
		this.searchTo      = ko.observable('');
		this.searchSubject = ko.observable('');
		this.searchBody    = ko.observable('');
		this.searchAttach  = ko.observable(false);
		this.searchUnseen  = ko.observable(false);
		this.searchFlagged = ko.observable(false);
		this.searchSince   = ko.observable('');
		this.searchBefore  = ko.observable('');
		this.searchQuery = ko.observable(''); // requête appliquée (tokens + texte)
		this.searchScope = ko.observable('folder'); // 'folder' | 'all' (multi-dossiers)
		this.appliedAdv  = ko.observable(''); // portion tokens → indicateur actif
		this.appliedFilterCount = ko.observable(0); // nb de critères → badge bouton
		this.hasSearchFilters = ko.pureComputed(() => this.appliedAdv() !== '');
		this.folder = ko.observable('INBOX');
		// Conversations (IMAP THREAD) — réglage list_threads
		this.useThreads = ko.observable(false);
		this.threadsSupported = ko.observable(false);
		this.threadChildren = {}; // parentUid → MessageModel[] insérés sous la ligne
		// Critère IMAP SORT (RFC 5256) — persisté dans le réglage list_sort
		this.sort = ko.observable(DEFAULT_SORT);
		this.sortOptions = [
			{ key: 'REVERSE DATE', label: 'app.sort.date_desc' },
			{ key: 'DATE',         label: 'app.sort.date_asc' },
			{ key: 'FROM',         label: 'app.sort.from' },
			{ key: 'TO',           label: 'app.sort.to' },
			{ key: 'SUBJECT',      label: 'app.sort.subject' },
			{ key: 'REVERSE SIZE', label: 'app.sort.size_desc' },
			{ key: 'SIZE',         label: 'app.sort.size_asc' },
		];
		// Tri ≠ défaut → bouton « survolé » (même convention que hasSearchFilters)
		this.hasCustomSort = ko.pureComputed(() => this.sort() !== DEFAULT_SORT);
		// Tooltip/aria-label du bouton : « Trier : Objet » (pass-through de t())
		this.sortTooltip = ko.pureComputed(() => {
			const o = this.sortOptions.find(o => o.key === this.sort()) || this.sortOptions[0];
			return t('app.sort') + ' : ' + t(o.label);
		});
		this._readTimer = null; // marquage « lu » différé (réglage mark_read_delay)
		this.searchModal = null; // bootstrap.Modal instanciée paresseusement (openSearch)

		// pureComputed inscriptible : lecture = tout coché, écriture = (dé)cocher tout
		this.allChecked = ko.pureComputed({
			read: () => {
				const ms = this.messages();
				return ms.length > 0 && ms.every(m => m.isChecked());
			},
			write: v => this.messages().forEach(m => m.isChecked(!!v)),
		});
		// vrai si au moins un coché mais pas tous → état visuel « indéterminé »
		this.someChecked = ko.pureComputed(() => {
			const n = this.checkedMessages().length;
			return n > 0 && n < this.messages().length;
		});
		// État agrégé de la sélection cochée : pilote les boutons toggle groupés.
		// Sélection mixte (ou rien de flaggé/lu) → false → le clic applique « poser ».
		this.checkedAllFlagged = ko.pureComputed(() => {
			const c = this.checkedMessages();
			return c.length > 0 && c.every(m => m.isFlagged());
		});
		this.checkedAllSeen = ko.pureComputed(() => {
			const c = this.checkedMessages();
			return c.length > 0 && c.every(m => m.isSeen());
		});

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(MessageListVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	async load() {
		this.loading(true);
		try {
			const global = this.searchScope() === 'all' && this.searchQuery() !== '';
			const q = `?folder=${encodeURIComponent(global ? '*' : this.folder())}` +
				`&page=${this.page()}&limit=${this.limit}` +
				`&search=${encodeURIComponent(this.searchQuery())}` +
				`&sort=${encodeURIComponent(this.sort())}` +
				(!global && this.useThreads() ? '&threads=1' : '');
			const data = await api.get('/messages' + q);
			this.threadsSupported(!!data.threadsSupported);
			this.threadChildren = {};
			this.total(data.total || 0);
			this.messages((data.messages || []).map(m => new MessageModel(m)));
		} catch (e) {
			this.error(e.message);
		} finally {
			this.loading(false);
		}
	}

	async selectFolder(folder) {
		this.cancelReadTimer();
		this.folder(folder);
		this.page(1);
		await this.load();
		location.hash = '#/' + encodeURIComponent(folder);
	}

	async select(message) {
		const mv = this.app.messageView;
		// Mail déjà affiché (ou en cours de chargement) : pas de re-fetch.
		// Calculé avant de toucher isSelected, qui est posé uniquement ici.
		const alreadyShown = message.isSelected()
			&& mv.requestedUid === message.uid && mv.requestedFolder === message.folder;
		// Le mail visionné devient l'unique coché ; les cases restent pilotables
		// à la main pour la multi-sélection (clickBubble: false dans le template).
		this.messages().forEach(m => { m.isSelected(false); m.isChecked(false); });
		message.isSelected(true);
		message.isChecked(true);
		// Return avant cancelReadTimer : le marquage « lu » différé en cours
		// (mark_read_delay) doit suivre son cours pour le mail encore affiché.
		if (alreadyShown) { return; }
		this.cancelReadTimer();
		// Dans le dossier Brouillons : ouvrir le message dans le compose
		const role = (this.app.folderList.folders().find(f => f.fullName === message.folder)?.role || '').toLowerCase();
		if (role.includes('draft') || /draft|brouillon/i.test(message.folder)) {
			const full = await api.get(`/messages/${message.uid}?folder=${encodeURIComponent(message.folder)}`);
			this.app.compose.openDraft(full);
			return;
		}
		await this.app.messageView.show(message.folder, message.uid);
		// Fetch échoué ou autre message affiché entre-temps : ne rien marquer
		const shown = this.app.messageView.message();
		if (message.isSeen() || !shown || shown.uid !== message.uid) { return; }
		const delay = Math.min(300, Math.max(0,
			parseInt(this.app.userSettings?.mark_read_delay, 10) || 0));
		if (delay === 0) {
			this.markSeen(message);
			return;
		}
		this._readTimer = setTimeout(() => {
			this._readTimer = null;
			// Ne marque lu que si le message est encore celui affiché
			if (!message.isSelected() || !this.messages().includes(message)) { return; }
			api.post(`/messages/${message.uid}/flags`,
				{ folder: message.folder, flag: '\\Seen', set: true }).catch(() => {});
			this.markSeen(message);
		}, delay * 1000);
	}

	/** Marque un message lu côté UI (pastille + badge non-lus du dossier). */
	markSeen(message) {
		if (!message.isSeen()) {
			message.isSeen(true);
			this.adjustUnread(-1);
		}
	}

	/** Annule le marquage « lu » différé en attente (mark_read_delay > 0). */
	cancelReadTimer() {
		clearTimeout(this._readTimer);
		this._readTimer = null;
	}

	/** Ajuste localement le badge non-lus du dossier courant. */
	adjustUnread(delta) {
		const f = this.app.folderList.folders().find(f => f.fullName === this.folder());
		if (f) { f.unread(Math.max(0, f.unread() + delta)); }
	}

	totalPages() {
		return Math.max(1, Math.ceil(this.total() / this.limit));
	}

	async nextPage() {
		if (this.page() < this.totalPages()) {
			this.page(this.page() + 1);
			await this.load();
		}
	}

	async prevPage() {
		if (this.page() > 1) {
			this.page(this.page() - 1);
			await this.load();
		}
	}

	async refresh() {
		await this.load();
	}

	/** Bascule le mode conversation — persisté dans list_threads. */
	async toggleThreads() {
		this.useThreads(!this.useThreads());
		this.page(1);
		await this.load();
		api.post('/settings', { list_threads: this.useThreads() }).catch(() => {});
	}

	/** Déplie/replie les messages d'un fil sous la ligne parente. */
	async toggleThread(m) {
		const idx = this.messages.indexOf(m);
		if (idx < 0) { return; }
		if (m._threadOpen()) {
			m._threadOpen(false);
			this.messages.removeAll(this.threadChildren[m.uid] || []);
			return;
		}
		try {
			const data = await api.get(`/messages?folder=${encodeURIComponent(this.folder())}` +
				`&threads=1&thread_uid=${m.uid}&limit=200&sort=DATE`);
			const kids = (data.messages || [])
				.filter(x => x.uid !== m.uid)
				.map(x => { const c = new MessageModel(x); c.isChild = true; return c; });
			this.threadChildren[m.uid] = kids;
			this.messages.splice(idx + 1, 0, ...kids);
			m._threadOpen(true);
		} catch (e) { this.error(e.message); }
	}

	/** Compose la requête IMAP key:value — tokens en premier (détection ancrée
	 *  dans SearchCriterias::fromString), texte libre en dernier. */
	buildSearch() {
		const quote = v => /[\s"]/.test(v) ? '"' + v.replace(/[\\"]/g, '\\$&') + '"' : v;
		const adv = [];
		if (this.searchFrom().trim())    { adv.push('from:' + quote(this.searchFrom().trim())); }
		if (this.searchTo().trim())      { adv.push('to:' + quote(this.searchTo().trim())); }
		if (this.searchSubject().trim()) { adv.push('subject:' + quote(this.searchSubject().trim())); }
		if (this.searchBody().trim())    { adv.push('body:' + quote(this.searchBody().trim())); }
		if (this.searchAttach())         { adv.push('has:attachment'); }
		if (this.searchUnseen())         { adv.push('is:unseen'); }
		if (this.searchFlagged())        { adv.push('is:flagged'); }
		if (this.searchSince())          { adv.push('since:' + this.searchSince()); }
		if (this.searchBefore())         { adv.push('before:' + this.searchBefore()); }
		const advStr = adv.join(' ');
		return { adv: advStr, count: adv.length, full: (advStr + ' ' + this.search().trim()).trim() };
	}

	openSearch() {
		if (!this.searchModal) {
			this.searchModal = new bootstrap.Modal(document.getElementById('search-modal'));
		}
		this.searchModal.show();
		// Le focus avant la fin de la transition est volé par Bootstrap →
		// focus du premier champ une fois la modale visible (pattern compose).
		document.getElementById('search-modal').addEventListener(
			'shown.bs.modal', () => document.getElementById('mx-search-from')?.focus(),
			{ once: true });
	}

	async applySearch() {
		await this.searchNow();
		this.searchModal?.hide();
	}

	async searchNow() {
		const { adv, count, full } = this.buildSearch();
		this.appliedAdv(adv);
		this.appliedFilterCount(count);
		this.searchQuery(full);
		this.page(1);
		await this.load();
	}

	async resetSearch() {
		this.search('');
		this.searchFrom(''); this.searchTo('');
		this.searchSubject(''); this.searchBody('');
		this.searchAttach(false); this.searchUnseen(false); this.searchFlagged(false);
		this.searchSince(''); this.searchBefore('');
		await this.searchNow();
		this.searchModal?.hide();
	}

	/** Réglage corrompu / ancien client : retombe sur le tri par défaut. */
	normalizeSort(v) {
		return this.sortOptions.some(o => o.key === v) ? v : DEFAULT_SORT;
	}

	async setSort(o) {
		if (this.sort() === o.key) { return; }
		this.sort(o.key);
		this.page(1);
		await this.load();
		// silencieux : préférence utilisateur, non bloquante
		api.post('/settings', { list_sort: o.key }).catch(() => {});
	}

	// ─── Sélection multiple ───

	checkedMessages() {
		return this.messages().filter(m => m.isChecked());
	}

	hasChecked() {
		return this.checkedMessages().length > 0;
	}

	/** Toggle \Flagged — sémantique SnappyMail : si le mail cliqué est
	 *  coché, applique à tous les cochés, sinon à lui seul. */
	async toggleFlag(message) {
		const set = !message.isFlagged();
		const targets = message.isChecked() ? this.checkedMessages() : [message];
		for (const m of targets) {
			try {
				await api.post(`/messages/${m.uid}/flags`,
					{ folder: m.folder, flag: '\\Flagged', set });
				m.isFlagged(set);
			} catch (e) { this.error(e.message); }
		}
	}

	async bulkFlag(flag, set) {
		for (const m of this.checkedMessages()) {
			try {
				await api.post(`/messages/${m.uid}/flags`, { folder: this.folder(), flag, set });
				if (flag === '\\Seen' && m.isSeen() !== set) {
					m.isSeen(set);
					this.adjustUnread(set ? -1 : 1);
				}
				if (flag === '\\Flagged') { m.isFlagged(set); }
			} catch (e) { this.error(e.message); }
		}
	}

	/** Voisin à sélectionner quand uid va être retiré de la liste
	 *  (réglage auto_advance : next|prev|none). Non mutateur — à appeler
	 *  AVANT le retrait, sur la liste encore complète ; le repli bascule
	 *  sur l'autre côté (ex. dernier élément + mode next → précédent). */
	peekAdvance(uid) {
		const mode = this.app.userSettings?.auto_advance || 'next';
		if (mode === 'none' || uid == null) { return null; }
		const list = this.messages();
		const i = list.findIndex(m => m.uid === uid);
		if (i < 0) { return null; }
		const fwd = mode !== 'prev';
		return list[fwd ? i + 1 : i - 1] ?? list[fwd ? i - 1 : i + 1] ?? null;
	}

	/** Après un bulk : rouvre le voisin du message visionné s'il a été retiré,
	 *  sinon ferme le lecteur (comportement historique). */
	async advanceOrClear(next) {
		if (next && this.messages().includes(next)) {
			await this.select(next);
		} else {
			this.app.messageView.clear();
		}
	}

	async bulkDelete() {
		const list = this.checkedMessages();
		const key = this.app.folderList.isTrashFolder(this.folder())
			? 'messages.delete_confirm_perm' : 'messages.delete_confirm';
		if (!list.length || !(await dialog.confirm(t(key, { n: list.length }),
			{ variant: 'danger', okLabel: t('common.delete'), title: t('messages.delete_title') }))) { return; }
		const next = this.peekAdvance(this.app.messageView.requestedUid);
		for (const m of list) {
			try {
				await api.post(`/messages/${m.uid}/delete`, { folder: this.folder() });
				if (!m.isSeen()) { this.adjustUnread(-1); }
				this.messages.remove(m);
			} catch (e) { this.error(e.message); }
		}
		await this.advanceOrClear(next);
	}

	async bulkMove(target) {
		const next = this.peekAdvance(this.app.messageView.requestedUid);
		for (const m of this.checkedMessages()) {
			try {
				await api.post(`/messages/${m.uid}/move`, { folder: this.folder(), to: target });
				if (!m.isSeen()) { this.adjustUnread(-1); }
				this.messages.remove(m);
			} catch (e) { this.error(e.message); }
		}
		await this.advanceOrClear(next);
	}

	async moveWithPicker() {
		const target = await this.app.folderList.pickFolder(this.folder());
		if (target) { await this.bulkMove(target); }
	}

	/** Déplace la sélection vers le dossier de rôle « archive » (s'il existe). */
	async bulkArchive() {
		const target = this.app.folderList.archiveFolder()?.fullName;
		if (target) { await this.bulkMove(target); }
	}

	/** Télécharge les messages cochés en .eml dans un zip (GET direct). */
	downloadEmlZip() {
		const uids = this.checkedMessages().map(m => m.uid);
		if (!uids.length) { return; }
		location.assign(`${base()}/api/folders/${b64url(this.folder())}/eml.zip?uids=${uids.join(',')}`);
	}

	async bulkSpam(spam) {
		let moved = false;
		const next = this.peekAdvance(this.app.messageView.requestedUid);
		for (const m of this.checkedMessages()) {
			try {
				const r = await api.post(`/messages/${m.uid}/spam`, { folder: this.folder(), spam });
				if (r.strategy === 'move') {
					if (!m.isSeen()) { this.adjustUnread(-1); }
					this.messages.remove(m);
					moved = true;
				} else {
					m.isJunk(spam);
				}
			} catch (e) { this.error(e.message); }
		}
		if (moved) { await this.advanceOrClear(next); }
	}
}
