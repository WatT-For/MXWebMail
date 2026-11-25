<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\UserFacingException;




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
		$this->requireAdminWrite();
		$name = $this->requireThemeName($request);
		$w = $this->writeConfig(['user_defaults' => ['theme' => $name]]);
		return JsonResponse::ok();
	}

	public function themeAllowedSet(Request $request): JsonResponse
	{
		$this->requireAdminWrite();
		$name = $this->requireThemeName($request);
		if ($name === $this->app->themes()->defaultTheme()) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'name']), 400, jsonCode: 'validation');
		}
		$this->app->themeRepo()->setAllowed($name, (bool) $request->input('allowed', true));
		return JsonResponse::ok();
	}

	public function themeConfigGet(Request $request): JsonResponse
	{
		$this->requireAdmin();
		$name = $this->requireThemeName($request);
		return JsonResponse::ok([
			'schema' => $this->app->themes()->schema($name),
			'values' => $this->app->themes()->config($name),
		]);
	}

	public function themeConfigSet(Request $request): JsonResponse
	{
		$this->requireAdminWrite();
		$name = $this->requireThemeName($request);
		try {
			$values = $this->app->themes()->validate($name, (array) $request->json());
		} catch (\RuntimeException $e) {
			throw new UserFacingException($e->getMessage(), 400, jsonCode: 'validation');
		}
		$this->app->themeRepo()->setConfig($name, $values);
		return JsonResponse::ok();
	}
}
