/**
 * Champs du formulaire d'app OAuth2 (enregistrement utilisateur) -
 * partagés entre l'ajout de compte (AppVM.promptOauthApp) et l'onglet
 * Apps OAuth des paramètres (SettingsVM.editOauthApp).
 *
 * `a` = app existante (édition) : client_id/tenant pré-remplis, le secret
 * porte le placeholder "inchangé". Sans `a` (premier ajout) : champs vides.
 */
import { t } from '../i18n.js';

export function oauthAppFields(provider, a = {}) {
	const fields = [
		{ name: 'client_id', label: t('settings.oauth_client_id'),
			defaultValue: a.client_id },
		{ name: 'secret', label: t('settings.oauth_secret'), inputType: 'password',
			placeholder: a.client_id ? t('settings.oauth_secret_keep') : '' },
	];
	if (provider === 'microsoft') {
		fields.push({ name: 'tenant', label: t('settings.oauth_tenant'),
			defaultValue: a.tenant || 'common' });
	}
	return fields;
}
