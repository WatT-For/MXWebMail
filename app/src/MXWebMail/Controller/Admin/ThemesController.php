<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;




final class ThemesController extends BaseController
{
	public function themes(Request $request): JsonResponse
	{
		$this->requireAdmin();
		return JsonResponse::ok([
			'default' => $this->app->themes()->defaultTheme(),
			'items'   => $this->app->themes()->details(),
		]);
	}

	public function themeDefaultSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		try {
			(new \MXWebMail\Config\ConfigWriter($this->app->rootDir()))
				->write(['user_defaults' => ['theme' => $name]], $this->app->config());
		} catch (\Throwable) {
			return JsonResponse::error($this->t('errors.config_write_failed'), 'config_write', 500);
		}
		return JsonResponse::ok();
	}

	public function themeAllowedSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)
			|| $name === $this->app->themes()->defaultTheme()) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$this->app->themeRepo()->setAllowed($name, (bool) $request->input('allowed', true));
		return JsonResponse::ok();
	}

	public function themeConfigGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		return JsonResponse::ok([
			'schema' => $this->app->themes()->schema($name),
			'values' => $this->app->themes()->config($name),
		]);
	}

	public function themeConfigSet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$this->requireCsrf();
		$name = (string) $request->params['name'];
		if (!$this->app->themes()->exists($name)) {
			return JsonResponse::error(
				$this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		try {
			$values = $this->app->themes()->validate($name, (array) $request->json());
		} catch (\RuntimeException $e) {
			return JsonResponse::error($e->getMessage(), 'validation', 400);
		}
		$this->app->themeRepo()->setConfig($name, $values);
		return JsonResponse::ok();
	}
}
