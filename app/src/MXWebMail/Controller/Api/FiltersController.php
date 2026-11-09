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




final class FiltersController extends BaseController
{
	public function filters(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->filtersList());
	}

	public function filterGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$name = $this->noCrlf((string) $request->input('name'), 'name');
		return JsonResponse::ok([
			'name'   => $name,
			'source' => $this->app->mail()->filterGet($name),
		]);
	}

	public function filterSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Deux formats : {name, source} (mode source) ou {name, rules}
		// (assistant - compilation serveur via ScriptBuilder ; rules =
		// doc complet ou simple tableau de règles).
		if (is_array($rules = $request->input('rules'))) {
			try {
				$doc = array_is_list($rules) ? ['rules' => $rules] : $rules;
				// Réinjecte le bloc "vacation" du script existant : l'assistant
				// n'édite que les règles - sans merge, la sauvegarde d'un filtre
				// éteindrait un répondeur actif.
				$existing = $this->noCrlf((string) ($request->input('old_name') ?: $request->input('name', '')), 'name');
				if ($existing !== '') {
					try {
						$prev = \MXWebMail\Sieve\ScriptBuilder::parseMarker(
							$this->app->mail()->filterGet($existing));
						if (isset($prev['vacation'])) {
							$doc['vacation'] = $prev['vacation'];
						}
					} catch (\Throwable) {}
				}
				$source = \MXWebMail\Sieve\ScriptBuilder::compile($doc, $this->app->locale());
			} catch (\InvalidArgumentException $e) {
				return JsonResponse::error(
					$this->t('errors.invalid_value', ['key' => 'rules'])
						. ' - ' . $e->getMessage(), 'validation', 400);
			}
		} else {
			$source = (string) $request->input('source', '');
			if (trim($source) === '') {
				return JsonResponse::error($this->t('errors.empty_script'), 'empty');
			}
		}
		$name = $this->noCrlf((string) $request->input('name'), 'name');
		if (trim($name) === '') {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$oldName = $this->noCrlf((string) $request->input('old_name', ''), 'old_name');
		// Vérif syntaxique côté serveur Sieve avant PUT
		$this->app->mail()->filterCheck($source);
		$this->app->mail()->filterSave($name, $source, $oldName !== '' ? $oldName : null);
		return JsonResponse::ok();
	}

	/** Prévisualisation de la source générée par l'assistant (rien d'écrit). */
	public function filterCompile(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		try {
			$rules = (array) $request->input('rules', []);
			$source = \MXWebMail\Sieve\ScriptBuilder::compile(
				array_is_list($rules) ? ['rules' => $rules] : $rules,
				$this->app->locale());
		} catch (\InvalidArgumentException $e) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'rules'])
					. ' - ' . $e->getMessage(), 'validation', 400);
		}
		return JsonResponse::ok(['source' => $source]);
	}

	public function filterActivate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterActivate($this->noCrlf((string) $request->input('name'), 'name'));
		return JsonResponse::ok();
	}

	public function filterDeactivate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterDeactivate();
		return JsonResponse::ok();
	}

	public function filterDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->filterDelete($this->noCrlf((string) $request->input('name'), 'name'));
		return JsonResponse::ok();
	}

	public function vacationGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->vacationGet());
	}

	public function vacationSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$v = (array) $request->json();
		$from = trim((string) ($v['from'] ?? ''));
		$to = trim((string) ($v['to'] ?? ''));
		$days = filter_var($v['days'] ?? 1, FILTER_VALIDATE_INT);
		$subject = $this->noCrlf(trim((string) ($v['subject'] ?? '')), 'subject');
		$message = (string) ($v['message'] ?? '');
		$enabled = (bool) ($v['enabled'] ?? false);
		$addresses = array_values(array_filter(array_map('trim',
			(array) ($v['addresses'] ?? []))));
		if ($days === false || $days < 1 || $days > 30 || strlen($subject) > 200
			|| strlen($message) > 8192) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		foreach ([$from, $to] as $d) {
			if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
			}
		}
		if ($from !== '' && $to !== '' && $from > $to) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		if ($enabled && trim($message) === '') {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'vacation']), 'validation', 400);
		}
		// :addresses ⊆ adresses du compte + identités déclarées (sinon le
		// répondeur partirait pour des courriers adressés à d'autres).
		$allowed = [mb_strtolower((string) $_SESSION['email'])];
		foreach ($this->app->identities()->allForUser((int) $_SESSION['user_id']) as $i) {
			$allowed[] = mb_strtolower((string) $i['email']);
		}
		$addresses = array_values(array_intersect(
			array_map('mb_strtolower', $addresses), array_unique($allowed)));
		if (!$addresses) {
			$addresses = [mb_strtolower((string) $_SESSION['email'])];
		}
		$this->app->mail()->vacationSave([
			'enabled' => $enabled, 'subject' => $subject, 'message' => $message,
			'days' => $days, 'from' => $from, 'to' => $to, 'addresses' => $addresses,
		]);
		return JsonResponse::ok();
	}
}
