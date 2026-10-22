<?php
declare(strict_types=1);

namespace MXWebMail\Security;

/**
 * Correspondance sur liste de motifs avec wildcard.
 * Format : valeurs séparées par espaces/virgules/point-virgules,
 * « * » = n'importe quelle séquence (ex. *@domain.tld, user*@x.tld).
 * Portage de MXWebMail\Plugins\Helper::ValidateWildcardValues (legacy).
 */
final class Wildcard
{
	public static function match(string $value, string $patterns, ?string &$matched = null): bool
	{
		$matched = null;
		$value = trim($value);
		if ($value === '') {
			return false;
		}
		$patterns = trim($patterns);
		if ($patterns === '' || $patterns === '*') {
			return true;
		}
		foreach (preg_split('/[\s,;]+/', preg_replace('/\*+/', '*', $patterns)) ?: [] as $item) {
			if ($item === '') {
				continue;
			}
			if (!str_contains($item, '*')) {
				if (strcasecmp($value, $item) === 0) {
					$matched = $item;
					return true;
				}
				continue;
			}
			$regex = '/^' . implode('.*', array_map(
				fn(string $p) => preg_quote($p, '/'),
				explode('*', $item)
			)) . '$/i';
			if (preg_match($regex, $value)) {
				$matched = $item;
				return true;
			}
		}
		return false;
	}
}
