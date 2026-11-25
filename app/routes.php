<?php
declare(strict_types=1);

/**
 * Routes déclaratives : [METHODE, chemin, [Contrôleur::class, 'action']].
 * Lues par App::registerRoutes - ordre = ordre d'enregistrement (les
 * routes à paramètres doivent rester après leurs homologues littérales
 * quand le router matche dans l'ordre). Les routes des plugins restent
 * enregistrées via le hook app.routes à la fin du chargement.
 */

use MXWebMail\Controller\AdminController;
use MXWebMail\Controller\ApiController;
use MXWebMail\Controller\PageController;
use MXWebMail\Controller\Admin;
use MXWebMail\Controller\Api;

return static function (string $adminPath): array {
	return [
		// ─── Pages ───
		['GET', '/', [PageController::class, 'home']],
		['GET', '/' . $adminPath, [PageController::class, 'admin']],
		['GET', '/favicon.ico', [PageController::class, 'favicon']],
		['GET', '/assets/plugin/{name}/{file}', [PageController::class, 'pluginAsset']],
		['GET', '/assets/theme/{name}/{file}', [PageController::class, 'themeAsset']],
		['GET', '/assets/i18n/{lang}.js', [PageController::class, 'i18nAsset']],

		// ─── API publique ───
		['POST', '/api/login', [ApiController::class, 'login']],
		['POST', '/api/logout', [ApiController::class, 'logout']],
		['GET', '/api/ping', [ApiController::class, 'ping']],
		['GET', '/api/appdata', [ApiController::class, 'appdata']],
		['GET', '/api/proxy', [ApiController::class, 'proxy']],

		// ─── API authentifiée ───
		['GET', '/api/folders', [Api\FoldersController::class, 'folders']],
		// Poll mutualisé (folders + watch + list + quota, 1 connexion IMAP)
		['GET', '/api/poll', [Api\PollController::class, 'poll']],
		['POST', '/api/folders/create', [Api\FoldersController::class, 'folderCreate']],
		['POST', '/api/folders/{name}/delete', [Api\FoldersController::class, 'folderDelete']],
		['POST', '/api/folders/{name}/rename', [Api\FoldersController::class, 'folderRename']],
		['POST', '/api/folders/{name}/subscribe', [Api\FoldersController::class, 'folderSubscribe']],
		['POST', '/api/folders/{name}/clear', [Api\FoldersController::class, 'folderClear']],
		['POST', '/api/folders/{name}/mark-read', [Api\FoldersController::class, 'folderMarkRead']],
		['POST', '/api/folders/{name}/import', [Api\FoldersController::class, 'folderImport']],
		['GET', '/api/folders/{name}/eml.zip', [Api\FoldersController::class, 'emlZip']],
		['GET', '/api/folders/{name}/acl', [Api\FoldersController::class, 'folderAcl']],
		['POST', '/api/folders/{name}/acl', [Api\FoldersController::class, 'folderAclSet']],
		['POST', '/api/folders/{name}/acl/delete', [Api\FoldersController::class, 'folderAclDelete']],
		['GET', '/api/folders/special', [Api\FoldersController::class, 'foldersSpecialGet']],
		['POST', '/api/folders/special', [Api\FoldersController::class, 'foldersSpecialSet']],
		['GET', '/api/folders/notify', [Api\FoldersController::class, 'foldersNotifyGet']],
		['POST', '/api/folders/notify', [Api\FoldersController::class, 'foldersNotifySet']],
		['POST', '/api/folders/collapsed', [Api\FoldersController::class, 'foldersCollapsedSet']],
		['POST', '/api/folders/hidden', [Api\FoldersController::class, 'foldersHiddenSet']],
		['POST', '/api/folders/last', [Api\FoldersController::class, 'foldersLastSet']],

		['GET', '/api/messages', [Api\MessagesController::class, 'messages']],
		['GET', '/api/messages/{uid}', [Api\MessagesController::class, 'message']],
		['GET', '/api/messages/{uid}/raw', [Api\MessagesController::class, 'messageRaw']],
		['GET', '/api/messages/{uid}/attachment/{id}', [Api\MessagesController::class, 'attachment']],
		['GET', '/api/messages/{uid}/attachment/{id}/eml', [Api\MessagesController::class, 'attachmentEml']],
		['GET', '/api/messages/{uid}/attachment/{id}/eml/{part}', [Api\MessagesController::class, 'attachmentEmlPart']],
		// Batch multi-dossiers (sélection cochée) - avant les routes {uid}.
		['POST', '/api/messages/delete', [Api\MessagesController::class, 'messagesDelete']],
		['POST', '/api/messages/move', [Api\MessagesController::class, 'messagesMove']],
		['POST', '/api/messages/copy', [Api\MessagesController::class, 'messagesCopy']],
		['POST', '/api/messages/flags', [Api\MessagesController::class, 'messagesFlags']],
		['POST', '/api/messages/spam', [Api\MessagesController::class, 'messagesSpam']],
		['POST', '/api/messages/{uid}/flags', [Api\MessagesController::class, 'messageFlags']],
		['POST', '/api/messages/{uid}/move', [Api\MessagesController::class, 'messageMove']],
		['POST', '/api/messages/{uid}/copy', [Api\MessagesController::class, 'messageCopy']],
		['POST', '/api/messages/{uid}/delete', [Api\MessagesController::class, 'messageDelete']],
		['POST', '/api/messages/{uid}/spam', [Api\MessagesController::class, 'messageSpam']],
		['POST', '/api/messages/{uid}/unsubscribe', [Api\MessagesController::class, 'messageUnsubscribe']],
		['POST', '/api/messages/{uid}/bounce', [Api\MessagesController::class, 'messageBounce']],
		['POST', '/api/messages/{uid}/mdn', [Api\MessagesController::class, 'messageMdnSend']],
		['POST', '/api/messages/{uid}/smime-verify', [Api\MessagesController::class, 'messageSmimeVerify']],
		['GET', '/api/messages/{uid}/attachments.zip', [Api\MessagesController::class, 'attachmentsZip']],
		['GET', '/api/responses', [Api\ComposeController::class, 'responses']],
		['POST', '/api/responses', [Api\ComposeController::class, 'responseSave']],
		['POST', '/api/responses/{id}/delete', [Api\ComposeController::class, 'responseDelete']],

		['POST', '/api/compose/send', [Api\ComposeController::class, 'composeSend']],
		['POST', '/api/compose/upload', [Api\ComposeController::class, 'composeUpload']],
		['POST', '/api/compose/upload/{token}/delete', [Api\ComposeController::class, 'composeUploadDelete']],
		['POST', '/api/compose/save-draft', [Api\ComposeController::class, 'composeSaveDraft']],

		['GET', '/api/contacts/group', [Api\ContactsController::class, 'contactsGroup']],
		['GET', '/api/contacts', [Api\ContactsController::class, 'contacts']],
		['POST', '/api/contacts', [Api\ContactsController::class, 'contactSave']],
		['POST', '/api/contacts/delete', [Api\ContactsController::class, 'contactDelete']],
		['POST', '/api/contacts/import', [Api\ContactsController::class, 'contactsImport']],
		['GET', '/api/contacts/export', [Api\ContactsController::class, 'contactsExport']],
		['POST', '/api/password/change', [Api\SettingsController::class, 'passwordChange']],
		['GET', '/api/sessions', [Api\SettingsController::class, 'sessions']],
		['POST', '/api/sessions/{id}/delete', [Api\SettingsController::class, 'sessionDelete']],
		['POST', '/api/sessions/revoke-others', [Api\SettingsController::class, 'sessionsRevokeOthers']],
		['GET', '/api/settings', [Api\SettingsController::class, 'settingsGet']],
		['POST', '/api/settings', [Api\SettingsController::class, 'settingsSet']],
		['GET', '/api/identities', [Api\SettingsController::class, 'identities']],
		['POST', '/api/identities', [Api\SettingsController::class, 'identitySave']],
		['POST', '/api/identities/delete', [Api\SettingsController::class, 'identityDelete']],
		['POST', '/api/identities/{id}/default', [Api\SettingsController::class, 'identityDefault']],

		// Comptes additionnels (+ flow OAuth2 : begin JSON, callback page ;
		// identifiants d'app par utilisateur : /api/oauth/apps)
		['GET', '/api/accounts', [Api\AccountsController::class, 'accounts']],
		['POST', '/api/accounts', [Api\AccountsController::class, 'accountAdd']],
		['POST', '/api/accounts/oauth', [Api\AccountsController::class, 'accountOauthBegin']],
		['GET', '/api/oauth/callback', [Api\AccountsController::class, 'accountOauthCallback']],
		['GET', '/api/oauth/apps', [Api\AccountsController::class, 'oauthApps']],
		['POST', '/api/oauth/apps', [Api\AccountsController::class, 'oauthAppSave']],
		['POST', '/api/oauth/apps/delete', [Api\AccountsController::class, 'oauthAppDelete']],
		['POST', '/api/accounts/{id}/switch', [Api\AccountsController::class, 'accountSwitch']],
		['POST', '/api/accounts/{id}/delete', [Api\AccountsController::class, 'accountDelete']],
		['POST', '/api/accounts/{id}/default', [Api\AccountsController::class, 'accountDefault']],
		['POST', '/api/accounts/order', [Api\AccountsController::class, 'accountOrder']],

		// Filtres Sieve
		['GET', '/api/filters', [Api\FiltersController::class, 'filters']],
		['GET', '/api/filters/script', [Api\FiltersController::class, 'filterGet']],
		['POST', '/api/filters/script', [Api\FiltersController::class, 'filterSave']],
		['POST', '/api/filters/activate', [Api\FiltersController::class, 'filterActivate']],
		['POST', '/api/filters/deactivate', [Api\FiltersController::class, 'filterDeactivate']],
		['POST', '/api/filters/compile', [Api\FiltersController::class, 'filterCompile']],
		['GET', '/api/filters/vacation', [Api\FiltersController::class, 'vacationGet']],
		['POST', '/api/filters/vacation', [Api\FiltersController::class, 'vacationSave']],
		['POST', '/api/filters/delete', [Api\FiltersController::class, 'filterDelete']],
		// PGP - gestion du keyring (crypto côté navigateur, OpenPGP.js)
		['GET', '/api/pgp/keys', [Api\PgpController::class, 'pgpKeys']],
		['POST', '/api/pgp/keys', [Api\PgpController::class, 'pgpKeySave']],
		['POST', '/api/pgp/keys/{id}/delete', [Api\PgpController::class, 'pgpKeyDelete']],
		['GET', '/api/pgp/keys/lookup', [Api\PgpController::class, 'pgpKeysLookup']],

		['GET', '/api/quota', [Api\SettingsController::class, 'quota']],

		// API générique des plugins : /api/plugin/{name}/{action} > Plugin::api<Action>
		['GET', '/api/plugin/{name}/{action}', [ApiController::class, 'pluginCall']],
		['POST', '/api/plugin/{name}/{action}', [ApiController::class, 'pluginCall']],

		// ─── API admin ───
		['POST', '/api/admin/login', [AdminController::class, 'login']],
		['POST', '/api/admin/logout', [AdminController::class, 'logout']],
		['GET', '/api/admin/bootstrap', [Admin\SystemController::class, 'bootstrap']],
		['GET', '/api/admin/users', [Admin\UsersController::class, 'users']],
		['GET', '/api/admin/users/{id}', [Admin\UsersController::class, 'userGet']],
		['POST', '/api/admin/users/{id}', [Admin\UsersController::class, 'userSave']],
		['POST', '/api/admin/users/{id}/delete', [Admin\UsersController::class, 'userDelete']],
		['POST', '/api/admin/users/{id}/settings', [Admin\UsersController::class, 'userSettingsSet']],
		['POST', '/api/admin/users/{id}/settings/reset', [Admin\UsersController::class, 'userSettingsReset']],
		['POST', '/api/admin/users/{id}/accounts/delete', [Admin\UsersController::class, 'userAccountDelete']],
		['POST', '/api/admin/users/{id}/identities', [Admin\UsersController::class, 'userIdentitySave']],
		['POST', '/api/admin/users/{id}/identities/delete', [Admin\UsersController::class, 'userIdentityDelete']],
		['POST', '/api/admin/users/{id}/identities/default', [Admin\UsersController::class, 'userIdentityDefault']],
		['GET', '/api/admin/users/{id}/sessions', [Admin\UsersController::class, 'userSessions']],
		['POST', '/api/admin/users/{id}/sessions/{sid}/delete', [Admin\UsersController::class, 'userSessionDelete']],
		['POST', '/api/admin/users/{id}/sessions/revoke', [Admin\UsersController::class, 'userSessionsRevoke']],
		['GET', '/api/admin/domains', [Admin\DomainsController::class, 'domains']],
		['POST', '/api/admin/domains', [Admin\DomainsController::class, 'domainSave']],
		['POST', '/api/admin/domains/delete', [Admin\DomainsController::class, 'domainDelete']],
		['POST', '/api/admin/domains/detect', [Admin\DomainsController::class, 'domainDetect']],
		['GET', '/api/admin/plugins', [Admin\PluginsController::class, 'plugins']],
		['GET', '/api/admin/plugins/catalog', [Admin\PluginsController::class, 'pluginsCatalog']],
		['POST', '/api/admin/plugins/{name}/install', [Admin\PluginsController::class, 'pluginInstall']],
		['POST', '/api/admin/plugins/{name}/remove', [Admin\PluginsController::class, 'pluginRemove']],
		['POST', '/api/admin/plugins/{name}/toggle', [Admin\PluginsController::class, 'pluginToggle']],
		['POST', '/api/admin/plugins/{name}/allowed', [Admin\PluginsController::class, 'pluginAllowedSet']],
		['GET', '/api/admin/plugins/{name}/config', [Admin\PluginsController::class, 'pluginConfigGet']],
		['POST', '/api/admin/plugins/{name}/config', [Admin\PluginsController::class, 'pluginConfigSet']],
		// API générique des plugins côté admin : > Plugin::apiAdmin<Action>
		['GET', '/api/admin/plugin/{name}/{action}', [AdminController::class, 'pluginCall']],
		['POST', '/api/admin/plugin/{name}/{action}', [AdminController::class, 'pluginCall']],
		['GET', '/api/admin/themes', [Admin\ThemesController::class, 'themes']],
		['POST', '/api/admin/themes/{name}/default', [Admin\ThemesController::class, 'themeDefaultSet']],
		['POST', '/api/admin/themes/{name}/allowed', [Admin\ThemesController::class, 'themeAllowedSet']],
		['GET', '/api/admin/themes/{name}/config', [Admin\ThemesController::class, 'themeConfigGet']],
		['POST', '/api/admin/themes/{name}/config', [Admin\ThemesController::class, 'themeConfigSet']],
		['POST', '/api/admin/test-connexion', [Admin\SystemController::class, 'testConnexion']],
		['POST', '/api/admin/test-db', [Admin\SystemController::class, 'testDb']],
		['GET', '/api/admin/logs', [Admin\SystemController::class, 'logs']],
		['POST', '/api/admin/logs/clear', [Admin\SystemController::class, 'logsClear']],
		['GET', '/api/admin/settings', [Admin\SystemController::class, 'settingsGet']],
		['POST', '/api/admin/settings', [Admin\SystemController::class, 'settingsSet']],
		['POST', '/api/admin/password', [Admin\SystemController::class, 'passwordSet']],
		['POST', '/api/admin/favicon', [Admin\SystemController::class, 'faviconUpload']],
		['POST', '/api/admin/favicon/delete', [Admin\SystemController::class, 'faviconDelete']],
		['GET', '/api/admin/about', [Admin\SystemController::class, 'about']],
		['GET', '/api/admin/backup', [Admin\SystemController::class, 'backup']],
		['GET', '/api/admin/update/check', [Admin\SystemController::class, 'updateCheck']],
		['GET', '/api/admin/update/status', [Admin\SystemController::class, 'updateStatus']],
		['POST', '/api/admin/update/download', [Admin\SystemController::class, 'updateDownload']],
		['POST', '/api/admin/update/apply', [Admin\SystemController::class, 'updateApply']],
		['POST', '/api/admin/update/finalize', [Admin\SystemController::class, 'updateFinalize']],
		['POST', '/api/admin/update/rollback', [Admin\SystemController::class, 'updateRollback']],
		['POST', '/api/admin/update/reset', [Admin\SystemController::class, 'updateReset']],
	];
};
