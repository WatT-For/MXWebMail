import { api, base, b64url } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { notify } from '../ui/notify.js';
import { toast } from '../ui/toast.js';
import { t } from '../i18n.js';
import { parseMarker, FIELDS, OPS, ACTIONS, TEMPLATES, templateRules } from '../filters.js';

// Rôles de dossiers système assignables par l'utilisateur (INBOX exclu :
// nom réservé par le protocole IMAP, toujours la boîte de réception).
const SPECIAL_ROLES = ['sent', 'drafts', 'trash', 'junk', 'archive'];

// Libellés d'affichage des providers OAuth2 (noms de marque, non traduits -
// même liste que la modale domaine de l'admin).
const OAUTH_PROVIDERS = {
	google: 'Google (Gmail)', microsoft: 'Microsoft (Outlook)', yahoo: 'Yahoo',
};

/**
 * ViewModel : page paramètres (hash #/@settings/<onglet>).
 * Onglets : général, identités, contacts, extensions (hooks plugins).
 */
export class SettingsVM {
	constructor(app) {
		this.app = app;
		this.tab = ko.observable('general');
		this.error = ko.observable('');
		this.saved = ko.observable(false);

		// Général
		this.values = ko.observable({});
		this.pluginTabs = ko.observableArray([]); // onglets settings.tabs.* injectés par plugins

		// Identités
		this.identities = ko.observableArray([]);
		this.editingIdentity = ko.observable(null);
		this.identitiesLoading = ko.observable(false);
		this._identitiesReq = null; // promesse mémoïsée : flag + dédup + refetch

		// Contacts
		this.contacts = ko.observableArray([]);
		this.editingContact = ko.observable(null);
		this.contactsLoading = ko.observable(false);
		this._contactsReq = null;
		// Pagination côté client : le carnet est déjà chargé en entier
		// (GET /contacts > all()), inutile de requêter par page.
		this.contactsPage = ko.observable(1);
		this.contactsPerPage = 25;
		this.contactsTotalPages = ko.pureComputed(() =>
			Math.max(1, Math.ceil(this.contacts().length / this.contactsPerPage)));
		// Page bornée : suppression du dernier contact d'une page, carnet
		// rechargé plus court > la page courante se replie sans reset brutal.
		this.contactsPageEff = ko.pureComputed(() =>
			Math.min(this.contactsPage(), this.contactsTotalPages()));
		this.pagedContacts = ko.pureComputed(() => this.contacts().slice(
			(this.contactsPageEff() - 1) * this.contactsPerPage,
			this.contactsPageEff() * this.contactsPerPage));

		// Comptes : id du compte en cours de glisser-déposer
		this._dragId = null;

		// Sessions "se souvenir de moi" (jetons persistants)
		this.sessions = ko.observableArray([]);
		this._sessionsLoaded = false;
		this.sessionDetail = ko.observable(null); // session affichée dans la modale

		// Dossiers système (assignation par compte : auto | none | fullName ;
		// /folders/special renvoie {assigned, detected} par rôle)
		this.foldersTab = ko.observable('manage'); // sous-onglet : manage | roles | import
		// Import .eml (sous-onglet "Importer" de l'onglet Dossiers)
		this.importFolder = ko.observable('INBOX');
		this.importProgress = ko.observable(null);
		this.importBusy = ko.observable(false);
		this.folderRoles = ko.observableArray([]);
		this.folderRolesLoaded = false;

		// Dossiers surveillés (notifications) - abonnement par compte, INBOX
		// toujours incluse côté serveur > cochée + désactivée ici.
		this._notifySel = {};              // fullName => observable bool
		this.notifyFoldersLoaded = false;
		// Valeurs en chaînes comme messages_per_page : évite le mismatch
		// number/string du binding value (user_defaults renvoie un int).
		this.refreshOptions = [
			{ value: '0', label: t('settings.list_refresh.off') },
			...[1, 2, 5, 10, 15, 30, 60].map(m => ({ value: String(m), label: m + ' min' })),
		];
		this.notifyChecks = ko.pureComputed(() =>
			this.app.folderList.folders().map(f => ({
				fullName: f.fullName,
				name: f.name,
				label: f.label,
				depth: f.depth,
				isInbox: f.isInbox,
				isSystem: f.isSystem,
				folder: f,
				checked: this._notifySel[f.fullName] ??= ko.observable(!!f.isInbox),
			})));
		// Colonne "Taille" masquée si le serveur ne remonte aucune taille
		// (STATUS=SIZE non annoncé, RFC 8438).
		this.hasFolderSize = ko.pureComputed(() =>
			this.app.folderList.folders().some(f => f.size() != null));

		// Filtres Sieve
		this.filtersTab = ko.observable('list'); // sous-onglet : list | vacation
		this.filters = ko.observableArray([]);
		this.sieveModules = ko.observableArray([]); // extensions SIEVE annoncées
		this.editingFilter = ko.observable(null);
		this.filterMode = ko.observable('assistant'); // assistant | source
		this.compiledSource = ko.observable('');      // prévisualisation assistant
		this.filtersError = ko.observable('');
		this.filtersLoaded = false;
		this.filterTemplates = TEMPLATES;
		this.filterFields = FIELDS;
		// Ops/actions masqués quand le serveur n'annonce pas l'extension
		this.filterOps = ko.pureComputed(() =>
			OPS.filter(o => !o.module || this.sieveModules().includes(o.module)));
		this.filterActions = ko.pureComputed(() =>
			ACTIONS.filter(a => !a.module || this.sieveModules().includes(a.module)));
		this.filterFolders = ko.pureComputed(() =>
			this.app.folderList.folders().map(f => ({
				label: '  '.repeat(f.depth) + f.label, value: f.fullName })));

		// Modale d'édition : l'observable est reset à la fermeture (Échap,
		// backdrop, boutons) > état propre à la réouverture.
		const filterModalEl = document.getElementById('filter-modal');
		this.filterModal = filterModalEl ? new bootstrap.Modal(filterModalEl) : null;
		filterModalEl?.addEventListener('hidden.bs.modal', () => {
			this.editingFilter(null);
			this.compiledSource('');
		});

		// Modale identité : même pattern - l'observable est reset à la
		// fermeture (Échap, backdrop, boutons) > état propre à la réouverture.
		const identityModalEl = document.getElementById('identity-modal');
		this.identityModal = identityModalEl ? new bootstrap.Modal(identityModalEl) : null;
		identityModalEl?.addEventListener('hidden.bs.modal', () => this.editingIdentity(null));

		// Modale contact : même pattern - reset à la fermeture.
		const contactModalEl = document.getElementById('contact-modal');
		this.contactModal = contactModalEl ? new bootstrap.Modal(contactModalEl) : null;
		contactModalEl?.addEventListener('hidden.bs.modal', () => this.editingContact(null));

		// Modale détails session : même pattern - reset à la fermeture.
		const sessionModalEl = document.getElementById('session-modal');
		this.sessionModal = sessionModalEl ? new bootstrap.Modal(sessionModalEl) : null;
		sessionModalEl?.addEventListener('hidden.bs.modal', () => this.sessionDetail(null));
		// Taille de la modale en px (0 = défaut Bootstrap, pas d'inline)
		this.identW = ko.observable(0);
		this.identH = ko.observable(0);

		// Réponses types
		this.responses = ko.observableArray([]);
		this.editingResponse = ko.observable(null);
		this.responsesLoaded = false;

		// Répondeur d'absence (Sieve vacation)
		this.vacation = ko.observable(null);
		this.vacationManaged = ko.observable(true);
		// Choix ":addresses" : email du compte actif + ses identités (le
		// serveur n'accepte de toute façon que ce sous-ensemble).
		this.vacationAddressChoices = ko.pureComputed(() => {
			const acct = this.app.accounts().find(a => a.active);
			const acctId = acct ? Number(acct.id) : null;
			const emails = acct ? [String(acct.email)] : [];
			for (const i of this.identities()) {
				const e = String(i.email || '');
				if (e && !emails.includes(e)
					&& (acctId === null || Number(i.account_id) === acctId)) {
					emails.push(e);
				}
			}
			return emails;
		});

		// OpenPGP
		this.pgpKeys = ko.observableArray([]);
		this.pgpEmail = ko.observable('');
		this.pgpArmored = ko.observable('');
		this.pgpPrivate = ko.observable(false);
		this.pgpTab = ko.observable('keys');
		this.pgpLoaded = false;

		// Applications OAuth2 (identifiants d'app par utilisateur)
		this.oauthApps = ko.observableArray([]);
		this.oauthProviders = ko.observableArray([]); // autorisés par les domaines
		this.oauthRedirectUri = ko.observable('');
		this.oauthAppsLoaded = false;

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(SettingsVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	/**
	 * Entrée dans la vue : seed depuis les données du boot (/appdata) - les
	 * requêtes /settings et /appdata étaient des doublons qui se sérialisaient
	 * (verrou de session FPM / php -S mono-thread) et vidaient la vue pendant
	 * leur attente. Identités/contacts : lazy-load par onglet (activate()).
	 */
	async init() {
		this.saved(false);
		this.error('');
		// Copie : les bindings "value: values().x" écrivent dans l'objet -
		// sans spread, on muterait app.userSettings avant tout save.
		this.values({ ...this.app.userSettings });
		this.pluginTabs(this.app.pluginTabs || []);
		// popup_identity : identité par défaut sans nom > édition forcée.
		// await AVANT le hash : sinon (fetch lent) hashchange > activate('identities')
		// puis le .then du routeFromHash initial ferait activate('general').
		if (this.app.popupIdentityPending) {
			this.app.popupIdentityPending = false;
			const ids = await this.loadIdentities();
			location.hash = '#/@settings/identities';
			const def = ids.find(i => Number(i.is_default)) || ids[0];
			if (def) { this.editIdentity(def); }
		}
	}

	/**
	 * Routeur : active un onglet depuis le hash (#/@settings/<tab>[/<sous-onglet>]).
	 * Whitelist = onglets cœur + ids pluginTabs ; lazy-load par onglet.
	 */
	activate(name, detail) {
		if (name === 'contacts' && this.app.features().contacts === false) {
			name = 'general';
		}
		// Comptes secondaires désactivés : les deux onglets liés retombent sur
		// Général (couvre aussi le deep-link #/@settings/oauthapps).
		if (['accounts', 'oauthapps'].includes(name)
			&& this.app.features().accounts === false) {
			name = 'general';
		}
		const known = ['general', 'identities', 'contacts', 'folders', 'filters', 'pgp',
			'sessions', 'oauthapps', 'accounts', 'responses', ...this.pluginTabs().map(t => t.id)];
		this.tab(known.includes(name) ? name : 'general');
		if (this.tab() !== 'filters') { this.filterModal?.hide(); }
		if (this.tab() !== 'identities') { this.identityModal?.hide(); }
		if (this.tab() !== 'contacts') { this.contactModal?.hide(); }
		if (this.tab() === 'identities') { this.loadIdentities(); }
		if (this.tab() === 'contacts') { this.loadContacts(); }
		if (this.tab() === 'sessions') { this.loadSessions(); }
		if (this.tab() === 'folders') {
			this.foldersTab(['manage', 'roles', 'import'].includes(detail) ? detail : 'manage');
			if (this.foldersTab() === 'roles') { this.loadFolderRoles(); }
			else if (this.foldersTab() === 'manage') { this.loadNotifyFolders(); }
		}
		if (this.tab() === 'filters') {
			this.filtersTab(detail === 'vacation' ? 'vacation' : 'list');
			this.loadFilters();
		}
		if (this.tab() === 'pgp') {
			this.pgpTab(detail === 'import' ? 'import' : 'keys');
			if (this.pgpTab() === 'keys') { this.loadPgpKeys(); }
		}
		if (this.tab() === 'oauthapps') { this.loadOauthApps(); }
		if (this.tab() === 'accounts') { this.loadAccounts(); }
		if (this.tab() === 'responses') { this.loadResponses(); }
	}

	async saveSettings() {
		this.saved(false);
		await api.post('/settings', this.values());
		this.saved(true);
		// Réglages appliqués immédiatement (pas de rechargement nécessaire)
		this.app.userSettings = { ...this.app.userSettings, ...this.values() };
		this.app.theme(this.values().theme || 'default');
		this.app.layout(this.values().layout === 'vertical' ? 'vertical' : 'horizontal');
		// resynchronise aussi la hauteur (persistée par drag, jamais éditée ici)
		const mh = parseInt(this.values().message_pane_height, 10);
		if (mh >= 120 && mh <= 1200) { this.app.messagePaneHeight(mh); }
		// Réactualisation + notifications appliquées à chaud
		this.app.armAutoRefresh(Math.min(60, Math.max(0,
			parseInt(this.values().list_refresh, 10) || 0)));
		this.app.notifyEnabled(!!this.values().notify_new_mail);
		const threads = !!this.values().list_threads;
		if (threads !== this.app.messageList.useThreads()) {
			this.app.messageList.useThreads(threads);
			this.app.messageList.page(1);
			this.app.messageList.load();
		}
		// Pagination : limite + rechargement immédiat de la liste
		const mpp = parseInt(this.values().messages_per_page, 10);
		if (mpp > 0 && mpp !== this.app.messageList.limit) {
			this.app.messageList.limit = mpp;
			this.app.messageList.page(1);
			this.app.messageList.load();
		}
		// Déconnexion après inactivité : ré-armement (0 = désarmé)
		this.app.armAutologout(parseInt(this.values().autologout, 10) || 0);
	}

	// ─── Notifications ───

	/** La permission navigateur se demande au geste du checkbox. */
	async toggleNotify() {
		await notify.request();
		return true; // laisser le binding checked écrire la valeur
	}

	// ─── Comptes ───

	/** Refresh de la liste partagée app.accounts (seedée au boot par /appdata). */
	loadAccounts() {
		api.get('/accounts').then(a => this.app.accounts(a)).catch(() => {});
	}

	/** Ré-édition des identifiants : même flow que l'ajout (update côté serveur). */
	editAccount(a) {
		this.app.addAccount(a.email);
	}

	async defaultAccount(a) {
		try {
			await api.post(`/accounts/${a.id}/default`);
			this.app.accounts(await api.get('/accounts'));
		} catch (e) { this.error(e.message); }
	}

	async deleteAccount(a) {
		if (!(await dialog.confirm(t('settings.account_delete_confirm', { email: a.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post(`/accounts/${a.id}/delete`);
			this.app.accounts(await api.get('/accounts'));
		} catch (e) { this.error(e.message); }
	}

	// Glisser-déposer des comptes secondaires : réordonne app.accounts puis
	// POST l'ordre complet - le principal (is_default) reste épingle en tête.

	/** Zone de dépose : 'before' | 'after' selon la moitié de ligne survolée. */
	_dropZone(e) {
		const r = e.currentTarget.getBoundingClientRect();
		return e.clientY > r.top + r.height / 2 ? 'after' : 'before';
	}

	_accountDragClear(tr) {
		tr.closest('tbody')
			.querySelectorAll('.mx-dragover, .mx-dragover-after')
			.forEach(el => el.classList.remove('mx-dragover', 'mx-dragover-after'));
	}

	accountDragStart(a, e) {
		if (Number(a.is_default)) { return false; }
		this._dragId = Number(a.id);
		// setData requis par Firefox pour que le drag démarre
		e.dataTransfer.setData('text/plain', String(a.id));
		e.dataTransfer.effectAllowed = 'move';
		e.currentTarget.classList.add('mx-dragging');
		return true;
	}

	accountDragOver(a, e) {
		const tr = e.currentTarget;
		this._accountDragClear(tr);
		// Cible non valide (principal ou ligne source) : pas de preventDefault
		// > le navigateur affiche le curseur "interdit" et refuse le drop.
		if (this._dragId === null || Number(a.is_default) || Number(a.id) === this._dragId) {
			return true;
		}
		e.preventDefault();
		tr.classList.add(this._dropZone(e) === 'after' ? 'mx-dragover-after' : 'mx-dragover');
		return true;
	}

	accountDrop(a, e) {
		e.preventDefault();
		this._accountDragClear(e.currentTarget);
		if (this._dragId === null || Number(a.is_default) || Number(a.id) === this._dragId) {
			return true;
		}
		const list = this.app.accounts().slice();
		const from = list.findIndex(x => Number(x.id) === this._dragId);
		if (from < 0) { return true; }
		const [moved] = list.splice(from, 1);
		// indexOf post-retrait : insertion avant/après la ligne cible
		list.splice(list.indexOf(a) + (this._dropZone(e) === 'after' ? 1 : 0), 0, moved);
		if (list.every((x, i) => x === this.app.accounts()[i])) { return true; } // inchangé
		this.app.accounts(list);
		api.post('/accounts/order', {
			ids: list.filter(x => !Number(x.is_default)).map(x => x.id),
		}).catch(err => {
			this.error(err.message);
			this.loadAccounts(); // resync depuis le serveur
		});
		return true;
	}

	accountDragEnd(a, e) {
		this._dragId = null;
		e.currentTarget.classList.remove('mx-dragging');
		this._accountDragClear(e.currentTarget);
		return true;
	}

	// ─── Identités ───

	/** Lazy-load onglet identités. Promesse mémoïsée : appels concurrents
	 *  dédupliqués, erreur > reset pour retry à la prochaine visite. */
	loadIdentities() {
		if (!this._identitiesReq) {
			this.identitiesLoading(true);
			this._identitiesReq = api.get('/identities')
				.then(d => { this.identities(d); return d; })
				.catch(() => { this._identitiesReq = null; return this.identities(); })
				.finally(() => this.identitiesLoading(false));
		}
		return this._identitiesReq;
	}

	editIdentity(i) {
		const s = i || {};
		// Signature legacy texte brut > HTML (sinon affichée sur une ligne)
		let sig = s.signature || '';
		if (sig && !/<[a-z!][^>]*>/i.test(sig)) {
			sig = sig.replace(/&/g, '&amp;').replace(/</g, '&lt;')
				.replace(/\r?\n/g, '<br>');
		}
		// Le binding signatureEditor exige des observables pour réécrire les champs
		this.editingIdentity({
			id: s.id, account_id: s.account_id, is_default: s.is_default,
			name: ko.observable(s.name || ''),
			email: ko.observable(s.email || ''),
			reply_to: ko.observable(s.reply_to || ''),
			bcc: ko.observable(s.bcc || ''),
			signature: ko.observable(sig),
		});
		this.identityModal?.show();
		// Focus du champ nom une fois la modale affichée (pattern search-modal)
		document.getElementById('identity-modal')?.addEventListener('shown.bs.modal',
			() => document.getElementById('identity-name')?.focus(), { once: true });
	}

	async saveIdentity() {
		const i = this.editingIdentity();
		await api.post('/identities', ko.toJS(i));
		this.identityModal?.hide(); // hidden.bs.modal > editingIdentity(null)
		this._identitiesReq = null;
		await this.loadIdentities();
		this._syncComposeIdentities();
	}

	async deleteIdentity(i) {
		if (!(await dialog.confirm(t('settings.delete_identity', { email: i.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post('/identities/delete', { id: i.id });
		this._identitiesReq = null;
		await this.loadIdentities();
		this._syncComposeIdentities();
	}

	async defaultIdentity(i) {
		await api.post(`/identities/${i.id}/default`);
		this._identitiesReq = null;
		await this.loadIdentities();
		this._syncComposeIdentities();
	}

	/** Après toute mutation d'identité : resynchronise la liste du compose
	 *  (sinon signature/expéditeur périmés jusqu'au prochain rechargement). */
	_syncComposeIdentities() {
		this.app.compose.setIdentities(this.identities());
	}

	// ─── Contacts ───

	/** Lazy-load onglet contacts (contacts_enable = garde côté client, 403 sinon). */
	loadContacts() {
		if (!this._contactsReq && this.app.features().contacts !== false) {
			this.contactsLoading(true);
			this._contactsReq = api.get('/contacts')
				.then(d => { this.contacts(d); return d; })
				.catch(() => { this._contactsReq = null; return this.contacts(); })
				.finally(() => this.contactsLoading(false));
		}
		return this._contactsReq || Promise.resolve(this.contacts());
	}

	contactsPrevPage() {
		this.contactsPage(Math.max(1, this.contactsPageEff() - 1));
	}

	contactsNextPage() {
		this.contactsPage(Math.min(this.contactsTotalPages(), this.contactsPageEff() + 1));
	}

	editContact(c) {
		const cats = (c?.properties?.categories || []).join(', ');
		// id toujours présent (null à la création) : le titre de la modale
		// teste "id ? contact_edit : contact_new" - clé absente > ReferenceError KO.
		this.editingContact({ ...(c || { id: null, name: '', emails: '' }), categories: cats });
		this.contactModal?.show();
		// Focus du champ nom une fois la modale affichée (pattern identity-modal)
		document.getElementById('contact-modal')?.addEventListener('shown.bs.modal',
			() => document.getElementById('contact-name')?.focus(), { once: true });
	}

	async saveContact() {
		const c = this.editingContact();
		// Champ "Groupes" (CSV) > properties.categories, les autres clés
		// de properties (org/tel/…) sont préservées.
		const cats = (c.categories || '').split(',').map(s => s.trim()).filter(Boolean);
		c.properties = { ...(c.properties || {}), categories: cats };
		if (!cats.length) { delete c.properties.categories; }
		delete c.categories;
		await api.post('/contacts', c);
		this.contactModal?.hide(); // hidden.bs.modal > editingContact(null)
		this._contactsReq = null;
		await this.loadContacts();
	}

	async deleteContact(c) {
		if (!(await dialog.confirm(t('settings.delete_contact', { name: c.name || c.emails }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post('/contacts/delete', { id: c.id });
		this._contactsReq = null;
		await this.loadContacts();
	}

	/** Import .vcf/.csv/.sqlite (carnet RainLoop/SnappyMail) - dédup serveur. */
	async importContacts(vm, e) {
		const input = e.target;
		const file = input.files && input.files[0];
		input.value = ''; // re-sélection du même fichier = nouvel import
		if (!file) { return; }
		const fd = new FormData();
		fd.append('file', file);
		try {
			const r = await api.upload('/contacts/import', fd);
			toast.success(t('settings.contacts_import_done', r),
				{ title: t('settings.import') });
			this._contactsReq = null;
			await this.loadContacts();
		} catch (err) {
			await dialog.alert(err.message,
				{ title: t('settings.import'), variant: 'danger' });
		}
	}

	/** Export du carnet en .vcf - navigation GET, téléchargement direct. */
	exportContacts() {
		location.href = base() + '/api/contacts/export';
	}

	// ─── Dossiers système ───

	/**
	 * Options du select d'un rôle : "Automatique (<détecté>)" +
	 * "Désactivé" + dossiers existants. Une assignation hors liste
	 * (dossier disparu) est ajoutée pour afficher la valeur courante.
	 */
	roleOptions(r) {
		const opts = [
			{ label: t('settings.folders.auto')
				+ (r.detected ? ` (${r.detected})` : ''), value: 'auto' },
			{ label: t('settings.folders.none'), value: 'none' },
			...this.app.folderList.folders().map(f => ({
				label: '  '.repeat(f.depth) + f.label, value: f.fullName })),
		];
		const v = r.value();
		if (v && v !== 'auto' && v !== 'none' && !opts.some(o => o.value === v)) {
			opts.push({ label: v, value: v });
		}
		return opts;
	}

	loadFolderRoles() {
		if (this.folderRolesLoaded) { return; }
		this.folderRolesLoaded = true;
		api.get('/folders/special').then(map => {
			this.folderRoles(SPECIAL_ROLES.map(role => ({
				role, label: `folders.${role}`,
				detected: map[role]?.detected || '',
				value: ko.observable(map[role]?.assigned || 'auto'),
			})));
		}).catch(e => { this.error(e.message); this.folderRolesLoaded = false; });
	}

	async saveFolderRoles() {
		const map = {};
		for (const item of this.folderRoles()) { map[item.role] = item.value(); }
		await api.post('/folders/special', map);
		this.saved(true);
		await this.app.folderList.load();
	}

	// ─── Onglet Dossiers (surveillance + gestion) ───

	loadNotifyFolders() {
		if (this.notifyFoldersLoaded) { return; }
		this.notifyFoldersLoaded = true;
		api.get('/folders/notify').then(list => {
			for (const name of list) {
				(this._notifySel[name] ??= ko.observable(false))(true);
			}
		}).catch(e => { this.error(e.message); this.notifyFoldersLoaded = false; });
	}

	/** Import de fichiers .eml/.mbox dans le dossier choisi (IMAP APPEND
	 *  serveur, un fichier par requête - progression n/N affichée ; la
	 *  réponse d'un .mbox compte ses messages : {imported, failed}). */
	async importEmlFiles(d, e) {
		const files = [...e.target.files];
		e.target.value = '';
		if (!files.length || this.importBusy()) { return; }
		this.importBusy(true);
		let done = 0; let msgs = 0; let failed = 0; let skipped = 0;
		for (const file of files) {
			this.importProgress(t('settings.import_running', { n: done + 1, total: files.length }));
			const fd = new FormData();
			fd.append('file', file);
			try {
				const r = await api.upload(`/folders/${b64url(this.importFolder())}/import`, fd);
				msgs += r?.imported ?? 1; // .eml > {uid}, pas de compteur ; 0 réel = échec total mbox
				failed += r?.failed ?? 0;
				skipped += r?.skipped ?? 0; // plafonnés côté serveur (non importés, non échoués)
				done++;
			} catch (err) { this.error(err.message); break; }
		}
		this.importBusy(false);
		this.importProgress(null);
		if (done) {
			toast.success(t('app.import_done', { n: msgs })
				+ (failed ? ' - ' + t('app.import_failed', { n: failed }) : '')
				+ (skipped ? ' - ' + t('app.import_skipped', { n: skipped }) : ''));
			this.app.folderList.load();
			if (this.app.messageList.folder() === this.importFolder()) {
				this.app.messageList.load();
			}
		}
	}

	// ─── Sessions ───

	fmtTs(ts) {
		return ts ? new Date(Number(ts) * 1000).toLocaleString() : '-';
	}

	async loadSessions() {
		if (this._sessionsLoaded) { return; }
		this.sessions(await api.get('/sessions').catch(() => []));
		this._sessionsLoaded = true;
	}

	/** Résumé "Navigateur · OS" d'un User-Agent (parse léger, affichage). */
	agentInfo(ua) {
		ua = String(ua || '');
		if (!ua) { return '-'; }
		const has = (re) => re.test(ua);
		// Ordre significatif : les UA Chrome/Edge/Opera contiennent aussi "Safari".
		const browser = has(/Edg(e|A|iOS)?\//) ? 'Edge'
			: has(/OPR\/|Opera/) ? 'Opera'
			: has(/Firefox\//) ? 'Firefox'
			: has(/Chrom(e|ium)\//) ? 'Chrome'
			: has(/Safari\//) ? 'Safari'
			: has(/MSIE |Trident\//) ? 'IE' : '';
		const os = has(/Windows NT/) ? 'Windows'
			: has(/Android/) ? 'Android'
			: has(/iPhone|iPad|CPU OS/) ? 'iOS'
			: has(/Mac OS X/) ? 'macOS'
			: has(/CrOS/) ? 'ChromeOS'
			: has(/Linux/) ? 'Linux' : '';
		const label = [browser, os].filter(Boolean).join(' · ');
		return label || (ua.length > 60 ? ua.slice(0, 57) + '…' : ua);
	}

	showSession(s) {
		this.sessionDetail(s);
		this.sessionModal?.show();
	}

	async deleteSession(s) {
		if (!(await dialog.confirm(t('settings.session_delete_confirm'),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post(`/sessions/${s.id}/delete`);
		this.sessions.remove(s);
		if (this.sessionDetail() === s) { this.sessionModal?.hide(); }
	}

	async revokeOtherSessions() {
		if (!(await dialog.confirm(t('settings.sessions_revoke_confirm'),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post('/sessions/revoke-others');
		this._sessionsLoaded = false;
		await this.loadSessions();
	}

	async saveNotifyFolders() {
		const names = this.notifyChecks()
			.filter(r => !r.isInbox && r.checked())
			.map(r => r.fullName);
		await api.post('/folders/notify', { folders: names });
		this.saved(true);
		// Synchronise le polling sans recharger (INBOX toujours côté serveur)
		this.app.notifyFolders(['INBOX', ...names]);
	}

	// ─── Filtres Sieve ───

	async loadFilters() {
		// Sieve non configuré pour le domaine : notice statique dans le
		// template, pas d'appel voué à un 400.
		if (this.filtersLoaded || this.app.features().sieve === false) { return; }
		this.filtersError('');
		try {
			const r = await api.get('/filters');
			this.filters(r.scripts);
			this.sieveModules(r.modules);
			this.filtersLoaded = true;
		} catch (e) {
			this.filtersError(e.message); // ex. Sieve non configuré pour le domaine
		}
		this.loadVacation();
	}

	// ─── Répondeur d'absence ───

	async loadVacation() {
		this.loadIdentities(); // mémoïsé - peuple vacationAddressChoices
		try {
			const r = await api.get('/filters/vacation');
			if (r.modules) { this.sieveModules(r.modules); }
			this.vacationManaged(r.managed !== false);
			const v = r.vacation || {};
			this.vacation({
				enabled: ko.observable(!!v.enabled),
				subject: ko.observable(v.subject || ''),
				message: ko.observable(v.message || ''),
				days: ko.observable(v.days ?? 1),
				from: ko.observable(v.from || ''),
				to: ko.observable(v.to || ''),
				addresses: ko.observableArray(v.addresses || []),
			});
		} catch (e) { this.filtersError(e.message); }
	}

	async saveVacation() {
		const v = this.vacation();
		try {
			await api.post('/filters/vacation', {
				enabled: v.enabled(), subject: v.subject(), message: v.message(),
				days: parseInt(v.days(), 10) || 1, from: v.from(), to: v.to(),
				addresses: v.addresses(),
			});
			this.saved(true);
			// Relecture : reflète la normalisation serveur (tout décoché >
			// repli sur l'email du compte, adresses hors whitelist purgées).
			await this.loadVacation();
		} catch (e) { this.filtersError(e.message); }
	}

	// ─── Réponses types ───

	loadResponses() {
		if (this.responsesLoaded) { return; }
		api.get('/responses')
			.then(d => { this.responses(d); this.responsesLoaded = true; })
			.catch(e => this.error(e.message));
	}

	editResponse(r) {
		this.editingResponse(r
			? { id: r.id, name: ko.observable(r.name), body: ko.observable(r.body) }
			: { id: 0, name: ko.observable(''), body: ko.observable('') });
	}

	async saveResponse() {
		const r = this.editingResponse();
		await api.post('/responses', { id: r.id, name: r.name(), body: r.body() });
		this.editingResponse(null);
		this.responsesLoaded = false;
		this.loadResponses();
		this.saved(true);
	}

	async deleteResponse(r) {
		if (!(await dialog.confirm(t('settings.responses_delete', { name: r.name }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post(`/responses/${r.id}/delete`);
		this.responsesLoaded = false;
		this.loadResponses();
	}

	// Règle KO = observables {match, conditions, actions} ; sérialisée en doc
	// JSON à la sauvegarde (compilation ScriptBuilder côté serveur).
	_koCond(c) {
		return { field: ko.observable(c.field ?? 'from'),
			op: ko.observable(c.op ?? 'contains'),
			value: ko.observable(c.value ?? ''),
			header: ko.observable(c.header ?? 'X-') };
	}
	_koAction(a) {
		return { type: ko.observable(a.type ?? 'keep'),
			arg: ko.observable(a.arg ?? '') };
	}
	_koRule(r) {
		return { match: ko.observable(r?.match ?? 'all'),
			conditions: ko.observableArray((r?.conditions ?? [{}]).map(c => this._koCond(c))),
			actions: ko.observableArray((r?.actions ?? [{}]).map(a => this._koAction(a))) };
	}
	_filterDoc() {
		return this.editingFilter().rules().map(r => ({
			match: r.match(),
			conditions: r.conditions().map(c => {
				const o = { field: c.field(), op: c.op(), value: c.value() };
				if (o.field === 'header') { o.header = c.header(); }
				return o;
			}),
			actions: r.actions().map(a => {
				const o = { type: a.type() };
				if (['fileinto', 'redirect', 'reject'].includes(o.type)) { o.arg = a.arg(); }
				return o;
			}),
		}));
	}
	// Type de paramètre d'une action ('folder'|'email'|'text'|null)
	filterArgKind(type) {
		return ACTIONS.find(a => a.value === type)?.arg ?? null;
	}
	_junkFolder() {
		const folders = this.app.folderList.folders();
		return (folders.find(f => f.special === 'junk')
			?? folders.find(f => this.app.folderList.isJunkFolder(f.fullName)))
			?.fullName ?? 'Junk';
	}

	newFilter(tpl = 'empty') {
		this.filtersError('');
		this.filterMode('assistant');
		this.compiledSource('');
		this.editingFilter({ name: tpl === 'empty' ? '' : t('settings.filters.tpl_' + tpl),
			source: '', managed: true, originalName: ko.observable(null),
			active: ko.observable(false),
			error: ko.observable(''), // erreur dans la modale (alerte racine masquée par le backdrop)
			rules: ko.observableArray(
				templateRules(tpl, { junk: this._junkFolder() }).map(r => this._koRule(r))) });
		this.filterModal?.show();
	}

	async editFilter(f) {
		this.filtersError('');
		try {
			const r = await api.get('/filters/script?name=' + encodeURIComponent(f.name));
			const doc = parseMarker(r.source);
			this.compiledSource('');
			this.filterMode(doc ? 'assistant' : 'source');
			// Script externe (sans marqueur) : mode source seul, assistant masqué.
			this.editingFilter({ name: r.name, source: r.source, managed: !!doc,
				originalName: ko.observable(r.name), active: ko.observable(!!f.active),
				error: ko.observable(''),
				rules: ko.observableArray(doc ? doc.rules.map(x => this._koRule(x)) : []) });
			this.filterModal?.show();
		} catch (e) { this.filtersError(e.message); }
	}

	async saveFilter() {
		const f = this.editingFilter();
		f.error('');
		try {
			const payload = { name: f.name, old_name: f.originalName() };
			if (this.filterMode() === 'assistant' && f.managed) {
				await api.post('/filters/script', { ...payload, rules: this._filterDoc() });
			} else {
				await api.post('/filters/script', { ...payload, source: f.source });
			}
			this.filterModal?.hide(); // hidden.bs.modal > editingFilter(null)
			this.filtersLoaded = false;
			await this.loadFilters();
		} catch (e) { f.error(e.message); }
	}

	async previewFilter() {
		const f = this.editingFilter();
		try {
			const r = await api.post('/filters/compile', { rules: this._filterDoc() });
			this.compiledSource(r.source);
		} catch (e) { f.error(e.message); }
	}

	addFilterRule() { this.editingFilter().rules.push(this._koRule()); }
	addFilterCond(rule) { rule.conditions.push(this._koCond({})); }
	addFilterAction(rule) { rule.actions.push(this._koAction({})); }

	// Appelé depuis la ligne du tableau : f = {name, active} (item de filters()).
	async activateFilter(f) {
		this.filtersError('');
		try {
			await api.post('/filters/activate', { name: f.name });
			this.filtersLoaded = false;
			await this.loadFilters();
		} catch (e) { this.filtersError(e.message); }
	}

	// SETACTIVE "" côté serveur : désactive le script actif, quel qu'il soit.
	async deactivateFilter() {
		this.filtersError('');
		try {
			await api.post('/filters/deactivate');
			this.filtersLoaded = false;
			await this.loadFilters();
		} catch (e) { this.filtersError(e.message); }
	}

	// f = item de ligne {name, active} ou objet d'édition (footer de la modale -
	// originalName y est un observable, absent des items de ligne).
	async deleteFilter(f) {
		const name = f.originalName?.() ?? f.name;
		if (!(await dialog.confirm(t('settings.delete_filter', { name }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post('/filters/delete', { name });
			this.filterModal?.hide(); // hidden.bs.modal > editingFilter(null)
			this.filtersLoaded = false;
			await this.loadFilters();
		} catch (e) {
			if (ko.isObservable(f.error)) { f.error(e.message); }
			else { this.filtersError(e.message); }
		}
	}

	// ─── OpenPGP ───

	async loadPgpKeys() {
		if (this.pgpLoaded) { return; }
		this.pgpKeys(await api.get('/pgp/keys').catch(() => []));
		this.pgpLoaded = true;
	}

	async savePgpKey() {
		await api.post('/pgp/keys', {
			email: this.pgpEmail(),
			armored: this.pgpArmored(),
			private: this.pgpPrivate(),
		});
		this.pgpArmored('');
		this.pgpLoaded = false;
		await this.loadPgpKeys();
		location.hash = '#/@settings/pgp/keys';
	}

	async deletePgpKey(k) {
		if (!(await dialog.confirm(t('settings.delete_pgp_key', { email: k.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post(`/pgp/keys/${k.id}/delete`);
		this.pgpLoaded = false;
		await this.loadPgpKeys();
	}

	// ─── Applications OAuth2 ───

	loadOauthApps() {
		if (this.oauthAppsLoaded) { return; }
		this.oauthAppsLoaded = true;
		api.get('/oauth/apps').then(d => {
			this.oauthApps(d.apps || []);
			this.oauthProviders(d.providers || []);
			this.oauthRedirectUri(d.redirect_uri || '');
		}).catch(e => { this.error(e.message); this.oauthAppsLoaded = false; });
	}

	oauthProviderLabel(p) {
		return OAUTH_PROVIDERS[p] || p;
	}

	async addOauthApp() {
		const taken = new Set(this.oauthApps().map(a => a.provider));
		const items = this.oauthProviders().filter(p => !taken.has(p))
			.map(p => ({ label: this.oauthProviderLabel(p), value: p }));
		if (!items.length) { return; }
		const provider = await dialog.pick(items, { title: t('settings.oauth_apps') });
		if (!provider) { return; }
		await this.editOauthApp({ provider, client_id: '', tenant: '' });
	}

	async editOauthApp(a) {
		const fields = [
			{ name: 'client_id', label: t('settings.oauth_client_id'),
				defaultValue: a.client_id },
			{ name: 'secret', label: t('settings.oauth_secret'), inputType: 'password',
				placeholder: a.client_id ? t('settings.oauth_secret_keep') : '' },
		];
		if (a.provider === 'microsoft') {
			fields.push({ name: 'tenant', label: t('settings.oauth_tenant'),
				defaultValue: a.tenant || 'common' });
		}
		const v = await dialog.form({
			title: this.oauthProviderLabel(a.provider),
			message: t('settings.oauth_redirect_uri') + ' : ' + this.oauthRedirectUri(),
			fields, okLabel: t('common.save'),
		});
		if (v === null) { return; }
		await api.post('/oauth/apps', { provider: a.provider, ...v });
		this.oauthAppsLoaded = false;
		this.loadOauthApps();
	}

	async deleteOauthApp(a) {
		if (!(await dialog.confirm(t('settings.oauth_delete_confirm',
				{ provider: this.oauthProviderLabel(a.provider), n: a.accounts }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post('/oauth/apps/delete', { provider: a.provider });
		this.oauthAppsLoaded = false;
		this.loadOauthApps();
	}
}
