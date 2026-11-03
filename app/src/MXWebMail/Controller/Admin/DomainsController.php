<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;




final class DomainsController extends BaseController
{
	public function domains(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->domains()->all());
	}

	public function domainSave(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$data = (array) $request->json();
		if (empty($data['name']) || empty($data['imap_host']) || empty($data['smtp_host'])) {
			return JsonResponse::error($this->t('errors.domain_required'), 'validation');
		}
		$bad = fn(string $key) => JsonResponse::error(
			$this->t('errors.invalid_value', ['key' => $key]), 'validation', 400);
		$name = (string) $data['name'];
		// '*' = domaine catch-all (paramètres pour tout domaine non listé)
		if ($name !== '*'
			&& !preg_match('/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/i', $name)) {
			return $bad('name');
		}
		foreach (['imap_host', 'smtp_host', 'sieve_host'] as $key) {
			$data[$key] = self::str($data[$key] ?? '', 255);
			if ($data[$key] === null || ($key !== 'sieve_host' && $data[$key] === '')) {
				return $bad($key);
			}
		}
		foreach (['imap_port', 'smtp_port'] as $key) {
			$data[$key] = self::intRange($data[$key] ?? 0, 1, 65535);
			if ($data[$key] === null) {
				return $bad($key);
			}
		}
		$data['sieve_port'] = self::intRange($data['sieve_port'] ?? 4190, 1, 65535);
		if ($data['sieve_port'] === null) {
			return $bad('sieve_port');
		}
		foreach (['imap_encryption', 'smtp_encryption'] as $key) {
			if (!in_array($data[$key] ?? '', ['ssl', 'tls', 'none'], true)) {
				return $bad($key);
			}
		}
		$data['active'] = (int) filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOL);
		$id = $this->app->domains()->save($data);
		return JsonResponse::ok(['id' => $id]);
	}

	public function domainDelete(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$this->app->domains()->delete((int) $request->input('id'));
		return JsonResponse::ok();
	}

	/**
	 * Détection explicite de la config d'un domaine (bouton "Détecter" du
	 * formulaire domaine). Indépendant de autodetect.enabled - action admin.
	 */
	public function domainDetect(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = strtolower(trim((string) $request->input('name', '')));
		if ($name === '' || $name === '*') {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$detected = $this->app->domainDetector()->detect($name, true);
		if (!$detected) {
			return JsonResponse::error($this->t('errors.domain_detect_failed'), 'detect_failed');
		}
		return JsonResponse::ok($detected);
	}
}
