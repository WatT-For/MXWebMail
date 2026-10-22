<?php
declare(strict_types=1);

namespace MXWebMail\Http;

/**
 * Router minimal : routes déclarées "METHOD /path/{param}".
 * Les paramètres {param} sont injectés dans $request->params.
 */
final class Router
{
	private array $routes = [];

	public function add(string $method, string $pattern, callable $handler): void
	{
		$this->routes[] = [
			'method'  => strtoupper($method),
			'pattern' => $pattern,
			'handler' => $handler,
		];
	}

	public function get(string $pattern, callable $handler): void
	{
		$this->add('GET', $pattern, $handler);
	}

	public function post(string $pattern, callable $handler): void
	{
		$this->add('POST', $pattern, $handler);
	}

	public function dispatch(Request $request): ?Response
	{
		foreach ($this->routes as $route) {
			if ($route['method'] !== $request->method) {
				continue;
			}
			$params = self::match($route['pattern'], $request->path);
			if ($params === null) {
				continue;
			}
			$request->params = $params;
			return ($route['handler'])($request);
		}
		return null;
	}

	private static function match(string $pattern, string $path): ?array
	{
		// Échappe tout sauf les {param} convertis en groupes nommés
		$regex = preg_replace_callback(
			'#\{([a-zA-Z_][a-zA-Z0-9_]*)\}|(.)#s',
			fn($m) => isset($m[1]) && $m[1] !== '' ? '(?P<' . $m[1] . '>[^/]+)' : preg_quote($m[2], '#'),
			$pattern
		);

		if (!preg_match('#^' . $regex . '$#', $path, $m)) {
			return null;
		}
		return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
	}
}
