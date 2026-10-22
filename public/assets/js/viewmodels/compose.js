import { api } from '../api.js';
import { t } from '../i18n.js';
import { dialog } from '../ui/dialog.js';

/**
 * ViewModel : composition d'un message (nouveau, réponse, transfert, brouillon).
 * Ouvert dans la modale Bootstrap #compose-modal.
 */
export class ComposeVM {
	constructor(app) {
		this.app = app;
		this.visible = ko.observable(false);
		this.sending = ko.observable(false);
		this.error = ko.observable('');

		this.identities = ko.observableArray([]);
		this.identity = ko.observable('');
		this.to = ko.observable('');
		this.cc = ko.observable('');
		this.bcc = ko.observable('');
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
		this.draftUid = null;     // brouillon ouvert/enregistré (REPLACE à la ré-enregistrement)
		this.draftFolder = '';
		this.modal = null;
		this.pgpEncrypt = ko.observable(false);
		this.showCc = ko.observable(false);   // champ Cc déplié (menu options du header)
		this.showBcc = ko.observable(false);  // champ Cci déplié
		this.suggestField = ko.observable(''); // 'to'|'cc'|'bcc' — champ sous lequel afficher les suggestions
		// Garde de fermeture : _dirty suit les saisies utilisateur (suspendu
		// pendant les remplissages programmatiques des open*/placeCaretAtMarker).
		this._dirty = false;
		this._suspendDirty = false;
		this._prompting = false;      // dialogue de confirmation de fermeture déjà ouvert
		this._closeConfirmed = false; // fermeture validée (envoi, brouillon, abandon)
		for (const o of [this.to, this.cc, this.bcc, this.subject, this.html, this.attachments]) {
			o.subscribe(() => { if (!this._suspendDirty) { this._dirty = true; } });
		}
		// Taille persistée de la modale (px ; 0 = défaut Bootstrap)
		this.composeW = ko.observable(0);
		this.composeH = ko.observable(0);

		// Changement d'identité → remplacer le bloc signature (modale déjà ouverte)
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
		const list = await api.get('/identities').catch(() => []);
		// Ne proposer que les identités du compte actif
		const active = this.app.accounts().find(a => a.active);
		const mine = active ? list.filter(i => Number(i.account_id) === Number(active.id)) : list;
		const usable = mine.length ? mine : list;
		this.identities(usable);
		const def = usable.find(i => i.is_default) || usable[0];
		if (def) { this.identity(def.email); }
		// Taille de la modale persistée dans les réglages utilisateur
		const w = parseInt(this.app.userSettings.compose_modal_width, 10) || 0;
		const h = parseInt(this.app.userSettings.compose_modal_height, 10) || 0;
		this.composeW(w >= 480 ? w : 0);
		this.composeH(h >= 360 ? h : 0);
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
			// Garde de fermeture : contenu saisi non envoyé/enregistré →
			// confirmation (couvre ×, Échap, backdrop et hide() programmatique).
			el.addEventListener('hide.bs.modal', e => this.guardClose(e));
			el.addEventListener('hidden.bs.modal', () => this.visible(false));
		}
		this.visible(true);
		this.modal.show();
		// Le focus avant la fin de la transition est volé par Bootstrap →
		// le caret du marqueur de signature est placé une fois la modale visible.
		document.getElementById('compose-modal').addEventListener(
			'shown.bs.modal', () => this.placeCaretAtMarker(), { once: true });
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
				this.saveDraft(); // succès → ferme via _closeConfirmed ; échec → modale + erreur
			} else if (choice === 'discard') {
				this._closeConfirmed = true;
				this.modal.hide();
				this.reset(); // purge les PJ tmp abandonnées
			}
		});
	}

	reset() {
		this.error('');
		this.to(''); this.cc(''); this.bcc('');
		this.showCc(false); this.showBcc(false);
		this.subject(''); this.html('');
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
		this.suggestions([]);
		this.suggestField('');
		this.inReplyTo = ''; this.references = '';
		this.draftUid = null; this.draftFolder = '';
		this._dirty = false;
		this._closeConfirmed = false;
	}

	openNew() {
		this.reset();
		this.applySignature();
		this._dirty = false;
		this.show();
	}

	/** Bloc signature HTML de l'identité choisie ('' si aucune). */
	signatureBlock() {
		const id = this.identities().find(i => i.email === this.identity());
		return id?.signature ? `<div class="mxwm-signature">${id.signature}</div>` : '';
	}

	/** Insère la signature de l'identité choisie en fin de corps. */
	applySignature() {
		const sig = this.signatureBlock();
		this.html(sig ? `<p><br></p>${sig}` : '');
	}

	/** Remplace le bloc .mxwm-signature du corps par celui de l'identité
	 *  courante — avant la citation (blockquote/hr) s'il y en a une. */
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

	/** Place le caret sur le marqueur .mxwm-sig-caret puis le retire. */
	placeCaretAtMarker() {
		const ed = tinymce.get('compose-body');
		if (!ed?.initialized) { return; }
		const marker = ed.getBody().querySelector('span.mxwm-sig-caret');
		if (!marker) { return; }
		const rng = ed.dom.createRng();
		rng.setStartBefore(marker);
		rng.collapse(true);
		ed.dom.remove(marker);
		ed.selection.setRng(rng);
		// dom.remove ne déclenche ni change ni keyup → resync explicite de
		// l'observable (update() voit le contenu identique → caret préservé).
		// Écriture programmatique : ne doit pas marquer le brouillon « dirty ».
		this._suspendDirty = true;
		this.html(ed.getContent());
		this._suspendDirty = false;
		if (this.visible()) { ed.focus(); }
	}

	/** HTML → texte brut (citation d'un mail sans part text/plain). */
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
		this.to(replyTo);
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
			this.cc(others.join(', '));
			this.showCc(!!this.cc());
		}
		const subject = msg.subject || '';
		this.subject(/^\s*re\s*:/i.test(subject) ? subject : 'Re: ' + subject);
		this.inReplyTo = msg.messageId || '';
		this.references = [...new Set(
			[...(msg.references || '').split(/\s+/), msg.inReplyTo, msg.messageId]
				.filter(Boolean))].join(' ');
		// Mail HTML-only : la citation est dérivée du HTML (texte brut).
		const quoted = (msg.bodyPlain || this.htmlToText(msg.bodyHtml))
			.split('\n').map(l => '> ' + l).join('\n');
		this.html(`<p><br></p>${this.signatureBlock()}<blockquote>` +
			`${quoted.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>')}</blockquote>`);
		this._dirty = false;
		this.show();
	}

	openForward(msg) {
		this.reset();
		const subject = msg.subject || '';
		this.subject(/^\s*fwd?\s*:/i.test(subject) ? subject : 'Fwd: ' + subject);
		const body = msg.bodyHtml || ('<pre>' + (msg.bodyPlain || '').replace(/</g, '&lt;') + '</pre>');
		this.html(`<p><br></p>${this.signatureBlock()}<hr><p><b>${t('compose.forwarded')}</b></p>${body}`);
		this.pushMsgrefAttachments(msg);
		this._dirty = false;
		this.show();
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

	openDraft(msg) {
		this.reset();
		this.draftUid = msg.uid || null;
		this.draftFolder = msg.folder || '';
		this.to(this.headerList(msg.to));
		this.cc(this.headerList(msg.cc));
		this.bcc(this.headerList(msg.bcc));
		this.showCc(!!this.cc());
		this.showBcc(!!this.bcc());
		this.subject(msg.subject || '');
		// Le brouillon d'une réponse conserve son threading.
		this.inReplyTo = msg.inReplyTo || '';
		this.references = msg.references || '';
		this.html(msg.bodyHtml || (msg.bodyPlain || '').replace(/\n/g, '<br>'));
		this.pushMsgrefAttachments(msg);
		this._dirty = false;
		this.show();
	}

	headerList(list) {
		return (list || []).map(e => this.formatAddr(e.name, e.email)).join(', ');
	}

	/** « Nom <a@b> » — le display-name est systématiquement quoté : un nom
	 *  contenant , ; ou " casserait sinon la liste à la relecture serveur. */
	formatAddr(name, email) {
		if (!name) { return email || ''; }
		return '"' + String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"')
			+ '" <' + email + '>';
	}

	async suggest(field) {
		const value = this[field]();
		const last = value.split(/[,;]/).pop().trim();
		// Contacts désactivés : pas d'appel voué à un 403
		if (last.length < 2 || this.app.features().contacts === false) {
			this.suggestions([]);
			this.suggestField('');
			return;
		}
		this.suggestField(field);
		this.suggestions(await api.get('/contacts?q=' + encodeURIComponent(last)).catch(() => []));
	}

	applySuggestion(field, contact) {
		const parts = this[field]().split(/[,;]/);
		parts[parts.length - 1] = ' ' + this.formatAddr(contact.name, contact.email);
		this[field](parts.join(','));
		this.suggestions([]);
		this.suggestField('');
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
			// visibles dès le début du transfert (size déjà connue → le
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
					// reset() entre-temps : liste vidée → token orphelin
					api.post(`/compose/upload/${r.token}/delete`).catch(() => {});
					break;
				}
				att.token = r.token;
				att.uploading(false);
				att.progress(100);
				att.req = null;
			} catch (e) {
				this.attachments.remove(att);
				if (e.code !== 'aborted') { this.error(e.message); }
				if (gen !== this.uploadGen) { break; }
			}
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
		const ed = tinymce.get('compose-body');
		return ed?.initialized ? ed.getContent() : (this.html() || '');
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
			to: this.to(), cc: this.cc(), bcc: this.bcc(),
			subject: this.subject(),
			html: this.bodyHtml(),
			inReplyTo: this.inReplyTo,
			references: this.references,
			draftUid: this.draftUid || 0,
			draftFolder: this.draftFolder || '',
			attachments: this.attachments().map(a => a.token ?? a.msgref).filter(Boolean),
		};
	}

	/** Chiffre le corps (texte) pour tous les destinataires + soi-même. */
	async encryptPayload(payload) {
		const extract = s => (s.match(/<([^>]+)>/)?.[1] || s).trim().toLowerCase();
		const rcpts = [payload.to, payload.cc, payload.bcc, payload.from]
			.join(',').split(/[,;]/).map(s => s.trim()).filter(Boolean).map(extract);
		const keys = await api.get('/pgp/keys/lookup?emails=' + encodeURIComponent(rcpts.join(',')));
		const missing = [...new Set(rcpts)].filter(r => !keys.find(k => k.email === r));
		// La clé publique de l'expéditeur est exigée (le message lui est aussi
		// chiffré) : si seule sa clé privée est importée, la partie publique
		// s'en déduit côté client — sinon no_pubkey pointerait son propre From.
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
		const armored = await openpgp.encrypt({
			message: await openpgp.createMessage({ text }),
			encryptionKeys: pubKeys,
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
		const hasRcpt = [this.to(), this.cc(), this.bcc()]
			.some(v => v.split(/[,;]/).some(s => s.trim()));
		if (!hasRcpt) { this.error(t('errors.no_rcpt')); return; }
		// Chiffrement = corps seul : une PJ partirait en clair → refusé
		// plutôt qu'un envoi partiellement chiffré.
		if (this.pgpEncrypt() && this.attachments().length) {
			this.error(t('compose.encrypt_no_attach')); return;
		}
		if (!this.subject().trim()
			&& !(await dialog.confirm(t('compose.empty_subject'), { variant: 'warning' }))) {
			return;
		}
		this.sending(true);
		try {
			let payload = this.payload();
			if (this.pgpEncrypt()) {
				payload = await this.encryptPayload(payload);
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
		// optionnel, sujet compris) — même contrat que le serveur.
		if (this.bodyEmpty()) { this.error(t('errors.empty_body')); return; }
		// Le chiffrement ne s'applique qu'à l'envoi : un brouillon « chiffré »
		// serait stocké en clair sur le serveur IMAP sans que l'on s'en rende compte.
		if (this.pgpEncrypt()) {
			await dialog.alert(t('compose.draft_not_encrypted'),
				{ variant: 'warning', title: 'OpenPGP' });
		}
		this.sending(true);
		try {
			const r = await api.post('/compose/save-draft', this.payload());
			// Nouvel UID (append sans UIDPLUS → null : la réf n'est juste
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
