<?php
declare(strict_types=1);

namespace MXWebMail\Http;

final class Request
{
	public string $method;
	public string $path;
	public array $query;
	public array $post;
	public array $files;
	public array $server;
	private ?array $jsonBody = null;
	public array $params = []; // paramètres de route {name}

	public static function fromGlobals(): self
	{
		$r = new self();
		$r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
		$r->query = $_GET;
		$r->post = $_POST;
		$r->files = $_FILES;
		$r->server = $_SERVER;

		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		$path = parse_url($uri, PHP_URL_PATH) ?: '/';
		// Retire le préfixe du sous-répertoire si l'app n'est pas à la racine du vhost
		$base = self::basePath();
		if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
			$path = substr($path, strlen($base)) ?: '/';
		}
		$r->path = '/' . trim($path, '/');
		if ($r->path === '//') { $r->path = '/'; }
		return $r;
	}

	/**
	 * Chemin de base de l'application tel que perçu par le navigateur
	 * ('' si servie à la racine du vhost, '/sous/dossier' sinon).
	 * Le front controller vit dans public/ ; la réécriture racine
	 * (RewriteRule -> public/) expose ce segment dans SCRIPT_NAME - on le
	 * retire pour que l'URL publique n'en porte pas trace.
	 */
	public static function basePath(): string
	{
		$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
		$base = rtrim($base, '/');
		if (str_ends_with($base, '/public')) {
			$base = substr($base, 0, -7);
		}
		return $base;
	}

	/**
	 * Entrée $_FILES validée : error = UPLOAD_ERR_OK + is_uploaded_file.
	 * null si absente ou invalide - l'appelant choisit son erreur métier.
	 */
	public function upload(string $name): ?array
	{
		$f = $this->files[$name] ?? null;
		return is_array($f)
			&& (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
			&& is_uploaded_file((string) ($f['tmp_name'] ?? ''))
			? $f : null;
	}

	/** Corps JSON décodé (pour les appels fetch avec content-type json). */
	public function json(): array
	{
		if ($this->jsonBody === null) {
			$raw = file_get_contents('php://input');
			$decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
			// JSON scalaire (123, "x", true) ou malformé > objet vide, pas TypeError.
			$this->jsonBody = is_array($decoded) ? $decoded : [];
		}
		return $this->jsonBody;
	}

	/** Entrée unifiée : JSON > POST > query. */
	public function input(string $key, mixed $default = null): mixed
	{
		return $this->json()[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
	}

	public function header(string $name): ?string
	{
		$key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
		return $this->server[$key] ?? null;
	}
}
