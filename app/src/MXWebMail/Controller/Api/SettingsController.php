<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Security\Csrf;
use MXWebMail\Security\TotpRequiredException;
use MXWebMail\Service\MailService;




final class SettingsController extends BaseController
{
	public function settingsGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->userSettings((int) $_SESSION['user_id']));
	}

	public function settingsSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Whitelist partagée : App::allowedUserSettingKeys (user_defaults +
		// hooks "settings.allowed", jamais "plugin.*"). Valeurs bornées
		// par les mêmes règles que les défauts globaux (App::checkUserSetting)
		// - invalide = ignorée, jamais stockée.
		$allowed = $this->app->allowedUserSettingKeys();
		foreach ((array) $request->json() as $key => $value) {
			$key = (string) $key;
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			[$ok, $v] = $this->app->checkUserSetting($key, $value);
			if ($ok) {
				$this->app->settings()->set((int) $_SESSION['user_id'], $key, $v);
			}
		}
		return JsonResponse::ok();
	}

	public function identities(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$uid = (int) $_SESSION['user_id'];
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
		$this->requireAuth();
		$this->requireCsrf();
		$data = (array) $request->json();
		// Création d'une identité supplémentaire (pas la par défaut) :
		// bloquée quand allow_additional_identities = false.
		if (empty($data['id'])
			&& !$this->app->config()->get('allow_additional_identities', true)) {
			return JsonResponse::error($this->t('errors.identities_off'), 'forbidden', 403);
		}
		// Une nouvelle identité s'attache au compte actif, pas au compte par défaut
		$data['account_id'] = (int) ($_SESSION['account_id'] ?? 0) ?: ($data['account_id'] ?? 0);
		try {
			$id = $this->app->identities()->save((int) $_SESSION['user_id'], $data);
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error(
				$this->t('errors.invalid_identity'), 'invalid', 400);
		}
		return JsonResponse::ok(['id' => $id]);
	}

	public function identityDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->identities()->delete((int) $_SESSION['user_id'], (int) $request->input('id'));
		return JsonResponse::ok();
	}

	public function identityDefault(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->identities()->setDefault((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Changement de mot de passe - délégué à un plugin via le hook password.change. */
	public function passwordChange(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$old = (string) $request->input('old', '');
		$new = (string) $request->input('new', '');
		// Politique (longueur, force, HIBP) déléguée au driver du hook
		// "password.change" - un plancher ici plafonnerait sa config.
		if ($old === '' || $new === '') {
			return JsonResponse::error($this->t('errors.password_input'), 'input');
		}
		$handled = $this->app->hooks()->collect('password.change',
			$this->app->currentUser(), $old, $new);
		if (!$handled) {
			return JsonResponse::error($this->t('errors.no_password_driver'), 'unavailable');
		}
		// Le nouveau mot de passe devient le credential du compte principal :
		// ligne accounts (remember-me / bascule) + session si elle est
		// positionnée dessus (sur un secondaire on ne touche pas
		// $_SESSION['imap_pass'] - c'est le credential du secondaire).
		$enc = CredentialCrypt::encrypt($new, $this->app->storageDir());
		$this->app->db()->pdo()->prepare(
			'UPDATE accounts SET imap_pass = :ip WHERE user_id = :u AND is_default = 1')
			->execute(['ip' => $enc,
				'u' => (int) $_SESSION['user_id']]);
		if (empty($_SESSION['acct_secondary'])) {
			$_SESSION['imap_pass'] = $enc;
		}
		return JsonResponse::ok();
	}

	public function sessions(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok(
			$this->app->auth()->rememberSessions((int) $_SESSION['user_id']));
	}

	public function sessionDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->deleteRememberToken(
			(int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	public function sessionsRevokeOthers(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->revokeOtherRememberTokens((int) $_SESSION['user_id']);
		return JsonResponse::ok();
	}

	public function quota(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->quota() ?? []);
	}
}
