<?php
declare(strict_types=1);

namespace MXWebMail\Compat;

use MXWebMail\Plugin\HookRegistry;

final class PluginsBridge
{
	public function __construct(private HookRegistry $hooks) {}

	/** Les args peuvent contenir des références (ex. [&$headers]) - call_user_func_array les préserve. */
	public function RunHook(string $name, array $args = []): void
	{
		$this->hooks->runArgs($name, $args);
	}
}
