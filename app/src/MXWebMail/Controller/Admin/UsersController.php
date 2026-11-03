<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;




final class UsersController extends BaseController
{
	public function users(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->users()->all());
	}

	/** Détail d'un compte : profil, réglages effectifs, comptes IMAP, identités. */
	public function userGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$u = $this->userOr404($request);
		$id = (int) $u['id'];
		// Valeurs effectives : réglages stockés + défauts user_defaults,
		// jamais les clés internes "plugin.*" (même filtre que /api/settings).
		$values = $this->app->settings()->allFor($id);
		foreach ((array) $this->app->config()->get('user_defaults', []) as $key => $v) {
			$values[$key] ??= $v;
		}
		// Template stocké devenu non utilisable (désactivé/supprimé) > défaut global
		if (!is_string($values['theme'] ?? null)
			|| !$this->app->themes()->isSelectable($values['theme'])) {
			$values['theme'] = $this->app->themes()->defaultTheme();
		}
		$values = array_filter($values,
			fn(string $k) => !str_starts_with($k, 'plugin.'), ARRAY_FILTER_USE_KEY);
		return JsonResponse::ok([
			'user'       => $u,
			'settings'   => ['schema' => $this->userSettingsSchema(), 'values' => $values],
			'accounts'   => $this->app->auth()->accounts($id), // jamais SELECT * (imap_pass)
			'identities' => $this->app->identities()->allForUser($id),
		]);
	}

	public function userSave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$data = (array) $request->json();
		// Clé absente = inchangée (évite qu'un POST partiel rétrograde/débloque)
		$isAdmin = (bool) ($data['is_admin'] ?? $u['is_admin']);
		$disabled = (bool) ($data['disabled'] ?? $u['disabled'] ?? 0);
		$this->guardSelf($u, !$isAdmin, $disabled, false);
		$name = self::str($data['display_name'] ?? $u['display_name'] ?? '', 255);
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'display_name']), 'validation', 400);
		}
		$this->app->users()->save((int) $u['id'], [
			'display_name' => $name,
			'is_admin'     => $isAdmin,
			'disabled'     => $disabled,
		]);
		return JsonResponse::ok();
	}

	public function userDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->guardSelf($u, false, false, true);
		// FK ON DELETE CASCADE : comptes, identités, réglages, clés PGP ;
		// carnet d'adresses : fichier storage/db/users/<id>.sqlite supprimé
		$this->app->users()->delete((int) $u['id']);
		$this->app->userDb()->deleteFor((int) $u['id']);
		$this->app->log('warning', "Utilisateur supprimé par admin : {$u['username']}");
		return JsonResponse::ok();
	}

	public function userSettingsSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$allowed = $this->app->allowedUserSettingKeys();
		foreach ((array) $request->json() as $key => $value) {
			$key = (string) $key;
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			// Mêmes règles que les défauts globaux (App::userSettingRules) -
			// valeur hors bornes = ignorée, jamais stockée.
			[$ok, $v] = $this->app->checkUserSetting($key, $value);
			if ($ok) {
				$this->app->settings()->set((int) $u['id'], $key, $v);
			}
		}
		return JsonResponse::ok();
	}

	public function userSettingsReset(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->settings()->deleteAllFor((int) $u['id']);
		return JsonResponse::ok();
	}

	public function userAccountDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$stmt = $this->app->db()->pdo()->prepare(
			'DELETE FROM accounts WHERE id = :id AND user_id = :u AND is_default = 0'
		);
		$stmt->execute(['id' => (int) $request->input('account_id'), 'u' => (int) $u['id']]);
		if (!$stmt->rowCount()) {
			return JsonResponse::error($this->t('errors.account_not_found_or_main'), 'not_found', 404);
		}
		return JsonResponse::ok();
	}

	public function userIdentitySave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		try {
			$id = $this->app->identities()->save((int) $u['id'], (array) $request->json());
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error($this->t('errors.invalid_identity'), 'validation', 400);
		} catch (\Throwable $e) {
			$code = $e->getCode();
			return JsonResponse::error($e->getMessage(), 'validation',
				($code >= 400 && $code < 600) ? $code : 400);
		}
		return JsonResponse::ok(['id' => $id]);
	}

	public function userIdentityDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->identities()->delete((int) $u['id'], (int) $request->input('identity_id'));
		return JsonResponse::ok();
	}

	public function userIdentityDefault(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$u = $this->userOr404($request);
		$this->app->identities()->setDefault((int) $u['id'], (int) $request->input('identity_id'));
		return JsonResponse::ok();
	}

	/** Utilisateur cible de la route {id} ; 404 si inconnu. */
	private function userOr404(Request $request): array
	{
		$u = $this->app->users()->findById((int) ($request->params['id'] ?? 0));
		if (!$u) {
			throw new \RuntimeException($this->t('errors.account_not_found'), 404);
		}
		return $u;
	}

	/**
	 * Un admin IMAP (session = user_id) ne peut ni se rétrograder, ni se
	 * bloquer, ni se supprimer - sinon il se coupe l'accès au panel.
	 */
	private function guardSelf(array $target, bool $demote, bool $disable, bool $delete): void
	{
		if (is_int($_SESSION['admin'] ?? null)
			&& (int) $target['id'] === $_SESSION['admin']
			&& ($demote || $disable || $delete)) {
			throw new \RuntimeException($this->t('errors.admin_self'), 400);
		}
	}

}
