<?php
declare(strict_types=1);

namespace MXWebMail\Config;

final class Config
{
	private array $values;

	public function __construct(string $rootDir)
	{
		$defaults = require $rootDir . '/config/config.example.php';
		$local = is_file($rootDir . '/config/config.php')
			? require $rootDir . '/config/config.php'
			: [];
		// Merge profond sur les tableaux associatifs (user_defaults, ssl, logs…) :
		// une surcharge partielle dans config.php n'écrase que ses propres clés.
		// Les listes sont remplacées, comme avec un array_merge classique.
		$merge = function (array $base, array $over) use (&$merge): array {
			foreach ($over as $k => $v) {
				$base[$k] = (is_array($v) && is_array($base[$k] ?? null)
					&& !array_is_list($v) && !array_is_list($base[$k]))
					? $merge($base[$k], $v)
					: $v;
			}
			return $base;
		};
		$this->values = $merge($defaults, is_array($local) ? $local : []);
	}

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->values[$key] ?? $default;
	}

	public function set(string $key, mixed $value): void
	{
		$this->values[$key] = $value;
	}

	public function all(): array
	{
		return $this->values;
	}

	public function isDebug(): bool
	{
		return (bool) $this->get('debug', false);
	}
}
