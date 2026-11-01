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
		this.showLoader = ko.observable(false); // voile centré — false pour les chargements d'arrière-plan (poll)
		this._loadCount = 0;   // requêtes en vol (toutes) — garde contre les loads concurrents
		this._fgLoadCount = 0; // requêtes de premier plan en vol (pilotent showLoader)
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
		// null = inconnu (avant la 1ère réponse dossier) — distingue « pas
		// encore chargé » de « non supporté » pour la note des réglages.
		this.threadsSupported = ko.observable(null);
		this.threadChildren = {}; // parentUid → MessageModel[] insérés sous la ligne
		// Messages retirés optimistement dont le POST n'est pas soldé — un
		// load() (poll…) passé avant la fin du MOVE serveur ne doit pas les
		// faire réapparaître. Clés 'folder\nuid', retirées à la fin du POST.
		this._gone = new Set();
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
		// ≥ 2 → la zone de lecture affiche le compteur de multi-sélection
		this.checkedCount = ko.pureComputed(() => this.checkedMessages().length);
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
			// La recherche multi-dossiers renvoie threadsSupported:false — elle
			// ne doit pas écraser l'état connu du dossier (item de menu masqué).
			if (!global) { this.threadsSupported(!!data.threadsSupported); }
			this.threadChildren = {};
			this.total(data.total || 0);
			this.messages((data.messages || [])
				.filter(m => !this._gone.has(this.goneKey(m.folder, m.uid)))
				.map(m => new MessageModel(m)));
		} catch (e) {
			this.error(e.message);
		} finally {
			if (--this._loadCount === 0) { this.loading(false); }
			if (!silent && --this._fgLoadCount === 0) { this.showLoader(false); }
		}
	}

	async selectFolder(folder) {
		this.cancelReadTimer();
		// Re-clic sur le dossier actif : reload sans fermer le mail ouvert.
		if (folder !== this.folder()) {
			this.app.messageView.clear();
		}
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
			this.adjustUnread(-1, message.folder);
		}
	}

	/** Annule le marquage « lu » différé en attente (mark_read_delay > 0). */
	cancelReadTimer() {
		clearTimeout(this._readTimer);
		this._readTimer = null;
	}

	/** Ajuste localement le badge non-lus d'un dossier (dossier courant par défaut). */
	adjustUnread(delta, folder = this.folder()) {
		const f = this.app.folderList.folders().find(f => f.fullName === folder);
		if (f) { f.unread(Math.max(0, f.unread() + delta)); }
	}

	/** Clé _gone d'un message (folder+uid — les UID sont par dossier). */
	goneKey(folder, uid) {
		return folder + '\n' + uid;
	}

	/** Fin de suivi optimiste d'un déplacement (POST soldé). */
	unmarkMoved(folder, uid) {
		this._gone.delete(this.goneKey(folder, uid));
	}

	/** Retrait optimiste d'un message en cours de déplacement : badge non-lu,
	 *  ligne de la liste, suivi _gone. m = MessageModel ou message() du
	 *  lecteur (mêmes champs folder/uid/isSeen). */
	hideMoved(m) {
		this._gone.add(this.goneKey(m.folder, m.uid));
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

	/** Bascule le mode conversation — persisté dans list_threads. */
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

	/** Évènement `search` de l'input[type=search] : émis par l'icône « × » native
	 *  (Blink/WebKit) et par Enter sous Blink (déjà traité en keydown). Ne recharge
	 *  que si le champ a été vidé et que la requête appliquée diffère — les
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
				// m.folder (dossier réel du message) : en recherche multi-dossiers
				// il diffère du dossier courant — les UID sont par dossier.
				await api.post(`/messages/${m.uid}/flags`, { folder: m.folder, flag, set });
				if (flag === '\\Seen' && m.isSeen() !== set) {
					m.isSeen(set);
					this.adjustUnread(set ? -1 : 1, m.folder);
				}
				if (flag === '\\Flagged') { m.isFlagged(set); }
			} catch (e) { this.error(e.message); }
		}
	}

	/** Voisin à sélectionner quand uid va être retiré de la liste
	 *  (réglage auto_advance : next|prev|none). Non mutateur — à appeler
	 *  AVANT le retrait, sur la liste encore complète ; le repli bascule
	 *  sur l'autre côté (ex. dernier élément + mode next → précédent). */
	peekAdvance(uid, folder) {
		const mode = this.app.userSettings?.auto_advance || 'next';
		if (mode === 'none' || uid == null) { return null; }
		const list = this.messages();
		// En recherche multi-dossiers le même uid existe dans plusieurs dossiers :
		// la correspondance porte aussi sur le dossier quand il est fourni.
		const i = list.findIndex(m => m.uid === uid && (folder === undefined || m.folder === folder));
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

	/** Retrait optimiste groupé (actions batch) : suivi _gone + badge non-lu
	 *  + removeAll en UNE notification KO (remove() par item re-rendrait la
	 *  liste à chaque ligne — effet « disparition une par une »).
	 *  Retourne le voisin à rouvrir pour advanceOrClear. */
	optimisticRemove(list) {
		const mv = this.app.messageView;
		const next = this.peekAdvance(mv.requestedUid, mv.requestedFolder);
		for (const m of list) {
			this._gone.add(this.goneKey(m.folder, m.uid));
			if (!m.isSeen()) { this.adjustUnread(-1, m.folder); }
		}
		// Sous-lignes des fils dépliés : retirées avec leur racine, en une
		// seule notification KO (elles ne sont pas _gone — toujours en IMAP).
		this.messages.removeAll([...list,
			...list.flatMap(m => this.threadChildren[m.uid] || [])]);
		list.forEach(m => delete this.threadChildren[m.uid]);
		return next;
	}

	/** Solde un POST batch : les clés _gone partent AVANT tout load() de
	 *  resync — sinon elles filtreraient les messages restaurés. */
	async settleBatch(list, fail) {
		list.forEach(m => this.unmarkMoved(m.folder, m.uid));
		if (fail) { this.error(fail); await this.load(); }
	}

	async bulkDelete() {
		const list = this.checkedMessages();
		// Sélection multi-dossiers : suppression définitive dès qu'un message
		// est dans la corbeille (le serveur expunge depuis Trash uniquement).
		const key = list.some(m => this.app.folderList.isTrashFolder(m.folder))
			? 'messages.delete_confirm_perm' : 'messages.delete_confirm';
		if (!list.length || !(await dialog.confirm(t(key, { n: list.length }),
			{ variant: 'danger', okLabel: t('common.delete'), title: t('messages.delete_title') }))) { return; }
		const next = this.optimisticRemove(list);
		await this.advanceOrClear(next);
		let fail = null;
		try {
			const r = await api.post('/messages/delete',
				{ items: list.map(m => ({ folder: m.folder, uid: m.uid })) });
			if (r.errors?.length) { fail = r.errors[0].error; }
		} catch (e) { fail = e.message; }
		await this.settleBatch(list, fail);
	}

	async bulkMove(target) {
		const list = this.checkedMessages().filter(m => m.folder !== target);
		if (!list.length) { return; } // tout est déjà dans le dossier cible
		const next = this.optimisticRemove(list);
		await this.advanceOrClear(next);
		let fail = null;
		try {
			const r = await api.post('/messages/move',
				{ items: list.map(m => ({ folder: m.folder, uid: m.uid })), to: target });
			if (r.errors?.length) { fail = r.errors[0].error; }
		} catch (e) { fail = e.message; }
		await this.settleBatch(list, fail);
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

	/** Télécharge les messages cochés en .eml dans un zip par dossier source
	 *  (GET direct — un zip par dossier en recherche multi-dossiers). */
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
		const mv = this.app.messageView;
		const next = this.peekAdvance(mv.requestedUid, mv.requestedFolder);
		const junkName = this.app.folderList.junkFolder()?.fullName;
		const targets = [];
		for (const m of this.checkedMessages()) {
			// Déjà dans l'état voulu : le serveur renverrait 'move' sans rien
			// déplacer (ex. spam sur un message déjà dans Junk) — sautés.
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
		if (moved) { await this.advanceOrClear(next); }
		// POSTs en fond ; échec ou stratégie divergente → resync de la liste.
		let resync = false;
		for (const { m, expectMove } of targets) {
			try {
				const r = await api.post(`/messages/${m.uid}/spam`, { folder: m.folder, spam });
				if (r.strategy === 'move' && !expectMove) {
					// Move imprévu : la ligne est encore affichée — la retirer.
					if (!m.isSeen()) { this.adjustUnread(-1, m.folder); }
					this.messages.remove(m);
				} else if (r.strategy !== 'move' && expectMove) {
					resync = true;
				}
			} catch (e) {
				this.error(e.message);
				if (expectMove && !m.isSeen()) { this.adjustUnread(1, m.folder); }
				resync = true;
			}
			this.unmarkMoved(m.folder, m.uid);
		}
		if (resync) { await this.load(); }
	}
}
