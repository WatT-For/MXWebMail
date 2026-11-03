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




final class AccountsController extends BaseController
{
	public function accounts(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$uid = (int) $_SESSION['user_id'];
		return JsonResponse::ok(array_map(
			fn($a) => $a + ['active' => (int) $a['id'] === (int) ($_SESSION['account_id'] ?? 0)],
			$this->app->auth()->accounts($uid)
		));
	}

	public function accountAdd(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (!$this->app->config()->get('allow_additional_accounts', true)) {
			return JsonResponse::error($this->t('errors.accounts_off'), 'forbidden', 403);
		}
		$r = $this->app->auth()->addAccount(
			(int) $_SESSION['user_id'],
			(string) $request->input('email'),
			(string) $request->input('password')
		);
		return JsonResponse::ok($r);
	}

	/**
	 * Début du flow OAuth2 d'ajout de compte secondaire : l'email doit porter
	 * sur un domaine listé marqué options.oauth, sinon {url:null} - le client
	 * retombe alors sur l'ajout par mot de passe. Domaine OAuth sans app
	 * enregistrée par l'utilisateur > needs_app (le client demande les
	 * identifiants d'application puis retente).
	 */
	public function accountOauthBegin(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (!$this->app->config()->get('allow_additional_accounts', true)) {
			return JsonResponse::error($this->t('errors.accounts_off'), 'forbidden', 403);
		}
		$email = trim(mb_strtolower((string) $request->input('email', '')));
		$domainName = str_contains($email, '@')
			? substr($email, strrpos($email, '@') + 1) : '';
		$domain = $domainName !== '' ? $this->app->auth()->domainFor($domainName, true) : [];
		$provider = (string) (\MXWebMail\Service\AuthService::domainOptions($domain)['oauth'] ?? '');
		$oauth = $this->app->oauth();
		if (empty($domain['id']) || $provider === '') {
			return JsonResponse::ok(['url' => null]);
		}
		$app = $oauth->appFor((int) $_SESSION['user_id'], $provider);
		if ($app === null) {
			return JsonResponse::ok(['url' => null, 'provider' => $provider,
				'needs_app' => true, 'redirect_uri' => $oauth->redirectUri()]);
		}
		$_SESSION['oauth_pending'] = $oauth->pending($provider, $email);
		return JsonResponse::ok(
			['url' => $oauth->authorizeUrl($_SESSION['oauth_pending'], $app)]);
	}

	/**
	 * Callback du provider (GET navigateur, popup ou redirection pleine page).
	 * Pas de JSON : renvoie une mini-page qui postMessage le résultat à
	 * window.opener puis se ferme - repli meta-refresh vers /?oauth=<résultat>.
	 */
	public function accountOauthCallback(Request $request): Response
	{
		$pending = $_SESSION['oauth_pending'] ?? null;
		if (empty($_SESSION['user_id']) || !is_array($pending)) {
			unset($_SESSION['oauth_pending']);
			return Response::redirect(\MXWebMail\Http\Request::basePath() . '/');
		}
		try {
			$error = (string) $request->input('error', '');
			if ($error !== '') {
				return $this->oauthDone(false,
					$error === 'access_denied' ? 'denied' : 'failed');
			}
			$state = (string) $request->input('state', '');
			$code = (string) $request->input('code', '');
			if ($state === '' || $code === ''
				|| !hash_equals((string) $pending['state'], $state)) {
				return $this->oauthDone(false, 'state');
			}
			$app = $this->app->oauth()->appFor(
				(int) $_SESSION['user_id'], (string) $pending['provider']);
			if ($app === null) {
				return $this->oauthDone(false, 'failed');
			}
			$tokens = $this->app->oauth()->exchangeCode($pending, $code, $app);
			// Email affirmé par le provider (id_token) : s'il est présent il
			// doit égaler celui demandé - s'il est absent la connexion IMAP
			// XOAUTH2 de addAccountOauth fait la preuve d'appartenance.
			$providerEmail = (string) ($tokens['email'] ?? '');
			if ($providerEmail !== '' && $providerEmail !== $pending['email']) {
				return $this->oauthDone(false, 'email_mismatch');
			}
			$this->app->auth()->addAccountOauth(
				(int) $_SESSION['user_id'],
				(string) $pending['email'],
				(string) $tokens['access_token'],
				(string) $tokens['refresh_token'],
				(string) $pending['provider']);
			return $this->oauthDone(true);
		} catch (\Throwable $e) {
			$this->app->log('warning', 'OAuth callback : ' . $e->getMessage());
			return $this->oauthDone(false, 'failed');
		}
	}

	private function oauthDone(bool $ok, string $err = ''): Response
	{
		unset($_SESSION['oauth_pending']);
		$back = \MXWebMail\Http\Request::basePath() . '/?oauth=' . ($ok ? 'ok' : $err);
		$payload = json_encode(['type' => 'mxwm-oauth', 'ok' => $ok, 'err' => $err]);
		$msg = htmlspecialchars($ok ? $this->t('app.account_added') : $this->t('errors.oauth_failed'));
		return Response::html(
			'<!doctype html><html><head><meta charset="utf-8"><title>OAuth</title>'
			. '<meta http-equiv="refresh" content="4;url=' . htmlspecialchars($back, ENT_QUOTES) . '">'
			. '</head><body><p>' . $msg . '</p><script>try{'
			. 'if(window.opener){window.opener.postMessage(' . $payload . ',location.origin);window.close();}'
			. 'else{location.replace(' . json_encode($back) . ');}'
			. '}catch(e){location.replace(' . json_encode($back) . ');}</script></body></html>');
	}

	/**
	 * Apps OAuth2 de l'utilisateur : liste sans secrets + providers autorisés
	 * (domaines flaggés options.oauth) + redirect URI à déclarer chez le provider.
	 */
	public function oauthApps(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$repo = $this->app->oauthApps();
		return JsonResponse::ok([
			'apps'         => $repo->allFor((int) $_SESSION['user_id']),
			'providers'    => $repo->allowedProviders(),
			'redirect_uri' => $this->app->oauth()->redirectUri(),
		]);
	}

	/**
	 * Enregistre/met à jour l'app d'un provider. Le provider doit être
	 * autorisé par au moins un domaine (options.oauth) - une app inutilisable
	 * n'a pas à être stockée. Secret obligatoire à la création (flow "web
	 * app" exigé par les 3 providers), vide = inchangé à l'édition.
	 */
	public function oauthAppSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$repo = $this->app->oauthApps();
		$provider = (string) $request->input('provider', '');
		if (!in_array($provider, \MXWebMail\Service\OAuthService::providers(), true)
			|| !in_array($provider, $repo->allowedProviders(), true)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'provider']), 'validation', 400);
		}
		$uid = (int) $_SESSION['user_id'];
		$clientId = $this->noCrlf(
			trim((string) $request->input('client_id', '')), 'client_id');
		$secret = $this->noCrlf(trim((string) $request->input('secret', '')), 'secret');
		$tenant = $this->noCrlf(trim((string) $request->input('tenant', '')), 'tenant');
		$exists = $repo->appFor($uid, $provider) !== null;
		if ($clientId === '' || strlen($clientId) > 255 || strlen($secret) > 255
			|| (!$exists && $secret === '')
			|| ($tenant !== '' && !preg_match('/^[A-Za-z0-9.\-]+$/', $tenant))) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'client_id']), 'validation', 400);
		}
		$repo->save($uid, $provider, $clientId,
			$secret !== '' ? $secret : null,
			$provider === 'microsoft' ? $tenant : '');
		return JsonResponse::ok();
	}

	public function oauthAppDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->oauthApps()->delete(
			(int) $_SESSION['user_id'], (string) $request->input('provider', ''));
		return JsonResponse::ok();
	}

	public function accountSwitch(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		return JsonResponse::ok(
			$this->app->auth()->switchAccount((int) $_SESSION['user_id'], (int) $request->params['id'])
		);
	}

	public function accountDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->removeAccount((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	public function accountDefault(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->setDefaultAccount(
			(int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Réordonnancement des comptes secondaires (glisser-déposer). */
	public function accountOrder(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->auth()->reorderAccounts(
			(int) $_SESSION['user_id'],
			array_map('intval', (array) $request->input('ids', [])));
		return JsonResponse::ok();
	}
}
