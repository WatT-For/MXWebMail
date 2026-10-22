<?php

namespace MXWebMailLib;

/**
 * Have I Been Pwned — vérification k-anonymity des mots de passe.
 * Seuls les 5 premiers caractères du SHA-1 partent sur le réseau ;
 * le suffixe est comparé localement. https://haveibeenpwned.com/API/v3
 */
abstract class Hibp
{
	/** Retourne le nombre d'occurrences du mot de passe dans les fuites (0 = sain). */
	public static function password(
		#[\SensitiveParameter]
		string $password
	) : int
	{
		$hash = \strtoupper(\sha1($password));
		$prefix = \substr($hash, 0, 5);
		$suffix = \substr($hash, 5);
		$response = HTTP\Request::factory()->doRequest(
			'GET', "https://api.pwnedpasswords.com/range/{$prefix}");
		if (!$response || 200 !== $response->status) {
			throw new \RuntimeException('HIBP injoignable (' . ($response->status ?? 'timeout') . ')', 502);
		}
		foreach (\preg_split('/\R/', (string) $response->body) as $entry) {
			if ($entry !== '') {
				[$suf, $count] = \explode(':', $entry, 2) + [1 => 0];
				if ($suf === $suffix) {
					return (int) $count;
				}
			}
		}
		return 0;
	}
}
