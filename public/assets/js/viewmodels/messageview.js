import { api, base } from '../api.js';
import { dialog } from '../ui/dialog.js';
import { toast } from '../ui/toast.js';
import { t } from '../i18n.js';
import { loadOpenPgp } from '../lazy.js';

/**
 * ViewModel : lecture d'un message.
 * bodyHtml est sanitisé (allowlist stricte, style SnappyMail) puis rendu
 * inline dans #mail-body - jamais innerHTML brut, jamais d'iframe.
 */
export class MessageViewVM {
	constructor(app) {
		this.app = app;
		this.message = ko.observable(null);
		this.loading = ko.observable(false);
		this.error = ko.observable('');
		this.decryptedText = ko.observable('');
		this.imagesBlocked = ko.observable(false); // images distantes masquées (view_images)
		this.avatarSrc = ko.observable('');       // plugin avatars
		this.sourceText = ko.observable('');      // source MIME affiché dans la modale
		this.sourceModal = null;
		// Aperçu de pièce jointe (image/PDF/texte) - modale dédiée #preview-modal.
		this.previewModal = null;
		this.previewName = ko.observable('');
		this.previewType = ko.observable('');      // 'image' | 'pdf' | 'text' | 'eml'
		this.previewUrl = ko.observable('');
		this.previewText = ko.observable('');
		this.previewEml = ko.observable(null);     // payload .eml (message/rfc822)
		this.previewEmlAtt = null;                 // PJ .eml de 1er niveau (mimeIndex source)
		this.previewEmlMsg = null;                 // message propriétaire du .eml (snapshot)
		this.previewEmlPath = ko.observable('');   // chemin ?sub= du noeud courant
		this._mdnDismissed = false;                // "Ignorer" : masque le bandeau du message courant
		// Bandeau accusé de réception : demandé + pas encore honoré +
		// réglage mdn_mode ≠ 'never' (ask par défaut).
		this.mdnBanner = ko.pureComputed(() => {
			const m = this.message();
			return !this._mdnDismissed && !!m?.dispositionTo && !m?.mdnSent
				&& (this.app.userSettings?.mdn_mode || 'ask') !== 'never';
		});
		// Badge S/MIME : '' | 'checking' | 'valid' | 'untrusted' | 'invalid'
		this.smimeState = ko.observable('');
		this.smimeLabel = ko.pureComputed(() => {
			const k = { '': 'app.smime_signed', checking: 'app.smime_checking',
				valid: 'app.smime_valid', untrusted: 'app.smime_untrusted',
				invalid: 'app.smime_invalid' }[this.smimeState()] || 'app.smime_signed';
			return t(k);
		});
		this.tab = ko.observable('message');      // onglet actif : 'message' | 'attachments'
		this.security = ko.observable(null);      // verdict plugin anti-spoofing
		this.requestedUid = null;                 // uid+dossier passés à show() - évite
		this.requestedFolder = null;              // un re-fetch au re-clic sur le même mail
		this.securityAlerts = ko.pureComputed(() => this.security()?.alerts || []);
		this.securityOk = ko.pureComputed(() => this.security()?.auth_ok || ''); // SPF/DKIM/DMARC pass
		// Couleur du bouclier : niveau max des alertes (info/warning/danger) ;
		// sans alerte > vert si auth pass, gris sinon (vérif faite, RAS).
		this.securityIconCss = ko.pureComputed(() => {
			const s = this.security();
			if (!s) { return ''; }
			const lvl = s.level || 'none';
			return lvl !== 'none' ? 'text-' + lvl
				: s.auth_ok ? 'text-success' : 'text-muted';
		});
		// Tooltip du bouclier : titre "usurpation possible" seulement si
		// warning/danger, puis une ligne par alerte, puis l'éventuel auth_ok.
		this.securityTip = ko.pureComputed(() => {
			const s = this.security();
			if (!s) { return ''; }
			const lines = [];
			if (s.level === 'warning' || s.level === 'danger') {
				lines.push(t('plugin.anti-spoofing.title'));
			}
			for (const a of this.securityAlerts()) { lines.push(a.text); }
			if (this.securityOk()) { lines.push(this.securityOk()); }
			return lines.join('\n') || t('plugin.anti-spoofing.clean');
		});

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(MessageViewVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}

		// Diagnostic (réglage Debug admin) : si du contenu du mail recouvre
		// un bouton, e.target montre l'élément intercepteur au lieu du bouton.
		// Le flag arrive via /appdata (async) > test dans le handler.
		document.addEventListener('pointerdown', e => {
			if (this.app.debug()) { console.info('[mxwm] hit:', e.target); }
		}, true);
	}

	async show(folder, uid) {
		this.requestedFolder = folder;
		this.requestedUid = uid;
		this.tab('message');
		this.decryptedText('');
		this.imagesBlocked(false);
		this.avatarSrc('');
		this.security(null);
		this._mdnDismissed = false;
		this.smimeState('');
		this.loading(true);
		try {
			const data = await api.get(`/messages/${uid}?folder=${encodeURIComponent(folder)}`);
			// Changement de dossier (clear()) pendant le fetch : ne pas
			// réafficher un mail qui n'appartient plus au dossier courant.
			if (this.requestedUid !== uid || this.requestedFolder !== folder) { return; }
			// Flags mutables en observables : les bindings se rafraîchissent
			// sans re-rendre le bloc `with: message` (qui viderait #mail-body).
			data.isFlagged = ko.observable(!!data.isFlagged);
			data.isJunk = ko.observable(!!data.isJunk);
			data.isSeen = ko.observable(!!data.isSeen);
			this.message(data);
			this.security(data.security || null);
			this.loadAvatar(data);
			const allow = await this.allowRemoteImages(data);
			// Nombre de refs distantes réellement neutralisées dans le doc
			// sanitisé (src/srcset/background/url()) - pas de regex sur le
			// HTML brut, qui pouvait matcher du contenu ensuite supprimé.
			this.imagesBlocked(this.renderBody(data, allow) > 0);
		} catch (e) {
			// Échec : on oublie la demande pour qu'un re-clic retente le fetch
			if (this.requestedUid === uid && this.requestedFolder === folder) {
				this.requestedUid = null;
				this.requestedFolder = null;
			}
			this.error(e.message);
		} finally {
			// Fetch "stale" (clear() ou show() plus récent entre-temps) : ne
			// pas éteindre le spinner du fetch en cours - null = clear() > off.
			if (this.requestedUid === uid || this.requestedUid === null) {
				this.loading(false);
			}
		}
	}

	/**
	 * Réglage utilisateur "view_images" :
	 *   always > proxy systématique ; ask > bloquées jusqu'à clic ;
	 *   match  > affichées si l'expéditeur est dans les contacts.
	 */
	async allowRemoteImages(m) {
		// Proxy désactivé en config admin : aucune image distante affichable.
		if (!this.app.features?.().image_proxy) { return false; }
		const mode = this.app.userSettings?.view_images || 'ask';
		if (mode === 'always') { return true; }
		if (mode === 'match') {
			const from = Array.isArray(m.from) ? m.from[0] : m.from;
			const email = typeof from === 'object' ? from?.email : from;
			// Contacts désactivés : pas d'appel voué à un 403
			if (email && this.app.features?.().contacts !== false) {
				const hits = await api.get('/contacts?q=' + encodeURIComponent(email)).catch(() => []);
				if (hits.length) { return true; }
			}
		}
		return false;
	}

	/** Bouton "Afficher les images" : re-rend le corps via le proxy. */
	showRemoteImages() {
		const m = this.message();
		this.imagesBlocked(false);
		if (m) {
			try { this.renderBody(m, true); }
			catch (e) { console.error('[mxwm] renderBody', e); }
		}
	}

	clear() {
		this.message(null);
		this.requestedUid = null;
		this.requestedFolder = null;
		this.tab('message');
		this.decryptedText('');
		this.imagesBlocked(false);
		this.avatarSrc('');
		this.security(null);
		document.getElementById('mail-body')?.replaceChildren();
	}

	/** Avatar de l'expéditeur via le plugin avatars (si activé). */
	loadAvatar(m) {
		if (!this.app.features?.().avatars) { return; }
		const from = Array.isArray(m.from) ? m.from[0] : m.from;
		const email = typeof from === 'object' ? from?.email : from;
		if (!email) { return; }
		api.get('/plugin/avatars/get?email=' + encodeURIComponent(email))
			.then(r => {
				if (r.dataUrl) { this.avatarSrc(r.dataUrl); }
				else if (r.svg) {
					this.avatarSrc('data:image/svg+xml;utf8,' + encodeURIComponent(r.svg));
				}
			})
			.catch(() => {});
	}

	/** Le corps contient un bloc OpenPGP à déchiffrer ? */
	isPgp() {
		const m = this.message();
		if (!m || this.decryptedText()) { return false; }
		const body = (m.bodyPlain || '') + (m.bodyHtml || '');
		return body.includes('BEGIN PGP MESSAGE');
	}

	async decryptPgp() {
		try {
			const keys = await api.get('/pgp/keys');
			const priv = keys.find(k => Number(k.is_private) === 1);
			// openpgp chargé à la demande (lazy.js) - ~540K hors du boot.
			const openpgp = await loadOpenPgp();
			if (!priv) {
				await dialog.alert(t('pgp.no_private_key'),
					{ variant: 'warning', title: 'OpenPGP' });
				return;
			}
			const pass = await dialog.prompt(t('pgp.passphrase_prompt'),
				{ title: t('pgp.decrypt_title'), inputType: 'password' });
			if (pass === null) { return; }
			const armored = (this.message().bodyPlain || this.message().bodyHtml || '')
				.match(/-----BEGIN PGP MESSAGE-----[\s\S]*?-----END PGP MESSAGE-----/)?.[0];
			if (!armored) {
				await dialog.alert(t('pgp.block_not_found'),
					{ variant: 'danger', title: 'OpenPGP' });
				return;
			}
			const privateKey = await openpgp.decryptKey({
				privateKey: await openpgp.readPrivateKey({ armoredKey: priv.key_data }),
				passphrase: pass,
			});
			const { data } = await openpgp.decrypt({
				message: await openpgp.readMessage({ armoredMessage: armored }),
				decryptionKeys: privateKey,
			});
			this.decryptedText(typeof data === 'string' ? data : new TextDecoder().decode(data));
		} catch (e) {
			await dialog.alert(t('pgp.decrypt_failed', { msg: e.message }),
				{ variant: 'danger', title: 'OpenPGP' });
		}
	}

	/**
	 * Assainit le HTML d'un mail pour un rendu inline dans #mail-body (pas de
	 * frame sandboxée) : allowlist stricte de balises et d'attributs - tout ce
	 * qui n'y figure pas est supprimé (on*, id, srcdoc, autofocus, data-*…).
	 * Seconde couche : CSP script-src 'self'+nonce bloque toute exécution
	 * inline survivante. DOMParser n'exécute rien : parsing sûr.
	 * Approche allowlist portée de SnappyMail cleanHtml().
	 * Retourne le document (refs images + styles réécrits ensuite).
	 */
	sanitizedDoc(html) {
		// <body> > div.mail-body : ses attributs (bgcolor, style, margins…)
		// restent sur la div, qui sera insérée dans #mail-body.
		html = html.replace(/<(\/?)body(\s[^>]*)?>/gi, '<$1div class="mail-body"$2>');
		const doc = new DOMParser().parseFromString(html, 'text/html');

		// Commentaires (dont les conditionnels IE) : inutiles au rendu
		const it = doc.createNodeIterator(doc, NodeFilter.SHOW_COMMENT);
		for (let c; (c = it.nextNode());) { c.remove(); }

		// Conteneurs inertes : le contenu est conservé, pas le conteneur
		for (const el of doc.querySelectorAll('form, button, data')) {
			el.replaceWith(...el.childNodes);
		}
		// xmp : texte brut historique > pre échappé (textContent = pas de HTML)
		for (const el of doc.querySelectorAll('xmp')) {
			const pre = doc.createElement('pre');
			pre.textContent = el.textContent;
			el.replaceWith(pre);
		}

		// Balises autorisées. Exclus : script, svg, math, template, iframe et
		// sous-documents, object, embed, applet, meta, link, base, médias
		// (video/audio/source/track), champs de formulaire (input/select/
		// textarea), frame/frameset, plaintext, bgsound, keygen…
		const allowed = 'a,abbr,acronym,address,article,aside,b,bdi,bdo,big,blockquote,br,'
			+ 'caption,center,cite,code,col,colgroup,dd,del,details,dfn,dir,div,dl,dt,'
			+ 'em,figcaption,figure,font,footer,h1,h2,h3,h4,h5,h6,header,hgroup,hr,i,img,'
			+ 'ins,kbd,li,main,mark,marquee,nav,nobr,ol,p,pre,q,rb,rp,rt,rtc,ruby,s,'
			+ 'samp,section,small,span,strike,strong,style,sub,summary,sup,table,tbody,'
			+ 'td,tfoot,th,thead,time,tr,tt,u,ul,var,wbr';
		// head/body ne sont jamais insérés - seuls leurs descendants sont
		// nettoyés puis déplacés (sinon l'allowlist tuerait tout le contenu).
		for (const root of [doc.head, doc.body]) {
			for (const el of root.querySelectorAll(':not(' + allowed + ')')) {
				el.remove();
			}
		}

		// Attributs autorisés. Exclus notamment : id (DOM clobbering), on*,
		// srcdoc, autofocus, data-*, formaction, usemap, ping, srcset exotiques.
		const allowedAttrs = new Set([
			'abbr','align','alt','background','bgcolor','border','bordercolor',
			'cellpadding','cellspacing','charset','class','color','cols','colspan',
			'dir','direction','download','face','frame','headers','height','high',
			'href','hreflang','hspace','lang','low','name','noshade','optimum',
			'reversed','rows','rowspan','rules','scope','size','sizes','src',
			'srcset','start','style','summary','target','title','valign','value',
			'vspace','width',
		]);
		for (const root of [doc.head, doc.body]) {
			for (const el of root.querySelectorAll('*')) {
				// Contenu masqué inline = tracker ou leurre (SnappyMail idem)
				if (el.style && (el.style.display === 'none'
					|| el.style.visibility === 'hidden')) {
					el.remove();
					continue;
				}
				for (const attr of [...el.attributes]) {
					const name = attr.name.toLowerCase();
					if (!allowedAttrs.has(name)) {
						el.removeAttribute(attr.name);
						continue;
					}
					const v = attr.value.replace(/[\x00-\x20]+/g, '');
					if (['href','src','background'].includes(name)
						&& /^(?:javascript|vbscript|data):/i.test(v)
						&& !(name === 'src' && /^data:image\//i.test(v))) {
						el.removeAttribute(attr.name);
						continue;
					}
					if (name === 'style') {
						// position:fixed/absolute = overlay possible sur l'app
						if (/fixed|absolute|sticky/i.test(el.style.position)) {
							el.style.removeProperty('position');
						}
						for (const p of ['behavior','-moz-binding']) {
							el.style.removeProperty(p);
						}
						if (/expression\s*\(|(?:javascript|vbscript):/i
							.test(el.getAttribute('style'))) {
							el.removeAttribute('style');
						}
					}
				}
				// class > msg-cls : le <style> du mail ne peut cibler les
				// classes de l'app, et la sienne ne reçoit pas nos styles.
				if (el.className && el.className !== 'mail-body') {
					el.className = el.className.replace(/(^|\s+)(\S)/g, '$1msg-$2');
				}
				if (el.tagName === 'A') {
					const href = el.getAttribute('href');
					if (href !== null) {
						if (/^\s*(?:https?:|mailto:|tel:)/i.test(href)) {
							el.setAttribute('target', '_blank');
							el.setAttribute('rel', 'noopener noreferrer');
						} else {
							el.setAttribute('data-x-href-broken', href);
							el.removeAttribute('href');
						}
					}
				}
				// Largeurs fixes attribut > CSS + responsive (newsletters
				// à layout table débordaient du panneau sinon)
				if (['IMG','TABLE'].includes(el.tagName)) {
					for (const key of ['width','height']) {
						const v = el.getAttribute(key);
						if (v && !el.style[key]) {
							el.style[key] = v.includes('%') ? v : v + 'px';
							el.removeAttribute(key);
						}
					}
					const w = el.style.width;
					if (100 < parseInt(w, 10) && !el.style.maxWidth) {
						el.style.maxWidth = w;
						el.style.width = '100%';
					}
				}
			}
		}
		return doc;
	}

	/**
	 * Namespacing des <style> du mail : chaque sélecteur est préfixé
	 * "#mail-body" (et .cls > .msg-cls, en miroir du préfixage d'attributs)
	 * pour qu'ils ne ciblent que le corps du mail. @media est récursé, les
	 * autres @règles (@import, @font-face, @keyframes…) sont ignorées.
	 * parseCSS porté de SnappyMail (textuel - .sheet n'est pas garanti sur
	 * un document DOMParser). scope = conteneur cible ("#mail-body" pour le
	 * lecteur, "#eml-preview-body" pour l'aperçu .eml).
	 */
	scopeStyles(doc, scope = '#mail-body') {
		const cleanRules = rules => rules
			// position:fixed/absolute survivrait au namespace (viewport)
			.replace(/(^|;)\s*position\s*:\s*(?:fixed|absolute|sticky)[^;{}]*/gi, '$1')
			.replace(/(^|;)\s*(?:behavior|-moz-binding)\s*:[^;{}]*/gi, '$1')
			// déclaration contenant un token dangereux > supprimée entièrement
			.replace(/(^|;)[^;{}]*?(?:expression\s*\(|javascript:|vbscript:)[^;{}]*/gi, '$1');
		const parse = source => {
			let out = '';
			source = source
				.replace(/\/\*[\s\S]*?\*\//g, '')   // commentaires CSS
				.replace(/<!--[\s\S]*?-->/g, '')    // commentaires MS Word
				.replace(/<[\s\S]*/g, '');          // balises dans le CSS
			const re = /(?:(\s*?@(?:media)[\s\S]*?){([\s\S]*?)}\s*?})|(?:([\s\S]*?){([\s\S]*?)})/gi;
			let m;
			while ((m = re.exec(source)) !== null) {
				const selector = (m[2] === undefined ? m[3] : m[1])
					.split('\r\n').join('\n').trim()
					.split(/\s+/g)
					.map(item => item.replace(/^(:root|html)$/, ''))
					.join(' ').trim();
				if (selector.includes('@media')) {
					out += selector + '{' + parse(m[2] + '\n}') + '}';
				} else if (selector && !selector.includes('@')) {
					const rules = cleanRules(m[4]);
					if (rules.trim()) {
						out += selector.split(',').map(s =>
							(scope + ' ' + s.trim().replace(/\.([\w-]+)/g, '.msg-$1'))
								.replace(/\sbody/gi, '')
						).join(',') + '{' + rules + '}';
					}
				}
			}
			return out;
		};
		for (const el of doc.querySelectorAll('style')) {
			const css = parse(el.textContent);
			if (css.trim()) { el.textContent = css; } else { el.remove(); }
		}
	}

	/** Normalise un Content-ID ("<a@b>", "a%40b") pour la correspondance cid:. */
	normCid(cid) {
		let v = String(cid).trim();
		try { v = decodeURIComponent(v); } catch { /* encodage malformé */ }
		return v.replace(/^<|>$/g, '').toLowerCase();
	}

	/**
	 * Réécrit les références d'images du corps sanitisé vers les endpoints
	 * authentifiés : cid: > /api/messages/{uid}/attachment/{mimeIndex},
	 * https: > /api/proxy (ou neutralisées si images distantes bloquées).
	 * Content-Location d'une PJ > attachment direct (aucun fetch externe).
	 *
	 * Opère sur le DOM, jamais par regex sur du HTML sérialisé - une regex
	 * pourrait matcher *dans* une valeur d'attribut (alt="logo src=cid:x").
	 * Couvre src, srcset, background, url() dans style et dans <style>.
	 */
	rewriteImageRefs(doc, data, showImages, urlFor = null) {
		const cidMap = new Map(), locMap = new Map();
		for (const a of data.attachments || []) {
			if (!a.mimeIndex && !urlFor) { continue; }
			const url = urlFor ? urlFor(a)
				: `${base()}/api/messages/${data.uid}/attachment/` +
					`${encodeURIComponent(a.mimeIndex)}` +
					`?folder=${encodeURIComponent(data.folder)}&download=0`;
			if (a.cid) { cidMap.set(this.normCid(a.cid), url); }
			if (a.location) { locMap.set(String(a.location).trim(), url); }
		}
		const px = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
		const missing = new Set();
		let blocked = 0; // refs distantes neutralisées (bandeau "Afficher")
		// null > référence à supprimer (distante bloquée, relative, exotique)
		const resolve = v => {
			v = String(v).trim();
			if (/^cid:/i.test(v)) {
				const cid = this.normCid(v.slice(4));
				const u = cidMap.get(cid);
				if (u) { return u; }
				missing.add(cid);
				return px; // cid sans PJ : pixel transparent (pas d'image cassée)
			}
			if (/^data:image\//i.test(v)) { return v; }
			if (v.startsWith('//')) { v = 'https:' + v; } // URL protocole-relative
			if (/^https?:/i.test(v)) {
				const loc = locMap.get(v);
				if (loc) { return loc; }
				if (showImages) {
					return `${base()}/api/proxy?url=${encodeURIComponent(v)}`;
				}
				blocked++;
				return null;
			}
			return null;
		};
		const cssUrl = (m, q, u) => {
			const r = resolve(u);
			if (r === u.trim()) { return m; } // inchangé (data:) : ne pas toucher
			return r === null ? 'none' : `url("${r}")`;
		};
		for (const el of doc.querySelectorAll('[src], [background], [srcset], [style]')) {
			for (const name of ['src', 'background']) {
				const v = el.getAttribute(name);
				if (v === null || !v.trim()) { continue; }
				const r = resolve(v);
				if (r === null) {
					el.removeAttribute(name);
					// mémorise l'URL distante bloquée (debug / "Afficher les images")
					if (/^(?:https?:)?\/\//i.test(v.trim())) {
						el.setAttribute('data-src-blocked', v);
					}
				} else {
					el.setAttribute(name, r);
				}
			}
			const ss = el.getAttribute('srcset');
			if (ss !== null) {
				// Entrées "url [descripteur]" séparées par des virgules. Une URI
				// data: en contient une (mediatype,payload) : si le fragment
				// commence par data: sans virgule, la virgule suivante appartient
				// à l'URI > on recolle avant de traiter l'entrée.
				const chunks = [];
				let pending = '';
				for (const piece of ss.split(',')) {
					pending = pending ? pending + ',' + piece : piece;
					if (/^\s*data:/i.test(pending) && !pending.includes(',')) {
						continue;
					}
					chunks.push(pending);
					pending = '';
				}
				if (pending) { chunks.push(pending); }
				const entries = chunks.map(e => {
					e = e.trim();
					const i = e.search(/\s/);
					const url = i < 0 ? e : e.slice(0, i);
					const r = resolve(url);
					return r === null ? '' : (i < 0 ? r : r + e.slice(i));
				}).filter(Boolean).join(', ');
				if (entries) { el.setAttribute('srcset', entries); }
				else { el.removeAttribute('srcset'); }
			}
			const st = el.getAttribute('style');
			if (st !== null && /url\(/i.test(st)) {
				el.setAttribute('style',
					st.replace(/url\(\s*(["']?)([^"')\s]+)\1\s*\)/gi, cssUrl));
			}
		}
		for (const el of doc.querySelectorAll('style')) {
			el.textContent = el.textContent
				.replace(/url\(\s*(["']?)([^"')\s]+)\1\s*\)/gi, cssUrl);
		}
		if (missing.size) {
			console.warn('[mxwm] cid: sans pièce jointe correspondante :',
				[...missing], '- cids disponibles :', [...cidMap.keys()]);
		}
		return blocked;
	}

	/** Rendu du corps HTML inline dans #mail-body (div sanitisée, pas d'iframe).
	 *  Retourne le nombre de refs d'images distantes neutralisées. */
	renderBody(data, showImages = true) {
		const box = document.getElementById('mail-body');
		if (!box) { return 0; }
		if (data.bodyHtml) {
			const doc = this.sanitizedDoc(data.bodyHtml);
			const blocked = this.rewriteImageRefs(doc, data, showImages);
			this.scopeStyles(doc);
			// <style> du head en premier, puis le contenu (div.mail-body incluse)
			box.replaceChildren(...doc.head.childNodes, ...doc.body.childNodes);
			return blocked;
		}
		const pre = document.createElement('pre');
		pre.style.cssText = 'font:14px/1.5 system-ui,sans-serif;white-space:pre-wrap;margin:0';
		pre.textContent = data.bodyPlain || '';
		box.replaceChildren(pre);
		return 0;
	}

	/** Types servis en aperçu inline : bitmap uniquement (pas de svg/html -
	 *  même whitelist bitmap que PROXY_IMAGE_TYPES côté serveur). */
	static PREVIEW_IMAGES = /^(image\/(png|jpe?g|gif|webp|avif|bmp|x-icon|vnd\.microsoft\.icon))$/i;

	isPreviewable(att) {
		const mime = (att.mime || '').toLowerCase();
		return MessageViewVM.PREVIEW_IMAGES.test(mime)
			|| mime === 'application/pdf'
			|| mime === 'text/plain'
			|| mime === 'message/rfc822'
			|| /\.eml$/i.test(att.name || '');
	}

	/** URL inline (download=0) - attachment() refuse le téléchargement forcé. */
	previewAttachmentUrl(att) {
		return this.attachmentUrl(att) + '&download=0';
	}

	async preview(att) {
		const mime = (att.mime || '').toLowerCase();
		this.previewName(att.name || t('app.search.attachment'));
		this.previewUrl(this.previewAttachmentUrl(att));
		this.previewText('');
		this.previewEml(null);
		this.previewEmlAtt = null;
		this.previewEmlMsg = null;
		this.previewEmlPath('');
		if (MessageViewVM.PREVIEW_IMAGES.test(mime)) {
			this.previewType('image');
		} else if (mime === 'application/pdf') {
			this.previewType('pdf');
		} else if (mime === 'message/rfc822' || /\.eml$/i.test(att.name || '')) {
			this.previewType('eml');
			this.previewEmlAtt = att;
			this.previewEmlMsg = this.message();
			await this.loadEml('');
		} else {
			this.previewType('text');
			try {
				const r = await fetch(this.previewUrl(), { credentials: 'same-origin' });
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				const raw = await r.text();
				this.previewText(raw.length > 262144 ? raw.slice(0, 262144) + '\n…' : raw);
			} catch (e) {
				this.previewText(t('errors.preview_failed') + ' (' + e.message + ')');
			}
		}
		if (!this.previewModal) {
			this.previewModal = new bootstrap.Modal(document.getElementById('preview-modal'));
		}
		this.previewModal.show();
	}

	/** Charge le noeud ?sub= de la PJ .eml courante (récursion eml-dans-eml). */
	async loadEml(sub) {
		try {
			const data = await api.get(this.emlJsonUrl(sub));
			if (this.previewType() !== 'eml') { return; }
			this.previewEml(data);
			this.previewEmlPath(data.sub || '');
			if (data.subject) { this.previewName(data.subject); }
			await this.renderEmlBody(data);
		} catch (e) {
			// Repli texte : un faux .eml (text/plain joint) affiche au moins
			// l'erreur dans le <pre> existant plutôt qu'un bloc invisible.
			this.previewType('text');
			this.previewText(t('errors.preview_failed') + ' (' + e.message + ')');
		}
	}

	/** URL JSON du noeud ?sub= de la PJ .eml courante (mimeIndex 1er niveau). */
	/** Chemin API nu du noeud ?sub= de la PJ .eml - consommé par api.get
	 *  (qui préfixe déjà cfg.base + '/api' : pas de base() ni de /api ici,
	 *  contrairement à emlPartUrl servi en href/src direct). */
	emlJsonUrl(sub) {
		const m = this.previewEmlMsg, att = this.previewEmlAtt;
		if (!m || !att) { return '#'; }
		return `/messages/${m.uid}/attachment/` +
			`${encodeURIComponent(att.mimeIndex)}/eml` +
			`?folder=${encodeURIComponent(m.folder)}` +
			(m.uv ? `&uv=${m.uv}` : '') +
			(sub ? `&sub=${encodeURIComponent(sub)}` : '');
	}

	/** URL d'une PJ imbriquée (n° n) du noeud courant - download=0 : image cid:. */
	emlPartUrl(n, download) {
		const m = this.previewEmlMsg, att = this.previewEmlAtt,
			sub = this.previewEmlPath();
		if (!m || !att) { return '#'; }
		return base() + `/api/messages/${m.uid}/attachment/` +
			`${encodeURIComponent(att.mimeIndex)}/eml/${encodeURIComponent(n)}` +
			`?folder=${encodeURIComponent(m.folder)}` +
			(m.uv ? `&uv=${m.uv}` : '') +
			(sub ? `&sub=${encodeURIComponent(sub)}` : '') +
			`&download=${download ? 1 : 0}`;
	}

	/** Corps du noeud .eml : même pipeline sanitisation/images que renderBody. */
	async renderEmlBody(data) {
		const box = document.getElementById('eml-preview-body');
		if (!box) { return; }
		if (data.bodyHtml) {
			const doc = this.sanitizedDoc(data.bodyHtml);
			// view_images identique : le from du .eml sert au mode "match".
			this.rewriteImageRefs(doc, { attachments: data.attachments || [] },
				await this.allowRemoteImages({ from: data.from }),
				a => this.emlPartUrl(a.n, 0));
			this.scopeStyles(doc, '#eml-preview-body');
			box.replaceChildren(...doc.head.childNodes, ...doc.body.childNodes);
			return;
		}
		const pre = document.createElement('pre');
		pre.style.cssText = 'font:14px/1.5 system-ui,sans-serif;white-space:pre-wrap;margin:0';
		pre.textContent = data.bodyPlain || '';
		box.replaceChildren(pre);
	}

	/** Aperçu d'une pièce jointe iCalendar (plugin ics-viewer). */
	async previewIcs(att) {
		const m = this.message();
		if (!m) { return; }
		try {
			const r = await api.get(`/plugin/ics-viewer/event?folder=${encodeURIComponent(m.folder)}` +
				`&uid=${m.uid}&mime=${encodeURIComponent(att.mimeIndex)}`);
			const lines = [];
			// ev.start/end sont ISO 8601 depuis ics-viewer 0.13 > formatage localisé
			const fmt = (s, allDay) => {
				const d = new Date(s);
				return isNaN(d) ? s
					: allDay ? d.toLocaleDateString(this.app.lang) : d.toLocaleString(this.app.lang);
			};
			for (const ev of r.events || []) {
				if (ev.summary) { lines.push(t('plugin.ics-viewer.event', { v: ev.summary })); }
				if (ev.start) { lines.push(t('plugin.ics-viewer.start', { v: fmt(ev.start, ev.allDay) }) + (ev.allDay ? t('plugin.ics-viewer.allday') : '')); }
				if (ev.end) { lines.push(t('plugin.ics-viewer.end', { v: fmt(ev.end, ev.allDay) })); }
				if (ev.location) { lines.push(t('plugin.ics-viewer.location', { v: ev.location })); }
				if (ev.organizer) { lines.push(t('plugin.ics-viewer.organizer', { v: ev.organizer })); }
				if (ev.status) { lines.push(t('plugin.ics-viewer.status', { v: ev.status })); }
				if (ev.description) { lines.push('', ev.description); }
				lines.push('─'.repeat(30));
			}
			await dialog.alert(lines.join('\n'),
				{ title: t('plugin.ics-viewer.title'), variant: 'info' });
			// Invitation (METHOD:REQUEST) > proposer la réponse iTIP.
			if (r.method === 'REQUEST') {
				const choice = await dialog.pick([
					{ value: 'ACCEPTED',  label: t('plugin.ics-viewer.reply_accept'),    icon: 'fa-check' },
					{ value: 'TENTATIVE', label: t('plugin.ics-viewer.reply_tentative'), icon: 'fa-question' },
					{ value: 'DECLINED',  label: t('plugin.ics-viewer.reply_decline'),   icon: 'fa-xmark' },
				], { title: t('plugin.ics-viewer.reply_title'), variant: 'info' });
				if (choice) {
					await api.post('/plugin/ics-viewer/reply', {
						folder: m.folder, uid: m.uid,
						mime: att.mimeIndex, partstat: choice });
					await dialog.alert(t('plugin.ics-viewer.reply_sent'),
						{ variant: 'success' });
				}
			}
		} catch (e) {
			await dialog.alert(e.message, { title: t('plugin.ics-viewer.attachment'), variant: 'danger' });
		}
	}

	async verifySmime() {
		const m = this.message();
		if (!m || this.smimeState() === 'checking') { return; }
		this.smimeState('checking');
		try {
			const r = await api.post(`/messages/${m.uid}/smime-verify`, { folder: m.folder });
			this.smimeState(r.valid ? (r.trusted ? 'valid' : 'untrusted') : 'invalid');
			const s = r.signer;
			if (s && (s.email || s.cn)) {
				toast.info(t('app.smime_signer', { v: s.email || s.cn }));
			}
		} catch (e) {
			this.smimeState('invalid');
			await dialog.alert(e.message, { variant: 'danger' });
		}
	}

	/** Envoie l'accusé de réception demandé (action manuelle - RFC 3798). */
	async sendMdn() {
		const m = this.message();
		if (!m) { return; }
		try {
			await api.post(`/messages/${m.uid}/mdn`, { folder: m.folder });
			m.mdnSent = true; // masque le bandeau sans re-fetch
			toast.success(t('app.mdn_sent'));
		} catch (e) {
			await dialog.alert(e.message, { variant: 'danger' });
		}
		this._mdnDismissed = true;
	}

	dismissMdn() {
		this._mdnDismissed = true;
	}

	/** Taille de pièce jointe lisible (estimatedSize est en octets). */
	formatSize(bytes) {
		const b = Number(bytes) || 0;
		if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
		if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
		if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
		return b + ' ' + t('unit.b');
	}

	attachmentUrl(att) {
		const m = this.message();
		// &uv= (UIDVALIDITY) : active le cache disque/immutable côté serveur -
		// le contenu d'une PJ est figé par (dossier,uidvalidity,uid,mimeIndex).
		return base() + `/api/messages/${m.uid}/attachment/${att.mimeIndex}` +
			`?folder=${encodeURIComponent(m.folder)}` +
			(m.uv ? `&uv=${m.uv}` : '');
	}

	rawUrl() {
		return base() + `/api/messages/${this.message().uid}/raw` +
			`?folder=${encodeURIComponent(this.message().folder)}`;
	}

	attachmentsZipUrl() {
		return base() + `/api/messages/${this.message().uid}/attachments.zip` +
			`?folder=${encodeURIComponent(this.message().folder)}`;
	}

	/** Se désabonner : one-click RFC 8058 côté serveur, sinon ouverture
	 *  du lien https ; mailto > composition pré-remplie. */
	async unsubscribe() {
		const m = this.message();
		if (!m?.unsubscribe) { return; }
		try {
			const r = await api.post(`/messages/${m.uid}/unsubscribe`, { folder: m.folder });
			if (r.done) {
				toast.success(t('app.unsubscribe_done'));
			} else if (r.url) {
				window.open(r.url, '_blank', 'noopener');
			} else if (r.mailto) {
				const c = this.app.compose;
				c.openNew();
				c.to([c.normChip(decodeURIComponent(
					r.mailto.replace(/^mailto:/i, '').split('?')[0]))]);
				c.subject('unsubscribe');
			}
		} catch (e) { this.error(e.message); }
	}

	/** Affiche le code source (.eml brut) du message dans une modale. */
	async showSource() {
		const m = this.message();
		if (!m) { return; }
		try {
			const res = await fetch(this.rawUrl(), { credentials: 'same-origin' });
			if (!res.ok) { throw new Error('HTTP ' + res.status); }
			this.sourceText(await res.text());
			if (!this.sourceModal) {
				this.sourceModal = new bootstrap.Modal(document.getElementById('source-modal'));
			}
			this.sourceModal.show();
		} catch (e) {
			this.error(e.message);
		}
	}

	/**
	 * Impression : document dédié dans une nouvelle fenêtre - en-têtes +
	 * corps sanitisé tel que rendu dans #mail-body. Pas de noopener dans
	 * window.open : il ferait retourner null, or le handle est requis.
	 */
	printMessage() {
		const m = this.message();
		if (!m) { return; }
		const w = window.open('', '_blank', 'width=900,height=700');
		if (!w) { this.error(t('app.print_blocked')); return; }
		const doc = w.document;
		// onload assigné AVANT close() : le load du document écrit part après
		// la tâche courante, mais on ne dépend pas de ce timing.
		let printed = false;
		w.onload = () => {
			if (printed) { return; }
			printed = true;
			w.focus();
			w.print();
		};
		doc.open();
		doc.write('<!doctype html><html><head><meta charset="utf-8">'
			+ '<base href="' + location.href + '">'
			+ '<style>'
			+ 'body{font:14px/1.5 system-ui,sans-serif;color:#000;margin:2rem}'
			+ '.print-hdr{border-bottom:1px solid #ccc;padding-bottom:.6em;margin-bottom:1em}'
			+ '.print-hdr h1{font-size:1.2em;margin:0 0 .4em}'
			+ '.print-hdr .lh{font-size:.9em;color:#333}'
			+ '.mail-body{font:14px/1.5 system-ui,sans-serif;color:#212529;background:#fff}'
			+ '#mail-body{overflow-wrap:break-word}img{max-width:100%}'
			+ '</style></head><body></body></html>');
		doc.close();
		doc.title = m.subject || '';
		// En-têtes via DOM + textContent - jamais de HTML interpolé
		const hdr = doc.createElement('div');
		hdr.className = 'print-hdr';
		const h1 = doc.createElement('h1');
		h1.textContent = m.subject || '';
		hdr.append(h1);
		const line = txt => {
			const d = doc.createElement('div');
			d.className = 'lh';
			d.textContent = txt;
			return d;
		};
		hdr.append(line(t('app.from') + ' ' + this.headerText('from')));
		hdr.append(line(t('app.to') + ' ' + this.headerText('to')));
		if (m.cc && this.headerText('cc')) {
			hdr.append(line(t('app.cc') + ' ' + this.headerText('cc')));
		}
		hdr.append(line(new Date(m.date * 1000).toLocaleString(this.app.lang)));
		// Corps : texte déchiffré PGP > <pre> ; sinon copie de #mail-body
		// (l'id est requis : les <style> copiés ont des sélecteurs préfixés
		// "#mail-body" - scopeStyles).
		if (this.decryptedText()) {
			const pre = doc.createElement('pre');
			pre.textContent = this.decryptedText();
			doc.body.append(hdr, pre);
		} else {
			const body = doc.createElement('div');
			body.id = 'mail-body';
			body.className = 'mail-body';
			body.innerHTML = document.getElementById('mail-body')?.innerHTML || '';
			doc.body.append(hdr, body);
		}
		// Garde-fou : si load tarde (images proxy lentes), imprimer quand même.
		setTimeout(() => {
			if (!printed) { printed = true; w.focus(); w.print(); }
		}, 3000);
		w.addEventListener('afterprint', () => w.close());
	}

	async toggleFlag() {
		const m = this.message();
		if (!m) { return; }
		const set = !m.isFlagged();
		try {
			await api.post(`/messages/${m.uid}/flags`, {
				folder: m.folder, flag: '\\Flagged', set
			});
		} catch (e) { this.error(e.message); return; }
		m.isFlagged(set);
		// Resync l'étoile de la ligne correspondante dans la liste
		this.app.messageList.messages()
			.find(x => x.uid === m.uid && x.folder === m.folder)?.isFlagged(set);
	}

	async toggleSpam() {
		const m = this.message();
		if (!m) { return; }
		const spam = !(this.app.folderList.isJunkFolder(m.folder) || !!m.isJunk());
		const ml = this.app.messageList;
		// Prédiction miroir du test serveur ($folder === $junk) : 'move'
		// attendu si le message part/rejoint le dossier Junk, 'keyword' sinon.
		const junkName = this.app.folderList.junkFolder()?.fullName;
		const expectMove = spam ? (!!junkName && m.folder !== junkName)
			: (m.folder === junkName);
		// UI optimiste : retrait immédiat + lecteur fermé, ou flag keyword.
		if (expectMove) {
			ml.hideMoved(m);
			this.clear();
		} else {
			m.isJunk(spam);
			ml.messages().find(x => x.uid === m.uid && x.folder === m.folder)?.isJunk(spam);
		}
		try {
			const r = await api.post(`/messages/${m.uid}/spam`, { folder: m.folder, spam });
			ml.unmarkMoved(m.folder, m.uid);
			// Stratégie réelle ≠ prédiction : resync complet (cas rare).
			if ((r.strategy === 'move') !== expectMove) { await ml.load(); }
		} catch (e) {
			ml.unmarkMoved(m.folder, m.uid);
			if (expectMove && !m.isSeen()) { ml.adjustUnread(1, m.folder); }
			// Le lecteur est fermé : erreur affichée côté liste.
			ml.error(e.message);
			await ml.load();
		}
	}

	spamLabel() {
		const m = this.message();
		if (!m) { return 'app.mark_spam'; }
		return (this.app.folderList.isJunkFolder(m.folder) || !!m.isJunk())
			? 'app.mark_not_spam' : 'app.mark_spam';
	}

	async deleteMessage() {
		const m = this.message();
		if (!m) { return; }
		const key = this.app.folderList.isTrashFolder(m.folder)
			? 'messages.delete_confirm_perm' : 'messages.delete_confirm';
		if (!(await dialog.confirm(t(key, { n: 1 }),
			{ variant: 'danger', okLabel: t('common.delete'), title: t('messages.delete_title') }))) { return; }
		const ml = this.app.messageList;
		try {
			await api.post(`/messages/${m.uid}/delete`, { folder: m.folder });
		} catch (e) { this.error(e.message); return; }
		const model = ml.messages().find(x => x.uid === m.uid && x.folder === m.folder);
		if (model) {
			if (!model.isSeen()) { ml.adjustUnread(-1, m.folder); }
			ml.messages.remove(model);
		}
		// Après une suppression : aucun mail sélectionné - lecteur fermé.
		ml.cancelReadTimer();
		this.clear();
		ml.refresh();
	}

	async markUnread() {
		const m = this.message();
		if (!m) { return; }
		this.app.messageList.cancelReadTimer();
		await api.post(`/messages/${m.uid}/flags`, { folder: m.folder, flag: '\\Seen', set: false });
		m.isSeen(false);
		this.app.messageList.messages()
			.find(x => x.uid === m.uid && x.folder === m.folder)?.isSeen(false);
		this.app.messageList.adjustUnread(1, m.folder);
	}

	async moveTo(target) {
		const m = this.message();
		if (!m || !target) { return; }
		const ml = this.app.messageList;
		await api.post(`/messages/${m.uid}/move`, { folder: m.folder, to: target });
		const model = ml.messages().find(x => x.uid === m.uid && x.folder === m.folder);
		if (model) {
			if (!model.isSeen()) { ml.adjustUnread(-1, m.folder); }
			ml.messages.remove(model);
		}
		// Après un déplacement : aucun mail sélectionné - lecteur fermé.
		ml.cancelReadTimer();
		this.clear();
		ml.refresh();
	}

	async moveWithPicker() {
		const m = this.message();
		if (!m) { return; }
		const target = await this.app.folderList.pickFolder(m.folder);
		if (target) { await this.moveTo(target); }
	}

	/** Déplace le message affiché vers le dossier de rôle "archive". */
	async archive() {
		const target = this.app.folderList.archiveFolder()?.fullName;
		if (target) { await this.moveTo(target); }
	}

	headerText(field) {
		return this.addrText(this.message()?.[field]);
	}

	/** "Nom <email>, …" - partagé par les en-têtes du message et du .eml. */
	addrText(v) {
		if (!v) { return ''; }
		if (Array.isArray(v)) {
			return v.map(e => e.name ? `${e.name} <${e.email}>` : (e.email || e)).join(', ');
		}
		return String(v);
	}
}
