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
		// Regex précompilée à l'enregistrement : match() la réévaluait sinon
		// pour chaque route testée (~136 routes × N essais par requête).
		$this->routes[] = [
			'method'  => strtoupper($method),
			'pattern' => $pattern,
			'regex'   => self::compile($pattern),
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
			$params = self::match($route['regex'], $request->path);
			if ($params === null) {
				continue;
			}
			$request->params = $params;
			return ($route['handler'])($request);
		}
		return null;
	}

	/** Compile le motif en regex : {param} → groupe nommé, reste échappé. */
	private static function compile(string $pattern): string
	{
		return '#^' . preg_replace_callback(
			'#\{([a-zA-Z_][a-zA-Z0-9_]*)\}|(.)#s',
			fn($m) => isset($m[1]) && $m[1] !== '' ? '(?P<' . $m[1] . '>[^/]+)' : preg_quote($m[2], '#'),
			$pattern
		) . '$#';
	}

	private static function match(string $regex, string $path): ?array
	{
		if (!preg_match($regex, $path, $m)) {
			return null;
		}
		return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
	}
}
