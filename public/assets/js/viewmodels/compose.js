import { api } from '../api.js';
import { t } from '../i18n.js';
import { dialog } from '../ui/dialog.js';
import { loadOpenPgp } from '../lazy.js';

/**
 * ViewModel : composition d'un message (nouveau, réponse, transfert, brouillon).
 * Ouvert dans la modale Bootstrap #compose-modal.
 */
export class ComposeVM {
	// Mention d'une pièce jointe dans le corps/sujet (fr/en) - rappel pré-envoi.
	// "attach" couvre attacher/attaché(e)(s) et attached/attachment.
	static RE_ATTACH = /(ci[-\s]?(?:jointe?s?|inclus)|pi[eè]ce\s+jointe|document\s+joint|en\s+annexe|j'ai\s+joint|je\s+(?:t'?envoie|joins)|\battach\w*|please\s+find\s+(?:\w+\s+)?(?:attach|enclos))/i;

	constructor(app) {
		this.app = app;
		this.visible = ko.observable(false);
		this.sending = ko.observable(false);
		this.error = ko.observable('');

		this.identities = ko.observableArray([]);
		this.identity = ko.observable('');
		// Destinataires : pastilles validées ({name,email,invalid}) + texte en
		// cours de frappe (≤1 fragment, cf. onAddrInput). La chaîne n'existe
		// qu'au payload() via addrString().
		this.to = ko.observableArray([]);
		this.cc = ko.observableArray([]);
		this.bcc = ko.observableArray([]);
		this.toText = ko.observable('');
		this.ccText = ko.observable('');
		this.bccText = ko.observable('');
		this.subject = ko.observable('');
		this.html = ko.observable('');
		this.attachments = ko.observableArray([]);
		this.suggestions = ko.observableArray([]);
		this.tab = ko.observable('message'); // 'message' | 'attachments'
		this.uploadGen = 0; // invalidation des uploads en vol au reset()
		this.uploadPending = ko.pureComputed(() =>
			this.attachments().some(a => a.uploading && a.uploading()));

		this.inReplyTo = '';
		this.references = '';
		this.replyUid = 0;        // cible d'une réponse (flag \Answered posé à l'envoi)
		this.replyFolder = '';
		this.draftUid = null;     // brouillon ouvert/enregistré (REPLACE à la ré-enregistrement)
		this.draftFolder = '';
		this.modal = null;
		this.pgpEncrypt = ko.observable(false);
		this.pgpSign = ko.observable(false);   // signature OpenPGP sans chiffrement
		this.priority = ko.observable(false);  // "Priorité haute" > X-Priority: 1
		this.mdn = ko.observable(false);       // "Accusé de réception" > Disposition-Notification-To
		this.showCc = ko.observable(false);   // champ Cc déplié (menu options du header)
		this.showBcc = ko.observable(false);  // champ Cci déplié
		this.suggestField = ko.observable(''); // 'to'|'cc'|'bcc' - champ sous lequel afficher les suggestions
		this.suggestIndex = ko.observable(-1); // suggestion surlignée au clavier
		this._suggestTimer = null; // debounce des suggestions (1 requête / frappe sinon)
		this._focusToInput = false; // openNew : focus "À" quand pas de marqueur caret signature
		// Garde de fermeture : _dirty suit les saisies utilisateur (suspendu
		// pendant les remplissages programmatiques des open*/placeCaretAtMarker).
		this._dirty = false;
		this._suspendDirty = false;
		this._prompting = false;      // dialogue de confirmation de fermeture déjà ouvert
		this._closeConfirmed = false; // fermeture validée (envoi, brouillon, abandon)
		// Enregistrement auto du brouillon (réglage compose_autosave, secondes,
		// 0 = jamais) : timer démarré à l'ouverture, tué à la fermeture.
		this._autosaveTimer = null;
		this._autoSaving = false;     // anti-chevauchement des ticks
		this.savedAt = ko.observable(null); // Date du dernier enregistrement auto
		this.dropActive = ko.observable(false); // voile "déposer pour joindre"
		this._dropDepth = 0;          // dragenter/leave imbriqués : pas de clignotement
		this._pasteBound = false;     // handler paste TinyMCE attaché une seule fois
		for (const o of [this.to, this.cc, this.bcc, this.toText, this.ccText,
			this.bccText, this.subject, this.html, this.attachments]) {
			o.subscribe(() => { if (!this._suspendDirty) { this._dirty = true; } });
		}
		// Taille persistée de la modale (px ; 0 = défaut Bootstrap)
		this.composeW = ko.observable(0);
		this.composeH = ko.observable(0);

		// Changement d'identité > remplacer le bloc signature (modale déjà ouverte)
		this.identity.subscribe(() => {
			this.rebuildSignature();
			this.placeCaretAtMarker();
		});

		// Knockout invoque les handlers avec this = $data du contexte courant
		// (item dans un foreach / objet dans un with) : on force le binding sur le VM.
		for (const m of Object.getOwnPropertyNames(ComposeVM.prototype)) {
			if (m !== 'constructor' && typeof this[m] === 'function') {
				this[m] = this[m].bind(this);
			}
		}
	}

	async init() {
		// Loader mémoïsé du SettingsVM : pas de doublon /identities au boot
		// quand popup_identity l'a déjà déclenché.
		this.setIdentities(await this.app.settings.loadIdentities());
		// Taille de la modale persistée dans les réglages utilisateur
		const w = parseInt(this.app.userSettings.compose_modal_width, 10) || 0;
		const h = parseInt(this.app.userSettings.compose_modal_height, 10) || 0;
		this.composeW(w >= 480 ? w : 0);
		this.composeH(h >= 360 ? h : 0);
	}

	/** Applique la liste brute des identités : filtre le compte actif et
	 *  (ré)sélectionne l'identité par défaut - aussi après un changement de
	 *  défaut ou une édition de signature dans Paramètres. */
	setIdentities(list) {
		const active = this.app.accounts().find(a => a.active);
		const mine = active ? list.filter(i => Number(i.account_id) === Number(active.id)) : list;
		const usable = mine.length ? mine : list;
		this.identities(usable);
		const def = usable.find(i => i.is_default) || usable[0];
		if (def) { this.identity(def.email); }
	}

	/** Persiste la taille de la modale dans les réglages utilisateur. */
	async saveComposeSize() {
		this.app.userSettings.compose_modal_width = this.composeW();
		this.app.userSettings.compose_modal_height = this.composeH();
		await api.post('/settings', {
			compose_modal_width: this.composeW(),
			compose_modal_height: this.composeH(),
		}).catch(() => {}); // silencieux : préférence cosmétique (cf. savePaneSizes)
	}

	show() {
		if (!this.modal) {
			const el = document.getElementById('compose-modal');
			this.modal = new bootstrap.Modal(el);
			// Garde de fermeture : contenu saisi non envoyé/enregistré >
			// confirmation (couvre ×, Échap, backdrop et hide() programmatique).
			el.addEventListener('hide.bs.modal', e => this.guardClose(e));
			el.addEventListener('hidden.bs.modal', () => {
				this.visible(false);
				this.stopAutosave();
			});
		}
		this.visible(true);
		this.modal.show();
		this.bindEditorPaste();
		this.startAutosave();
		// Réponses types : préchargement lazy pour le sous-menu "Options"
		// (no-op si déjà chargée, refetch après édition dans Paramètres).
		this.app.settings.loadResponses();
		// Le focus avant la fin de la transition est volé par Bootstrap >
		// le caret du marqueur de signature est placé une fois la modale visible.
		// Sans marqueur, openNew pose le focus sur le champ "À".
		document.getElementById('compose-modal').addEventListener('shown.bs.modal', () => {
			const placed = this.placeCaretAtMarker();
			if (this._focusToInput && !placed) {
				document.getElementById('compose-to-input')?.focus();
			}
			this._focusToInput = false;
		}, { once: true });
	}

	hide() {
		this.modal?.hide();
	}

	/** Intercepte hide.bs.modal : propose brouillon/abandon si contenu saisi. */
	guardClose(e) {
		if (this._closeConfirmed || !this._dirty) { return; }
		e.preventDefault();
		if (this._prompting) { return; }
		this._prompting = true;
		dialog.pick([
			{ label: t('compose.close_draft'), value: 'draft', icon: 'fa-floppy-disk' },
			{ label: t('compose.close_discard'), value: 'discard', icon: 'fa-trash' },
		], { title: t('compose.confirm_close') }).then(choice => {
			this._prompting = false;
			if (choice === 'draft') {
				this.saveDraft(); // succès > ferme via _closeConfirmed ; échec > modale + erreur
			} else if (choice === 'discard') {
				this._closeConfirmed = true;
				this.modal.hide();
				this.reset(); // purge les PJ tmp abandonnées
			}
		});
	}

	reset() {
		this.error('');
		this.to([]); this.cc([]); this.bcc([]);
		this.toText(''); this.ccText(''); this.bccText('');
		this.showCc(false); this.showBcc(false);
		this.subject(''); this.html('');
		this.priority(false); // intention par message, contrairement à pgpEncrypt
		this.pgpSign(false);  // passphrase exigée : pas de persistance tacite
		this.mdn(false);
		// Invalide la boucle upload() en vol, annule les transferts et
		// purge les tmp serveur des PJ abandonnées.
		++this.uploadGen;
		for (const a of this.attachments()) {
			if (a.uploading && a.uploading()) {
				a.req?.abort();
			} else if (a.token) {
				api.post(`/compose/upload/${a.token}/delete`).catch(() => {});
			}
		}
		this.attachments([]);
		this.tab('message');
		this.closeSuggestions();
		this.inReplyTo = ''; this.references = '';
		this.replyUid = 0; this.replyFolder = '';
		this.draftUid = null; this.draftFolder = '';
		this._dirty = false;
		this._closeConfirmed = false;
		this.savedAt(null);
		this.stopAutosave();
	}

	// ─── Enregistrement auto du brouillon ───

	startAutosave() {
		this.stopAutosave();
		const secs = parseInt(this.app.userSettings?.compose_autosave, 10) || 0;
		if (secs > 0) {
			this._autosaveTimer = setInterval(() => this.autoSave(),
				Math.max(10, secs) * 1000);
		}
	}

	stopAutosave() {
		if (this._autosaveTimer) {
			clearInterval(this._autosaveTimer);
			this._autosaveTimer = null;
		}
	}

	/** Enregistrement silencieux : jamais de fermeture ni d'erreur affichée
	 *  (contrairement à saveDraft) - un échec est réessayé au prochain tick. */
	async autoSave() {
		if (!this.visible() || this._autoSaving || this.sending()
			|| this.uploadPending() || !this._dirty || this.bodyEmpty()) {
			return;
		}
		this._autoSaving = true;
		try {
			const r = await api.post('/compose/save-draft', this.payload());
			this.draftUid = r?.uid ?? null;
			this.draftFolder = r?.folder || '';
			this._dirty = false;
			this.savedAt(new Date());
		} catch { /* silencieux : prochain tick */ }
		this._autoSaving = false;
	}

	openNew() {
		this.reset();
		this.applySignature();
		this._dirty = false;
		this._focusToInput = true; // pastilles : la saisie commence par "À"
		this.show();
	}

	/** Bloc signature HTML de l'identité choisie ('' si aucune). */
	signatureBlock() {
		const id = this.identities().find(i => i.email === this.identity());
		return id?.signature ? `<div class="mxwm-signature">${id.signature}</div>` : '';
	}

	/** Ligne d'amorce avant la signature : absente quand la signature
	 *  emporte son marqueur de curseur (sinon ligne vide parasite). */
	sigLead(sig) {
		return sig.includes('mxwm-sig-caret') ? '' : '<p><br></p>';
	}

	/** Insère la signature de l'identité choisie en fin de corps. */
	applySignature() {
		const sig = this.signatureBlock();
		this.html(sig ? this.sigLead(sig) + sig : '');
	}

	/** Remplace le bloc .mxwm-signature du corps par celui de l'identité
	 *  courante - avant la citation (blockquote/hr) s'il y en a une. */
	rebuildSignature() {
		const doc = new DOMParser().parseFromString(this.html() || '<p><br></p>', 'text/html');
		doc.querySelector('.mxwm-signature')?.remove();
		const sig = this.signatureBlock();
		if (sig) {
			const anchor = doc.body.querySelector('blockquote, hr');
			if (anchor) { anchor.insertAdjacentHTML('beforebegin', sig); }
			else { doc.body.insertAdjacentHTML('beforeend', sig); }
		}
		this.html(doc.body.innerHTML);
	}

	/** Place le caret sur le marqueur .mxwm-sig-caret puis le retire.
	 *  Retourne true quand un marqueur a été trouvé (focus éditeur). */
	placeCaretAtMarker() {
		const ed = window.tinymce?.get('compose-body');
		if (!ed) { return false; }
		// tinymce chargé en lazy (applyBindings) : l'éditeur peut exister sans
		// être initialisé au shown.bs.modal > retry borné au init (idempotent).
		if (!ed.initialized) { ed.once('init', () => this.placeCaretAtMarker()); return false; }
		const marker = ed.getBody().querySelector('span.mxwm-sig-caret');
		if (!marker) { return false; }
		const parent = marker.parentNode;
		const next = marker.nextSibling;
		const rng = ed.dom.createRng();
		rng.setStartBefore(marker);
		rng.collapse(true);
		ed.dom.remove(marker);
		// nbsp auto-inséré par l'ancien bouton sigcaret (signatures déjà enregistrées)
		if (next?.nodeType === 3 && next.data.startsWith('\u00A0')) {
			next.data = next.data.slice(1);
			if (!next.data) { next.remove(); }
		}
		// Un bloc vidé par le retrait du marqueur garderait hauteur 0 et serait
		// droppé par le sérialiseur - <br> = ligne vide adressable au caret.
		if (parent?.nodeType === 1 && !parent.textContent
			&& !parent.querySelector('br,img,hr,iframe,object,embed,video,audio')) {
			parent.appendChild(ed.dom.create('br'));
		}
		ed.selection.setRng(rng);
		// dom.remove ne déclenche ni change ni keyup > resync explicite de
		// l'observable (update() voit le contenu identique > caret préservé).
		// Écriture programmatique : ne doit pas marquer le brouillon "dirty".
		this._suspendDirty = true;
		this.html(ed.getContent());
		this._suspendDirty = false;
		if (this.visible()) { ed.focus(); }
		return true;
	}

	/** HTML > texte brut (citation d'un mail sans part text/plain). */
	htmlToText(html) {
		if (!html) { return ''; }
		return new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
	}

	/** Réponse à un message (objet message complet de l'API). */
	openReply(msg, all = false) {
		this.reset();
		const first = (list) => Array.isArray(list) && list[0]
			? (list[0].email || list[0])
			: '';
		const replyTo = first(msg.replyTo) || first(msg.from);
		this.to(replyTo ? [this.normChip(replyTo)] : []);
		if (all) {
			// Exclure toutes les adresses du compte (identités + email de
			// session) et l'adresse déjà placée en To ; dédoublonnage par Set.
			const active = this.app.accounts().find(a => a.active);
			const mine = new Set(this.identities()
				.map(i => (i.email || '').toLowerCase())
				.concat(active?.email ? [active.email.toLowerCase()] : []));
			mine.add(replyTo.toLowerCase());
			const others = [...new Set(
				[...(msg.to || []), ...(msg.cc || [])]
					.map(e => (e.email || e || '').toLowerCase())
					.filter(e => e && !mine.has(e)))];
			this.cc(others.map(e => ({ name: '', email: e, invalid: false })));
			this.showCc(!!this.cc().length);
		}
		const subject = msg.subject || '';
		this.subject(/^\s*re\s*:/i.test(subject) ? subject : 'Re: ' + subject);
		this.inReplyTo = msg.messageId || '';
		this.references = [...new Set(
			[...(msg.references || '').split(/\s+/), msg.inReplyTo, msg.messageId]
				.filter(Boolean))].join(' ');
		this.replyUid = msg.uid || 0;
		this.replyFolder = msg.folder || '';
		// Mail HTML-only : la citation est dérivée du HTML (texte brut).
		const quoted = (msg.bodyPlain || this.htmlToText(msg.bodyHtml))
			.split('\n').map(l => '> ' + l).join('\n');
		const sig = this.signatureBlock();
		this.html(`${this.sigLead(sig)}${sig}<blockquote>` +
			`${quoted.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>')}</blockquote>`);
		this._dirty = false;
		this.show();
	}

	openForward(msg, asAttachment = false) {
		this.reset();
		const subject = msg.subject || '';
		this.subject(/^\s*fwd?\s*:/i.test(subject) ? subject : 'Fwd: ' + subject);
		if (asAttachment) {
			const sig = this.signatureBlock();
			this.html(`${this.sigLead(sig)}${sig}<p><i>${t('compose.forwarded_attach')}</i></p>`);
			this.pushEmlRef(msg);
		} else {
			const body = msg.bodyHtml || ('<pre>' + (msg.bodyPlain || '').replace(/</g, '&lt;') + '</pre>');
			const sig = this.signatureBlock();
			this.html(`${this.sigLead(sig)}${sig}<hr><p><b>${t('compose.forwarded')}</b></p>${body}`);
			this.pushMsgrefAttachments(msg);
		}
		this._dirty = false;
		this.show();
	}

	/** Insère la réponse type `r` à la position du curseur (TinyMCE
	 *  insertContent), puis ferme le menu options (auto-close "outside"). */
	applyResponse(r) {
		const ed = window.tinymce?.get('compose-body');
		if (ed?.initialized) {
			ed.insertContent(r.body);
			this._suspendDirty = true;
			this.html(ed.getContent());
			this._suspendDirty = false;
		} else {
			this.html((this.html() || '') + r.body);
		}
		const tgl = document.querySelector('#compose-modal [data-bs-toggle="dropdown"]');
		bootstrap.Dropdown.getInstance(tgl)?.hide();
	}

	/** Référence les PJ d'un message existant (brouillon rouvert, transfert)
	 *  : le serveur résoudra contenu et métadonnées à la sauvegarde. */
	pushMsgrefAttachments(msg) {
		for (const a of msg.attachments || []) {
			this.attachments.push({ token: null,
				msgref: { folder: msg.folder, uid: msg.uid, part: a.mimeIndex },
				name: a.name, size: a.size,
				progress: ko.observable(100), uploading: ko.observable(false), req: null });
		}
	}

	/** Joint un message existant en .eml (transfert PJ, drop "Nouveau
	 *  message") - sujet entièrement filtré > "message.eml". */
	pushEmlRef(m) {
		const name = (String(m.subject || 'message')
			.replace(/[\\/:*?"<>|\x00-\x1F]/g, '').trim() || 'message') + '.eml';
		this.attachments.push({ token: null,
			msgref: { folder: m.folder, uid: m.uid, eml: true },
			name, size: m.size || 0,
			progress: ko.observable(100), uploading: ko.observable(false), req: null });
	}

	// ─── Glisser-déposer de fichiers / mails et collage ───

	// dragover se déclenche en continu : comptage uniquement sur dragenter.
	// Seuls les fichiers OS sont acceptés sur la modale - un mail glissé ne
	// peut pas l'atteindre (backdrop) : le drop de mails vise le bouton
	// "Nouveau message" (mailBtnDrop).
	onDragEnter(d, e) {
		if (!(e.dataTransfer?.types || []).includes('Files')) { return true; }
		e.preventDefault();
		if (++this._dropDepth === 1) { this.dropActive(true); }
		return true;
	}

	onDragOver(d, e) {
		if (!(e.dataTransfer?.types || []).includes('Files')) { return true; }
		e.preventDefault(); // requis pour autoriser le drop
		e.dataTransfer.dropEffect = 'copy';
		return true;
	}

	onDragLeave() {
		if (--this._dropDepth <= 0) { this._dropDepth = 0; this.dropActive(false); }
		return true;
	}

	onDrop(d, e) {
		e.preventDefault();
		this._dropDepth = 0;
		this.dropActive(false);
		const files = [...(e.dataTransfer?.files || [])];
		if (files.length) { this.upload(files); }
		return true;
	}

	/** Survol d'un drag de mails sur "Nouveau message" : drop autorisé
	 *  (un drag de fichier, lui, ne doit PAS être accepté sur le bouton). */
	mailBtnDragOver(d, e) {
		if (this.app.messageList.dragItems() === null) { return true; }
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		e.currentTarget.classList.add('mx-drop');
		return true;
	}

	mailBtnDragLeave(d, e) {
		if (e.currentTarget.contains(e.relatedTarget)) { return true; }
		e.currentTarget.classList.remove('mx-drop');
		return true;
	}

	/** Drop de mail(s) sur "Nouveau message" : compose ouvert + .eml joints. */
	mailBtnDrop(d, e) {
		e.preventDefault(); // avant tout retour (style folderDrop)
		e.currentTarget.classList.remove('mx-drop');
		const list = this.app.messageList.dragItems();
		if (list === null) { return true; }
		this.openNew(); // reset + signature + focus "À", réutilisé tel quel
		for (const m of list) { this.pushEmlRef(m); }
		return true;
	}

	/** Coller un fichier non-image dans le corps > pièce jointe (les images
	 *  restent gérées par TinyMCE : inline > cid côté serveur). */
	onPaste(d, e) {
		const files = [...(e.clipboardData?.files || [])];
		if (files.length) { e.preventDefault(); this.upload(files); }
		return true;
	}

	/** Attache le handler paste de l'éditeur une seule fois (init paresseuse). */
	bindEditorPaste() {
		if (this._pasteBound) { return; }
		const ed = window.tinymce?.get('compose-body');
		const attach = () => {
			if (this._pasteBound) { return; }
			this._pasteBound = true;
			ed.on('paste', e => {
				const fs = [...(e.clipboardData?.files || [])];
				if (fs.some(f => !(f.type || '').startsWith('image/'))) {
					e.preventDefault();
					this.upload(fs);
				}
			});
		};
		if (ed?.initialized) { attach(); }
		else if (ed) { ed.once('init', attach); }
	}

	openDraft(msg) {
		this.reset();
		this.draftUid = msg.uid || null;
		this.draftFolder = msg.folder || '';
		this.to((msg.to || []).map(this.normChip));
		this.cc((msg.cc || []).map(this.normChip));
		this.bcc((msg.bcc || []).map(this.normChip));
		this.showCc(!!this.cc().length);
		this.showBcc(!!this.bcc().length);
		this.subject(msg.subject || '');
		// Le brouillon d'une réponse conserve son threading.
		this.inReplyTo = msg.inReplyTo || '';
		this.references = msg.references || '';
		// ... et la cible du flag \Answered (X-Draft-Info, posé au save-draft).
		const di = msg.draftInfo;
		if (Array.isArray(di) && di[0] === 'reply') {
			this.replyUid = di[1] || 0;
			this.replyFolder = di[2] || '';
		}
		this.html(msg.bodyHtml || (msg.bodyPlain || '').replace(/\n/g, '<br>'));
		this.pushMsgrefAttachments(msg);
		this._dirty = false;
		this.show();
	}

	/** "Modifier comme nouveau" : reproduction fidèle du message (destinataires,
	 *  sujet, corps, PJ) sans brouillon source ni en-têtes de fil. */
	openAsNew(msg) {
		this.reset();
		this.to((msg.to || []).map(this.normChip));
		this.cc((msg.cc || []).map(this.normChip));
		this.bcc((msg.bcc || []).map(this.normChip));
		this.showCc(!!this.cc().length);
		this.showBcc(!!this.bcc().length);
		this.subject(msg.subject || '');
		this.html(msg.bodyHtml || (msg.bodyPlain || '').replace(/\n/g, '<br>'));
		this.pushMsgrefAttachments(msg);
		this._dirty = false;
		this.show();
	}

	/** Lien mailto: externe (protocole enregistré ou lien dans une page). */
	openMailto(uri) {
		const m = String(uri || '').replace(/^mailto:/i, '');
		const q = m.indexOf('?');
		const path = q < 0 ? m : m.slice(0, q);
		const params = new URLSearchParams(q < 0 ? '' : m.slice(q + 1));
		const dec = v => { try { return decodeURIComponent(v || ''); } catch { return v || ''; } };
		this.reset();
		this.applySignature();
		this.commitParts('to', this.splitAddrList(dec(path)));
		for (const f of ['cc', 'bcc']) {
			const v = dec(params.get(f));
			if (v) { this.commitParts(f, this.splitAddrList(v)); }
		}
		this.showCc(!!this.cc().length);
		this.showBcc(!!this.bcc().length);
		this.subject(dec(params.get('subject')));
		const body = dec(params.get('body'));
		if (body) {
			const esc = body.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>');
			this.html(`<p>${esc}</p>` + this.html());
		}
		this._dirty = false;
		this.show();
	}

	/** "Nom <a@b>" ou adresse brute > pastille {name,email,invalid}. */
	normChip(e) {
		if (e && typeof e === 'object') {
			return { name: e.name || '', email: e.email || '', invalid: false };
		}
		return { name: '', email: String(e || ''), invalid: false };
	}

	/** "Nom <a@b>" - le display-name est systématiquement quoté : un nom
	 *  contenant , ; ou " casserait sinon la liste à la relecture serveur. */
	formatAddr(name, email) {
		if (!name) { return email || ''; }
		return '"' + String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"')
			+ '" <' + email + '>';
	}

	/** Pastilles + texte en cours > chaîne du payload (contrat serveur
	 *  identique à l'ancien champ texte libre). */
	addrString(field) {
		return [...this[field]().map(c => this.formatAddr(c.name, c.email)),
			this[field + 'Text']().trim()].filter(Boolean).join(', ');
	}

	/** Découpe une liste d'adresses sur , ; en dehors des guillemets -
	 *  ""Dupont, Jean" <a@b>" reste un seul fragment. */
	splitAddrList(s) {
		const out = [];
		let cur = '', inQ = false;
		for (let i = 0; i < s.length; i++) {
			const ch = s[i];
			if (ch === '\\' && inQ) { cur += ch + (s[++i] || ''); continue; }
			if (ch === '"') { inQ = !inQ; }
			if ((ch === ',' || ch === ';') && !inQ) { out.push(cur); cur = ''; }
			else { cur += ch; }
		}
		out.push(cur);
		return out;
	}

	/** Fragment d'adresse > pastille : priorité à "Nom <a@b>", email nu
	 *  validé par regex souple ; sinon pastille marquée invalide (le serveur
	 *  rejettera à l'envoi, comme un texte libre auparavant). */
	parseAddr(s) {
		s = (s || '').trim();
		if (!s) { return null; }
		const m = s.match(/^(?:"((?:[^"\\]|\\.)*)"|(.*?))?\s*<([^<>\s]+)>\s*$/);
		if (m && m[3].includes('@')) {
			return { name: (m[1] ?? m[2] ?? '').replace(/\\(.)/g, '$1').trim(),
				email: m[3], invalid: false };
		}
		const ok = /^[^\s@,;<>]+@[^\s@,;<>]+$/.test(s);
		return { name: '', email: s, invalid: !ok };
	}

	/** Fragment > pastille si non-doublon (insensible à la casse, dans le
	 *  champ concerné uniquement). Retourne la pastille ou null. */
	commitFragment(field, frag) {
		const chip = this.parseAddr(frag);
		if (!chip) { return null; }
		const lower = chip.email.toLowerCase();
		if (this[field]().some(c => (c.email || '').toLowerCase() === lower)) {
			return null;
		}
		this[field].push(chip);
		return chip;
	}

	/** Commit d'une liste de fragments ; retourne les adresses valides
	 *  effectivement ajoutées. */
	commitParts(field, parts) {
		const added = [];
		for (const p of parts) {
			const c = this.commitFragment(field, p);
			if (c && !c.invalid) { added.push(c); }
		}
		return added;
	}

	/** Valide tout le texte en cours en pastilles. wkdLookup uniquement sur
	 *  commit unitaire : un collage massif ne doit pas déclencher une tempête
	 *  de dialogues d'import de clé. */
	commitInput(field) {
		const added = this.commitParts(field,
			this.splitAddrList(this[field + 'Text']()));
		this[field + 'Text']('');
		if (added.length === 1) { this.wkdLookup(added[0].email); }
	}

	/** Saisie : les fragments terminés par , ; deviennent des pastilles - le
	 *  reste (≤1 fragment) sert de requête de suggestion. */
	onAddrInput(field) {
		const parts = this.splitAddrList(this[field + 'Text']());
		if (parts.length > 1) {
			const added = this.commitParts(field, parts.slice(0, -1));
			this[field + 'Text'](parts[parts.length - 1].trim());
			if (added.length === 1) { this.wkdLookup(added[0].email); }
		}
		this.suggest(field);
	}

	/** Clavier : navigation suggestions (↑↓), validation (Entrée/Tab),
	 *  fermeture (Échap, sans fermer la modale), récupération de la dernière
	 *  pastille (Backspace sur champ vide). */
	onAddrKey(field, e) {
		const open = this.suggestOpen(field);
		const idx = this.suggestIndex();
		switch (e.key) {
		case 'ArrowDown':
			if (open) { this.navSuggest(1, e.target); }
			else { this.suggest(field); }
			e.preventDefault();
			break;
		case 'ArrowUp':
			if (open) { this.navSuggest(-1, e.target); }
			e.preventDefault();
			break;
		case 'Enter':
			// Pas d'auto-pick du 1er item : un index est exigé, sinon le
			// texte est validé (jamais de surprise).
			if (open && idx >= 0) {
				this.applySuggestion(field, this.suggestions()[idx]);
			} else {
				this.commitInput(field);
			}
			e.preventDefault();
			break;
		case 'Tab':
			if (open && idx >= 0) {
				this.applySuggestion(field, this.suggestions()[idx]);
				e.preventDefault(); // reste dans le champ pour l'adresse suivante
			} else {
				this.commitInput(field); // propagation : focus suivant
			}
			break;
		case 'Escape':
			if (open) {
				this.closeSuggestions();
				e.stopPropagation(); // la modale écoute keydown > ne pas la fermer
			}
			break;
		case 'Backspace':
			if (!this[field + 'Text']() && this[field]().length) {
				const chip = this[field].pop();
				this[field + 'Text'](chip.email);
				e.preventDefault();
			}
			break;
		}
		return true;
	}

	/** ↑/↓ : index cyclique + item actif ramené dans le viewport. */
	navSuggest(dir, inputEl) {
		const n = this.suggestions().length;
		if (!n) { return; }
		const i = this.suggestIndex();
		this.suggestIndex(dir === 1 ? (i + 1) % n : (i <= 0 ? n - 1 : i - 1));
		inputEl?.closest('.position-relative')
			?.querySelector('.compose-suggestions')
			?.children[this.suggestIndex()]
			?.scrollIntoView({ block: 'nearest' });
	}

	removeChip(field, chip) {
		this[field].remove(chip);
	}

	suggestOpen(field) {
		return this.suggestField() === field && this.suggestions().length > 0;
	}

	closeSuggestions() {
		clearTimeout(this._suggestTimer);
		this.suggestions([]);
		this.suggestField('');
		this.suggestIndex(-1);
	}

	/** Blur : ferme la liste uniquement - le texte en cours reste éditable
	 *  et part quand même dans le payload (addrString). */
	onAddrBlur() {
		this.closeSuggestions();
	}

	/** Debounce : une frappe = un timer, la requête ne part qu'au calme. */
	suggest(field) {
		clearTimeout(this._suggestTimer);
		this._suggestTimer = setTimeout(() => this.fetchSuggestions(field), 250);
	}

	async fetchSuggestions(field) {
		const q = this[field + 'Text']().trim();
		// Contacts désactivés : pas d'appel voué à un 403
		if (q.length < 2 || this.app.features().contacts === false) {
			this.closeSuggestions();
			return;
		}
		const have = new Set(this[field]()
			.map(c => (c.email || '').toLowerCase()));
		const list = await api.get('/contacts?q=' + encodeURIComponent(q))
			.catch(() => []);
		// Réponse arrivée après une nouvelle frappe : périmée > ignorée.
		if (this[field + 'Text']().trim() !== q) { return; }
		this.suggestField(field);
		this.suggestIndex(-1);
		this.suggestions((list || []).filter(
			c => !have.has((c.email || '').toLowerCase())));
	}

	applySuggestion(field, contact) {
		this[field + 'Text']('');
		// Entrée "groupe" : expansion en pastilles membres (dedupe inclus).
		if (contact.group) {
			this.closeSuggestions();
			api.get('/contacts/group?name=' + encodeURIComponent(contact.group))
				.then(list => {
					const have = new Set(this[field]()
						.map(c => (c.email || '').toLowerCase()));
					for (const m of list || []) {
						const e = (m.email || '').toLowerCase();
						if (e && !have.has(e)) {
							have.add(e);
							this[field].push({ name: m.name || '', email: m.email, invalid: false });
						}
					}
				}).catch(() => {});
			document.getElementById(`compose-${field}-input`)?.focus();
			return;
		}
		if (!this[field]().some(c => (c.email || '').toLowerCase()
			=== (contact.email || '').toLowerCase())) {
			this[field].push({ name: contact.name || '',
				email: contact.email || '', invalid: false });
		}
		this.closeSuggestions();
		document.getElementById(`compose-${field}-input`)?.focus();
		this.wkdLookup(contact.email); // recherche PGP en arrière-plan
	}

	/**
	 * Recherche la clé PGP d'un destinataire (plugin wkd-keys : WKD +
	 * Autocrypt récolté + keyserver optionnel). Silencieuse si absente ;
	 * propose l'import si trouvée et pas déjà dans le trousseau.
	 */
	async wkdLookup(email) {
		if (!this.app.features().wkd_keys
			|| !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email || '')) {
			return;
		}
		try {
			const have = await api.get('/pgp/keys/lookup?emails='
				+ encodeURIComponent(email)).catch(() => []);
			if ((have || []).length) { return; }
			const r = await api.get('/plugin/wkd-keys/lookup?email='
				+ encodeURIComponent(email));
			if (!r?.armored) { return; }
			if (await dialog.confirm(
				t('plugin.wkd-keys.import_confirm', { email }),
				{ variant: 'info' })) {
				await api.post('/pgp/keys', { email, armored: r.armored });
				await dialog.alert(t('plugin.wkd-keys.imported'),
					{ variant: 'success' });
			}
		} catch { /* lookup échouée : clé optionnelle > silencieux */ }
	}

	async upload(files) {
		// Contrôles préventifs : taille par fichier puis cumul
		// (le serveur re-vérifie à l'upload et à l'envoi)
		const maxFile = (this.app.features().attachment_max_size || 0) * 1048576;
		const maxTotal = (this.app.features().attachments_max_total || 0) * 1048576;
		const gen = this.uploadGen;
		for (const file of files) {
			if (gen !== this.uploadGen) { break; } // reset() pendant la boucle
			if (maxFile > 0 && file.size > maxFile) {
				if (await this.filelink(file)) { continue; }
				this.error(t('errors.file_too_big',
					{ mb: Math.round(maxFile / 1048576) }));
				continue;
			}
			const used = this.attachments().reduce((s, a) => s + a.size, 0);
			if (maxTotal > 0 && used + file.size > maxTotal) {
				this.error(t('errors.attachments_too_big',
					{ mb: Math.round(maxTotal / 1048576) }));
				continue;
			}
			// Placeholder immédiat : la PJ et sa barre de progression sont
			// visibles dès le début du transfert (size déjà connue > le
			// contrôle de cumul des fichiers suivants reste correct).
			const att = { token: null, name: file.name, size: file.size,
				progress: ko.observable(0), uploading: ko.observable(true), req: null };
			this.attachments.push(att);
			this.tab('attachments');
			const fd = new FormData();
			fd.append('file', file);
			try {
				const req = api.upload('/compose/upload', fd, pct => att.progress(pct));
				att.req = req;
				const r = await req;
				if (gen !== this.uploadGen) {
					// reset() entre-temps : liste vidée > token orphelin
					api.post(`/compose/upload/${r.token}/delete`).catch(() => {});
					break;
				}
				att.token = r.token;
				att.uploading(false);
				att.progress(100);
				att.req = null;
			} catch (e) {
				this.attachments.remove(att);
				if (e.code === 'too_big' && await this.filelink(file)) { continue; }
				if (e.code !== 'aborted') { this.error(e.message); }
				if (gen !== this.uploadGen) { break; }
			}
		}
	}

	/**
	 * Pièce trop grosse pour le SMTP > envoi "par lien" (plugin
	 * filelink) : upload sur storage/filelink + insertion du lien de
	 * téléchargement expirable dans le corps du message.
	 * Retourne true si le fichier a été pris en charge (succès ou erreur
	 * déjà affichée), false si le plugin est absent > erreur too_big.
	 */
	async filelink(file) {
		if (!this.app.features().filelink) { return false; }
		try {
			const fd = new FormData();
			fd.append('file', file);
			const r = await api.upload('/plugin/filelink/upload', fd);
			const esc = s => String(s).replace(/[&<>"]/g,
				c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
			const link = `<p>${esc(r.name)} : <a href="${esc(r.url)}">${esc(r.url)}</a></p>`;
			const ed = window.tinymce?.get('compose-body');
			if (ed) { ed.insertContent(link); }
			else { this.html((this.html() || '') + link); }
			return true;
		} catch (e) {
			if (e.code !== 'aborted') { this.error(e.message); }
			return true;
		}
	}

	removeAttachment(att) {
		if (att.uploading && att.uploading()) {
			att.req?.abort(); // le catch de upload() retire l'item
			return;
		}
		this.attachments.remove(att);
		if (att.token) {
			api.post(`/compose/upload/${att.token}/delete`).catch(() => {});
		}
	}

	/** Taille de pièce jointe lisible (size est en octets). */
	formatSize(bytes) {
		const b = Number(bytes) || 0;
		if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
		if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
		if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
		return b + ' ' + t('unit.b');
	}

	/** Contenu live de l'éditeur : l'observable html peut être périmée
	 *  (sync seulement sur change/keyup/undo/redo). */
	bodyHtml() {
		const ed = window.tinymce?.get('compose-body');
		return ed?.initialized ? ed.getContent() : (this.html() || '');
	}

	/** Texte du corps hors citations (réponses : l'original en <blockquote>
	 *  ne doit pas déclencher le rappel "pièce jointe oubliée"). */
	bodyText() {
		const doc = new DOMParser().parseFromString(this.bodyHtml(), 'text/html');
		doc.body.querySelectorAll('blockquote').forEach(el => el.remove());
		return doc.body.textContent || '';
	}

	/** Corps visuellement vide : <p><br></p>, &nbsp;… comptent comme vide,
	 *  un corps image/média compte comme rempli. */
	bodyEmpty() {
		const doc = new DOMParser().parseFromString(this.bodyHtml(), 'text/html');
		return doc.body.textContent.trim() === ''
			&& !doc.body.querySelector('img,video,audio,iframe,embed,object');
	}

	payload() {
		return {
			from: this.identity(),
			to: this.addrString('to'), cc: this.addrString('cc'),
			bcc: this.addrString('bcc'),
			subject: this.subject(),
			priority: this.priority(),
			mdn: this.mdn(),
			html: this.bodyHtml(),
			inReplyTo: this.inReplyTo,
			references: this.references,
			replyUid: this.replyUid,
			replyFolder: this.replyFolder,
			draftUid: this.draftUid || 0,
			draftFolder: this.draftFolder || '',
			attachments: this.attachments().map(a => a.token ?? a.msgref).filter(Boolean),
		};
	}

	/** Clé privée de l'identité From, déchiffrée (passphrase demandée si besoin). */
	async signingKey() {
		const openpgp = await loadOpenPgp();
		const own = await api.get('/pgp/keys');
		const from = (this.identity() || '').toLowerCase();
		const rec = own.find(k => Number(k.is_private) === 1
			&& String(k.email || '').toLowerCase() === from)
			|| own.find(k => Number(k.is_private) === 1);
		if (!rec) { throw new Error(t('compose.no_privkey')); }
		const key = await openpgp.readPrivateKey({ armoredKey: rec.key_data });
		if (key.isDecrypted()) { return key; }
		const pass = await dialog.prompt(t('pgp.passphrase_prompt'),
			{ title: 'OpenPGP', inputType: 'password' });
		if (pass === null) { throw new Error(t('compose.no_privkey')); }
		return openpgp.decryptKey({ privateKey: key, passphrase: pass });
	}

	/** Signe le corps en clair (BEGIN PGP SIGNED MESSAGE - lisible partout). */
	async signPayload(payload) {
		const openpgp = await loadOpenPgp();
		const key = await this.signingKey();
		const div = document.createElement('div');
		div.innerHTML = payload.html;
		const text = div.textContent || '';
		const armored = await openpgp.sign({
			message: await openpgp.createCleartextMessage({ text }),
			signingKeys: [key],
			format: 'armored',
		});
		return { ...payload, html: '', plain: armored };
	}

	/** Chiffre le corps (texte) pour tous les destinataires + soi-même. */
	async encryptPayload(payload) {
		const extract = s => (s.match(/<([^>]+)>/)?.[1] || s).trim().toLowerCase();
		const rcpts = [payload.to, payload.cc, payload.bcc, payload.from]
			.join(',').split(/[,;]/).map(s => s.trim()).filter(Boolean).map(extract);
		const keys = await api.get('/pgp/keys/lookup?emails=' + encodeURIComponent(rcpts.join(',')));
		const missing = [...new Set(rcpts)].filter(r => !keys.find(k => k.email === r));
		// openpgp chargé à la demande (lazy.js) - ~540K hors du boot.
		const openpgp = await loadOpenPgp();
		// La clé publique de l'expéditeur est exigée (le message lui est aussi
		// chiffré) : si seule sa clé privée est importée, la partie publique
		// s'en déduit côté client - sinon no_pubkey pointerait son propre From.
		let selfPub = null;
		if (missing.length) {
			const selfEmail = extract(payload.from);
			if (missing.includes(selfEmail)) {
				const own = await api.get('/pgp/keys').catch(() => []);
				const priv = own.find(k => Number(k.is_private) === 1
					&& String(k.email || '').toLowerCase() === selfEmail);
				if (priv) {
					selfPub = (await openpgp.readPrivateKey({ armoredKey: priv.key_data })).toPublic();
					missing.splice(missing.indexOf(selfEmail), 1);
				}
			}
		}
		if (missing.length) {
			throw new Error(t('compose.no_pubkey', { list: missing.join(', ') }));
		}
		const pubKeys = await Promise.all(keys.map(k => openpgp.readKey({ armoredKey: k.key_data })));
		if (selfPub) { pubKeys.push(selfPub); }
		const div = document.createElement('div');
		div.innerHTML = payload.html;
		const text = div.textContent || '';
		// Signature avant chiffrement quand les deux sont demandés (standard).
		const signingKeys = this.pgpSign() ? [await this.signingKey()] : [];
		const armored = await openpgp.encrypt({
			message: await openpgp.createMessage({ text }),
			encryptionKeys: pubKeys,
			...(signingKeys.length ? { signingKeys } : {}),
		});
		return { ...payload, html: '', plain: armored };
	}

	async send() {
		if (this.sending()) { return; }
		if (this.uploadPending()) { this.error(t('compose.wait_upload')); return; }
		this.error('');
		// Validé avant le POST : ces erreurs rendraient la requête vouée à
		// une 400 (miroir du contrat serveur : corps puis aucun destinataire).
		if (this.bodyEmpty()) { this.error(t('errors.empty_body')); return; }
		const hasRcpt = ['to', 'cc', 'bcc']
			.some(f => this[f]().length || this[f + 'Text']().trim());
		if (!hasRcpt) { this.error(t('errors.no_rcpt')); return; }
		// Chiffrement = corps seul : une PJ partirait en clair > refusé
		// plutôt qu'un envoi partiellement chiffré.
		if (this.pgpEncrypt() && this.attachments().length) {
			this.error(t('compose.encrypt_no_attach')); return;
		}
		if (!this.subject().trim()
			&& !(await dialog.confirm(t('compose.empty_subject'), { variant: 'warning' }))) {
			return;
		}
		// Rappel "pièce jointe oubliée" : le corps/sujet annonce une PJ mais
		// rien n'est joint. Citations (<blockquote>) exclues du texte testé ;
		// PGP exclu (les PJ y sont déjà refusées - encrypt_no_attach).
		if (!this.attachments().length && !this.pgpEncrypt()
			&& ComposeVM.RE_ATTACH.test(this.bodyText() + ' ' + this.subject())
			&& !(await dialog.confirm(t('compose.attach_reminder'), { variant: 'warning' }))) {
			return;
		}
		this.sending(true);
		try {
			let payload = this.payload();
			if (this.pgpEncrypt()) {
				payload = await this.encryptPayload(payload);
			} else if (this.pgpSign()) {
				payload = await this.signPayload(payload);
			}
			await api.post('/compose/send', payload);
			this.draftUid = null; // brouillon consommé côté serveur
			this._closeConfirmed = true; // fermeture validée : pas de garde
			this.hide();
			this.app.messageList.refresh();
		} catch (e) {
			this.error(e.message);
		} finally {
			this.sending(false);
		}
	}

	async saveDraft() {
		if (this.sending()) { return; }
		if (this.uploadPending()) { this.error(t('compose.wait_upload')); return; }
		this.error('');
		// Validé avant le POST : un brouillon exige un corps (le reste est
		// optionnel, sujet compris) - même contrat que le serveur.
		if (this.bodyEmpty()) { this.error(t('errors.empty_body')); return; }
		// Le chiffrement ne s'applique qu'à l'envoi : un brouillon "chiffré"
		// serait stocké en clair sur le serveur IMAP sans que l'on s'en rende compte.
		if (this.pgpEncrypt()) {
			await dialog.alert(t('compose.draft_not_encrypted'),
				{ variant: 'warning', title: 'OpenPGP' });
		}
		this.sending(true);
		try {
			const r = await api.post('/compose/save-draft', this.payload());
			// Nouvel UID (append sans UIDPLUS > null : la réf n'est juste
			// pas suivie, prochain enregistrement ajoutera un doublon).
			this.draftUid = r?.uid ?? null;
			this.draftFolder = r?.folder || '';
			this._closeConfirmed = true;
			this.hide();
			this.app.messageList.refresh();
		} catch (e) {
			this.error(e.message);
		} finally {
			this.sending(false);
		}
	}
}
