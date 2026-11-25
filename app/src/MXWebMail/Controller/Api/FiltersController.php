<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\UserFacingException;




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
		$this->requireWrite();
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
				throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'rules'])
						. ' - ' . $e->getMessage(), 400, jsonCode: 'validation');
			}
		} else {
			$source = (string) $request->input('source', '');
			if (trim($source) === '') {
				throw new UserFacingException($this->t('errors.empty_script'), jsonCode: 'empty');
			}
		}
		$name = $this->noCrlf((string) $request->input('name'), 'name');
		if (trim($name) === '') {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'name']), 400, jsonCode: 'validation');
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
		$this->requireWrite();
		try {
			$rules = (array) $request->input('rules', []);
			$source = \MXWebMail\Sieve\ScriptBuilder::compile(
				array_is_list($rules) ? ['rules' => $rules] : $rules,
				$this->app->locale());
		} catch (\InvalidArgumentException $e) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'rules'])
					. ' - ' . $e->getMessage(), 400, jsonCode: 'validation');
		}
		return JsonResponse::ok(['source' => $source]);
	}

	public function filterActivate(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->mail()->filterActivate($this->noCrlf((string) $request->input('name'), 'name'));
		return JsonResponse::ok();
	}

	public function filterDeactivate(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->mail()->filterDeactivate();
		return JsonResponse::ok();
	}

	public function filterDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
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
		$this->requireWrite();
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
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'vacation']), 400, jsonCode: 'validation');
		}
		foreach ([$from, $to] as $d) {
			if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
				throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'vacation']), 400, jsonCode: 'validation');
			}
		}
		if ($from !== '' && $to !== '' && $from > $to) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'vacation']), 400, jsonCode: 'validation');
		}
		if ($enabled && trim($message) === '') {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'vacation']), 400, jsonCode: 'validation');
		}
		// :addresses ⊆ adresses du compte + identités déclarées (sinon le
		// répondeur partirait pour des courriers adressés à d'autres).
		$allowed = [mb_strtolower($this->app->sessionEmail())];
		foreach ($this->app->identities()->allForUser($this->app->sessionUserId()) as $i) {
			$allowed[] = mb_strtolower((string) $i['email']);
		}
		$addresses = array_values(array_intersect(
			array_map('mb_strtolower', $addresses), array_unique($allowed)));
		if (!$addresses) {
			$addresses = [mb_strtolower($this->app->sessionEmail())];
		}
		$this->app->mail()->vacationSave([
			'enabled' => $enabled, 'subject' => $subject, 'message' => $message,
			'days' => $days, 'from' => $from, 'to' => $to, 'addresses' => $addresses,
		]);
		return JsonResponse::ok();
	}
}
