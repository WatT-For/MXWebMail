<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;




final class PluginsController extends BaseController
{
	public function plugins(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok($this->app->plugins()->available());
	}

	public function pluginsCatalog(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$force = (bool) $request->input('force', false);
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->catalog($force));
	}

	public function pluginInstall(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->install($name));
	}

	/** Désinstallation définitive : dossier supprimé + ligne DB purgée. */
	public function pluginRemove(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return $this->serviceApi(
			fn() => $this->app->pluginPackages()->remove($name));
	}

	public function pluginToggle(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->pluginRepo()->setEnabled($name, (bool) $request->input('enabled', false));
		return JsonResponse::ok();
	}

	/** "Utilisable par les utilisateurs" : sinon chargé uniquement en contexte admin. */
	public function pluginAllowedSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->pluginRepo()->setUserAllowed($name, (bool) $request->input('allowed', false));
		return JsonResponse::ok();
	}

	public function pluginConfigGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		// Le schéma sur disque décrit les champs ; les valeurs sont en base
		return JsonResponse::ok([
			'schema' => $this->pluginSchema($name),
			'values' => $this->app->pluginRepo()->getConfig($name),
		]);
	}

	public function pluginConfigSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = self::pluginName($request->params['name'] ?? '');
		if ($name === null) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		// Validation par le schéma : postée > stockée > default ; clés hors
		// schéma ignorées ; sans schéma, rien à stocker.
		$posted = (array) $request->json();
		$stored = $this->app->pluginRepo()->getConfig($name);
		$out = [];
		foreach ($this->pluginSchema($name) as $key => $def) {
			$raw = array_key_exists($key, $posted)
				? $posted[$key]
				: ($stored[$key] ?? (is_array($def) ? ($def['default'] ?? '') : ''));
			try {
				$out[$key] = $this->coercePluginValue($key, $raw, is_array($def) ? $def : []);
			} catch (\RuntimeException) {
				return JsonResponse::error(
					$this->t('errors.invalid_value', ['key' => $key]), 'validation', 400);
			}
		}
		$this->app->pluginRepo()->setConfig($name, $out);
		return JsonResponse::ok();
	}

	/** Schéma de config d'un plugin : config.schema.json, plat {clé: def}. */
	private function pluginSchema(string $name): array
	{
		$file = $this->app->rootDir() . "/plugins/{$name}/config.schema.json";
		return is_file($file)
			? (json_decode((string) file_get_contents($file), true) ?: [])
			: [];
	}

	private static function pluginName(mixed $name): ?string
	{
		$name = (string) $name;
		return preg_match('/^[a-z0-9\-]+$/i', $name) ? $name : null;
	}

	private function coercePluginValue(string $key, mixed $v, array $def): mixed
	{
		$fail = fn() => throw new \RuntimeException(
			$this->t('errors.invalid_value', ['key' => $key]), 400);
		$type = $def['type'] ?? 'string';
		if ($type === 'enum') {
			$opts = (array) ($def['options'] ?? []);
			return array_key_exists((string) $v, $opts) || in_array((string) $v, $opts, true)
				? (string) $v : $fail();
		}
		return match ($type) {
			'bool' => (int) filter_var($v, FILTER_VALIDATE_BOOL),
			// ''/null accepté : champ vidé = retour au défaut/repli du plugin
			'color' => $v === '' || $v === null ? ''
				: (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)
					? strtolower($v) : $fail()),
			'integer' => self::intRange($v, PHP_INT_MIN, PHP_INT_MAX) ?? $fail(),
			'number' => is_numeric($v) ? (float) $v : $fail(),
			'list' => is_array($v)
				? implode(', ', array_filter(array_map('trim', array_map('strval', $v))))
				: self::str($v, 8192) ?? $fail(),
			default => self::str($v, 4096) ?? $fail(),
		};
	}
}
