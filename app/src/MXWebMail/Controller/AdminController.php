<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;
use MXWebMail\UserFacingException;

/**
 * API admin : une seule administration globale (multi-domaine inclus).
 * Session admin distincte ($_SESSION['admin']).
 */
final class AdminController extends Admin\BaseController
{
	public function login(Request $request): JsonResponse
	{
		if (!Csrf::verifyRequest()) {
			throw new UserFacingException($this->t('errors.csrf'), 403, jsonCode: 'csrf');
		}
		$this->requireAdminIp();
		$user = (string) $request->input('user', $request->input('email', ''));
		$password = (string) $request->input('password', '');
		$rateKey = 'admin:' . strtolower($user);
		$auth = $this->app->auth();
		try {
			$auth->checkRateLimit($rateKey);

			$cfgUser = (string) $this->app->config()->get('admin_user', '');
			$cfgHash = (string) $this->app->config()->get('admin_password_hash', '');
			if ($cfgUser !== '' && $cfgHash !== '') {
				// Compte admin local - indépendant d'IMAP
				if (!hash_equals($cfgUser, $user) || !password_verify($password, $cfgHash)) {
					throw new UserFacingException($this->t('errors.bad_credentials'), 401, jsonCode: 'auth');
				}
				// TOTP du compte local : délégué au plugin two-factor-auth
				// (admin.post-login - plugin désactivé = non exigé).
				$this->app->hooks()->run('admin.post-login',
					(string) $request->input('totp', ''));
				// Régénère l'ID à l'élévation de privilèges (fixation de
				// session) - le chemin IMAP le fait déjà via AuthService::login.
				session_regenerate_id(true);
				$_SESSION['admin'] = true;
				$auth->resetRateLimit($rateKey);
				$this->app->logAuth('admin:' . $user, 'AUTH OK');
				return JsonResponse::ok();
			}

			// Fallback : utilisateur is_admin vérifié via IMAP
			$auth->login($user, $password, (string) $request->input('totp', '') ?: null);
			$u = $this->app->users()->findByUsername(strtolower($user));
			if (!$u || !$this->app->users()->isAdmin((int) $u['id'])) {
				// login() a créé une session webmail complète : la détruire,
				// sinon un non-admin repart avec une session valide.
				$auth->logout();
				$this->app->logAuth('admin:' . $user, 'AUTH FAILED (403)');
				throw new UserFacingException($this->t('errors.admin_required'), 403, jsonCode: 'forbidden');
			}
			$_SESSION['admin'] = (int) $u['id'];
			$auth->resetRateLimit($rateKey);
			$this->app->logAuth('admin:' . $user, 'AUTH OK');
			return JsonResponse::ok();
		} catch (\MXWebMail\Security\TotpRequiredException $e) {
			$this->loginTotpError($request, $rateKey, 'admin:' . $user);
		} catch (\Throwable $e) {
			$this->loginError($e, $rateKey, 'admin:' . $user, 'errors.bad_credentials');
		}
	}

	public function logout(Request $request): JsonResponse
	{
		$this->requireCsrf();
		unset($_SESSION['admin']);
		return JsonResponse::ok();
	}

	/** Dispatch borné : /api/admin/plugin/{name}/{action} > Plugin::apiAdmin<Action>. */
	public function pluginCall(Request $request): JsonResponse
	{
		$this->requireAdmin();
		// CSRF exigé pour TOUTES les méthodes (GET inclus), comme pluginCall user.
		$this->requireCsrf();
		return JsonResponse::ok($this->app->plugins()->callAdminApi(
			(string) $request->params['name'],
			(string) $request->params['action'],
			$request
		));
	}
}

