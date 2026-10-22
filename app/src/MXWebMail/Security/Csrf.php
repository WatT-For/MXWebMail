<?php
declare(strict_types=1);

namespace MXWebMail\Security;

final class Csrf
{
	private const SESSION_KEY = 'csrf_token';

	public static function token(): string
	{
		if (empty($_SESSION[self::SESSION_KEY])) {
			$_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
		}
		return $_SESSION[self::SESSION_KEY];
	}

	public static function check(?string $token): bool
	{
		return is_string($token)
			&& isset($_SESSION[self::SESSION_KEY])
			&& hash_equals($_SESSION[self::SESSION_KEY], $token);
	}

	/** Vérifie le token envoyé en header X-CSRF-Token ou en champ csrf_token. */
	public static function verifyRequest(): bool
	{
		$token = $_SERVER['HTTP_X_CSRF_TOKEN']
			?? $_POST['csrf_token']
			?? null;
		if ($token === null) {
			$json = json_decode(file_get_contents('php://input') ?: '', true);
			$token = $json['csrf_token'] ?? null;
		}
		return self::check($token);
	}
}
