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
		$this->values = self::mergeDeep($defaults, is_array($local) ? $local : []);
	}

	/**
	 * Merge profond sur les tableaux associatifs (user_defaults, ssl, logs…) :
	 * une surcharge partielle n'écrase que ses propres clés. Les listes sont
	 * remplacées, comme avec un array_merge classique. Source unique aussi
	 * utilisée par ConfigWriter (le save admin doit produire la même
	 * structure que le load - merge shallow y écraserait un sous-niveau).
	 */
	public static function mergeDeep(array $base, array $over): array
	{
		foreach ($over as $k => $v) {
			$base[$k] = (is_array($v) && is_array($base[$k] ?? null)
				&& !array_is_list($v) && !array_is_list($base[$k]))
				? self::mergeDeep($base[$k], $v)
				: $v;
		}
		return $base;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->values[$key] ?? $default;
	}

	/** Section de config normalisée en tableau ([] pour une valeur non tableau). */
	public function section(string $key): array
	{
		$v = $this->get($key, []);
		return is_array($v) ? $v : [];
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
