<?php
declare(strict_types=1);

namespace MXWebMail;

/**
 * Exception dont le message est déjà traduit et sûr à exposer à
 * l'utilisateur - le dispatcher l'affiche quel que soit le statut HTTP,
 * contrairement au masquage "errors.internal" des 5xx.
 */
class UserFacingException extends \RuntimeException {}
