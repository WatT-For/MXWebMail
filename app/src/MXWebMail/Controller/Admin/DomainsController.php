<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Validation\Rule;
use MXWebMail\UserFacingException;




final class DomainsController extends BaseController
{
	public function domains(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->domains()->all());
	}

	public function domainSave(Request $request): JsonResponse
	{
		$this->requireAdminWrite();
		$data = (array) $request->json();
		if (empty($data['name']) || empty($data['imap_host']) || empty($data['smtp_host'])) {
			throw new UserFacingException($this->t('errors.domain_required'), jsonCode: 'validation');
		}
		$bad = fn(string $key) => throw new UserFacingException(
			$this->t('errors.invalid_value', ['key' => $key]), 400, jsonCode: 'validation');
		$name = (string) $data['name'];
		// '*' = domaine catch-all (paramètres pour tout domaine non listé)
		if ($name !== '*' && !Rule::domain($name)) {
			$bad('name');
		}
		foreach (['imap_host', 'smtp_host', 'sieve_host'] as $key) {
			$data[$key] = Rule::str($data[$key] ?? '', 255);
			if ($data[$key] === null || ($key !== 'sieve_host' && $data[$key] === '')) {
				$bad($key);
			}
		}
		foreach (['imap_port', 'smtp_port'] as $key) {
			$data[$key] = Rule::intRange($data[$key] ?? 0, 1, 65535);
			if ($data[$key] === null) {
				$bad($key);
			}
		}
		$data['sieve_port'] = Rule::intRange($data['sieve_port'] ?? 4190, 1, 65535);
		if ($data['sieve_port'] === null) {
			$bad('sieve_port');
		}
		foreach (['imap_encryption', 'smtp_encryption'] as $key) {
			if (!in_array($data[$key] ?? '', ['ssl', 'tls', 'none'], true)) {
				$bad($key);
			}
		}
		$data['active'] = (int) filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOL);
		$id = $this->app->domains()->save($data);
		return JsonResponse::ok(['id' => $id]);
	}

	public function domainDelete(Request $request): JsonResponse
	{
		$this->requireAdminWrite();
		$this->app->domains()->delete((int) $request->input('id'));
		return JsonResponse::ok();
	}

	/**
	 * Détection explicite de la config d'un domaine (bouton "Détecter" du
	 * formulaire domaine). Indépendant de autodetect.enabled - action admin.
	 */
	public function domainDetect(Request $request): JsonResponse
	{
		$this->requireAdminWrite();
		$name = strtolower(trim((string) $request->input('name', '')));
		if ($name === '' || $name === '*') {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'name']), 400, jsonCode: 'validation');
		}
		$detected = $this->app->domainDetector()->detect($name, true);
		if (!$detected) {
			throw new UserFacingException($this->t('errors.domain_detect_failed'), jsonCode: 'detect_failed');
		}
		return JsonResponse::ok($detected);
	}
}
