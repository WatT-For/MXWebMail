<?php
declare(strict_types=1);

namespace MXWebMail\Http;

class Response
{
	public function __construct(
		protected string $body = '',
		protected int $status = 200,
		protected array $headers = []
	) {}

	public function send(): void
	{
		http_response_code($this->status);
		foreach ($this->headers as $name => $value) {
			header("{$name}: {$value}");
		}
		echo $this->body;
	}

	/**
	 * Compresse le corps en gzip si le type le permet. Pose toujours
	 * « Vary: Accept-Encoding » dès que le type est compressible (même si le
	 * corps est trop petit ou le client ne l'accepte pas) — un cache partagé
	 * doit distinguer les variantes. Retourne true si le corps a été compressé.
	 */
	public function gzip(int $level, int $minSize, bool $accepted): bool
	{
		if ($this->status >= 300 || isset($this->headers['Content-Encoding'])) {
			return false;
		}
		$type = (string) ($this->headers['Content-Type'] ?? '');
		$compressible = str_starts_with($type, 'text/')
			|| (bool) preg_match('/json|javascript|xml|svg/i', $type);
		if (!$compressible) {
			return false;
		}
		$vary = (string) ($this->headers['Vary'] ?? '');
		if (stripos($vary, 'Accept-Encoding') === false) {
			$this->headers['Vary'] = trim($vary . ($vary !== '' ? ', ' : '') . 'Accept-Encoding');
		}
		if (!$accepted || strlen($this->body) < $minSize) {
			return false;
		}
		$compressed = gzencode($this->body, $level);
		if ($compressed === false) {
			return false;
		}
		$this->body = $compressed;
		$this->headers['Content-Encoding'] = 'gzip';
		return true;
	}

	public static function html(string $body, int $status = 200): self
	{
		return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
	}

	public static function redirect(string $location): self
	{
		return new self('', 302, ['Location' => $location]);
	}

	public static function notFound(): self
	{
		return new self('404 — Page introuvable', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
	}
}
