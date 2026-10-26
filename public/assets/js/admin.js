/**
 * MXWebMail — panneau d'administration (/admin).
 * Données initiales : une seule requête /admin/bootstrap — évite la rafale
 * d'appels sérialisés (verrou de session FPM / serveur dev mono-thread).
 */
import { api, base } from './api.js';
import { dialog } from './ui/dialog.js';
import { t, lang } from './i18n.js';
import './bindings/tooltip.js';
import './bindings/plugin-mount.js';

class AdminVM {
	constructor() {
		this.t = t;       // bindings : $root.t('cle')
		this.lang = lang; // locale courante (toLocaleString…)
		this.tab = ko.observable('general');
		this.error = ko.observable('');
		this.notice = ko.observable('');
		this.maintenance = ko.observable(false);
		this.domains = ko.observableArray([]);
		this.users = ko.observableArray([]);
		this.plugins = ko.observableArray([]);
		// Catalogue GitHub (PluginPackageService) : install/update/remove
		this.pluginsErr = ko.observable('');
		this.pluginCatalog = ko.observable(null); // {configured, plugins:[…]}
		this.catalogLoaded = ko.observable(false);
		this.catalogBusy = ko.observable(false);
		this.pluginBusy = ko.observable('');      // nom du plugin en cours d'opération
		this.catalogAvailable = ko.pureComputed(() =>
			(this.pluginCatalog()?.plugins || []).filter(p => !p.installed));
		// Blocs UI déclarés par les plugins (hook admin.mounts → bootstrap)
		this.adminMounts = ko.observableArray([]);
		this.logs = ko.observableArray([]);
		this.logFile = ko.observable('app');
		this.editingDomain = ko.observable(null);
		this.editingPlugin = ko.observable(null);
		this.editingTheme = ko.observable(null);
		this.editingUser = ko.observable(null);
		this.editingUserIdentity = ko.observable(null);
		this.userSearch = ko.observable('');
		this.usersBusy = ko.observable(false);
		this.userModalTab = ko.observable('profile');
		this.domainModalTab = ko.observable('general');
		this.filteredUsers = ko.pureComputed(() => {
			const q = this.userSearch().trim().toLowerCase();
			return q
				? this.users().filter(u =>
					(u.username || '').toLowerCase().includes(q)
					|| (u.display_name || '').toLowerCase().includes(q))
				: this.users();
		});
		this.themes = ko.observableArray([]);
		this.themeDefault = ko.observable('default');
		this.settings = ko.observable(null);
		this.about = ko.observable(null);
		this.updateInfo = ko.observable(null);   // statut local (bootstrap)
		this.updateResult = ko.observable(null); // résultat du dernier check
		this.updateBusy = ko.observable('');     // 'check' | 'download' | 'apply' | ''
		this.updateErr = ko.observable('');
		this.updateStep = ko.pureComputed(() => this.updateInfo()?.state?.step || '');
		this.aboutExtensions = ko.pureComputed(() =>
			Object.entries(this.about()?.extensions || {}).map(([name, ok]) => ({ name, ok: !!ok })));
		this.aboutMissingCount = ko.pureComputed(() =>
			this.aboutExtensions().filter(e => !e.ok).length);
		this.newPass = ko.observable('');
		this.newPass2 = ko.observable('');
		this.favicon = ko.observable('');      // nom du fichier dans storage/branding ('' = défaut)
		this.faviconFile = ko.observable(null); // File sélectionné, en attente d'envoi
		this.faviconV = ko.observable(Date.now()); // cache-buster du preview
		this.faviconUrl = ko.pureComputed(() => base() + '/favicon.ico?v=' + this.faviconV());
		this.formSections = {};
		for (const sec of ['general', 'domains', 'database', 'features', 'security', 'logs', 'performance']) {
			this.formSections[sec] = ko.observableArray([]);
		}
		this.subtabs = {
			security: ko.observable('account'),
			logs: ko.observable('viewer'),
			features: ko.observable('features'),
			domains: ko.observable('list'),
			performance: ko.observable('http'),
		};
		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(AdminVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
		// Modales de config plugin / template : l'observable est reset à la
		// fermeture (Échap, backdrop, boutons) → état propre à la réouverture.
		const pluginModalEl = document.getElementById('plugin-config-modal');
		const themeModalEl = document.getElementById('theme-config-modal');
		const userModalEl = document.getElementById('user-modal');
		const domainModalEl = document.getElementById('domain-modal');
		this.pluginConfigModal = pluginModalEl ? new bootstrap.Modal(pluginModalEl) : null;
		this.themeConfigModal = themeModalEl ? new bootstrap.Modal(themeModalEl) : null;
		this.userModal = userModalEl ? new bootstrap.Modal(userModalEl) : null;
		this.domainModal = domainModalEl ? new bootstrap.Modal(domainModalEl) : null;
		pluginModalEl?.addEventListener('hidden.bs.modal', () => this.editingPlugin(null));
		themeModalEl?.addEventListener('hidden.bs.modal', () => this.editingTheme(null));
		userModalEl?.addEventListener('hidden.bs.modal', () => {
			this.editingUser(null);
			this.editingUserIdentity(null);
		});
		domainModalEl?.addEventListener('hidden.bs.modal', () => this.editingDomain(null));
		// Navigation pilotée par l'URL : #/<page>[/<sous-onglet>]
		window.addEventListener('hashchange', this.syncFromHash);
		this.syncFromHash();
		// Catalogue plugins chargé à la première ouverture de l'onglet Extensions
		this.tab.subscribe(v => { if (v === 'plugins') { this.ensurePluginsCatalog(); } });
	}
	async init() {
		const b = await api.get('/admin/bootstrap').catch(e => { this.error(e.message); return null; });
		if (!b) { return; }
		const s = b.settings || null;
		this.settings(s);
		this.maintenance(!!s?.values?.maintenance);
		this.favicon(s?.values?.favicon || '');
		for (const sec of Object.keys(this.formSections)) {
			this.formSections[sec](this.buildFields(sec, s || {}));
		}
		this.domains((b.domains || []).map(this.mapDomain));
		this.users(b.users || []);
		this.plugins((b.plugins || []).map(this.mapPlugin));
		if (this.tab() === 'plugins') { this.ensurePluginsCatalog(); }
		this.adminMounts(b.adminMounts || []);
		// Les sous-onglets de plugins n'étaient pas connus au syncFromHash()
		// du constructeur — re-sync pour les deep-links (#/security/2fa…).
		this.syncFromHash();
		this.themeDefault(b.themes?.default || 'default');
		this.themes(this.mapThemes(b.themes?.items));
		this.about(b.about || null);
		this.updateInfo(b.update || null);
		this.loadLogs();
	}
	ok(msg) { this.notice(msg); setTimeout(() => this.notice(''), 4000); }

	// Favicon : upload multipart (champ 'file') → storage/branding + config.
	async uploadFavicon() {
		const f = this.faviconFile();
		if (!f) { return; }
		const fd = new FormData();
		fd.append('file', f);
		try {
			const r = await api.upload('/admin/favicon', fd);
			this.favicon(r.favicon || '');
			this.faviconFile(null);
			this.faviconV(Date.now());
			const input = document.getElementById('favicon-input');
			if (input) { input.value = ''; }
			this.ok(t('settings.saved'));
		} catch (e) { this.error(e.message); }
	}
	async deleteFavicon() {
		try {
			await api.post('/admin/favicon/delete');
			this.favicon('');
			this.faviconV(Date.now());
			this.ok(t('settings.saved'));
		} catch (e) { this.error(e.message); }
	}

	// Taille lisible (octets → o/Ko/Mo/Go, unités binaires).
	formatSize(bytes) {
		if (bytes === null || bytes === undefined) { return ''; }
		const b = Number(bytes);
		if (!Number.isFinite(b) || b < 0) { return ''; }
		if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
		if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
		if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
		return b + ' ' + t('unit.b');
	}

	buildFields(sec, r) {
		const fields = r.schema?.[sec]?.fields || {};
		return Object.entries(fields).map(([key, f]) => {
			const [a, b] = key.split('.');
			let v = b !== undefined ? r.values[a]?.[b] : r.values[key];
			if (v === undefined) { v = f.default; }
			if (f.type === 'bool') { v = !!v; }
			if (f.type === 'enum' && v !== undefined) { v = String(v); }
			const field = {
				key, type: f.type || 'string', label: f.label || key, help: f.help || '',
				group: f.group || '',
				optionsList: Object.entries(f.options || {}),
				value: ko.observable(v ?? ''),
			};
			if (sec === 'domains' && key === 'default_domain') {
				// Select alimenté par les domaines de la table ; la valeur
				// courante est conservée si le domaine n'y figure plus.
				// peek() : ne pas dépendre de value — sinon chaque sélection
				// recrée les options et KO réécrit le modèle (childrenComplete).
				field.optionsList = ko.pureComputed(() => {
					const cur = field.value.peek();
					const names = this.domains().map(d => d.name);
					const list = [['', 'admin.domain_none'], ['auto', 'admin.domain_from_host'], ...names.map(n => [n, n])];
					if (cur !== '' && !names.includes(cur)) { list.push([cur, cur]); }
					return list;
				});
			}
			return field;
		});
	}
	fieldsIn(sec, group) {
		return this.formSections[sec]().filter(f => f.group === group);
	}
	adminMountsIn(section) {
		return this.adminMounts().filter(m => m.section === section);
	}
	// Sous-onglets déclarés par les plugins (mounts avec « tab ») — filtrés aux
	// plugins activés pour que le hash routing rejette les onglets disparus.
	adminTabsIn(page) {
		return this.adminMounts().filter(m =>
			m.section === page && m.tab && this.pluginEnabled(m.plugin));
	}
	pluginEnabled(name) {
		return this.plugins().some(p => p.name === name && ko.unwrap(p.enabled));
	}
	syncFromHash() {
		const pages = ['general','database','features','security','logs',
			'performance','domains','users','plugins','themes','about'];
		const subs = {
			security: ['account', 'access', 'headers', 'tls',
				...this.adminTabsIn('security').map(m => m.tab)],
			logs: ['viewer', 'params'],
			features: ['features', 'defaults'],
			domains: ['list', 'params'],
			performance: ['http', 'cache'],
		};
		const [page, sub] = location.hash.replace(/^#\/?/, '').split('/');
		this.tab(pages.includes(page) ? page : 'general');
		if (subs[this.tab()]) {
			this.subtabs[this.tab()](subs[this.tab()].includes(sub) ? sub : subs[this.tab()][0]);
		}
	}
	collectSection(sec) {
		const data = {};
		for (const f of this.formSections[sec]()) { data[f.key] = f.value(); }
		return data;
	}
	async saveSection(sec) {
		this.error('');
		try {
			const data = this.collectSection(sec);
			const r = await api.post('/admin/settings', data);
			if (data.maintenance !== undefined) { this.maintenance(!!data.maintenance); }
			this.ok(t(r.writable ? 'admin.saved_writable' : 'admin.saved_readonly'));
		} catch (e) { this.error(e.message); }
	}
	async testDb() {
		const title = t('admin.test_db_title');
		try { const r = await api.post('/admin/test-db', this.collectSection('database'));
			await dialog.alert(r.message, { title, variant: 'success' }); }
		catch (e) { await dialog.alert(e.message, { title, variant: 'danger' }); }
	}
	addMyIp() {
		const f = this.formSections.security().find(f => f.key === 'admin_ip_allowlist');
		const ip = this.settings()?.ip;
		if (!f || !ip) { return; }
		const list = f.value() ? f.value().split(',').map(s => s.trim()) : [];
		if (!list.includes(ip)) { f.value([...list, ip].join(', ')); }
	}
	// Onglet Compte : « Enregistrer » applique les champs ET le mot de passe
	// si les deux champs sont remplis (validation avant tout envoi).
	async saveAccount() {
		this.error('');
		if (this.newPass() !== '' || this.newPass2() !== '') {
			if (this.newPass().length < 8) { this.error(t('errors.password_short')); return; }
			if (this.newPass() !== this.newPass2()) { this.error(t('errors.password_mismatch')); return; }
		}
		await this.saveSection('security');
		if (this.error() || this.newPass() === '') { return; }
		try {
			await api.post('/admin/password', { password: this.newPass() });
			this.newPass(''); this.newPass2('');
			this.ok(t('admin.password_changed'));
		} catch (e) { this.error(e.message); }
	}

	// options JSON de la ligne → flags dérivés pour les badges.
	mapDomain(d) {
		const o = typeof d.options === 'string' ? JSON.parse(d.options || '{}') : (d.options || {});
		return { ...d, detected: !!o.detected, secondary_only: !!o.secondary_only };
	}
	async loadDomains() { this.domains((await api.get('/admin/domains')).map(this.mapDomain)); }
	async loadUsers() {
		this.usersBusy(true);
		try { this.users(await api.get('/admin/users')); }
		catch (e) { this.error(e.message); }
		finally { this.usersBusy(false); }
	}
	async loadLogs() { this.logs(await api.get('/admin/logs?file=' + this.logFile())); }

	async refreshUpdateStatus() {
		try { this.updateInfo(await api.get('/admin/update/status')); } catch (e) { /* silencieux */ }
	}
	async checkUpdate() {
		this.updateErr(''); this.updateBusy('check');
		try {
			const r = await api.get('/admin/update/check?force=1');
			if (r.configured === false) {
				this.updateInfo({ ...(this.updateInfo() || {}), configured: false });
				this.updateResult(null);
				return;
			}
			this.updateResult(r);
		} catch (e) { this.updateErr(e.message); }
		finally { this.updateBusy(''); }
	}
	async downloadUpdate() {
		this.updateErr(''); this.updateBusy('download');
		try {
			await api.post('/admin/update/download');
			await this.refreshUpdateStatus();
		} catch (e) { this.updateErr(e.message); }
		finally { this.updateBusy(''); }
	}
	async applyUpdate() {
		this.updateErr('');
		const ver = this.updateInfo()?.state?.version || '';
		if (!(await dialog.confirm(t('admin.update.confirm_apply', { ver }),
			{ variant: 'danger', okLabel: t('admin.update.apply') }))) { return; }
		this.updateBusy('apply');
		try {
			const r = await api.post('/admin/update/apply');
			await this.refreshUpdateStatus();
			await dialog.alert(t('admin.update.applied', { ver: r.version }), { variant: 'success' });
			location.reload(); // le code vient de changer : recharger le panneau
		} catch (e) { this.updateErr(e.message); }
		finally { this.updateBusy(''); }
	}
	async resetUpdate() {
		this.updateErr('');
		try {
			await api.post('/admin/update/reset');
			this.updateResult(null);
			await this.refreshUpdateStatus();
		} catch (e) { this.updateErr(e.message); }
	}

	newDomain() {
		this.editingDomain({ id:0, name:'', imap_host:'', imap_port:993, imap_encryption:'ssl',
			smtp_host:'', smtp_port:465, smtp_encryption:'ssl', sieve_host:'', sieve_port:4190, active:1,
			opt_whitelist:'', opt_secondary_only:false, opt_oauth:'',
			opt_imap_timeout:'', opt_smtp_timeout:'',
			opt_imap_disabled_capabilities:'', opt_imap_shortLogin:false, opt_imap_lowerLogin:true,
			opt_smtp_useAuth:true, opt_sieve_encryption:'',
			error: ko.observable('') }); // erreur dans la modale (alerte racine masquée par le backdrop)
		this.domainModalTab('general');
		this.domainModal?.show();
	}
	editDomain(d) {
		const o = typeof d.options === 'string' ? (JSON.parse(d.options || '{}')) : (d.options || {});
		this.editingDomain({ ...d,
			active: !!Number(d.active), // MariaDB renvoie les INT en chaînes ("0" truthy)
			opt_whitelist: o.whitelist || '',
			opt_secondary_only: !!o.secondary_only,
			opt_oauth: o.oauth || '',
			opt_imap_timeout: o.imap?.timeout ?? '',
			opt_smtp_timeout: o.smtp?.timeout ?? '',
			opt_imap_disabled_capabilities: (o.imap?.disabled_capabilities || []).join(', '),
			opt_imap_shortLogin: !!o.imap?.shortLogin,
			opt_imap_lowerLogin: o.imap?.lowerLogin ?? true,
			opt_smtp_useAuth: o.smtp?.useAuth ?? true,
			opt_sieve_encryption: o.sieve?.encryption || '',
			error: ko.observable(''),
		});
		this.domainModalTab('general');
		this.domainModal?.show();
	}
	// Options avancées du formulaire domaine → JSON {imap, smtp, sieve,
	// whitelist, secondary_only, oauth}. Fusionné avec les options stockées :
	// les clés hors formulaire (imap.sasl, spam_headers…) doivent survivre à
	// une sauvegarde ; vider un champ du formulaire retire sa clé. Le marqueur
	// « detected » est retiré : une sauvegarde manuelle = domaine administré.
	buildDomainOptions(d) {
		let stored = d.options;
		if (typeof stored === 'string') {
			try { stored = JSON.parse(stored || '{}'); } catch { stored = {}; }
		}
		const opt = { ...(stored || {}) };
		delete opt.detected;
		const set = (k, v) => { (v !== undefined && v !== '' && v !== false) ? opt[k] = v : delete opt[k]; };
		set('whitelist', (d.opt_whitelist || '').trim());
		set('secondary_only', !!d.opt_secondary_only);
		set('oauth', d.opt_oauth);
		opt.imap = { ...(opt.imap || {}), shortLogin: !!d.opt_imap_shortLogin, lowerLogin: !!d.opt_imap_lowerLogin };
		const caps = (d.opt_imap_disabled_capabilities || '').split(',').map(s => s.trim()).filter(Boolean);
		caps.length ? opt.imap.disabled_capabilities = caps : delete opt.imap.disabled_capabilities;
		+d.opt_imap_timeout > 0 ? opt.imap.timeout = +d.opt_imap_timeout : delete opt.imap.timeout;
		opt.smtp = { ...(opt.smtp || {}), useAuth: !!d.opt_smtp_useAuth };
		+d.opt_smtp_timeout > 0 ? opt.smtp.timeout = +d.opt_smtp_timeout : delete opt.smtp.timeout;
		opt.sieve = { ...(opt.sieve || {}) };
		d.opt_sieve_encryption ? opt.sieve.encryption = d.opt_sieve_encryption : delete opt.sieve.encryption;
		if (!Object.keys(opt.sieve).length) { delete opt.sieve; }
		return opt;
	}
	async saveDomain() {
		const ed = this.editingDomain();
		ed.error('');
		const d = { ...ed, options: this.buildDomainOptions(ed) };
		delete d.error; // observable UI, pas une donnée du domaine
		try { await api.post('/admin/domains', d); this.domainModal?.hide(); this.loadDomains(); }
		catch(e) { ed.error(e.message); }
	}
	async testDomain() {
		// Protocole testé = onglet actif de la modale (imap|smtp|sieve) ;
		// onglets général/avancé → imap par défaut.
		const proto = ['imap', 'smtp', 'sieve'].includes(this.domainModalTab())
			? this.domainModalTab() : 'imap';
		const title = t('admin.test_domain_title') + ' — ' + proto.toUpperCase();
		// options en dernier : écrase le JSON stocké présent dans editingDomain
		const d = { ...this.editingDomain(), options: this.buildDomainOptions(this.editingDomain()) };
		try { const r = await api.post('/admin/test-connexion', { type: proto, ...d });
			await dialog.alert(r.message, { title, variant: 'success' }); }
		catch(e) { await dialog.alert(e.message, { title, variant: 'danger' }); }
	}
	// Détection explicite depuis le formulaire domaine (indépendante de
	// autodetect.enabled) : remplit les champs sans enregistrer.
	async detectDomain() {
		const d = this.editingDomain();
		const name = (d?.name || '').trim();
		if (!name || name === '*') { return; }
		const title = t('admin.detect');
		d.error?.('');
		try {
			const r = await api.post('/admin/domains/detect', { name });
			const o = r.options || {};
			this.editingDomain({ ...d, name,
				imap_host: r.imap_host ?? d.imap_host,
				imap_port: r.imap_port ?? d.imap_port,
				imap_encryption: r.imap_encryption ?? d.imap_encryption,
				smtp_host: r.smtp_host ?? d.smtp_host,
				smtp_port: r.smtp_port ?? d.smtp_port,
				smtp_encryption: r.smtp_encryption ?? d.smtp_encryption,
				sieve_host: r.sieve_host ?? d.sieve_host,
				sieve_port: r.sieve_port ?? d.sieve_port,
				opt_imap_shortLogin: !!o.imap?.shortLogin,
				opt_sieve_encryption: o.sieve?.encryption || '',
			});
			// notice racine masquée par le backdrop → dialogue empilé
			await dialog.alert(t('admin.detect_ok'), { title, variant: 'success' });
		} catch (e) { await dialog.alert(e.message, { title, variant: 'danger' }); }
	}
	async deleteDomain(d) {
		if (!(await dialog.confirm(t('admin.delete_domain', { name: d.name }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try { await api.post('/admin/domains/delete', { id: d.id }); this.loadDomains(); }
		catch(e) { this.error(e.message); }
	}
	// ─── Utilisateurs ───

	async editUser(u) {
		const r = await api.get(`/admin/users/${u.id}`).catch(e => { this.error(e.message); return null; });
		if (!r) { return; }
		const vals = r.settings?.values || {};
		const fields = Object.entries(r.settings?.schema || {}).map(([key, f]) => {
			let v = vals[key] ?? f.default ?? '';
			if (f.type === 'bool') { v = !!(v === true || v === 1 || v === '1' || v === 'true'); }
			if (f.type === 'enum' && v !== undefined) { v = String(v); }
			return {
				key, type: f.type || 'string', label: f.label || key, help: f.help || '',
				optionsList: Object.entries(f.options || {}),
				value: ko.observable(v),
			};
		});
		this.editingUser({
			id: u.id,
			username: r.user.username,
			created_at: r.user.created_at,
			last_login_at: r.user.last_login_at,
			display_name: ko.observable(r.user.display_name || ''),
			is_admin: ko.observable(!!Number(r.user.is_admin)),
			was_admin: !!Number(r.user.is_admin),
			disabled: ko.observable(!!Number(r.user.disabled)),
			error: ko.observable(''), // erreur dans la modale (alerte racine masquée par le backdrop)
			settingsFields: ko.observableArray(fields),
			accounts: r.accounts || [],
			identities: r.identities || [],
		});
		this.editingUserIdentity(null);
		this.userModalTab('profile');
		this.userModal?.show();
	}
	// Identités d'un compte (listes plates côté VM → filtre par account_id)
	identitiesFor(account) {
		const eu = this.editingUser();
		return eu ? eu.identities.filter(i => Number(i.account_id) === Number(account.id)) : [];
	}
	async saveUser() {
		const eu = this.editingUser();
		eu.error('');
		if (eu.is_admin() && !eu.was_admin
			&& !(await dialog.confirm(t('admin.grant_admin', { user: eu.username }),
				{ variant: 'warning', okLabel: t('common.confirm') }))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}`, {
				display_name: eu.display_name(),
				is_admin: eu.is_admin(),
				disabled: eu.disabled(),
			});
			eu.was_admin = eu.is_admin();
			this.loadUsers();
			this.ok(t('settings.saved'));
		} catch (e) { eu.error(e.message); }
	}
	async deleteUser(u) {
		if (!(await dialog.confirm(t('admin.user_delete_confirm', { user: u.username }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post(`/admin/users/${u.id}/delete`);
			this.userModal?.hide();
			this.loadUsers();
		} catch (e) {
			const eu = this.editingUser();
			eu ? eu.error(e.message) : this.error(e.message);
		}
	}
	async saveUserSettings() {
		const eu = this.editingUser();
		eu.error('');
		// Types natifs JSON (user_settings est (dé)sérialisé en JSON) —
		// pas la conversion bool→1/0 des configs plugin.
		const values = {};
		for (const f of eu.settingsFields()) {
			values[f.key] = f.type === 'bool' ? !!f.value()
				: f.type === 'integer' ? parseInt(f.value(), 10) || 0
				: f.value();
		}
		try {
			await api.post(`/admin/users/${eu.id}/settings`, values);
			this.ok(t('settings.saved'));
		} catch (e) { eu.error(e.message); }
	}
	async resetUserSettings() {
		const eu = this.editingUser();
		if (!(await dialog.confirm(t('admin.user_settings_reset_confirm', { user: eu.username }),
			{ variant: 'warning', okLabel: t('common.confirm') }))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}/settings/reset`);
			await this.editUser({ id: eu.id }); // recharge les valeurs effectives
		} catch (e) { eu.error(e.message); }
	}
	async deleteUserAccount(a) {
		const eu = this.editingUser();
		if (!(await dialog.confirm(t('admin.user_account_delete', { email: a.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}/accounts/delete`, { account_id: a.id });
			await this.editUser({ id: eu.id });
		} catch (e) { eu.error(e.message); }
	}
	editUserIdentity(account, i) {
		const s = i || {};
		this.editingUserIdentity({
			account_id: account.id,
			id: s.id || 0,
			name: ko.observable(s.name || ''),
			email: ko.observable(s.email || ''),
			reply_to: ko.observable(s.reply_to || ''),
			bcc: ko.observable(s.bcc || ''),
			signature: ko.observable(s.signature || ''),
		});
	}
	async saveUserIdentity() {
		const eu = this.editingUser();
		eu.error('');
		try {
			await api.post(`/admin/users/${eu.id}/identities`, ko.toJS(this.editingUserIdentity()));
			this.editingUserIdentity(null);
			await this.editUser({ id: eu.id });
		} catch (e) { eu.error(e.message); }
	}
	async deleteUserIdentity(i) {
		const eu = this.editingUser();
		if (!(await dialog.confirm(t('settings.delete_identity', { email: i.email }),
			{ variant: 'danger', okLabel: t('common.delete') }))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}/identities/delete`, { identity_id: i.id });
			await this.editUser({ id: eu.id });
		} catch (e) { eu.error(e.message); }
	}
	async defaultUserIdentity(i) {
		const eu = this.editingUser();
		try {
			await api.post(`/admin/users/${eu.id}/identities/default`, { identity_id: i.id });
			await this.editUser({ id: eu.id });
		} catch (e) { eu.error(e.message); }
	}
	mapPlugin(p) {
		return { ...p,
			enabled: ko.observable(!!p.enabled),
			user_allowed: ko.observable(p.user_allowed !== false) };
	}
	// Entrée catalogue d'un plugin installé (latest/update_available/compatible)
	catalogFor(name) {
		return (this.pluginCatalog()?.plugins || []).find(p => p.name === name) || null;
	}
	// Chargement paresseux à la première ouverture de l'onglet Extensions —
	// le cache release est partagé avec le check cœur (gratuit si récent).
	async ensurePluginsCatalog() {
		if (this.catalogLoaded() || this.catalogBusy()) { return; }
		this.catalogLoaded(true); this.catalogBusy(true);
		try {
			this.pluginCatalog(await api.get('/admin/plugins/catalog'));
		} catch (e) { /* silencieux — non bloquant à l'ouverture */ }
		finally { this.catalogBusy(false); }
	}
	async checkPluginsCatalog(force = false) {
		this.pluginsErr(''); this.catalogBusy(true);
		try {
			this.pluginCatalog(await api.get('/admin/plugins/catalog' + (force ? '?force=1' : '')));
			this.catalogLoaded(true);
		} catch (e) { this.pluginsErr(e.message); }
		finally { this.catalogBusy(false); }
	}
	async refreshPlugins() {
		try {
			this.plugins((await api.get('/admin/plugins')).map(this.mapPlugin));
		} catch (e) { this.pluginsErr(e.message); }
	}
	async installPlugin(p) {
		this.pluginsErr(''); this.pluginBusy(p.name);
		try {
			await api.post(`/admin/plugins/${p.name}/install`);
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.installed_ok', { name: p.name }));
		} catch (e) { this.pluginsErr(e.message); }
		finally { this.pluginBusy(''); }
	}
	async updatePlugin(p) {
		const c = this.catalogFor(p.name);
		if (!(await dialog.confirm(t('admin.plugins.confirm_update', { name: p.name, ver: c?.latest || '' }),
			{ okLabel: t('admin.plugins.update') }))) { return; }
		this.pluginsErr(''); this.pluginBusy(p.name);
		const wasEnabled = !!ko.unwrap(p.enabled);
		try {
			await api.post(`/admin/plugins/${p.name}/install`);
			if (wasEnabled) {
				location.reload(); // code + assets du plugin changés
				return;
			}
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.updated_ok', { name: p.name }));
		} catch (e) { this.pluginsErr(e.message); }
		finally { this.pluginBusy(''); }
	}
	// Désinstallation définitive : dossier supprimé + config purgée.
	async removePlugin(p) {
		if (!(await dialog.confirm(t('admin.plugins.confirm_remove', { name: p.name }),
			{ variant: 'danger', okLabel: t('admin.plugins.remove') }))) { return; }
		this.pluginsErr(''); this.pluginBusy(p.name);
		const wasEnabled = !!ko.unwrap(p.enabled);
		try {
			await api.post(`/admin/plugins/${p.name}/remove`);
			if (wasEnabled) {
				location.reload(); // hooks admin.mounts du plugin disparus
				return;
			}
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.removed_ok', { name: p.name }));
		} catch (e) { this.pluginsErr(e.message); }
		finally { this.pluginBusy(''); }
	}
	async togglePlugin(p, e) {
		try {
			await api.post(`/admin/plugins/${p.name}/toggle`, { enabled: p.enabled() });
		} catch (err) {
			p.enabled(!p.enabled()); // revert : la case recoche d'elle-même
			this.error(err.message);
		}
	}
	async togglePluginAllowed(p) {
		try {
			await api.post(`/admin/plugins/${p.name}/allowed`, { allowed: p.user_allowed() });
		} catch (e) {
			p.user_allowed(!p.user_allowed()); // revert : la case recoche d'elle-même
			this.error(e.message);
		}
	}
	async editPluginConfig(p) {
		if (!p.has_schema) { return; }
		const r = await api.get(`/admin/plugins/${p.name}/config`).catch(() => null);
		if (!r) { this.editingPlugin(null); return; }
		this.editingPlugin({
			name: p.name,
			error: ko.observable(''), // erreur affichée dans la modale (alerte racine masquée par le backdrop)
			fields: ko.observableArray(
				Object.entries(r.schema || {}).map(([key, f]) => {
					let v = r.values?.[key] ?? f.default ?? '';
					if (f.type === 'bool') { v = !!(v === true || v === 1 || v === '1' || v === 'true'); }
					// « options » objet {v:label} ou liste [v] — normalisé en paires
					const opts = f.options || {};
					const field = {
						key, label: f.label || key, type: f.type || 'string',
						help: f.help || f.description || '',
						optionsList: Array.isArray(opts) ? opts.map(v => [v, v]) : Object.entries(opts),
					};
					if (f.type === 'list') {
						// items observables : sinon « value: $data » ne remonte pas l'édition
						const items = String(v).split(',').map(s => s.trim()).filter(Boolean);
						const arr = ko.observableArray(items.map(s => ko.observable(s)));
						field.value = arr;
						field.addItem    = () => arr.push(ko.observable(''));
						field.removeItem = (item) => arr.remove(item);
					} else {
						field.value = ko.observable(v);
					}
					return field;
				})
			),
		});
		this.pluginConfigModal?.show();
	}
	async savePluginConfig() {
		const p = this.editingPlugin();
		const values = {};
		for (const f of p.fields()) {
			values[f.key] = f.type === 'bool' ? (f.value() ? 1 : 0)
				: f.type === 'integer' ? parseInt(f.value(), 10) || 0
				: f.type === 'list' ? f.value().map(o => ko.unwrap(o)).filter(s => s.trim() !== '').join(', ')
				: String(f.value());
		}
		try {
			await api.post(`/admin/plugins/${p.name}/config`, values);
			this.pluginConfigModal?.hide(); // hidden.bs.modal → editingPlugin(null)
			this.ok(t('settings.saved'));
		} catch (e) { p.error(e.message); }
	}
	async editThemeConfig(t) {
		const r = await api.get(`/admin/themes/${t.name}/config`).catch(() => null);
		if (!r) { this.editingTheme(null); return; }
		this.editingTheme({
			name: t.name,
			error: ko.observable(''),
			fields: ko.observableArray(
				Object.entries(r.schema || {}).map(([key, f]) => {
					let v = r.values?.[key] ?? f.default ?? '';
					if (f.type === 'bool') { v = !!(v === true || v === 1 || v === '1' || v === 'true'); }
					const opts = f.options || {};
					return {
						key, label: f.label || key, type: f.type || 'string',
						help: f.help || f.description || '',
						optionsList: Array.isArray(opts) ? opts.map(v => [v, v]) : Object.entries(opts),
						value: ko.observable(v),
					};
				})
			),
		});
		this.themeConfigModal?.show();
	}
	async saveThemeConfig() {
		const p = this.editingTheme();
		const values = {};
		for (const f of p.fields()) {
			values[f.key] = f.type === 'bool' ? (f.value() ? 1 : 0)
				: f.type === 'integer' ? parseInt(f.value(), 10) || 0
				: String(f.value());
		}
		try {
			await api.post(`/admin/themes/${p.name}/config`, values);
			this.themeConfigModal?.hide();
			this.ok(t('settings.saved'));
		} catch (e) { p.error(e.message); }
	}
	mapThemes(items) {
		return (items || []).map(t => ({ ...t, allowed: ko.observable(!!t.selectable) }));
	}
	// Resync complet : changer le défaut change aussi le « selectable » forcé
	// de l'ancien/nouveau défaut — les observables seuls ne suffisent pas.
	async loadThemes() {
		const r = await api.get('/admin/themes');
		this.themeDefault(r.default || 'default');
		this.themes(this.mapThemes(r.items));
	}
	async setThemeDefault(theme) {
		try {
			await api.post(`/admin/themes/${theme.name}/default`);
			await this.loadThemes();
			this.ok(t('settings.saved'));
		} catch (e) { this.error(e.message); }
	}
	async toggleThemeAllowed(theme) {
		try {
			await api.post(`/admin/themes/${theme.name}/allowed`, { allowed: theme.allowed() });
		} catch (e) {
			theme.allowed(!theme.allowed()); // revert : la case recoche d'elle-même
			this.error(e.message);
		}
	}
	async logout() { await api.post('/admin/logout'); location.reload(); }
}
const vm = new AdminVM();
ko.applyBindings(vm);
vm.init().catch(e => vm.error(e.message));
