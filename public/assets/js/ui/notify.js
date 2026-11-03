import { base } from '../api.js';
import { t } from '../i18n.js';

/**
 * Notifications navigateur (API Notification) pour l'arrivée de mails.
 * Actif tant que l'onglet webmail est ouvert - la permission navigateur est
 * requise en plus du réglage utilisateur notify_new_mail.
 */
export const notify = {
	/** 'granted' | 'denied' | 'default' | 'unsupported' */
	permission() {
		return 'Notification' in window ? Notification.permission : 'unsupported';
	},

	async request() {
		if (!('Notification' in window)) { return 'unsupported'; }
		return Notification.requestPermission();
	},

	/**
	 * Affiche une notification "nouveau message".
	 * folderLabel : nom du dossier pour les surveillés ≠ INBOX (titre enrichi).
	 * onOpen(uid, folder) : callback au clic (focus + navigation vers le mail).
	 */
	newMail({ folderLabel = '', from = '', subject = '', uid = 0, folder = 'INBOX', onOpen = null }) {
		if (this.permission() !== 'granted') { return; }
		const n = new Notification(
			folderLabel ? t('app.new_mail_in', { folder: folderLabel }) : t('app.new_mail'),
			{
				body: `${from}\n${subject}`,
				icon: base() + '/favicon.ico',
				// tag fixe : une nouvelle notif remplace la précédente (multi-onglets compris)
				tag: 'mxwm-new-mail',
			});
		n.onclick = () => {
			window.focus();
			if (onOpen) { onOpen(uid, folder); }
			n.close();
		};
	},
};
