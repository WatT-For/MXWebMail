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
