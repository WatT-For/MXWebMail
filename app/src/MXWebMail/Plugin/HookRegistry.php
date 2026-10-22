<?php
declare(strict_types=1);

namespace MXWebMail\Plugin;

/**
 * Registre de hooks minimal et borné (~pas la machine SnappyMail).
 * - run(name, ...args)    : appelle tous les listeners (événement)
 * - filter(name, value)   : chaque listener peut transformer la valeur (chaîne)
 * - first(name, ...args)  : retourne le premier résultat non null (provider)
 */
final class HookRegistry
{
	/** @var array<string, callable[]> */
	private array $listeners = [];

	public function on(string $hook, callable $callback): void
	{
		$this->listeners[$hook][] = $callback;
	}

	public function run(string $hook, mixed ...$args): void
	{
		foreach ($this->listeners[$hook] ?? [] as $cb) {
			$cb(...$args);
		}
	}

	/** Variante tableau : préserve les références (compat MailSo RunHook). */
	public function runArgs(string $hook, array $args): void
	{
		foreach ($this->listeners[$hook] ?? [] as $cb) {
			call_user_func_array($cb, $args);
		}
	}

	public function filter(string $hook, mixed $value, mixed ...$args): mixed
	{
		foreach ($this->listeners[$hook] ?? [] as $cb) {
			$value = $cb($value, ...$args);
		}
		return $value;
	}

	public function first(string $hook, mixed ...$args): mixed
	{
		foreach ($this->listeners[$hook] ?? [] as $cb) {
			$result = $cb(...$args);
			if ($result !== null) {
				return $result;
			}
		}
		return null;
	}

	/** Retourne tous les résultats non null des listeners (fusion de providers). */
	public function collect(string $hook, mixed ...$args): array
	{
		$out = [];
		foreach ($this->listeners[$hook] ?? [] as $cb) {
			$result = $cb(...$args);
			if ($result !== null) {
				$out[] = $result;
			}
		}
		return $out;
	}
}
