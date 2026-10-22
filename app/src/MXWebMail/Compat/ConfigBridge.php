<?php
declare(strict_types=1);

namespace MXWebMail\Compat;

use MXWebMail\Config\Config;

/** Compat MailSo : Get('ssl', 'verify_certificate', true) → section[key] puis «section_key» à plat. */
final class ConfigBridge
{
	public function __construct(private Config $config) {}

	public function Get(string $section, string $key, mixed $default = null): mixed
	{
		$sec = $this->config->get($section);
		if (is_array($sec) && array_key_exists($key, $sec)) {
			return $sec[$key];
		}
		return $this->config->get("{$section}_{$key}", $default);
	}
}
