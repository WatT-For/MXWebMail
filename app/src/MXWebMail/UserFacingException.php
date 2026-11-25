<?php
declare(strict_types=1);

namespace MXWebMail;

/**
 * Exception dont le message est déjà traduit et sûr à exposer à
 * l'utilisateur - le dispatcher l'affiche quel que soit le statut HTTP,
 * contrairement au masquage "errors.internal" des 5xx.
 *
 * Convention contrôleurs : toute erreur métier remonte par `throw` ici
 * plutôt que `return JsonResponse::error()` - le code sémantique de
 * error.code voyage dans l'exception ($jsonCode), le statut HTTP dans
 * getCode().
 */
class UserFacingException extends \RuntimeException
{
	public function __construct(
		string $message,
		int $status = 400,
		?\Throwable $previous = null,
		private readonly string $jsonCode = 'error',
	) {
		parent::__construct($message, $status, $previous);
	}

	/** Code sémantique exposé dans error.code de la réponse JSON. */
	public function jsonCode(): string
	{
		return $this->jsonCode;
	}
}
