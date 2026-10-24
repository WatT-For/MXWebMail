import { api } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { notify } from '../ui/notify.js';
import { t } from '../i18n.js';
import { parseMarker, FIELDS, OPS, ACTIONS, TEMPLATES, templateRules } from '../filters.js';

// Rôles de dossiers système assignables par l'utilisateur (INBOX exclu :
// nom réservé par le protocole IMAP, toujours la boîte de réception).
const SPECIAL_ROLES = ['sent', 'drafts', 'trash', 'junk', 'archive'];

// Libellés d'affichage des providers OAuth2 (noms de marque, non traduits —
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

		// Dossiers système (assignation par compte : auto | none | fullName ;
		// /folders/special renvoie {assigned, detected} par rôle)
		this.foldersTab = ko.observable('roles'); // sous-onglet : roles | notify
		this.folderRoles = ko.observableArray([]);
		this.folderRolesLoaded = false;

		// Dossiers surveillés (notifications) — abonnement par compte, INBOX
		// toujours incluse côté serveur → cochée + désactivée ici.
		this._notifySel = {};              // fullName => observable bool
		this.notifyFoldersLoaded = false;
		this.notifyPermission = ko.observable(notify.permission());
		// Valeurs en chaînes comme messages_per_page : évite le mismatch
		// number/string du binding value (user_defaults renvoie un int).
		this.refreshOptions = [
			{ value: '0', label: t('settings.list_refresh.off') },
			...[1, 2, 5, 10, 15, 30, 60].map(m => ({ value: String(m), label: m + ' min' })),
		];
		this.notifyChecks = ko.pureComputed(() =>
			this.app.folderList.folders().map(f => ({
				fullName: f.fullName,
				label: f.label,
				depth: f.depth,
				isInbox: f.isInbox,
				checked: this._notifySel[f.fullName] ??= ko.observable(!!f.isInbox),
			})));

		// Filtres Sieve
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
		// backdrop, boutons) → état propre à la réouverture.
		const filterModalEl = document.getElementById('filter-modal');
		this.filterModal = filterModalEl ? new bootstrap.Modal(filterModalEl) : null;
		filterModalEl?.addEventListener('hidden.bs.modal', () => {
			this.editingFilter(null);
			this.compiledSource('');
		});

		// Modale identité : même pattern — l'observable est reset à la
		// fermeture (Échap, backdrop, boutons) → état propre à la réouverture.
		const identityModalEl = document.getElementById('identity-modal');
		this.identityModal = identityModalEl ? new bootstrap.Modal(identityModalEl) : null;
		identityModalEl?.addEventListener('hidden.bs.modal', () => this.editingIdentity(null));
		// Taille de la modale en px (0 = défaut Bootstrap, pas d'inline)
		this.identW = ko.observable(0);
		this.identH = ko.observable(0);

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
	 * Entrée dans la vue : seed depuis les données du boot (/appdata) — les
	 * requêtes /settings et /appdata étaient des doublons qui se sérialisaient
	 * (verrou de session FPM / php -S mono-thread) et vidaient la vue pendant
	 * leur attente. Identités/contacts : lazy-load par onglet (activate()).
	 */
	async init() {
		this.saved(false);
		this.error('');
		// Copie : les bindings « value: values().x » écrivent dans l'objet —
		// sans spread, on muterait app.userSettings avant tout save.
		this.values({ ...this.app.userSettings });
		this.pluginTabs(this.app.pluginTabs || []);
		// popup_identity : identité par défaut sans nom → édition forcée.
		// await AVANT le hash : sinon (fetch lent) hashchange → activate('identities')
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
			'oauthapps', 'accounts', ...this.pluginTabs().map(t => t.id)];
		this.tab(known.includes(name) ? name : 'general');
		if (this.tab() !== 'filters') { this.filterModal?.hide(); }
		if (this.tab() !== 'identities') { this.identityModal?.hide(); }
		if (this.tab() === 'identities') { this.loadIdentities(); }
		if (this.tab() === 'contacts') { this.loadContacts(); }
		if (this.tab() === 'folders') {
			this.foldersTab(detail === 'notify' ? 'notify' : 'roles');
			if (this.foldersTab() === 'roles') { this.loadFolderRoles(); }
			else { this.loadNotifyFolders(); }
		}
		if (this.tab() === 'filters') { this.loadFilters(); }
		if (this.tab() === 'pgp') {
			this.pgpTab(detail === 'import' ? 'import' : 'keys');
			if (this.pgpTab() === 'keys') { this.loadPgpKeys(); }
		}
		if (this.tab() === 'oauthapps') { this.loadOauthApps(); }
		if (this.tab() === 'accounts') { this.loadAccounts(); }
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
	}

	// ─── Notifications ───

	/** La permission navigateur se demande au geste du checkbox. */
	async toggleNotify() {
		this.notifyPermission(await notify.request());
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

	// ─── Identités ───

	/** Lazy-load onglet identités. Promesse mémoïsée : appels concurrents
	 *  dédupliqués, erreur → reset pour retry à la prochaine visite. */
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
		// Signature legacy texte brut → HTML (sinon affichée sur une ligne)
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
		this.identityModal?.hide(); // hidden.bs.modal → editingIdentity(null)
		this._identitiesReq = null;
		await this.loadIdentities();
	}

	async deleteIdentity(i) {
		if (!(await dialog.confirm(t('settings.delete_identity', { email: i.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		await api.post('/identities/delete', { id: i.id });
		this._identitiesReq = null;
		await this.loadIdentities();
	}

	async defaultIdentity(i) {
		await api.post(`/identities/${i.id}/default`);
		this._identitiesReq = null;
		await this.loadIdentities();
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

	editContact(c) {
		this.editingContact({ ...(c || { name: '', emails: '' }) });
	}

	async saveContact() {
		const c = this.editingContact();
		await api.post('/contacts', c);
		this.editingContact(null);
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

	// ─── Dossiers système ───

	/**
	 * Options du select d'un rôle : « Automatique (<détecté>) » +
	 * « Désactivé » + dossiers existants. Une assignation hors liste
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

	// ─── Dossiers surveillés ───

	loadNotifyFolders() {
		if (this.notifyFoldersLoaded) { return; }
		this.notifyFoldersLoaded = true;
		api.get('/folders/notify').then(list => {
			for (const name of list) {
				(this._notifySel[name] ??= ko.observable(false))(true);
			}
		}).catch(e => { this.error(e.message); this.notifyFoldersLoaded = false; });
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
			this.filterModal?.hide(); // hidden.bs.modal → editingFilter(null)
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

	// f = item de ligne {name, active} ou objet d'édition (footer de la modale —
	// originalName y est un observable, absent des items de ligne).
	async deleteFilter(f) {
		const name = f.originalName?.() ?? f.name;
		if (!(await dialog.confirm(t('settings.delete_filter', { name }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post('/filters/delete', { name });
			this.filterModal?.hide(); // hidden.bs.modal → editingFilter(null)
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
