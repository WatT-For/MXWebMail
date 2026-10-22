<?php
declare(strict_types=1);

namespace MXWebMail\Security;

/**
 * Levée quand un code TOTP est requis ou invalide au login (hook
 * auth.post-login). Le code d'erreur stable « totp_required » est exposé
 * au client, qui ne doit pas analyser le message localisé.
 */
final class TotpRequiredException extends \RuntimeException {}
