import { api, b64url } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { t, lang } from '../i18n.js';

/**
 * ViewModel : arborescence des dossiers IMAP.
 * Les dossiers sont plats côté IMAP ; on affiche la hiérarchie via le délimiteur.
 */

// Rôle effectif calculé serveur (special-use IMAP, assignation ou nom usuel)
// → clé i18n. f.special est un rôle exact en minuscules : lookup direct.
const ROLE_KEY = {
	inbox: 'folders.inbox', sent: 'folders.sent', drafts: 'folders.drafts',
	trash: 'folders.trash', junk: 'folders.junk',
	archive: 'folders.archive', all: 'folders.archive',
};

// Nom feuille usuel (insensible à la casse) → clé i18n : repli de label
// traduit pour les dossiers non classés. Aligné sur FOLDER_FALLBACKS de
// MailService (la classification est serveur : assignation > special-use >
// nom usuel, hors dossiers créés via l'interface).
const NAME_KEY = {
	inbox: 'folders.inbox',
	sent: 'folders.sent', 'sent items': 'folders.sent',
	'envoyés': 'folders.sent', 'courrier envoyé': 'folders.sent',
	drafts: 'folders.drafts', brouillons: 'folders.drafts', draft: 'folders.drafts',
	trash: 'folders.trash', deleted: 'folders.trash', corbeille: 'folders.trash',
	'deleted items': 'folders.trash',
	junk: 'folders.junk', spam: 'folders.junk',
	'courrier indésirable': 'folders.junk',
	archive: 'folders.archive', archives: 'folders.archive',
	'all mail': 'folders.archive',
};

// Ordre canonique des dossiers de base en tête de liste (clé i18n → rang)
const SYS_ORDER = {
	'folders.inbox': 0, 'folders.sent': 1, 'folders.drafts': 2,
	'folders.archive': 3, 'folders.junk': 4, 'folders.trash': 5,
};

const isInbox = f => f.fullName.toUpperCase() === 'INBOX' || f.special === 'inbox';

/** Taille lisible (octets → o/Ko/Mo/Go, unités binaires) ; '—' si inconnue. */
const fmtSize = b => {
	if (b === null || b === undefined) { return '—'; }
	b = Number(b);
	if (!Number.isFinite(b) || b < 0) { return '—'; }
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
		// Dossier de rôle « archive » (assignation > special-use > nom usuel,
		// déjà résolu serveur en f.special) — null si absent.
		this.archiveFolder = ko.pureComputed(() =>
			this.folders().find(f => f.special === 'archive') || null);
		// Dossier de rôle « junk » — null si absent. f.special est résolu
		// serveur : même cible que MailService::junkFolder() (prédiction de
		// stratégie pour l'UI optimiste de markSpam).
		this.junkFolder = ko.pureComputed(() =>
			this.folders().find(f => f.special === 'junk') || null);
		// Objet dossier actuellement affiché (null avant le 1er chargement)
		this.currentFolder = ko.pureComputed(() =>
			this.folders().find(f => f.fullName === this.current()) || null);

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(FolderListVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	async load() {
		this.loading(true);
		try {
			const list = await api.get('/folders');
			const arr = list
				.filter(f => f.isSelectable !== false)
				.map(f => {
					const name = f.name || f.fullName.split(f.delimiter || '/').pop();
					// Rôle effectif calculé serveur (assignation > special-use > nom
					// usuel, sauf dossiers créés via l'interface) ; le nom usuel ne
					// sert ici qu'au label traduit.
					const key = ROLE_KEY[f.special] || NAME_KEY[name.toLowerCase()] || null;
					const isSys = !!f.system;
					return {
						fullName: f.fullName,
						name,
						label: key && t(key) !== key ? t(key) : name,
						depth: f.delimiter ? (f.fullName.split(f.delimiter).length - 1) : 0,
						unread: ko.observable(f.unread || 0),
						size: f.size ?? null,
						sizeLabel: fmtSize(f.size),
						role: f.role || '',
						special: f.special || '',
						// Dossier « par défaut » : rôle effectif ou assigné — pas de renommage/ACL/suppression
						isInbox: isInbox(f),
						isSystem: isSys,
						sysRank: !isSys ? 99 : (isInbox(f) ? 0 : (SYS_ORDER[key] ?? 6)),
						sep: false,
					};
				})
				// Dossiers de base d'abord (ordre canonique), puis les autres par chemin
				// complet : le tri sur fullName garde les sous-dossiers sous leur parent.
				.sort((a, b) => (a.sysRank - b.sysRank)
					|| a.fullName.localeCompare(b.fullName, lang, { sensitivity: 'base' }));
			// Séparation visuelle sur le premier dossier non-système
			const firstUser = arr.findIndex(f => !f.isSystem);
			if (firstUser > 0) { arr[firstUser].sep = true; }
			this.folders(arr);
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
		const role = (folder.role || '').toLowerCase();
		if (role.includes('inbox') || folder.fullName === 'INBOX') { return 'fa-inbox'; }
		if (role.includes('sent') || /sent|envoy/i.test(folder.fullName)) { return 'fa-paper-plane'; }
		if (role.includes('draft') || /brouillon|draft/i.test(folder.fullName)) { return 'fa-file-lines'; }
		if (role.includes('trash') || /corbeille|trash/i.test(folder.fullName)) { return 'fa-trash'; }
		if (/junk|spam/.test(role) || /spam|junk|courrier ind/i.test(folder.fullName)) { return 'fa-ban'; }
		return 'fa-folder';
	}

	/** Le dossier est-il le dossier Junk (rôle effectif ou nom usuel) ? */
	isJunkFolder(fullName) {
		const f = this.folders().find(x => x.fullName === fullName);
		if (!f) { return false; }
		return f.special === 'junk'
			|| /junk/.test((f.role || '').toLowerCase())
			|| /spam|junk|courrier ind/i.test(f.fullName);
	}

	/** Le dossier est-il la Corbeille (rôle effectif ou nom usuel) ? */
	isTrashFolder(fullName) {
		const f = this.folders().find(x => x.fullName === fullName);
		if (!f) { return false; }
		return f.special === 'trash'
			|| /trash/.test((f.role || '').toLowerCase())
			|| /corbeille|trash|deleted/i.test(f.fullName);
	}

	async select(folder) {
		await this.app.messageList.selectFolder(folder.fullName);
	}

	/** Modale de choix d'un dossier cible → Promise<fullName|null>. */
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
		// Le message affiché dans le lecteur venait du dossier vidé → fermer.
		if (this.app.messageView.requestedFolder === f.fullName) {
			this.app.messageView.clear();
		}
		// Dossier affiché (cas du bouton d'en-tête) ou lignes du dossier encore
		// visibles (recherche multi-dossiers) → reload serveur (total, pagination).
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
}
