import { api } from './api.js';
import { t, lang } from './i18n.js';
import './bindings/editor.js';
import './bindings/plugin-mount.js';
import './bindings/pane-resize.js';
import './bindings/modal-resize.js';
import './bindings/indeterminate.js';
import './bindings/tooltip.js';
import './bindings/toast.js';
import { FolderListVM } from './viewmodels/folders.js';
import { MessageListVM } from './viewmodels/messagelist.js';
import { MessageViewVM } from './viewmodels/messageview.js';
import { ComposeVM } from './viewmodels/compose.js';
import { SettingsVM } from './viewmodels/settings.js';
import { dialog } from './ui/dialog.js';
import { notify } from './ui/notify.js';
import { toast } from './ui/toast.js';
import { initShortcuts } from './shortcuts.js';

/** decodeURIComponent qui ne casse pas le routage sur un hash malformé (%zz). */
const safeDecode = (s) => { try { return decodeURIComponent(s); } catch { return s; } };

/**
 * ViewModel racine de l'application mail.
 * Hash routing : #/{dossier} | #/@settings[/<onglet>] - le serveur ne rend que la page shell.
 */
class AppVM {
	constructor() {
		this.t = t;       // utilisable dans les bindings : $root.t('cle')
		this.lang = lang; // locale courante (toLocaleString…)
		this.view = ko.observable('mail'); // 'mail' | 'settings'
		this.folderList = new FolderListVM(this);
		this.messageList = new MessageListVM(this);
		this.messageView = new MessageViewVM(this);
		this.compose = new ComposeVM(this);
		this.settings = new SettingsVM(this);
		this.user = ko.observable(null);
		this.accounts = ko.observableArray([]);
		this.features = ko.observable({});   // flags admin : contacts, accounts, dangerous_actions…
		this.messageToolbar = ko.observableArray([]); // slots barre d'actions (plugins)
		this.pluginTabs = []; // onglets Paramètres injectés par plugins (app.data, fixé au boot)
		// Surface d'accès liste pour les modules montés via pluginMount
		window.mxList = {
			checked: () => this.messageList.checkedMessages(),
			reload:  () => this.messageList.load(),
			folder:  () => this.messageList.folder(),
		};
		this.debug = ko.observable(false);   // réglage admin Debug (logs console)
		this.userSettings = {};              // réglages utilisateur (fusion user_defaults)
		this._lastFolder = null;             // dernier dossier consulté (appdata, par compte)
		this.folderPaneWidth = ko.observable(240);
		this.messagePaneWidth = ko.observable(340);
		this.layout = ko.observable('horizontal');
		this.messagePaneHeight = ko.observable(320);
		// En vertical : 3 colonnes × 3 lignes ; dossiers + poignée 1 occupent toute
		// la hauteur, liste/poignée 2/lecteur s'auto-placent en colonne 3 (ordre DOM).
		// Toujours renvoyer les 2 propriétés : KO 3.5.1 ne retire pas une propriété
		// de style précédemment posée si elle disparaît du nouvel objet.
		this.gridTemplate = ko.pureComputed(() => this.layout() === 'vertical'
			? { gridTemplateColumns: `${this.folderPaneWidth()}px 6px 1fr`,
				gridTemplateRows: `${this.messagePaneHeight()}px 6px 1fr` }
			: { gridTemplateColumns: `${this.folderPaneWidth()}px 6px ${this.messagePaneWidth()}px 6px 1fr`,
				gridTemplateRows: '1fr' });
		// Proxy : observable piloté par le drag du séparateur liste↔lecteur selon l'axe actif
		this.messagePaneSize = ko.pureComputed({
			read: () => this.layout() === 'vertical' ? this.messagePaneHeight() : this.messagePaneWidth(),
			write: v => this.layout() === 'vertical' ? this.messagePaneHeight(v) : this.messagePaneWidth(v),
		});
		this.listPaneAxis = ko.pureComputed(() => this.layout() === 'vertical' ? 'y' : 'x');
		this.listPaneMin = ko.pureComputed(() => this.layout() === 'vertical' ? 120 : 200);
		this.listPaneMax = ko.pureComputed(() => this.layout() === 'vertical' ? 1200 : 800);
		this.theme = ko.observable('default');
		this.themeOptions = ko.observableArray([]);
		this.themeData = {};
		this.popupIdentityPending = false;
		this._logoutTimer = null;
		this._logoutReset = null;
		this._refreshTimer = null;
		this._polling = false;      // anti-recouvrement des ticks poll()
		// Détection nouveaux mails : fullName => { unread, lastUid } (seedé au boot)
		this._watch = {};
		this.notifyFolders = ko.observableArray([]); // surveillés, INBOX incluse
		this.notifyEnabled = ko.observable(false);   // réglage notify_new_mail

		// Badge non-lus de l'INBOX dans le titre d'onglet ("(3) MXWebMail") -
		// computed en effet de bord : retracke chaque unread() (poll, lectures,
		// bulk ops) sans site d'appel à maintenir.
		this._baseTitle = document.title;
		ko.computed(() => {
			const n = this.folderList.folders()
				.filter(f => f.isInbox).reduce((s, f) => s + f.unread(), 0);
			document.title = n > 0 ? `(${n}) ${this._baseTitle}` : this._baseTitle;
		});

		this.quota = {
			used: ko.observable(0),   // Ko
			limit: ko.observable(0),  // Ko - 0 = pas de quota / non supporté
		};
		this.quota.pct = ko.pureComputed(() =>
			this.quota.limit() ? Math.min(100, Math.round(this.quota.used() / this.quota.limit() * 100)) : 0);
		this.quota.label = ko.pureComputed(() => {
			const fmt = kb => kb >= 1048576 ? (kb / 1048576).toFixed(1) + ' ' + t('unit.gb')
				: kb >= 1024 ? Math.round(kb / 1024) + ' ' + t('unit.mb') : kb + ' ' + t('unit.kb');
			return t('app.quota_used', {
				used: fmt(this.quota.used()),
				limit: fmt(this.quota.limit()),
				pct: this.quota.pct(),
			});
		});
		// Largeur du label interne = largeur piste / largeur barre
		this.quota.labelWidth = ko.pureComputed(() =>
			(10000 / Math.max(this.quota.pct(), 1)).toFixed(2) + '%');

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(AppVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	async init() {
		const data = await api.get('/appdata');
		this.user(data.user);
		this.accounts(data.accounts || []);
		// Retour du flow OAuth2 en redirection pleine page (popup bloquée)
		// Lien mailto: externe (protocole enregistré) > composition pré-remplie.
		// replaceState retire le param pour qu'un F5 ne ré-ouvre pas le compose.
		const mailto = new URLSearchParams(location.search).get('mailto');
		if (mailto) { history.replaceState(null, '', location.pathname + location.hash); }
		const oauth = new URLSearchParams(location.search).get('oauth');
		if (oauth) {
			history.replaceState(null, '', location.pathname + location.hash);
			if (oauth === 'ok') {
				toast.success(t('app.account_added'), { title: t('app.add_account') });
			} else {
				dialog.alert(this.oauthError(oauth),
					{ title: t('app.add_account'), variant: 'danger' });
			}
		}
		this.features(data.features || {});
		this.messageToolbar(data.messageToolbar || []);
		this.pluginTabs = data.pluginTabs || [];
		this.debug(!!data.debug);
		this.userSettings = data.settings || {};
		this._lastFolder = data.last_folder || null;
		this.themeOptions(data.themes || []);
		this.themeData = data.themeData || {};
		// Thème valide uniquement : repli sur 'default' comme côté serveur (current())
		this.theme((data.themes || []).some(x => x.name === data.settings?.theme)
			? data.settings.theme : 'default');
		// Changement de thème > swap CSS + config à chaud
		this.theme.subscribe(name => this.applyTheme(name));
		this.applyTheme(this.theme()); // état initial (idempotent avec le rendu serveur)
		// Largeurs des panneaux (bornes identiques à la validation admin)
		const clamp = (v, min, max, def) => {
			v = parseInt(v, 10);
			return (v >= min && v <= max) ? v : def;
		};
		this.folderPaneWidth(clamp(data.settings?.folder_pane_width, 140, 600, 240));
		this.messagePaneWidth(clamp(data.settings?.message_pane_width, 200, 800, 340));
		this.layout(data.settings?.layout === 'vertical' ? 'vertical' : 'horizontal');
		this.messagePaneHeight(clamp(data.settings?.message_pane_height, 120, 1200, 320));
		// Réglages utilisateur : messages par page + tri de la liste
		const mpp = parseInt(data.settings?.messages_per_page, 10);
		if (mpp > 0) { this.messageList.limit = mpp; }
		this.messageList.sort(this.messageList.normalizeSort(data.settings?.list_sort));
		this.messageList.useThreads(!!data.settings?.list_threads);
		// Déconnexion après inactivité (minutes, 0 = jamais)
		const autologout = parseInt(data.settings?.autologout, 10) || 0;
		if (autologout > 0) { this.armAutologout(autologout); }
		// Identité incomplète au premier login > popup réglages.
		// Passe par le loader du SettingsVM : promesse mémoïsée, pas de doublon.
		if (data.features?.popup_identity) {
			const ids = await this.settings.loadIdentities();
			const def = ids.find(i => Number(i.is_default)) || ids[0];
			if (def && !(def.name || '').trim()) { this.popupIdentityPending = true; }
		}
		// Boot mutualisé : dossiers + première page de messages + quota en
		// UNE requête /api/poll = UNE connexion IMAP (avant : /folders +
		// /messages + /quota = 3 connexions). watch vide : le seed _watch a
		// lieu après application des dossiers.
		const initialFolder = this.folderFromHash();
		if (initialFolder !== null) {
			// Positionné AVANT routeFromHash : hash identique > pas de
			// selectFolder > pas de second chargement de liste.
			this.messageList.folder(initialFolder);
			this.messageList.page(1);
			// Hash vide (dossier restauré) > URL canonisée en replaceState :
			// reflète l'état sans empiler une entrée d'historique.
			if (location.hash.replace(/^#\//, '') === '') {
				history.replaceState(null, '', location.pathname + location.search
					+ '#/' + encodeURIComponent(initialFolder));
			}
		}
		const listP = initialFolder === null ? null : {
			folder: initialFolder, page: 1, limit: this.messageList.limit,
			sort: this.messageList.sort(), threads: this.messageList.useThreads() ? 1 : 0,
		};
		const bootP = api.get('/poll?' + new URLSearchParams({
			watch: '{}',
			...(listP ? { list: JSON.stringify(listP) } : {}),
			quota: '1',
		}));
		// Dossiers surveillés (données du boot appdata, pas de RTT dédié) +
		// seed de l'état de détection : seules les hausses de non-lus
		// postérieures au boot déclenchent une notification.
		this.notifyFolders(data.notify_folders || ['INBOX']);
		this.notifyEnabled(!!data.settings?.notify_new_mail);
		const refresh = parseInt(data.settings?.list_refresh, 10) || 0;
		await this.compose.init();
		if (mailto) { this.compose.openMailto(mailto); }
		// Handler mailto: système - une seule proposition par navigateur
		// (accepté ou refusé, Chrome ne re-demande pas).
		try {
			if (!localStorage.getItem('mxwm_mailto_reg')) {
				navigator.registerProtocolHandler?.('mailto',
					window.MXWM.base + '/?mailto=%s');
				localStorage.setItem('mxwm_mailto_reg', '1');
			}
		} catch (e) { /* contexte non sécurisé / navigateur sans support */ }
		window.addEventListener('hashchange', () => this.routeFromHash());
		if (this.popupIdentityPending) {
			// popup_identity : ouvre l'onglet identités tant que le nom est vide
			location.hash = '#/@settings/identities';
		}
		this.routeFromHash();
		// Repli si /api/poll échoue : les handlers unitaires gardent leurs
		// surfaces d'erreur habituelles (toast panneau dossiers / liste).
		const boot = await bootP.catch(() => null);
		let bootListDone = false;
		if (boot?.folders) {
			this.folderList.applyList(boot.folders);
			for (const f of this.folderList.folders()) {
				if (f.isInbox || this.notifyFolders().includes(f.fullName)) {
					this._watch[f.fullName] = { unread: f.unread(), lastUid: 0 };
				}
			}
			const folderOk = this.folderList.folders()
				.some(f => f.fullName === this.messageList.folder());
			if (this.view() === 'mail' && !folderOk) {
				// Dossier cible (restauré ou hash) disparu : repli INBOX -
				// selectFolder le persiste, auto-réparation du réglage.
				await this.messageList.selectFolder('INBOX');
				bootListDone = true;
			} else if (listP) {
				bootListDone = true;
				if (boot.list) {
					this.messageList.applyData(boot.list);
				} else if (this.view() === 'mail') {
					// Section "list" en échec côté serveur : repli endpoint dédié.
					this.messageList.load();
				}
			}
			if (boot.quota) {
				this.quota.used(boot.quota[0] ?? 0);
				this.quota.limit(boot.quota[1] ?? 0);
			}
		} else {
			// /api/poll en échec : repli sur l'endpoint dédié (erreur dans le
			// panneau dossiers) ; la liste suit le repli de fin d'init.
			this.folderList.load();
		}
		// Réactualisation auto de la liste (réglage list_refresh, minutes, 0 = jamais)
		if (refresh > 0) { this.armAutoRefresh(refresh); }
		// Onglet masqué : les ticks sont suspendus ; retour visible > poll immédiat.
		document.addEventListener('visibilitychange', () => {
			if (!document.hidden && this._refreshTimer) { this.poll(); }
		});
		if (this.view() === 'mail' && !this.messageList.messages().length && !bootListDone) {
			this.messageList.load();
		}
		initShortcuts(this);
	}

	/** Dossier cible du hash courant pour le préchargement du boot -
	 *  null pour la vue settings (pas de liste mail à charger). */
	folderFromHash() {
		const raw = location.hash.replace(/^#\//, '');
		if (raw.split('/')[0] === '@settings' || this.popupIdentityPending) {
			return null;
		}
		// Hash vide > dernier dossier consulté (réglage par compte).
		return raw === '' ? (this._lastFolder || 'INBOX') : safeDecode(raw);
	}

	routeFromHash() {
		// Le test de vue se fait sur le hash BRUT : '@settings' ne peut pas
		// être produit par encodeURIComponent d'un nom de dossier ('@' > %40),
		// donc un dossier nommé 'settings' (ou '@settings') ne masque pas la vue.
		const raw = location.hash.replace(/^#\//, '');
		const [page, sub, detail] = raw.split('/');
		if (page === '@settings') {
			const entering = this.view() !== 'settings';
			this.view('settings');
			// init() seede depuis les données du boot (aucun réseau, sauf
			// popup_identity) ; activate() attend sa résolution - la whitelist
			// dépend de pluginTabs (deep-link onglet plugin).
			(entering ? this.settings.init().catch(() => {}) : Promise.resolve())
				.then(() => this.settings.activate(
					safeDecode(sub || '') || 'general',
					detail ? safeDecode(detail) : detail));
			return;
		}
		this.view('mail');
		this.settings.filterModal?.hide();
		this.settings.identityModal?.hide();
		// Hash vide > dernier dossier consulté ; le decode ne s'applique
		// qu'au hash explicite (le nom stocké est déjà littéral).
		const target = raw === '' ? (this._lastFolder || 'INBOX') : safeDecode(raw);
		if (target !== this.messageList.folder()) {
			this.messageList.selectFolder(target); // hash complet : un dossier peut contenir '/'
		}
	}

	async switchAccount(a) {
		if (a.active) { return; }
		await api.post(`/accounts/${a.id}/switch`);
		// Retour systématique sur INBOX : un dossier est propre à un compte.
		// replaceState (pas de hashchange > pas de selectFolder fantôme sur
		// l'ancien compte) puis reload : l'ancien dossier disparaît aussi de
		// l'historique - "retour" ne peut pas retomber sur le hash périmé.
		history.replaceState(null, '', '#/INBOX');
		location.reload();
	}

	/**
	 * Ajout de compte secondaire ; arg = email pré-rempli (ré-édition des
	 * identifiants depuis Réglages > Comptes) - KO passe $data au handler
	 * click, seule une chaîne déclenche le mode "édition".
	 */
	async addAccount(arg) {
		const preset = typeof arg === 'string' ? arg.trim() : '';
		const title = preset ? t('settings.account_edit') : t('app.add_account');
		// Domaine OAuth2 (gmail, outlook…) > consentement provider en popup ;
		// sinon ajout classique par mot de passe IMAP. needs_app : l'utilisateur
		// n'a pas encore enregistré d'application OAuth2 pour ce provider.
		let oauth = null; // réponse /accounts/oauth si handoff vers le flow OAuth
		const v = await dialog.form({
			title,
			fields: [
				{ name: 'email', label: t('app.add_account_email'), inputType: 'email',
					defaultValue: preset, readonly: !!preset },
				{ name: 'password', label: t('app.add_account_pass'), inputType: 'password',
					hint: t('app.add_account_pass_hint') },
			],
			okLabel: preset ? t('common.save') : t('common.add'),
			onSubmit: async (f) => {
				const email = f.email.trim().toLowerCase();
				if (!email.includes('@')) { return t('errors.email_pass_required'); }
				let r;
				try { r = await api.post('/accounts/oauth', { email }); }
				catch (e) { return e.message; }
				if (r.needs_app || r.url) { oauth = r; return true; }
				if (!f.password) { return t('errors.email_pass_required'); }
				try { await api.post('/accounts', { email, password: f.password }); }
				catch (e) { return e.message; }
				return true;
			},
		});
		if (v === null) { return; } // annulation
		if (oauth) {
			// Suite hors modale (séquentiel, pas d'empilement de modales).
			try {
				let r = oauth;
				if (r.needs_app) {
					if (!(await this.promptOauthApp(r.provider, r.redirect_uri, title))) { return; }
					r = await api.post('/accounts/oauth', { email: v.email.trim().toLowerCase() });
				}
				if (r.url) { this.openOauthPopup(r.url, title); return; }
				// url toujours null après retry needs_app > garde-fou serveur.
				await dialog.alert(t('errors.oauth_required'), { title, variant: 'danger' });
			} catch (e) {
				await dialog.alert(t('errors.failed', { msg: e.message }),
					{ title, variant: 'danger' });
			}
			return;
		}
		this.accounts(await api.get('/accounts'));
		toast.success(t('app.account_added'), { title });
	}

	oauthError(code) {
		const keys = { denied: 'errors.oauth_denied', state: 'errors.oauth_state',
			email_mismatch: 'errors.oauth_email_mismatch' };
		return t(keys[code] || 'errors.oauth_failed');
	}

	/**
	 * Formulaire d'enregistrement de l'app OAuth2 de l'utilisateur pour un
	 * provider (premier ajout d'un compte de ce domaine). false si annulé.
	 */
	async promptOauthApp(provider, redirectUri, title = null) {
		const fields = [
			{ name: 'client_id', label: t('settings.oauth_client_id') },
			{ name: 'secret', label: t('settings.oauth_secret'), inputType: 'password' },
		];
		if (provider === 'microsoft') {
			fields.push({ name: 'tenant', label: t('settings.oauth_tenant'),
				defaultValue: 'common' });
		}
		const v = await dialog.form({
			title: title || t('app.add_account'),
			message: t('app.oauth_app_required', { provider })
				+ '\n\n' + t('settings.oauth_redirect_uri') + ' : ' + redirectUri,
			fields, okLabel: t('common.save'),
		});
		if (v === null) { return false; }
		await api.post('/oauth/apps', { provider, ...v });
		return true;
	}

	/** Popup de consentement provider ; repli pleine page si bloquée. */
	openOauthPopup(url, title = null) {
		const w = window.open('about:blank', 'mxwm-oauth', 'width=620,height=720');
		if (!w) { location.href = url; return; }
		w.location = url;
		const done = () => {
			window.removeEventListener('message', onMsg);
			clearInterval(timer);
			api.get('/accounts').then(a => this.accounts(a)).catch(() => {});
		};
		const onMsg = (e) => {
			if (e.origin !== location.origin || e.data?.type !== 'mxwm-oauth') { return; }
			const ok = !!e.data.ok;
			done();
			if (ok) {
				toast.success(t('app.account_added'), { title: title || t('app.add_account') });
			} else {
				dialog.alert(this.oauthError(e.data.err),
					{ title: title || t('app.add_account'), variant: 'danger' });
			}
		};
		window.addEventListener('message', onMsg);
		// Popup fermée sans message (fermeture manuelle) > refresh quand même
		const timer = setInterval(() => { if (w.closed) { done(); } }, 500);
	}

	/** Timer de réactualisation auto (minutes, réglage list_refresh ; 0 = arrêt). */
	armAutoRefresh(minutes) {
		clearInterval(this._refreshTimer);
		this._refreshTimer = null;
		if (minutes > 0) {
			this._refreshTimer = setInterval(() => this.poll(), minutes * 60000);
		}
	}

	/**
	 * Tick de réactualisation : UNE requête /api/poll = dossiers (badges) +
	 * derniers messages des dossiers surveillés (détection nouveaux mails) +
	 * reload de la liste courante - une seule connexion IMAP au lieu de
	 * 1 + N + 1. Effectué même avec un message affiché ; sauté en
	 * multi-sélection manuelle, marquage "lu" différé en vol ou onglet
	 * masqué (visibilitychange relance un tick au retour).
	 * _polling : un tick n'en chevauche pas un autre encore en vol.
	 */
	async poll() {
		if (this._polling || document.hidden) { return; }
		this._polling = true;
		try {
			// Baseline = unread du tick précédent ; le serveur ne fetch le
			// dernier message que si unread a augmenté (plafond 20 dossiers).
			const watch = {};
			for (const f of this.folderList.folders()) {
				if (!f.isInbox && !this.notifyFolders().includes(f.fullName)) { continue; }
				// ??= : un dossier abonné après le boot se seede sans notifier
				const prev = this._watch[f.fullName] ??= { unread: f.unread(), lastUid: 0 };
				watch[f.fullName] = prev.unread;
			}
			const ml = this.messageList;
			// Le mail visionné est auto-coché par select() : "sélection en
			// cours" = uniquement les coches manuelles (non sélectionnées).
			// dragItems() : un reload en plein glisser-déposer remplacerait les
			// MessageModel sous le pointeur (retrait optimiste par identité).
			const busy = ml.checkedMessages().some(m => !m.isSelected())
				|| ml._readTimer !== null
				|| ml.dragItems() !== null;
			const doList = this.view() === 'mail' && !busy;
			const global = ml.searchScope() === 'all' && ml.searchQuery() !== '';
			const qs = new URLSearchParams({ watch: JSON.stringify(watch) });
			if (doList) {
				qs.set('list', JSON.stringify({
					folder: global ? '*' : ml.folder(), page: ml.page(), limit: ml.limit,
					search: ml.searchQuery(), sort: ml.sort(),
					threads: !global && ml.useThreads() ? 1 : 0,
				}));
			}
			let d;
			try {
				d = await api.get('/poll?' + qs);
			} catch (e) {
				// Même surface d'erreur que folderList.load() : toast du panneau
				// dossiers, pas de popup globale à chaque tick en échec.
				this.folderList.error(e.message);
				return;
			}
			this.folderList.applyList(d.folders);
			for (const [name, payload] of Object.entries(d.latest || {})) {
				const prev = this._watch[name];
				// null = fetch impossible côté serveur : baseline conservée,
				// la détection sera retentée au prochain tick.
				if (!prev || payload === null) { continue; }
				const f = this.folderList.folders().find(x => x.fullName === name);
				prev.unread = f ? f.unread() : prev.unread;
				const msg = payload.messages?.[0];
				if (msg && msg.uid !== prev.lastUid) {
					prev.lastUid = msg.uid;
					if (this.notifyEnabled() && f) {
						notify.newMail({
							folderLabel: f.isInbox ? '' : f.label,
							from: msg.from || '', subject: msg.subject || '',
							uid: msg.uid, folder: name,
							onOpen: (uid, folder) => this.openMail(uid, folder),
						});
					}
				}
			}
			// Baselines des dossiers surveillés non remontés dans latest
			// (unread stable) : alignées sur les valeurs fraîches.
			for (const f of this.folderList.folders()) {
				const prev = this._watch[f.fullName];
				if (prev && !(f.fullName in (d.latest || {}))) {
					prev.unread = f.unread();
				}
			}
			if (doList) {
				if (d.list) {
					// applyData préserve lui-même coches et sélection (rebuild)
					// ou réconcilie in-place (tick sans changement structurel).
					ml.applyData(d.list);
				} else {
					// Section "list" en échec côté serveur : repli endpoint dédié.
					await ml.load(true);
				}
			}
		} finally {
			this._polling = false;
		}
	}

	/** Notification cliquée : focus + ouvre le dossier puis sélectionne le mail. */
	async openMail(uid, folder) {
		if (folder !== this.messageList.folder()) {
			await this.messageList.selectFolder(folder);
		} else {
			location.hash = '#/' + encodeURIComponent(folder);
		}
		const msg = this.messageList.messages().find(m => m.uid === uid);
		if (msg) { await this.messageList.select(msg); }
	}

	/** Timer d'inactivité > logout automatique (réglage autologout, minutes).
	 *  Ré-armable sans duplication de listeners ; 0 = désarmé. */
	armAutologout(minutes) {
		this.disarmAutologout();
		if (minutes <= 0) { return; }
		const reset = () => {
			clearTimeout(this._logoutTimer);
			this._logoutTimer = setTimeout(() => this.idleLogout(), minutes * 60000);
		};
		this._logoutReset = reset;
		for (const ev of ['click', 'keydown', 'mousemove', 'scroll']) {
			window.addEventListener(ev, reset, { passive: true });
		}
		reset();
	}

	/** Retire le timer et les listeners d'inactivité (réglage autologout = 0). */
	disarmAutologout() {
		clearTimeout(this._logoutTimer);
		this._logoutTimer = null;
		if (this._logoutReset) {
			for (const ev of ['click', 'keydown', 'mousemove', 'scroll']) {
				window.removeEventListener(ev, this._logoutReset);
			}
			this._logoutReset = null;
		}
	}

	openSettings() {
		location.hash = '#/@settings';
	}

	/** Applique un template : CSS + variables de config + attributs <html>. */
	applyTheme(name) {
		const link = document.getElementById('theme-css');
		// Swap uniquement sur vrai changement : le ?v= du rendu serveur est un
		// mtime, celui calculé ici la version du manifeste - une comparaison
		// d'URL complète serait toujours fausse, on teste le nom dans le path.
		if (link && !link.href.includes(`/assets/theme/${name}/`)) {
			const v = this.themeOptions().find(t => t.name === name)?.version || window.MXWM.ver;
			link.href = `${window.MXWM.base}/assets/theme/${name}/app.css?v=${v}`;
		}
		const d = this.themeData?.[name];
		if (d) {
			const el = document.getElementById('theme-config');
			if (el) { el.textContent = d.varsCss || ''; }
			const dark = d.darkMode === 'dark'
				|| (d.darkMode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
			document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
			document.documentElement.setAttribute('data-dark-mode', d.darkMode || 'auto');
		}
	}

	/** Persiste les dimensions des panneaux dans les réglages utilisateur. */
	async savePaneSizes() {
		this.userSettings.folder_pane_width = this.folderPaneWidth();
		this.userSettings.message_pane_width = this.messagePaneWidth();
		this.userSettings.message_pane_height = this.messagePaneHeight();
		await api.post('/settings', {
			folder_pane_width: this.folderPaneWidth(),
			message_pane_width: this.messagePaneWidth(),
			message_pane_height: this.messagePaneHeight(),
		}).catch(() => {}); // silencieux : préférence cosmétique, non bloquante
	}

	async logout() {
		await api.post('/logout');
		location.href = window.MXWM.base + '/';
	}

	/**
	 * Déconnexion d'inactivité (timer autologout) : la session est détruite
	 * mais le jeton "Rester connecté" survit - la page rechargée restaure la
	 * session silencieusement. Sans jeton : retour à la page de login comme
	 * avant. Le catch silencieux évite un toast d'erreur intempestif sur un
	 * timer en tâche de fond (le reload retombe alors sur l'app ou le login).
	 */
	async idleLogout() {
		this.disarmAutologout();
		await api.post('/logout', { idle: true }).catch(() => {});
		location.reload();
	}
}

const vm = new AppVM();
ko.applyBindings(vm);

// Splash de démarrage (HTML/CSS pur, visible avant knockout) : retiré en
// fondu quand l'interface est prête - fin d'init() + 1er chargement de
// liste - avec durée minimale d'affichage pour éviter le clignotement.
const splash = document.getElementById('app-splash');
let splashHidden = false;
const hideSplash = () => {
	if (splashHidden || !splash) { return; }
	splashHidden = true;
	clearTimeout(splashGuard);
	splash.classList.add('done');
	// remove() différé d'une frame : le navigateur commite d'abord une frame
	// où l'élément est déjà invisible (opacity:0 + visibility:hidden) - sinon
	// le compositeur peut repeindre une frame à pleine opacité (flash fantôme).
	splash.addEventListener('transitionend',
		() => requestAnimationFrame(() => splash.remove()), { once: true });
	// transitionend non garanti (prefers-reduced-motion > transition:none)
	// et rAF suspendu en onglet caché : repli à 600 ms (> 250 ms de fondu).
	setTimeout(() => splash.remove(), 600);
};
// API en carafe : jamais d'écran figé.
const splashGuard = setTimeout(hideSplash, 20000);

// Résout quand le 1er chargement de liste se termine ; en vue settings
// aucun load() n'est lancé > promesse déjà résolue.
const firstListLoaded = () => {
	if (vm.view() !== 'mail' || !vm.messageList.loading()) { return Promise.resolve(); }
	return new Promise(res => {
		const sub = vm.messageList.loading.subscribe(v => {
			if (!v) { sub.dispose(); res(); }
		});
	});
};
// performance.now() = ms depuis le début de navigation.
const minSplashTime = () => new Promise(res =>
	setTimeout(res, Math.max(0, 400 - performance.now())));

vm.init()
	.then(firstListLoaded)
	.then(minSplashTime)
	.catch(e => {
		// textContent : pas de HTML depuis un message d'erreur
		const div = document.createElement('div');
		div.className = 'alert alert-danger m-3';
		div.textContent = t('errors.load', { msg: e.message });
		document.body.append(div);
	})
	.finally(hideSplash);
