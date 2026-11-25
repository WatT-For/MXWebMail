<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\UserFacingException;




final class SettingsController extends BaseController
{
	public function settingsGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->userSettings($this->app->sessionUserId()));
	}

	public function settingsSet(Request $request): JsonResponse
	{
		$this->requireWrite();
		// Whitelist + bornage centralisés dans App::saveUserSettings -
		// invalide = ignorée, jamais stockée.
		$this->app->saveUserSettings(
			$this->app->sessionUserId(), (array) $request->json());
		return JsonResponse::ok();
	}

	public function identities(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$uid = $this->app->sessionUserId();
		$data = $this->app->identities()->allForUser($uid);
		// Hook : un plugin peut fournir des identités externes (ldap-identities…)
		foreach ($this->app->hooks()->collect('identities.provider', $uid) as $extra) {
			foreach ((array) $extra as $i) {
				$data[] = $i;
			}
		}
		return JsonResponse::ok($data);
	}

	public function identitySave(Request $request): JsonResponse
	{
		$this->requireWrite();
		$data = (array) $request->json();
		// Création d'une identité supplémentaire (pas la par défaut) :
		// bloquée quand allow_additional_identities = false.
		if (empty($data['id'])
			&& !$this->app->config()->get('allow_additional_identities', true)) {
			throw new UserFacingException($this->t('errors.identities_off'), 403, jsonCode: 'forbidden');
		}
		// Une nouvelle identité s'attache au compte actif, pas au compte par défaut
		$data['account_id'] = $this->app->sessionAccountId() ?: ($data['account_id'] ?? 0);
		try {
			$id = $this->app->identities()->save($this->app->sessionUserId(), $data);
		} catch (\InvalidArgumentException $e) {
			throw new UserFacingException($this->t('errors.invalid_identity'), 400, jsonCode: 'invalid');
		}
		return JsonResponse::ok(['id' => $id]);
	}

	public function identityDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->identities()->delete($this->app->sessionUserId(), (int) $request->input('id'));
		return JsonResponse::ok();
	}

	public function identityDefault(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->identities()->setDefault($this->app->sessionUserId(), (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Changement de mot de passe - délégué à un plugin via le hook password.change. */
	public function passwordChange(Request $request): JsonResponse
	{
		$this->requireWrite();
		$old = (string) $request->input('old', '');
		$new = (string) $request->input('new', '');
		// Politique (longueur, force, HIBP) déléguée au driver du hook
		// "password.change" - un plancher ici plafonnerait sa config.
		if ($old === '' || $new === '') {
			throw new UserFacingException($this->t('errors.password_input'), jsonCode: 'input');
		}
		$handled = $this->app->hooks()->collect('password.change',
			$this->app->currentUser(), $old, $new);
		if (!$handled) {
			throw new UserFacingException($this->t('errors.no_password_driver'), jsonCode: 'unavailable');
		}
		// Le nouveau mot de passe devient le credential du compte principal :
		// ligne accounts (remember-me / bascule) + session si elle est
		// positionnée dessus (sur un secondaire on ne touche pas
		// $_SESSION['imap_pass'] - c'est le credential du secondaire).
		$enc = CredentialCrypt::encrypt($new, $this->app->storageDir());
		$this->app->accounts()->updateDefaultCredential(
			$this->app->sessionUserId(), $enc);
		if (!$this->app->isSecondaryAccount()) {
			$_SESSION['imap_pass'] = $enc;
		}
		return JsonResponse::ok();
	}

	public function sessions(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok(
			$this->app->auth()->rememberSessions($this->app->sessionUserId()));
	}

	public function sessionDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->auth()->deleteRememberToken(
			$this->app->sessionUserId(), (int) $request->params['id']);
		return JsonResponse::ok();
	}

	public function sessionsRevokeOthers(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->auth()->revokeOtherRememberTokens($this->app->sessionUserId());
		return JsonResponse::ok();
	}

	public function quota(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->quota() ?? []);
	}
}
