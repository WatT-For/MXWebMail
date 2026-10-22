<?php
declare(strict_types=1);

namespace MXWebMail\Security;

/** Vérifie qu'une IP figure dans une liste CSV d'IP exactes ou de CIDR (IPv4/IPv6). */
final class IpMatch
{
	public static function inList(string $ip, string $list): bool
	{
		foreach (explode(',', $list) as $entry) {
			$entry = trim($entry);
			if ($entry === '') {
				continue;
			}
			if (!str_contains($entry, '/')) {
				if (strcasecmp($entry, $ip) === 0) {
					return true;
				}
				continue;
			}
			[$net, $bits] = explode('/', $entry, 2);
			if (self::inCidr($ip, $net, (int) $bits)) {
				return true;
			}
		}
		return false;
	}

	private static function inCidr(string $ip, string $net, int $bits): bool
	{
		$ipBin = @inet_pton($ip);
		$netBin = @inet_pton($net);
		if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
			return false;
		}
		$max = strlen($ipBin) * 8;
		if ($bits < 0 || $bits > $max) {
			return false;
		}
		$bytes = intdiv($bits, 8);
		$rest = $bits % 8;
		if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
			return false;
		}
		if ($rest > 0) {
			$mask = 0xFF << (8 - $rest) & 0xFF;
			return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
		}
		return true;
	}
}
