import { api, b64url, base } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { toast } from '../ui/toast.js';
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
		this.showLoader = ko.observable(false); // voile centré - false pour les chargements d'arrière-plan (poll)
		this._loadCount = 0;   // requêtes en vol (toutes) - garde contre les loads concurrents
		this._fgLoadCount = 0; // requêtes de premier plan en vol (pilotent showLoader)
		this.error = ko.observable('');
		this.page = ko.observable(1);
		this.limit = 50;
		this.total = ko.observable(0);
		this.search = ko.observable('');
		// Recherche avancée : champs de la modale (brouillon). La requête n'est
		// commitée que dans searchNow() - sinon un load() ultérieur (dossier,
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
		this.appliedAdv  = ko.observable(''); // portion tokens > indicateur actif
		this.appliedFilterCount = ko.observable(0); // nb de critères > badge bouton
		this.hasSearchFilters = ko.pureComputed(() => this.appliedAdv() !== '');
		this.folder = ko.observable('INBOX');
		// Conversations (IMAP THREAD) - réglage list_threads
		this.useThreads = ko.observable(false);
		// null = inconnu (avant la 1ère réponse dossier) - distingue "pas
		// encore chargé" de "non supporté" pour la note des réglages.
		this.threadsSupported = ko.observable(null);
		this.threadChildren = {}; // parentUid > MessageModel[] insérés sous la ligne
		// Messages retirés optimistement dont le POST n'est pas soldé - un
		// load() (poll…) passé avant la fin du MOVE serveur ne doit pas les
		// faire réapparaître. Clés 'folder\nuid', retirées à la fin du POST.
		this._gone = new Set();
		// Critère IMAP SORT (RFC 5256) - persisté dans le réglage list_sort
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
		// Tri ≠ défaut > bouton "survolé" (même convention que hasSearchFilters)
		this.hasCustomSort = ko.pureComputed(() => this.sort() !== DEFAULT_SORT);
		// Tooltip/aria-label du bouton : "Trier : Objet" (pass-through de t())
		this.sortTooltip = ko.pureComputed(() => {
			const o = this.sortOptions.find(o => o.key === this.sort()) || this.sortOptions[0];
			return t('app.sort') + ' : ' + t(o.label);
		});
		this._readTimer = null; // marquage "lu" différé (réglage mark_read_delay)
		this.searchModal = null; // bootstrap.Modal instanciée paresseusement (openSearch)
		this._dragList = null;  // MessageModel[] en cours de glisser-déposer
		this._dragGhost = null; // fantôme compteur affiché pendant le drag
		this._anchor = null;    // MessageModel pivot des sélections par plage (Shift)
		// Index de la ligne du message affiché au moment de son retrait -
		// capturé AVANT le remove, consommé par advanceOrClear (réglage
		// after_delete : 'next'|'prev'|'close').
		this._advanceIdx = null;

		// pureComputed inscriptible : lecture = tout coché, écriture = (dé)cocher tout
		this.allChecked = ko.pureComputed({
			read: () => {
				const ms = this.messages();
				return ms.length > 0 && ms.every(m => m.isChecked());
			},
			write: v => this.messages().forEach(m => m.isChecked(!!v)),
		});
		// vrai si au moins un coché mais pas tous > état visuel "indéterminé"
		this.someChecked = ko.pureComputed(() => {
			const n = this.checkedMessages().length;
			return n > 0 && n < this.messages().length;
		});
		// ≥ 2 > la zone de lecture affiche le compteur de multi-sélection
		this.checkedCount = ko.pureComputed(() => this.checkedMessages().length);
		// État agrégé de la sélection cochée : pilote les boutons toggle groupés.
		// Sélection mixte (ou rien de flaggé/lu) > false > le clic applique "poser".
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

	/**
	 * Applique une réponse /api/messages (fetch dédié ou section "list" du
	 * /api/poll) : compteurs + MessageModel. Ne touche pas les flags de
	 * chargement - silencieux par nature (poll d'arrière-plan).
	 */
	applyData(data) {
		// La recherche multi-dossiers renvoie threadsSupported:false - elle
		// ne doit pas écraser l'état connu du dossier (item de menu masqué).
		const global = this.searchScope() === 'all' && this.searchQuery() !== '';
		if (!global) { this.threadsSupported(!!data.threadsSupported); }
		this.total(data.total || 0);
		// Page devenue hors limites (retraits, expunge externe) : clamp sur la
		// dernière page valide + rechargement silencieux - couvre load() et la
		// section "list" du poll. La réponse hors limites (vide par définition)
		// n'est pas appliquée : les lignes courantes restent jusqu'au resync.
		if (this.page() > this.totalPages()) {
			this.page(this.totalPages());
			queueMicrotask(() => this.load(true));
			return;
		}
		const rows = (data.messages || [])
			.filter(m => !this._gone.has(this.goneKey(m.folder, m.uid)));
		// Les sous-lignes de fils dépliés (isChild) vivent dans messages() :
		// la comparaison de séquence porte sur les seules lignes de niveau 0.
		const targets = this.messages().filter(m => !m.isChild);
		const sameSeq = targets.length === rows.length
			&& targets.every((m, i) => m.uid === rows[i].uid && m.folder === rows[i].folder);
		if (sameSeq) {
			// Réconciliation in-place : flags rafraîchis, fils dépliés et
			// sélection préservés - aucun re-render du foreach.
			for (let i = 0; i < targets.length; i++) {
				const m = targets[i];
				if (m.staticSignature() !== MessageModel.signature(rows[i])) {
					const nm = new MessageModel(rows[i]);
					nm.isSelected(m.isSelected());
					nm.isChecked(m.isChecked());
					nm._threadOpen(m._threadOpen());
					this.messages.splice(this.messages.indexOf(m), 1, nm);
				} else {
					m.updateFrom(rows[i]);
				}
			}
			return;
		}
		// Rebuild : sélection et coches préservées par clé folder+uid.
		const checked = new Set(this.messages().filter(m => m.isChecked())
			.map(m => this.goneKey(m.folder, m.uid)));
		const sel = this.messages().find(m => m.isSelected());
		const selKey = sel ? this.goneKey(sel.folder, sel.uid) : null;
		this.threadChildren = {};
		this.messages(rows.map(d => {
			const m = new MessageModel(d);
			if (selKey !== null && this.goneKey(d.folder, d.uid) === selKey) { m.isSelected(true); }
			m.isChecked(checked.has(this.goneKey(d.folder, d.uid)));
			return m;
		}));
	}

	/** silent = chargement d'arrière-plan (poll auto) : pas de voile bloquant. */
	async load(silent = false) {
		this._loadCount++;
		if (!silent) { this._fgLoadCount++; }
		this.loading(true);
		if (!silent) { this.showLoader(true); }
		try {
			const global = this.searchScope() === 'all' && this.searchQuery() !== '';
			const q = `?folder=${encodeURIComponent(global ? '*' : this.folder())}` +
				`&page=${this.page()}&limit=${this.limit}` +
				`&search=${encodeURIComponent(this.searchQuery())}` +
				`&sort=${encodeURIComponent(this.sort())}` +
				(!global && this.useThreads() ? '&threads=1' : '');
			const data = await api.get('/messages' + q);
			this.applyData(data);
		} catch (e) {
			this.error(e.message);
		} finally {
			if (--this._loadCount === 0) { this.loading(false); }
			if (!silent && --this._fgLoadCount === 0) { this.showLoader(false); }
		}
	}

	async selectFolder(folder) {
		this.cancelReadTimer();
		const changed = folder !== this.folder();
		// Re-clic sur le dossier actif : reload sans fermer le mail ouvert.
		if (changed) {
			this.app.messageView.clear();
		}
		this.folder(folder);
		this.page(1);
		await this.load();
		location.hash = '#/' + encodeURIComponent(folder);
		// Mémorisé côté serveur (par compte) : restauré à la prochaine ouverture.
		if (changed) {
			this.app.folderList.persistLast(folder);
		}
	}

	/** Clic sur la ligne : Ctrl/Meta = bascule la coche sans ouvrir,
	 *  Shift = plage depuis l'ancre, sinon ouverture (comportement de base). */
	rowClick(m, e) {
		if (e.ctrlKey || e.metaKey) {
			m.isChecked(!m.isChecked());
			this._anchor = m;
			return;
		}
		if (e.shiftKey) {
			this.checkRange(m);
			return;
		}
		this._anchor = m;
		this.select(m);
	}

	/** Clic sur la case : Shift = plage depuis l'ancre (preventDefault requis
	 *  pour bloquer le toggle natif), sinon toggle normal + ancre. */
	checkClick(m, e) {
		if (e.shiftKey) {
			e.preventDefault();
			this.checkRange(m);
			return;
		}
		this._anchor = m;
		return true;
	}

	/** Coche tous les messages entre l'ancre et m (bornes incluses).
	 *  Sans ancre valide dans la liste : toggle unitaire de m. */
	checkRange(m) {
		const list = this.messages();
		const i = list.indexOf(m);
		const a = this._anchor ? list.indexOf(this._anchor) : -1;
		if (i < 0 || a < 0) {
			m.isChecked(!m.isChecked());
			this._anchor = m;
			return;
		}
		for (let k = Math.min(a, i); k <= Math.max(a, i); k++) {
			list[k].isChecked(true);
		}
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
		// Return avant cancelReadTimer : le marquage "lu" différé en cours
		// (mark_read_delay) doit suivre son cours pour le mail encore affiché.
		if (alreadyShown) { return; }
		this.cancelReadTimer();
		// Dans le dossier Brouillons : ouvrir le message dans le compose
		const role = (this.app.folderList.folders().find(f => f.fullName === message.folder)?.role || '').toLowerCase();
		if (role.includes('draft') || /draft|brouillon/i.test(message.folder)) {
			try {
				const full = await api.get(`/messages/${message.uid}?folder=${encodeURIComponent(message.folder)}`);
				this.app.compose.openDraft(full);
			} catch (e) { this.error(e.message); }
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
			// Ne marque lu que si le message est encore celui affiché - testé
			// via requestedUid du lecteur : un rebuild de la liste remplace les
			// MessageModel, includes() sur l'ancienne instance serait faux.
			const mv = this.app.messageView;
			if (mv.requestedUid !== message.uid
				|| mv.requestedFolder !== message.folder) { return; }
			api.post(`/messages/${message.uid}/flags`,
				{ folder: message.folder, flag: '\\Seen', set: true }).catch(() => {});
			this.markSeen(this.messages().find(
				x => x.uid === message.uid && x.folder === message.folder) || message);
		}, delay * 1000);
	}

	/** Marque un message lu côté UI (pastille + badge non-lus du dossier). */
	markSeen(message) {
		if (!message.isSeen()) {
			message.isSeen(true);
			this.adjustUnread(-1, message.folder);
		}
	}

	/** Annule le marquage "lu" différé en attente (mark_read_delay > 0). */
	cancelReadTimer() {
		clearTimeout(this._readTimer);
		this._readTimer = null;
	}

	/** Ajuste localement le badge non-lus d'un dossier (dossier courant par défaut). */
	adjustUnread(delta, folder = this.folder()) {
		const f = this.app.folderList.folders().find(f => f.fullName === folder);
		if (f) { f.unread(Math.max(0, f.unread() + delta)); }
	}

	/** Clé _gone d'un message (folder+uid - les UID sont par dossier). */
	goneKey(folder, uid) {
		return folder + '\n' + uid;
	}

	/** Fin de suivi optimiste d'un déplacement (POST soldé). */
	unmarkMoved(folder, uid) {
		this._gone.delete(this.goneKey(folder, uid));
	}

	// ─── Navigation précédent/suivant + avance après suppression ───

	/** Index dans messages() du message affiché dans le lecteur (-1 si absent). */
	indexOfViewed() {
		const mv = this.app.messageView;
		if (mv.requestedUid === null) { return -1; }
		return this.messages().findIndex(
			m => m.uid === mv.requestedUid && m.folder === mv.requestedFolder);
	}

	/** Un message existe-t-il à dir lignes du message affiché
	 *  (dir > 0 = ligne suivante dans le tri courant, dir < 0 = précédente) ? */
	hasAdjacent(dir, uid, folder) {
		const i = this.messages().findIndex(m => m.uid === uid && m.folder === folder);
		const j = i + dir;
		return i >= 0 && j >= 0 && j < this.messages().length;
	}

	/** Sélectionne le message dir positions après le courant (lecture
	 *  précédent/suivant et raccourcis clavier). */
	async selectAdjacent(dir) {
		const i = this.indexOfViewed();
		if (i < 0) { return; }
		const target = this.messages()[i + dir];
		if (!target) { return; }
		await this.select(target);
		document.querySelector('.message-pane .table-active')
			?.scrollIntoView({ block: 'nearest' });
	}

	/** Mémorise l'index de la ligne affichée avant son retrait - à appeler
	 *  AVANT tout remove touchant le message du lecteur. */
	captureAdvanceIdx(m) {
		const mv = this.app.messageView;
		if (mv.requestedUid === m.uid && mv.requestedFolder === m.folder) {
			this._advanceIdx = this.messages().indexOf(m);
		}
	}

	/** Après disparition du message affiché : sélectionne le voisin selon le
	 *  réglage after_delete, sinon ferme le lecteur (comportement historique). */
	async advanceOrClear() {
		const mv = this.app.messageView;
		const mode = this.app.userSettings?.after_delete || 'next';
		const idx = this._advanceIdx;
		this._advanceIdx = null;
		const list = this.messages();
		// Index non capturé ou liste vide : fermeture quel que soit le mode -
		// jamais de repli sur list[0] (message arbitraire).
		const target = idx !== null && idx >= 0 && list.length
			? (mode === 'prev' ? list[Math.max(0, idx - 1)]
				: mode === 'next' ? list[Math.min(idx, list.length - 1)]
				: null)
			: null;
		if (target) { await this.select(target); }
		else { mv.clear(); }
	}

	/** Retrait optimiste d'un message en cours de déplacement : badge non-lu,
	 *  ligne de la liste, suivi _gone. m = MessageModel ou message() du
	 *  lecteur (mêmes champs folder/uid/isSeen). */
	hideMoved(m) {
		this._gone.add(this.goneKey(m.folder, m.uid));
		this.captureAdvanceIdx(m);
		const row = this.messages().find(x => x.uid === m.uid && x.folder === m.folder);
		if (!(row || m).isSeen()) { this.adjustUnread(-1, m.folder); }
		if (row) { this.messages.remove(row); }
		// Sous-lignes d'un fil déplié : retirées avec leur racine
		this.messages.removeAll(this.threadChildren[m.uid] || []);
		delete this.threadChildren[m.uid];
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

	/** Bascule le mode conversation - persisté dans list_threads. */
	async toggleThreads() {
		this.useThreads(!this.useThreads());
		// Synchro userSettings : seede la case des réglages à leur ouverture
		// (même pattern que savePaneSizes / saveComposeSize).
		this.app.userSettings.list_threads = this.useThreads();
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

	/** Compose la requête IMAP key:value - tokens en premier (détection ancrée
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
		// Le focus avant la fin de la transition est volé par Bootstrap >
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

	/** Évènement `search` de l'input[type=search] : émis par l'icône "×" native
	 *  (Blink/WebKit) et par Enter sous Blink (déjà traité en keydown). Ne recharge
	 *  que si le champ a été vidé et que la requête appliquée diffère - les
	 *  filtres avancés éventuels restent actifs (rebuild via buildSearch). */
	clearSearch(d, e) {
		if (e.target.value !== '') { return; }
		this.search(''); // l'event input peut ne pas être encore passé
		if (this.buildSearch().full !== this.searchQuery()) { this.searchNow(); }
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
		this.app.userSettings.list_sort = o.key;
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

	/** Toggle \Flagged - sémantique SnappyMail : si le mail cliqué est
	 *  coché, applique à tous les cochés, sinon à lui seul. */
	async toggleFlag(message) {
		const set = !message.isFlagged();
		await this.setFlagBatch(
			message.isChecked() ? this.checkedMessages() : [message],
			'\\Flagged', set);
	}

	async bulkFlag(flag, set) {
		await this.setFlagBatch(this.checkedMessages(), flag, set);
	}

	/** Un POST /messages/flags groupé par dossier côté serveur (un UID
	 *  STORE par dossier) au lieu d'une requête par message. Échec partiel
	 *  > resync de la liste (même pattern que settleBatch). */
	async setFlagBatch(targets, flag, set) {
		if (!targets.length) { return; }
		try {
			const r = await api.post('/messages/flags', {
				items: targets.map(m => ({ folder: m.folder, uid: m.uid })),
				flag, set });
			if (r.errors?.length) {
				this.error(r.errors[0].error);
				await this.load();
				return;
			}
		} catch (e) { this.error(e.message); return; }
		for (const m of targets) {
			// m.folder (dossier réel du message) : en recherche multi-dossiers
			// il diffère du dossier courant - les UID sont par dossier.
			if (flag === '\\Seen' && m.isSeen() !== set) {
				m.isSeen(set);
				this.adjustUnread(set ? -1 : 1, m.folder);
			}
			if (flag === '\\Flagged') { m.isFlagged(set); }
		}
	}

	/** Après retrait groupé (déplacement, suppression, spam) : aucune
	 *  sélection automatique - le lecteur n'est fermé que si le message
	 *  qu'il affichait a disparu de la liste (couvre aussi les sous-lignes
	 *  de fils retirées avec leur racine). */
	closeViewerIfRemoved() {
		const mv = this.app.messageView;
		if (mv.requestedUid !== null
			&& !this.messages().some(m => m.uid === mv.requestedUid && m.folder === mv.requestedFolder)) {
			this.cancelReadTimer(); // même hygiène que selectFolder/clearFolder
			this.advanceOrClear();
		}
	}

	/** Retrait optimiste groupé (actions batch) : suivi _gone + badge non-lu
	 *  + removeAll en UNE notification KO (remove() par item re-rendrait la
	 *  liste à chaque ligne - effet "disparition une par une"). */
	optimisticRemove(list) {
		const cur = this.messages(); // tableau pré-retrait (index d'origine)
		for (const m of list) {
			this._gone.add(this.goneKey(m.folder, m.uid));
			if (!m.isSeen()) { this.adjustUnread(-1, m.folder); }
		}
		// Sous-lignes des fils dépliés : retirées avec leur racine, en une
		// seule notification KO (elles ne sont pas _gone - toujours en IMAP).
		const removed = [...list,
			...list.flatMap(m => this.threadChildren[m.uid] || [])];
		// Capture sur `removed` (racines + sous-lignes) : un mail affiché qui
		// est une sous-ligne de fil part avec sa racine - list seul le manque.
		for (const m of removed) { this.captureAdvanceIdx(m); }
		// Position post-retrait du successeur du mail affiché = index capturé
		// moins les retraits situés au-dessus.
		if (this._advanceIdx !== null && this._advanceIdx >= 0) {
			this._advanceIdx -= removed.filter(m => {
				const i = cur.indexOf(m);
				return i >= 0 && i < this._advanceIdx;
			}).length;
		}
		this.messages.removeAll(removed);
		list.forEach(m => delete this.threadChildren[m.uid]);
	}

	/** Solde un POST batch : les clés _gone partent AVANT tout load() de
	 *  resync - sinon elles filtreraient les messages restaurés. */
	async settleBatch(list, fail) {
		list.forEach(m => this.unmarkMoved(m.folder, m.uid));
		if (fail) { this.error(fail); }
		// Succès : resync silencieux - la pagination offset côté serveur exige
		// le backfill des lignes retirées et le nouveau total ; échec : voile.
		await this.load(!fail);
	}

	async bulkDelete() {
		const list = this.checkedMessages();
		// Sélection multi-dossiers : suppression définitive dès qu'un message
		// est dans la corbeille (le serveur expunge depuis Trash uniquement).
		const key = list.some(m => this.app.folderList.isTrashFolder(m.folder))
			? 'messages.delete_confirm_perm' : 'messages.delete_confirm';
		if (!list.length || !(await dialog.confirm(t(key, { n: list.length }),
			{ variant: 'danger', okLabel: t('common.delete'), title: t('messages.delete_title') }))) { return; }
		this.optimisticRemove(list);
		this.closeViewerIfRemoved();
		let fail = null;
		try {
			const r = await api.post('/messages/delete',
				{ items: list.map(m => ({ folder: m.folder, uid: m.uid })) });
			if (r.errors?.length) { fail = r.errors[0].error; }
		} catch (e) { fail = e.message; }
		await this.settleBatch(list, fail);
	}

	/** Déplacement optimiste d'une liste de messages (sélection cochée ou
	 *  glisser-déposer) vers un dossier. */
	async moveMessages(list, target) {
		list = list.filter(m => m.folder !== target);
		if (!list.length) { return; } // tout est déjà dans le dossier cible
		this.optimisticRemove(list);
		this.closeViewerIfRemoved();
		let fail = null;
		try {
			const r = await api.post('/messages/move',
				{ items: list.map(m => ({ folder: m.folder, uid: m.uid })), to: target });
			if (r.errors?.length) { fail = r.errors[0].error; }
		} catch (e) { fail = e.message; }
		await this.settleBatch(list, fail);
	}

	async bulkMove(target) {
		await this.moveMessages(this.checkedMessages(), target);
	}

	/** Transfere la selection en une composition : chaque message joint
	 *  en .eml (le marquage $Forwarded est cote serveur a l'envoi). */
	bulkForwardAttach() {
		const list = this.checkedMessages();
		if (list.length) { this.app.compose.openForwardMany(list); }
	}

	/** Copie la selection vers un dossier : les originaux restent en place,
	 *  seuls les badges dossiers changent (non-lus de la cible). */
	async bulkCopy(target) {
		const list = this.checkedMessages().filter(m => m.folder !== target);
		if (!list.length) { return; }
		let fail = null;
		try {
			const r = await api.post('/messages/copy',
				{ items: list.map(m => ({ folder: m.folder, uid: m.uid })), to: target });
			if (r.errors?.length) { fail = r.errors[0].error; }
		} catch (e) { fail = e.message; }
		if (fail) { this.error(fail); }
		else { toast.success(t('app.copied_many', { n: list.length })); }
		await this.refresh();
	}

	// ─── Glisser-déposer vers les dossiers ───

	/** MessageModel[] en cours de glisser-déposer - null hors drag (lecture
	 *  par FolderListVM pour la cible et AppVM pour la garde du poll). */
	dragItems() {
		return this._dragList;
	}

	/** Charge utile du drag : tous les cochés si la ligne saisie est cochée,
	 *  sinon elle seule - même sémantique que toggleFlag. */
	messageDragStart(m, e) {
		this._dragList = m.isChecked() ? this.checkedMessages() : [m];
		// setData requis par Firefox pour que le drag démarre
		e.dataTransfer.setData('text/plain', String(this._dragList.length));
		e.dataTransfer.effectAllowed = 'move';
		e.currentTarget.classList.add('mx-dragging');
		document.body.classList.add('mx-msg-drag');
		// Fantôme compteur à la place de la ligne - retiré à dragend (Firefox
		// le rend après setDragImage : une suppression immédiate = image vide).
		const ghost = document.createElement('div');
		ghost.className = 'msg-drag-ghost';
		ghost.innerHTML = '<i class="fa-solid fa-envelope"></i> '
			+ t('app.selected_count', { n: this._dragList.length });
		document.body.append(ghost);
		e.dataTransfer.setDragImage(ghost, 10, 12);
		this._dragGhost = ghost;
		return true;
	}

	messageDragEnd(m, e) {
		this._dragList = null;
		this._dragGhost?.remove();
		this._dragGhost = null;
		e.currentTarget.classList.remove('mx-dragging');
		document.body.classList.remove('mx-msg-drag');
		// Surbrillance de cible + timer d'expansion restés sur un dossier
		this.app.folderList.dropCleanup();
		return true;
	}

	async moveWithPicker() {
		const target = await this.app.folderList.pickFolder(this.folder());
		if (target) { await this.bulkMove(target); }
	}

	async copyWithPicker() {
		const target = await this.app.folderList.pickFolder(this.folder());
		if (target) { await this.bulkCopy(target); }
	}

	/** Déplace la sélection vers le dossier de rôle "archive" (s'il existe). */
	async bulkArchive() {
		const target = this.app.folderList.archiveFolder()?.fullName;
		if (target) { await this.bulkMove(target); }
	}

	/** Télécharge les messages cochés en .eml dans un zip par dossier source
	 *  (GET direct - un zip par dossier en recherche multi-dossiers). */
	downloadEmlZip() {
		const byFolder = new Map();
		for (const m of this.checkedMessages()) {
			const uids = byFolder.get(m.folder) || [];
			uids.push(m.uid);
			byFolder.set(m.folder, uids);
		}
		for (const [folder, uids] of byFolder) {
			const a = document.createElement('a');
			a.href = `${base()}/api/folders/${b64url(folder)}/eml.zip?uids=${uids.join(',')}`;
			a.download = '';
			document.body.append(a);
			a.click();
			a.remove();
		}
	}

	async bulkSpam(spam) {
		const junkName = this.app.folderList.junkFolder()?.fullName;
		const targets = [];
		for (const m of this.checkedMessages()) {
			// Déjà dans l'état voulu : le serveur renverrait 'move' sans rien
			// déplacer (ex. spam sur un message déjà dans Junk) - sautés.
			const junk = this.app.folderList.isJunkFolder(m.folder) || !!m.isJunk();
			if (junk === spam) { continue; }
			// Prédiction miroir du test serveur ($folder === $junk) : 'move'
			// attendu si le message part/rejoint le dossier Junk, 'keyword' sinon.
			const expectMove = spam ? (!!junkName && m.folder !== junkName)
				: (m.folder === junkName);
			targets.push({ m, expectMove });
		}
		// UI optimiste : retrait immédiat (move attendu) ou flag (keyword).
		let moved = false;
		for (const { m, expectMove } of targets) {
			if (expectMove) { this.hideMoved(m); moved = true; }
			else { m.isJunk(spam); }
		}
		if (moved) { this.closeViewerIfRemoved(); }
		// POST unique groupé : N requêtes HTTP + N connexions IMAP > 1 + 1.
		// Échec global, item sans résultat ou stratégie divergente > resync.
		let resync = false;
		let r;
		try {
			r = await api.post('/messages/spam', {
				items: targets.map(({ m }) => ({ folder: m.folder, uid: m.uid })),
				spam });
		} catch (e) { r = { error: e.message }; }
		const byKey = {};
		for (const res of r.results || []) {
			byKey[this.goneKey(res.folder, res.uid)] = res.result;
		}
		if (r.error) { this.error(r.error); resync = true; }
		if (r.errors?.length) { this.error(r.errors[0].error); }
		targets.forEach(({ m, expectMove }) => {
			const v = byKey[this.goneKey(m.folder, m.uid)];
			if (v) {
				if (v.strategy === 'move' && !expectMove) {
					// Move imprévu : la ligne est encore affichée - la retirer.
					if (!m.isSeen()) { this.adjustUnread(-1, m.folder); }
					this.messages.remove(m);
					this.closeViewerIfRemoved();
					moved = true;
				} else if (v.strategy !== 'move' && expectMove) {
					resync = true;
				}
			} else {
				if (expectMove && !m.isSeen()) { this.adjustUnread(1, m.folder); }
				resync = true;
			}
			this.unmarkMoved(m.folder, m.uid);
		});
		if (resync || moved) { await this.load(true); this.closeViewerIfRemoved(); }
	}
}
