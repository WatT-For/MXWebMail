import { api, b64url } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { t, lang } from '../i18n.js';

/**
 * ViewModel : arborescence des dossiers IMAP.
 * Les dossiers sont plats côté IMAP ; on affiche la hiérarchie via le délimiteur.
 */

// Rôle effectif calculé serveur (special-use IMAP, assignation ou nom usuel)
// > clé i18n. f.special est un rôle exact en minuscules : lookup direct.
const ROLE_KEY = {
	inbox: 'folders.inbox', sent: 'folders.sent', drafts: 'folders.drafts',
	trash: 'folders.trash', junk: 'folders.junk',
	archive: 'folders.archive', all: 'folders.archive',
};

// Nom feuille usuel (insensible à la casse) > rôle : repli pour les dossiers
// non classés. Aligné sur FOLDER_FALLBACKS de MailService (la classification
// est serveur : assignation > special-use > nom usuel, hors dossiers créés
// via l'interface). N'est appliqué qu'aux positions candidates : racine ou
// enfant direct de INBOX - un "Projets/Envoyé" reste un dossier personnel.
const NAME_ROLE = {
	inbox: 'inbox',
	sent: 'sent', 'sent items': 'sent', 'envoyés': 'sent', 'envoyé': 'sent',
	'courrier envoyé': 'sent', 'éléments envoyés': 'sent', 'éléments envoyes': 'sent',
	drafts: 'drafts', brouillons: 'drafts', draft: 'drafts',
	trash: 'trash', deleted: 'trash', corbeille: 'trash', 'deleted items': 'trash',
	'éléments supprimés': 'trash', 'éléments supprimes': 'trash',
	junk: 'junk', spam: 'junk', 'courrier indésirable': 'junk',
	archive: 'archive', archives: 'archive', 'all mail': 'archive',
};

// Rôle > icône FontAwesome (archive/all et rôles exotiques > fa-folder).
const ROLE_ICON = {
	inbox: 'fa-inbox', sent: 'fa-paper-plane', drafts: 'fa-file-lines',
	trash: 'fa-trash', junk: 'fa-ban',
};

// Ordre canonique des dossiers de base en tête de liste (clé i18n > rang)
// - 'folders.archive' absent volontairement : le dossier de rôle archive est
// rangé dans la section des dossiers utilisateur, pas dans le groupe de tête.
const SYS_ORDER = {
	'folders.inbox': 0, 'folders.sent': 1, 'folders.drafts': 2,
	'folders.junk': 3, 'folders.trash': 4,
};

const isInbox = f => f.fullName.toUpperCase() === 'INBOX' || f.special === 'inbox';

/** Taille lisible (octets > o/Ko/Mo/Go, unités binaires) ; '-' si inconnue. */
const fmtSize = b => {
	if (b === null || b === undefined) { return '-'; }
	b = Number(b);
	if (!Number.isFinite(b) || b < 0) { return '-'; }
	if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
	if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
	if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
	return b + ' ' + t('unit.b');
};

export class FolderListVM {
	constructor(app) {
		this.app = app;
		this.folders = ko.observableArray([]);
		// Dossier affiché = dossier actif : source de vérité unique
		// (messageList.folder), synchro impossible à perdre (hash, F5,
		// navigation, notification).
		this.current = ko.pureComputed(() => this.app.messageList.folder());
		this.loading = ko.observable(false);
		this.error = ko.observable('');
		// Dossier de rôle "archive" - rôle effectif d'abord, repli nom-usuel
		// ensuite (ordre de detectedSpecialFolder) - null si absent.
		this.archiveFolder = ko.pureComputed(() =>
			this.folders().find(f => f.special === 'archive')
				?? this.folders().find(f => f.usual === 'archive') ?? null);
		// Dossier de rôle "junk" - null si absent. Même cible que
		// MailService::junkFolder() (prédiction de stratégie pour l'UI
		// optimiste de markSpam).
		this.junkFolder = ko.pureComputed(() =>
			this.folders().find(f => f.special === 'junk')
				?? this.folders().find(f => f.usual === 'junk') ?? null);
		// Objet dossier actuellement affiché (null avant le 1er chargement)
		this.currentFolder = ko.pureComputed(() =>
			this.folders().find(f => f.fullName === this.current()) || null);
		// 1er dossier visible hors groupe de tête > porteur du séparateur
		// visuel (le flag statique ne survivrait pas au repli de son parent).
		this.firstBottomName = ko.pureComputed(() =>
			(this.folders().find(f => !f.topGroup && !f.hidden()) || {}).fullName || '');
		this._expandT = null; // timer d'expansion d'un parent replié au survol
		this._expandF = null; // dossier pour lequel il est armé (dédup dragover)

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(FolderListVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	/**
	 * Lignes dossier depuis la réponse /folders : tri, liens parent/enfant,
	 * hidden/caretTip. Ne touche pas folders() - load() choisit ensuite entre
	 * remplacement complet et mise à jour in-place.
	 */
	buildRows(list) {
		const arr = list
			.filter(f => f.isSelectable !== false)
			.map(f => {
				const name = f.name || f.fullName.split(f.delimiter || '/').pop();
				const delim = f.delimiter || '';
				const cut = delim ? f.fullName.lastIndexOf(delim) : -1;
				const parentName = cut > 0 ? f.fullName.slice(0, cut) : null;
				// Rôle effectif calculé serveur (assignation > special-use > nom
				// usuel, sauf dossiers créés via l'interface) ; le nom usuel ne
				// sert qu'aux positions candidates (racine ou enfant de INBOX).
				const usual = (parentName === null || parentName.toUpperCase() === 'INBOX')
					? (NAME_ROLE[name.toLowerCase()] || null) : null;
				const key = ROLE_KEY[f.special] || ROLE_KEY[usual] || null;
				const isSys = !!f.system;
				// Groupe de tête = dossiers de base SAUF le rôle archive
				// (rangé dans la section utilisateur, label "Archives").
				const topGroup = isSys && key !== 'folders.archive';
				const row = {
					fullName: f.fullName,
					name,
					label: key && t(key) !== key ? t(key) : name,
					depth: f.delimiter ? (f.fullName.split(f.delimiter).length - 1) : 0,
					parentName,
					topGroup,
					collapsed: ko.observable(!!f.collapsed),
					unread: ko.observable(f.unread || 0),
					size: ko.observable(f.size ?? null),
					role: f.role || '',
					special: f.special || '',
					// Rôle du repli nom-usuel à position candidate (null sinon)
					// - signal indépendant de special, comme le serveur.
					usual,
					// Dossier "par défaut" : rôle effectif ou assigné - pas de renommage/ACL/suppression
					isInbox: isInbox(f),
					isSystem: isSys,
					sysRank: !topGroup ? 99 : (isInbox(f) ? 0 : (SYS_ORDER[key] ?? 6)),
				};
				// Computed : la colonne Taille suit les mises à jour in-place.
				row.sizeLabel = ko.pureComputed(() => fmtSize(row.size()));
				return row;
			})
			// Dossiers de base d'abord (ordre canonique), puis les autres par chemin
			// complet : le tri sur fullName garde les sous-dossiers sous leur parent.
			.sort((a, b) => (a.sysRank - b.sysRank)
				|| a.fullName.localeCompare(b.fullName, lang, { sensitivity: 'base' }));
		// Liens parent/enfant sur l'ensemble affiché : un parent hors liste
		// (conteneur non sélectionnable filtré) > null > jamais masqué.
		const byName = {};
		for (const f of arr) { byName[f.fullName] = f; }
		for (const f of arr) {
			f.parent = f.parentName ? (byName[f.parentName] || null) : null;
			f.hasChildren = arr.some(g => g.parentName === f.fullName);
			f.hidden = ko.pureComputed(() => {
				for (let p = f.parent; p; p = p.parent) {
					if (p.collapsed()) { return true; }
				}
				return false;
			});
			// Computed requis : le binding tooltip ne lit sa valeur qu'à
			// l'init - conf.text computed est relu par t() à l'affichage.
			f.caretTip = ko.pureComputed(() => !f.hasChildren ? ''
				: (f.collapsed() ? 'folders.expand' : 'folders.collapse'));
		}
		return arr;
	}

	async load() {
		this.loading(true);
		try {
			const arr = this.buildRows(await api.get('/folders'));
			const cur = this.folders();
			// Mise à jour in-place quand la structure est inchangée : badges et
			// tailles rafraîchis sur les objets existants - scroll, repli et
			// chevrons préservés, pas de re-render. La comparaison couvre la
			// classification : une réassignation de rôle (special) via
			// Paramètres ou un changement de hiérarchie force le rebuild.
			// unread/size exclus : ce sont les valeurs rafraîchies.
			const sameSet = arr.length === cur.length && arr.every((n, i) =>
				n.fullName === cur[i].fullName && n.name === cur[i].name
				&& n.role === cur[i].role && n.special === cur[i].special
				&& n.isSystem === cur[i].isSystem
				&& n.parentName === cur[i].parentName && n.depth === cur[i].depth);
			if (sameSet) {
				for (let i = 0; i < cur.length; i++) {
					cur[i].unread(arr[i].unread());
					cur[i].size(arr[i].size());
				}
			} else {
				this.folders(arr);
			}
			// Abonnement paresseux (et non au constructeur) : folderList est
			// instancié AVANT messageList dans AppVM - subscribe() évalue
			// immédiatement le pureComputed > messageList serait undefined.
			// Navigation vers un dossier masqué (hash, notification) >
			// expansion de ses ancêtres pour le rendre visible.
			this._navWatch ??= this.current.subscribe(() => this.expandActiveAncestors());
			// Boot/hash direct : le dossier actif peut être masqué par un
			// ancêtre replié (la subscription a pu tirer sur liste vide).
			this.expandActiveAncestors();
		} catch (e) {
			this.error(e.message);
		} finally {
			this.loading(false);
		}
	}

	isActive(folder) {
		return this.current() === folder.fullName;
	}

	iconFor(folder) {
		if (folder.fullName === 'INBOX') { return 'fa-inbox'; }
		// Rôle effectif s'il a une icône dédiée, sinon repli nom-usuel à
		// position candidate - un rôle exotique (\Flagged…) retombe sur le
		// nom, comme le faisaient les regex sur fullName.
		return ROLE_ICON[folder.special] || ROLE_ICON[folder.usual] || 'fa-folder';
	}

	/** Le dossier est-il le dossier Junk (rôle effectif ou nom usuel) ? */
	isJunkFolder(fullName) {
		const f = this.folders().find(x => x.fullName === fullName);
		return !!f && (f.special === 'junk' || f.usual === 'junk');
	}

	/** Le dossier est-il la Corbeille (rôle effectif ou nom usuel) ? */
	isTrashFolder(fullName) {
		const f = this.folders().find(x => x.fullName === fullName);
		return !!f && (f.special === 'trash' || f.usual === 'trash');
	}

	async select(folder) {
		await this.app.messageList.selectFolder(folder.fullName);
	}

	/** Bascule repli/extension - no-op sans enfants (chevron invisible). */
	toggleCollapse(f) {
		if (!f.hasChildren) { return; }
		f.collapsed(!f.collapsed());
		this.persistCollapsed();
	}

	/** Persiste l'état repli (debounce - clics rapides = 1 requête). */
	persistCollapsed() {
		clearTimeout(this._collapseT);
		this._collapseT = setTimeout(() => {
			api.post('/folders/collapsed', {
				folders: this.folders().filter(f => f.collapsed()).map(f => f.fullName),
			}).catch(() => {}); // cosmétique : silencieux comme savePaneSizes
		}, 400);
	}

	/** Étend les ancêtres du dossier actif s'il est masqué par un repli. */
	expandActiveAncestors() {
		const f = this.currentFolder();
		let changed = false;
		for (let p = f && f.parent; p; p = p.parent) {
			if (p.collapsed()) { p.collapsed(false); changed = true; }
		}
		if (changed) { this.persistCollapsed(); }
	}

	/** Modale de choix d'un dossier cible > Promise<fullName|null>. */
	async pickFolder(current) {
		const items = this.folders().map(f => ({
			label: f.label, value: f.fullName, icon: this.iconFor(f),
			depth: f.depth, disabled: f.fullName === current,
		}));
		return dialog.pick(items, {
			title: t('app.move_to'), icon: 'fa-file-export',
			searchable: items.length > 8,
		});
	}

	async createFolder() {
		const parents = [{ label: t('folders.parent_root'), value: '', depth: 0 },
			...this.folders().map(f => ({
				label: f.label, value: f.fullName, depth: f.depth }))];
		const r = await dialog.form({
			title: t('folders.new_title'), icon: 'fa-folder-plus',
			fields: [
				{ name: 'name',   label: t('folders.new_prompt'), type: 'text' },
				{ name: 'parent', label: t('folders.parent'),     type: 'select', options: parents },
			],
		});
		if (!r || !r.name?.trim()) { return; }
		await api.post('/folders/create', { name: r.name.trim(), parent: r.parent });
		await this.load();
	}

	async renameFolder(f) {
		const name = await dialog.prompt(t('folders.rename_prompt'),
			{ title: t('folders.rename_title'), defaultValue: f.name });
		if (!name || name === f.name) { return; }
		await api.post(`/folders/${b64url(f.fullName)}/rename`, { newName: name });
		await this.load();
	}

	async deleteFolder(f) {
		if (!(await dialog.confirm(t('folders.delete_confirm', { name: f.fullName }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post(`/folders/${b64url(f.fullName)}/delete`);
		await this.load();
	}

	async clearFolder(f) {
		if (!(await dialog.confirm(t('folders.clear_confirm', { name: f.fullName }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post(`/folders/${b64url(f.fullName)}/clear`);
		// Le message affiché dans le lecteur venait du dossier vidé > fermer.
		if (this.app.messageView.requestedFolder === f.fullName) {
			this.app.messageView.clear();
		}
		// Dossier affiché (cas du bouton d'en-tête) ou lignes du dossier encore
		// visibles (recherche multi-dossiers) > reload serveur (total, pagination).
		const ml = this.app.messageList;
		if (ml.folder() === f.fullName || ml.messages().some(m => m.folder === f.fullName)) {
			ml.cancelReadTimer();
			ml.page(1);
			await ml.load();
		}
		await this.load();
	}

	async markAllRead(f) {
		await api.post(`/folders/${b64url(f.fullName)}/mark-read`);
		f.unread(0);
		// Dossier courant : marque les modèles chargés sans reload.
		if (f.fullName === this.app.messageList.folder()) {
			this.app.messageList.messages().forEach(m => m.isSeen(true));
			const open = this.app.messageView.message();
			if (open && open.folder === f.fullName) { open.isSeen(true); }
		}
	}

	/** Boutons d'en-tête de la colonne : actions sur le dossier affiché. */
	async markAllReadCurrent() {
		const f = this.currentFolder();
		if (f) { await this.markAllRead(f); }
	}

	async clearCurrent() {
		const f = this.currentFolder();
		if (f && !f.isInbox) { await this.clearFolder(f); }
	}

	async editAcl(f) {
		let acl;
		try {
			acl = await api.get(`/folders/${b64url(f.fullName)}/acl`);
		} catch (e) {
			await dialog.alert(t('folders.acl_unsupported', { msg: e.message }),
				{ variant: 'danger' });
			return;
		}
		const current = acl.map(a => `${a.identifier} : ${a.rights}${a.mine ? t('folders.acl_me') : ''}`).join('\n');
		const identifier = await dialog.prompt(
			t('folders.acl_prompt', { current }),
			{ title: t('folders.acl_title') });
		if (!identifier) { return; }
		const rights = await dialog.prompt(
			t('folders.acl_rights', { id: identifier }),
			{ title: t('folders.acl_title') });
		if (rights === null) { return; }
		if (rights.trim() === '') {
			await api.post(`/folders/${b64url(f.fullName)}/acl/delete`, { identifier });
		} else {
			await api.post(`/folders/${b64url(f.fullName)}/acl`, { identifier, rights: rights.trim() });
		}
	}

	// ─── Glisser-déposer de messages (cible = ligne dossier) ───

	/** Cible invalide : tous les messages déjà dans f (no-op) ou Corbeille
	 *  interdite - déplacer vers la Corbeille = supprimer, refusé serveur
	 *  quand dangerous_actions est désactivée. */
	_dropInvalid(f, list) {
		return list.every(m => m.folder === f.fullName)
			|| (this.isTrashFolder(f.fullName)
				&& this.app.features().dangerous_actions === false);
	}

	/** Retire le surlignage de cible de tous les items - à chaque dragover,
	 *  ne touche pas au timer d'expansion (_expandF/_expandT). */
	_hlClear() {
		document
			.querySelectorAll('.folder-pane .mx-drop, .folder-pane .mx-drop-danger')
			.forEach(el => el.classList.remove('mx-drop', 'mx-drop-danger'));
	}

	/** Nettoyage complet en fin de drag (drop sur un item, dragend source). */
	dropCleanup() {
		this._hlClear();
		clearTimeout(this._expandT);
		this._expandT = this._expandF = null;
	}

	folderDragOver(f, e) {
		this._hlClear();
		const list = this.app.messageList.dragItems();
		if (list === null) { return true; } // drag étranger (fichier, texte…)
		// Expansion au survol d'un parent replié - avant le test de validité :
		// une cible invalide (dossier courant) doit s'étendre pour atteindre
		// ses sous-dossiers. Dédupliqué par _expandF : dragover tire en
		// continu, un réarmement à chaque event empêcherait le timer d'aboutir.
		if (f.hasChildren && f.collapsed() && this._expandF !== f) {
			clearTimeout(this._expandT);
			this._expandF = f;
			this._expandT = setTimeout(() => {
				this._expandT = this._expandF = null;
				f.collapsed(false);
				this.persistCollapsed();
			}, 600);
		}
		if (this._dropInvalid(f, list)) { return true; }
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		e.currentTarget.classList.add(
			this.isTrashFolder(f.fullName) ? 'mx-drop-danger' : 'mx-drop');
		return true;
	}

	folderDragLeave(f, e) {
		// Entrée dans un élément enfant de la ligne (span, badge) : pas un vrai
		// départ - sinon le surlignage clignoterait à chaque passage.
		if (e.currentTarget.contains(e.relatedTarget)) { return true; }
		e.currentTarget.classList.remove('mx-drop', 'mx-drop-danger');
		if (this._expandF === f) {
			clearTimeout(this._expandT);
			this._expandT = this._expandF = null;
		}
		return true;
	}

	folderDrop(f, e) {
		e.preventDefault();
		const list = this.app.messageList.dragItems();
		this.dropCleanup();
		if (list !== null && !this._dropInvalid(f, list)) {
			this.app.messageList.moveMessages(list, f.fullName);
		}
		return true;
	}
}
