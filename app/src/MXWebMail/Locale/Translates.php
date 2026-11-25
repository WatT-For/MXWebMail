<?php
declare(strict_types=1);

namespace MXWebMail\Locale;

/**
 * Helper t() partagé - remplace les copies identiques de
 * "$this->app->locale()->t()" dans les contrôleurs, services et
 * PluginBase. La classe consommatrice doit exposer $this->app.
 */
trait Translates
{
	protected function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}
}
