<?php
declare(strict_types=1);

namespace MXWebMail\Http;

final class JsonResponse extends Response
{
	public function __construct(array $payload, int $status = 200)
	{
		parent::__construct(
			// UTF-8 invalide > U+FFFD plutôt que false ; le reste des erreurs
			// (récursion, type non sérialisable) lève JsonException > 500 propre.
			json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				| JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
			$status,
			['Content-Type' => 'application/json; charset=utf-8']
		);
	}

	public static function ok(mixed $data = []): self
	{
		return new self(['ok' => true, 'data' => $data]);
	}

	public static function error(string $message, string $code = 'error', int $status = 400): self
	{
		return new self(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
	}
}
