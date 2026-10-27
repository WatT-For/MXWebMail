import { t, lang } from '../i18n.js';

/**
 * Modèle d'un message côté vue : wrap les données API en observables.
 */
export class MessageModel {
	constructor(data = {}) {
		this.uid = data.uid;
		this.folder = data.folder;
		this.subject = data.subject || t('message.no_subject');
		this.from = data.from;
		this.date = data.date;
		this.size = data.size;
		this.isSeen = ko.observable(!!data.isSeen);
		this.isFlagged = ko.observable(!!data.isFlagged);
		this.isJunk = ko.observable(!!data.isJunk);
		this.isSelected = ko.observable(false);   // ligne courante (lecture)
		this.isChecked = ko.observable(false);    // case à cocher (actions groupées)
		this.colorTag = ko.observable(data.colorTag || null); // étiquette couleur (plugin)
		this.colorTagId = data.colorTagId || null;
		this.colorTagLabel = data.colorTagLabel || null;
		this.hasAttach = !!data.hasAttach;
		this.threads = data.threads || [];
		this.threadUnseen = data.threadUnseen || [];
		this.isChild = false;      // sous-ligne d'un fil déplié
		this._threadOpen = ko.observable(false);
		this.raw = data;
	}

	fromLabel() {
		const f = this.from;
		if (!f) { return ''; }
		if (Array.isArray(f) && f.length) {
			const e = f[0];
			return e.name || e.email || e;
		}
		return typeof f === 'string' ? f : (f.email || '');
	}

	dateLabel() {
		if (!this.date) { return ''; }
		const d = new Date(this.date * 1000);
		const today = new Date();
		return d.toDateString() === today.toDateString()
			? d.toLocaleTimeString(lang, { hour: '2-digit', minute: '2-digit' })
			: d.toLocaleDateString(lang, { day: '2-digit', month: 'short' });
	}

	/** Taille lisible (size est en octets, unités binaires o/Ko/Mo/Go). */
	sizeLabel() {
		const b = Number(this.size) || 0;
		if (b >= 1073741824) { return (b / 1073741824).toFixed(1) + ' ' + t('unit.gb'); }
		if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' ' + t('unit.mb'); }
		if (b >= 1024) { return Math.round(b / 1024) + ' ' + t('unit.kb'); }
		return b + ' ' + t('unit.b');
	}
}
