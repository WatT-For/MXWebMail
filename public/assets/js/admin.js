/**
 * MXWebMail - panneau d'administration (/admin).
 * Données initiales : une seule requête /admin/bootstrap - évite la rafale
 * d'appels sérialisés (verrou de session FPM / serveur dev mono-thread).
 */
import { api, base } from './api.js';
import { dialog } from './ui/dialog.js';
import { bindModal } from './ui/modal.js';
import { toast } from './ui/toast.js';
import { formatSize, formatTs, formatDate } from './ui/format.js';
import { bindAll } from './ui/bind-all.js';
import { t, lang } from './i18n.js';
import './bindings/tooltip.js';
import './bindings/plugin-mount.js';
import './bindings/toast.js';

class AdminVM {
	constructor() {
		this.t = t;       // bindings : $root.t('cle')
		this.lang = lang; // locale courante (toLocaleString…)
		// Formats exposés aux bindings : $root.fmtTs(ts) / $root.fmtDate(ts)
		this.fmtTs = ts => formatTs(ts, this.lang);
		this.fmtDate = ts => formatDate(ts, this.lang);
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
		// Blocs UI déclarés par les plugins (hook admin.mounts > bootstrap)
		this.adminMounts = ko.observableArray([]);
		this.logs = ko.observableArray([]);
		this.logFile = ko.observable('app');
		// Dernier rédacteur gagne : une réponse tardive d'un chargement
		// périmé ne peut pas écraser l'état courant (cf. loadLogs/clearLog).
		this.logsSeq = 0;
		this.editingDomain = ko.observable(null);
		this.editingPlugin = ko.observable(null);
		this.editingTheme = ko.observable(null);
		this.editingUser = ko.observable(null);
		this.userSessions = ko.observableArray([]);
		this.editingUserIdentity = ko.observable(null);
		this.userSearch = ko.observable('');
		this.usersBusy = ko.observable(false);
		// Page détail utilisateur : #/users/<id>[/<tab>] (cf. syncFromHash).
		this.userDetailId = ko.observable(null);
		this.userDetailTab = ko.observable('profile');
		this.userLoading = null; // id en cours de fetch (anti double-GET)
		this.domainModalTab = ko.observable('general');
		this.filteredUsers = ko.pureComputed(() => {
			const q = this.userSearch().trim().toLowerCase();
			return q
				? this.users().filter(u =>
					(u.username || '').toLowerCase().includes(q)
					|| (u.display_name || '').toLowerCase().includes(q))
				: this.users();
		});
		this.userStats = ko.pureComputed(() => {
			const cut = Date.now() / 1000 - 30 * 86400, list = this.users();
			return {
				total: list.length,
				active: list.filter(u => u.last_login_at && u.last_login_at > cut).length,
				disabled: list.filter(u => Number(u.disabled)).length,
			};
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
		this.updatePending = ko.pureComputed(() => ['downloaded', 'extracted'].includes(this.updateStep()));
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
		bindAll(this, AdminVM.prototype);
		// Modales de config plugin / thème / domaine : l'observable est reset à
		// la fermeture (Échap, backdrop, boutons) > état propre à la réouverture.
		this.pluginConfigModal = bindModal('plugin-config-modal',
			{ onHidden: () => this.editingPlugin(null) });
		this.themeConfigModal = bindModal('theme-config-modal',
			{ onHidden: () => this.editingTheme(null) });
		this.domainModal = bindModal('domain-modal',
			{ onHidden: () => this.editingDomain(null) });
		// Navigation pilotée par l'URL : #/<page>[/<sous-onglet>] ; la page
		// Utilisateurs ajoute un 3e segment : #/users/<id>[/<tab>].
		window.addEventListener('hashchange', this.syncFromHash);
		this.syncFromHash();
		// Catalogue plugins chargé à la première ouverture de l'onglet Extensions
		this.tab.subscribe(v => { if (v === 'plugins') { this.ensurePluginsCatalog(); } });
		// Rechargement piloté par l'observable (écritures programmatiques
		// incluses) plutôt que par l'évènement DOM du <select>.
		this.logFile.subscribe(() => { this.loadLogs(); });
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
		// du constructeur - re-sync pour les deep-links (#/security/2fa…).
		this.syncFromHash();
		this.themeDefault(b.themes?.default || 'default');
		this.themes(this.mapThemes(b.themes?.items));
		this.about(b.about || null);
		this.updateInfo(b.update || null);
		this.loadLogs();
	}
	// notice('') puis notice(msg) : le binding toast n'est re-notifié que
	// sur changement - deux succès identiques consécutifs doivent re-toaster.
	ok(msg) { this.notice(''); this.notice(msg); }

	// Favicon : upload multipart (champ 'file') > storage/branding + config.
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

	// Taille lisible (octets > o/Ko/Mo/Go, unités binaires ; '' si inconnue).
	formatSize(bytes) {
		return formatSize(bytes, '');
	}

	/**
	 * Schéma -> champs KO (sections admin, réglages utilisateur, configs
	 * plugin/thème) : coercions uniformes - bool strict (1/'1'/true, la
	 * variante !!v affichait "0" coché), enum stringifié, type 'list' =
	 * observableArray d'observables (sinon "value: $data" ne remonte pas).
	 * `nested` : les clés 'a.b' lisent values[a][b]. labelCol/fieldCol :
	 * colonnes Bootstrap du fields-tpl ('col-4/8' en modale,
	 * 'col-md-4/8' en onglets).
	 */
	schemaFields(fieldsMap, values = {}, { nested = false,
		labelCol = 'col-md-4', fieldCol = 'col-md-8' } = {}) {
		return Object.entries(fieldsMap || {}).map(([key, f]) => {
			const [a, b] = key.split('.');
			let v = nested && b !== undefined ? values?.[a]?.[b] : values?.[key];
			if (v === undefined) { v = f.default; }
			if (f.type === 'bool') { v = v === true || v === 1 || v === '1' || v === 'true'; }
			if (f.type === 'enum' && v !== undefined) { v = String(v); }
			const opts = f.options || {};
			const field = {
				key, type: f.type || 'string', label: f.label || key,
				help: f.help || f.description || '', group: f.group || '',
				labelCol, fieldCol,
				optionsList: Array.isArray(opts)
					? opts.map(o => [o, o]) : Object.entries(opts),
			};
			if (f.type === 'list') {
				const items = String(v ?? '').split(',')
					.map(s => s.trim()).filter(Boolean);
				const arr = ko.observableArray(items.map(s => ko.observable(s)));
				field.value = arr;
				field.addItem    = () => arr.push(ko.observable(''));
				field.removeItem = (item) => arr.remove(item);
			} else {
				field.value = ko.observable(v ?? '');
			}
			return field;
		});
	}

	/**
	 * Champs -> valeurs à poster : bool (1/0 pour les configs fichier,
	 * natif JSON sinon), integer parsé, 'list' jointe sur ', '.
	 */
	collectFields(fields, { boolInt = false, stringify = false } = {}) {
		const values = {};
		for (const f of fields) {
			if (f.type === 'bool') {
				values[f.key] = boolInt ? (f.value() ? 1 : 0) : !!f.value();
			} else if (f.type === 'integer') {
				values[f.key] = parseInt(f.value(), 10) || 0;
			} else if (f.type === 'list') {
				values[f.key] = f.value().map(o => ko.unwrap(o))
					.filter(s => s.trim() !== '').join(', ');
			} else {
				values[f.key] = stringify ? String(f.value() ?? '') : f.value();
			}
		}
		return values;
	}

	buildFields(sec, r) {
		return this.schemaFields(r.schema?.[sec]?.fields, r.values, { nested: true })
			.map(field => {
				if (sec === 'domains' && field.key === 'default_domain') {
					// Select alimenté par les domaines de la table ; la valeur
					// courante est conservée si le domaine n'y figure plus.
					// peek() : ne pas dépendre de value - sinon chaque sélection
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
	// Sous-onglets déclarés par les plugins (mounts avec "tab") - filtrés aux
	// plugins activés pour que le hash routing rejette les onglets disparus.
	adminTabsIn(page) {
		return this.adminMounts().filter(m =>
			m.section === page && m.tab && this.pluginEnabled(m.plugin));
	}
	pluginEnabled(name) {
		return this.plugins().some(p => p.name === name && ko.unwrap(p.enabled));
	}
	async syncFromHash() {
		const pages = ['general','database','features','security','logs',
			'performance','domains','users','plugins','themes','about',
			// Pages de niveau supérieur déclarées par les plugins (admin.mounts)
			...this.adminTabsIn('page').map(m => m.tab)];
		const subs = {
			security: ['account', 'access', 'headers', 'tls',
				...this.adminTabsIn('security').map(m => m.tab)],
			logs: ['viewer', 'params'],
			features: ['features', 'defaults'],
			domains: ['list', 'params'],
			performance: ['http', 'cache'],
		};
		const [page, sub, sub2] = location.hash.replace(/^#\/?/, '').split('/');
		// Tips orphelins : purgés au hashchange par le binding tooltip lui-même
		// (un trigger masqué ne reçoit plus de mouseleave > tip body restant).
		this.tab(pages.includes(page) ? page : 'general');
		if (subs[this.tab()]) {
			this.subtabs[this.tab()](subs[this.tab()].includes(sub) ? sub : subs[this.tab()][0]);
		}
		// Détail utilisateur : #/users/<id>[/<tab>] - id non numérique > liste.
		const uid = this.tab() === 'users' && /^\d+$/.test(sub || '') ? Number(sub) : 0;
		this.userDetailId(uid || null);
		if (uid) {
			const tabs = ['profile', 'settings', 'accounts', 'sessions'];
			const tab = tabs.includes(sub2) ? sub2 : 'profile';
			this.userDetailTab(tab);
			await this.loadUserDetail(uid);
			// Sessions chargées à la demande, seulement si ce détail est
			// toujours courant (le fetch a pu échouer > retour liste).
			if (this.userDetailId() === uid && tab === 'sessions') {
				this.loadUserSessions();
			}
		} else {
			this.editingUser(null);
			this.editingUserIdentity(null);
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
			toast.success(r.message, { title }); }
		catch (e) { await dialog.alert(e.message, { title, variant: 'danger' }); }
	}
	addMyIp() {
		const f = this.formSections.security().find(f => f.key === 'admin_ip_allowlist');
		const ip = this.settings()?.ip;
		if (!f || !ip) { return; }
		const list = f.value() ? f.value().split(',').map(s => s.trim()) : [];
		if (!list.includes(ip)) { f.value([...list, ip].join(', ')); }
	}
	// Onglet Compte : "Enregistrer" applique les champs ET le mot de passe
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

	// options JSON de la ligne > flags dérivés pour les badges.
	mapDomain(d) {
		let o = d.options;
		if (typeof o === 'string') {
			try { o = JSON.parse(o || '{}'); } catch { o = {}; } // options malformées > aucun flag
		}
		o = o || {};
		return { ...d, detected: !!o.detected, secondary_only: !!o.secondary_only };
	}
	async loadDomains() { this.domains((await api.get('/admin/domains')).map(this.mapDomain)); }
	async loadUsers() {
		this.usersBusy(true);
		try { this.users(await api.get('/admin/users')); }
		catch (e) { this.error(e.message); }
		finally { this.usersBusy(false); }
	}
	async loadLogs() {
		const file = this.logFile();
		const seq = ++this.logsSeq;
		try {
			const lines = await api.get('/admin/logs?file=' + file);
			// Une réponse tardive d'un chargement périmé est ignorée.
			if (seq === this.logsSeq) { this.logs(lines); }
		} catch (e) {
			if (seq === this.logsSeq) { this.error(e.message); }
		}
	}
	async clearLog() {
		const file = this.logFile();
		if (!(await dialog.confirmDanger(
			t('admin.log_clear_confirm', { file: t('admin.log_' + file) }),
			{ variant: 'danger', okLabel: t('admin.log_clear') }))) { return; }
		try {
			await api.post('/admin/logs/clear', { file });
			// Recharge l'état réel (vide + nouvelles lignes éventuelles) :
			// le bump seq invalide les GET en vol - le contenu vidé ne
			// ressuscite pas - et si la sélection a changé pendant la
			// modale c'est le bon fichier qui est affiché.
			await this.loadLogs();
			this.ok(t('admin.log_cleared'));
		} catch (e) { this.error(e.message); }
	}

	async refreshUpdateStatus() {
		try { this.updateInfo(await api.get('/admin/update/status')); } catch (e) { /* silencieux */ }
	}
	/**
	 * Enveloppes busy+err des opérations update/plugins : erreur effacée,
	 * busy posé, fn exécutée, erreur rapportée, busy libéré (finally).
	 * Les confirmations dialog restent AVANT l'appel (pas de busy pendant
	 * l'attente utilisateur).
	 */
	async updateOp(step, fn) {
		this.updateErr(''); this.updateBusy(step);
		try { await fn(); }
		catch (e) { this.updateErr(e.message); }
		finally { this.updateBusy(''); }
	}
	async pluginOp(name, fn) {
		this.pluginsErr(''); this.pluginBusy(name);
		try { await fn(); }
		catch (e) { this.pluginsErr(e.message); }
		finally { this.pluginBusy(''); }
	}
	/** POST optimiste d'un toggle admin : revert de l'observable si refus
	 *  (la case recoche d'elle-même). */
	async togglePost(url, obs, key) {
		try {
			await api.post(url, { [key]: obs() });
		} catch (e) {
			obs(!obs());
			this.error(e.message);
		}
	}

	async checkUpdate() {
		await this.updateOp('check', async () => {
			const r = await api.get('/admin/update/check?force=1');
			if (r.configured === false) {
				this.updateInfo({ ...(this.updateInfo() || {}), configured: false });
				this.updateResult(null);
				return;
			}
			this.updateResult(r);
		});
	}
	async downloadUpdate() {
		await this.updateOp('download', async () => {
			await api.post('/admin/update/download');
			// Le résultat du check est consommé par l'état (step downloaded/
			// extracted) - sinon le badge v{latest} + le bouton Télécharger
			// resteraient affichés à côté du paquet en attente.
			this.updateResult(null);
			await this.refreshUpdateStatus();
		});
	}
	async applyUpdate() {
		this.updateErr('');
		const st = this.updateInfo()?.state || {};
		const ver = st.version || '';
		let msg = t('admin.update.confirm_apply', { ver });
		if (this.about()?.db_driver === 'mysql') {
			msg += ' ' + t('admin.update.confirm_apply_db');
		}
		if (st.modified?.length) {
			msg += ' ' + t('admin.update.confirm_apply_modified', { count: st.modified.length });
		}
		if (!(await dialog.confirmDanger(msg, { okLabel: t('admin.update.apply') }))) { return; }
		await this.updateOp('apply', async () => {
			const r = await api.post('/admin/update/apply');
			// Finalize = migrations du nouveau code (requête fraîche).
			let done = t('admin.update.applied', { ver: r.version });
			try {
				const f = await api.post('/admin/update/finalize');
				done = t('admin.update.applied_migrated',
					{ ver: r.version, schema: f.schema_version ?? '?' });
			} catch (e) {
				done += ' ' + t('admin.update.migrations_failed', { msg: e.message });
			}
			await this.refreshUpdateStatus();
			await dialog.alert(done, { variant: 'success' });
			location.reload(); // le code vient de changer : recharger le panneau
		});
	}
	async finalizeUpdate() {
		await this.updateOp('apply', async () => {
			const f = await api.post('/admin/update/finalize');
			this.ok(t('admin.update.finalized', { schema: f.schema_version ?? '?' }));
			await this.refreshUpdateStatus();
		});
	}
	async rollbackUpdate() {
		this.updateErr('');
		const rb = this.updateInfo()?.rollback || {};
		if (!(await dialog.confirmDanger(t('admin.update.confirm_rollback',
			{ from: rb.from || '', to: rb.to || '' }), { okLabel: t('admin.update.rollback') }))) { return; }
		await this.updateOp('apply', async () => {
			let r;
			try {
				r = await api.post('/admin/update/rollback');
			} catch (e) {
				// 409 = schéma MariaDB plus récent que le backup : l'admin doit
				// restaurer son dump puis confirmer le rollback forcé.
				if (e.status !== 409
					|| !(await dialog.confirmDanger(e.message + ' ' + t('admin.update.rollback_forcedb'), { okLabel: t('admin.update.rollback') }))) {
					throw e;
				}
				r = await api.post('/admin/update/rollback', { force: 1 });
			}
			await dialog.alert(t('admin.update.rolled_back', { ver: r.version }),
				{ variant: 'success' });
			location.reload();
		});
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
	// Options avancées du formulaire domaine > JSON {imap, smtp, sieve,
	// whitelist, secondary_only, oauth}. Fusionné avec les options stockées :
	// les clés hors formulaire (imap.sasl, spam_headers…) doivent survivre à
	// une sauvegarde ; vider un champ du formulaire retire sa clé. Le marqueur
	// "detected" est retiré : une sauvegarde manuelle = domaine administré.
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
		// onglets général/avancé > imap par défaut.
		const proto = ['imap', 'smtp', 'sieve'].includes(this.domainModalTab())
			? this.domainModalTab() : 'imap';
		const title = t('admin.test_domain_title') + ' - ' + proto.toUpperCase();
		// options en dernier : écrase le JSON stocké présent dans editingDomain
		const d = { ...this.editingDomain(), options: this.buildDomainOptions(this.editingDomain()) };
		try { const r = await api.post('/admin/test-connexion', { type: proto, ...d });
			toast.success(r.message, { title }); }
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
			toast.success(t('admin.detect_ok'), { title });
		} catch (e) { await dialog.alert(e.message, { title, variant: 'danger' }); }
	}
	async deleteDomain(d) {
		if (!(await dialog.confirmDanger(t('admin.delete_domain', { name: d.name })))) { return; }
		try { await api.post('/admin/domains/delete', { id: d.id }); this.loadDomains(); }
		catch(e) { this.error(e.message); }
	}
	// ─── Utilisateurs ───

	// Ouvre la page détail : le hashchange déclenche syncFromHash > loadUserDetail.
	editUser(u) { location.hash = '#/users/' + u.id; }

	/** Détail d'un compte : GET /admin/users/{id} > editingUser. reload=true
	 *  force le re-fetch (post-mutation) ; sinon on saute si l'id est déjà
	 *  affiché ou en cours de chargement (changement d'onglet, double sync). */
	async loadUserDetail(id, reload = false) {
		if (!reload && (this.editingUser()?.id === id || this.userLoading === id)) { return; }
		this.userLoading = id;
		try {
			const r = await api.get(`/admin/users/${id}`);
			if (this.userDetailId() !== id) { return; } // navigation entre-temps
			const vals = r.settings?.values || {};
			const fields = this.schemaFields(r.settings?.schema, vals);
			this.editingUser({
				id: Number(r.user.id),
				username: r.user.username,
				created_at: r.user.created_at,
				last_login_at: r.user.last_login_at,
				display_name: ko.observable(r.user.display_name || ''),
				is_admin: ko.observable(!!Number(r.user.is_admin)),
				was_admin: !!Number(r.user.is_admin),
				disabled: ko.observable(!!Number(r.user.disabled)),
				error: ko.observable(''), // erreur inline dans la page détail
				settingsFields: ko.observableArray(fields),
				accounts: r.accounts || [],
				identities: r.identities || [],
			});
			this.editingUserIdentity(null);
			this.userSessions([]);
		} catch (e) {
			if (this.userDetailId() !== id) { return; }
			const eu = this.editingUser();
			if (eu && eu.id === id) { eu.error(e.message); } // reload > on reste
			else { this.error(e.message); location.hash = '#/users'; } // id inexistant > liste
		} finally {
			if (this.userLoading === id) { this.userLoading = null; }
		}
	}

	/** Sessions "Rester connecte" du compte édité (onglet Sessions). */
	async loadUserSessions() {
		const eu = this.editingUser();
		if (!eu) { return; }
		this.userSessions(
			await api.get(`/admin/users/${eu.id}/sessions`).catch(() => []));
	}

	async deleteUserSession(s) {
		const eu = this.editingUser();
		if (!eu) { return; }
		await api.post(`/admin/users/${eu.id}/sessions/${s.id}/delete`)
			.catch(e => toast.error(e.message));
		await this.loadUserSessions();
	}

	/** Jetons remember-me + sessions PHP vivantes (bump epoch cote serveur). */
	async revokeUserSessions() {
		const eu = this.editingUser();
		if (!eu || !(await dialog.confirmDanger(t('admin.session_revoke_all_confirm'), { okLabel: t('admin.session_revoke_all') }))) { return; }
		await api.post(`/admin/users/${eu.id}/sessions/revoke`)
			.catch(e => toast.error(e.message));
		await this.loadUserSessions();
	}
	// Identités d'un compte (listes plates côté VM > filtre par account_id)
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
		if (!(await dialog.confirmDanger(t('admin.user_delete_confirm', { user: u.username })))) { return; }
		try {
			await api.post(`/admin/users/${u.id}/delete`);
			if (this.userDetailId()) { location.hash = '#/users'; } // détail ouvert > retour liste
			this.loadUsers();
		} catch (e) {
			const eu = this.editingUser();
			eu ? eu.error(e.message) : this.error(e.message);
		}
	}
	async saveUserSettings() {
		const eu = this.editingUser();
		eu.error('');
		// Types natifs JSON (user_settings est (dé)sérialisé en JSON) -
		// pas la conversion bool>1/0 des configs plugin.
		const values = this.collectFields(eu.settingsFields());
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
			await this.loadUserDetail(eu.id, true); // recharge les valeurs effectives
		} catch (e) { eu.error(e.message); }
	}
	async deleteUserAccount(a) {
		const eu = this.editingUser();
		if (!(await dialog.confirmDanger(t('admin.user_account_delete', { email: a.email })))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}/accounts/delete`, { account_id: a.id });
			await this.loadUserDetail(eu.id, true);
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
			await this.loadUserDetail(eu.id, true);
		} catch (e) { eu.error(e.message); }
	}
	async deleteUserIdentity(i) {
		const eu = this.editingUser();
		if (!(await dialog.confirmDanger(t('settings.delete_identity', { email: i.email })))) { return; }
		try {
			await api.post(`/admin/users/${eu.id}/identities/delete`, { identity_id: i.id });
			await this.loadUserDetail(eu.id, true);
		} catch (e) { eu.error(e.message); }
	}
	async defaultUserIdentity(i) {
		const eu = this.editingUser();
		try {
			await api.post(`/admin/users/${eu.id}/identities/default`, { identity_id: i.id });
			await this.loadUserDetail(eu.id, true);
		} catch (e) { eu.error(e.message); }
	}
	mapPlugin(p) {
		return { ...p,
			enabled: ko.observable(!!p.enabled),
			user_allowed: ko.observable(!!p.user_allowed),
			admin_only: !!p.admin_only };
	}
	// Nom d'affichage : label du manifeste traduit ; nom technique si la clé
	// n'est pas résolue (plugin du catalogue non installé - langs absentes)
	// ou absente. Un littéral sans point est affiché tel quel.
	pluginLabel(p) {
		const l = ko.unwrap(p.label) || '';
		if (l === '') { return p.name; }
		const r = t(l);
		return (r !== l || !l.includes('.')) ? r : p.name;
	}
	// Entrée catalogue d'un plugin installé (latest/update_available/compatible)
	catalogFor(name) {
		return (this.pluginCatalog()?.plugins || []).find(p => p.name === name) || null;
	}
	// Chargement paresseux à la première ouverture de l'onglet Extensions -
	// le cache release est partagé avec le check cœur (gratuit si récent).
	async ensurePluginsCatalog() {
		if (this.catalogLoaded() || this.catalogBusy()) { return; }
		this.catalogLoaded(true); this.catalogBusy(true);
		// KO ne re-notifie pas une primitive identique : le reset permet de
		// re-toaster un échec répété (même logique que notice('') de ok()).
		this.pluginsErr('');
		try {
			this.pluginCatalog(await api.get('/admin/plugins/catalog'));
		} catch (e) {
			this.catalogLoaded(false); // retenter à la prochaine ouverture de l'onglet
			this.pluginsErr(e.message); // toast (binding admin.php)
		}
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
		await this.pluginOp(p.name, async () => {
			await api.post(`/admin/plugins/${p.name}/install`);
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.installed_ok', { name: p.name }));
		});
	}
	async updatePlugin(p) {
		const c = this.catalogFor(p.name);
		if (!(await dialog.confirm(t('admin.plugins.confirm_update', { name: p.name, ver: c?.latest || '' }),
			{ okLabel: t('admin.plugins.update') }))) { return; }
		const wasEnabled = !!ko.unwrap(p.enabled);
		await this.pluginOp(p.name, async () => {
			await api.post(`/admin/plugins/${p.name}/install`);
			if (wasEnabled) {
				location.reload(); // code + assets du plugin changés
				return;
			}
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.updated_ok', { name: p.name }));
		});
	}
	// Désinstallation définitive : dossier supprimé + config purgée.
	async removePlugin(p) {
		if (!(await dialog.confirmDanger(t('admin.plugins.confirm_remove', { name: p.name }), { okLabel: t('admin.plugins.remove') }))) { return; }
		const wasEnabled = !!ko.unwrap(p.enabled);
		await this.pluginOp(p.name, async () => {
			await api.post(`/admin/plugins/${p.name}/remove`);
			if (wasEnabled) {
				location.reload(); // hooks admin.mounts du plugin disparus
				return;
			}
			await this.refreshPlugins();
			await this.checkPluginsCatalog();
			this.ok(t('admin.plugins.removed_ok', { name: p.name }));
		});
	}
	async togglePlugin(p, e) {
		await this.togglePost(`/admin/plugins/${p.name}/toggle`, p.enabled, 'enabled');
	}
	async togglePluginAllowed(p) {
		await this.togglePost(`/admin/plugins/${p.name}/allowed`, p.user_allowed, 'allowed');
	}
	// Édition/sauvegarde de la config schéma d'un plugin ou d'un thème :
	// même GET/POST /admin/<kind>/<name>/config, mêmes champs - seuls
	// l'observable d'édition et la modale diffèrent.
	_schemaCfg(kind) {
		return kind === 'plugins'
			? { obs: 'editingPlugin', modal: 'pluginConfigModal' }
			: { obs: 'editingTheme', modal: 'themeConfigModal' };
	}
	async editSchemaConfig(kind, name, extra = {}) {
		const cfg = this._schemaCfg(kind);
		const r = await api.get(`/admin/${kind}/${name}/config`)
			.catch(e => { this.error(e.message); return null; });
		if (!r) { this[cfg.obs](null); return; }
		this[cfg.obs]({
			name, ...extra,
			error: ko.observable(''), // erreur affichée dans la modale (alerte racine masquée par le backdrop)
			fields: ko.observableArray(this.schemaFields(r.schema, r.values,
				{ labelCol: 'col-4', fieldCol: 'col-8' })),
		});
		this[cfg.modal]?.show();
	}
	async saveSchemaConfig(kind) {
		const cfg = this._schemaCfg(kind);
		const p = this[cfg.obs]();
		const values = this.collectFields(p.fields(),
			{ boolInt: true, stringify: true });
		try {
			await api.post(`/admin/${kind}/${p.name}/config`, values);
			this[cfg.modal]?.hide(); // hidden.bs.modal > observable null
			this.ok(t('settings.saved'));
		} catch (e) { p.error(e.message); }
	}
	async editPluginConfig(p) {
		if (!p.has_schema) { return; }
		await this.editSchemaConfig('plugins', p.name, { label: p.label });
	}
	async savePluginConfig() { await this.saveSchemaConfig('plugins'); }
	async editThemeConfig(t) { await this.editSchemaConfig('themes', t.name); }
	async saveThemeConfig() { await this.saveSchemaConfig('themes'); }
	mapThemes(items) {
		return (items || []).map(t => ({ ...t, allowed: ko.observable(!!t.selectable) }));
	}
	// Resync complet : changer le défaut change aussi le "selectable" forcé
	// de l'ancien/nouveau défaut - les observables seuls ne suffisent pas.
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
		await this.togglePost(`/admin/themes/${theme.name}/allowed`, theme.allowed, 'allowed');
	}
	async logout() { await api.post('/admin/logout'); location.reload(); }
}
const vm = new AdminVM();
ko.applyBindings(vm);
vm.init().catch(e => vm.error(e.message));
