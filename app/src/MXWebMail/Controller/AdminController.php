<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;

/**
 * API admin : une seule administration globale (multi-domaine inclus).
 * Session admin distincte ($_SESSION['admin']).
 */
final class AdminController extends Admin\BaseController
{
	public function login(Request $request): JsonResponse
	{
		if (!Csrf::verifyRequest()) {
			return JsonResponse::error($this->t('errors.csrf'), 'csrf', 403);
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
					throw new \RuntimeException($this->t('errors.bad_credentials'), 401);
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
				return JsonResponse::error($this->t('errors.admin_required'), 'forbidden', 403);
			}
			$_SESSION['admin'] = (int) $u['id'];
			$auth->resetRateLimit($rateKey);
			$this->app->logAuth('admin:' . $user, 'AUTH OK');
			return JsonResponse::ok();
		} catch (\MXWebMail\Security\TotpRequiredException $e) {
			if (trim((string) $request->input('totp', '')) !== '') {
				$auth->recordFailedAttempt($rateKey);
				$this->app->logAuth('admin:' . $user, 'AUTH FAILED (totp)');
			}
			return JsonResponse::error($e->getMessage(), 'totp_required', 401);
		} catch (\Throwable $e) {
			// Un 429 = tentative déjà bloquée : ne pas prolonger le verrou.
			if ($e->getCode() !== 429) {
				$auth->recordFailedAttempt($rateKey);
			}
			$this->app->logAuth('admin:' . $user, 'AUTH FAILED (' . $e->getCode() . ')');
			$this->app->log('warning', 'Login admin échoué: ' . $e->getMessage());
			if (in_array($e->getCode(), [401, 403, 429], true)) {
				return JsonResponse::error($e->getMessage(), 'auth', $e->getCode());
			}
			return JsonResponse::error($this->t('errors.bad_credentials'), 'auth', 401);
		}
	}

	public function logout(Request $request): JsonResponse
	{
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

