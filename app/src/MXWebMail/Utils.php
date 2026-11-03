<?php
declare(strict_types=1);

namespace MXWebMail;

/**
 * Façade de compatibilité pour le code vendoré (ex-RainLoop\Utils).
 * Appelée par MXWebMailLib\HTTP\Stream::JSON() et MXWebMailLib\GPG\Base::findBinary().
 * Toute nouvelle fonctionnalité ne doit PAS utiliser cette façade.
 */
final class Utils
{
	public static function jsonEncode(mixed $data): string
	{
		return \json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
	}

	public static function inOpenBasedir(string $path): bool
	{
		$base = \ini_get('open_basedir');
		if (!$base) {
			return true;
		}
		$path = \strtolower(\str_replace('\\', '/', $path));
		foreach (\explode(\PATH_SEPARATOR, $base) as $dir) {
			$dir = \strtolower(\str_replace('\\', '/', \rtrim($dir, '/\\')));
			// Borne de segment : "/var/wwwevil" ne doit pas matcher "/var/www".
			if ($dir !== '' && ($path === $dir || \str_starts_with($path, $dir . '/'))) {
				return true;
			}
		}
		return false;
	}
}
