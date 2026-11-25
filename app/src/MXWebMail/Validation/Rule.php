<?php
declare(strict_types=1);

namespace MXWebMail\Validation;

/**
 * Mini-langage de validation partagé ('bool', 'str', 'key', 'port', 'enc',
 * 'csv_ip', 'csv_domain', 'filename', 'tz', 'enum:a,b', 'int:min:max') :
 * check() non fatal pour les réglages utilisateur (App::checkUserSetting),
 * apply() jetant pour les formulaires admin (Admin\BaseController::validate).
 * Les primitives (str, intRange, csvIp…) sont aussi exposées aux contrôleurs
 * qui valident à la main.
 */
final class Rule
{
	/**
	 * Valide $value contre $rule - [accepté, valeur normalisée].
	 * 'bool' est strict (la chaîne "false" passerait pour true après cast).
	 */
	public static function check(string $rule, mixed $value): array
	{
		return match (true) {
			$rule === 'bool' => [is_bool($value), $value],
			$rule === 'str' => ($v = self::str($value, 255)) !== null
				? [true, $v] : [false, null],
			$rule === 'key' => is_scalar($value)
				&& preg_match('/^[a-z0-9_\-]{1,32}$/', (string) $value)
				? [true, (string) $value] : [false, null],
			$rule === 'port' => ($v = self::intRange($value, 1, 65535)) !== null
				? [true, $v] : [false, null],
			$rule === 'enc' => in_array($value, ['ssl', 'tls', 'none'], true)
				? [true, $value] : [false, null],
			$rule === 'csv_ip' => ($v = self::csvIp($value)) !== null
				? [true, $v] : [false, null],
			$rule === 'csv_domain' => ($v = self::csvDomain($value)) !== null
				? [true, $v] : [false, null],
			$rule === 'filename' => ($v = self::filename($value)) !== null
				? [true, $v] : [false, null],
			$rule === 'tz' => ($v = self::timeZone($value)) !== null
				? [true, $v] : [false, null],
			str_starts_with($rule, 'enum:') => is_string($value)
				&& in_array($value, explode(',', substr($rule, 5)), true)
				? [true, $value] : [false, null],
			str_starts_with($rule, 'int:') => ($v = self::intRange($value,
				...array_map('intval', array_slice(explode(':', $rule), 1)))) !== null
				? [true, $v] : [false, null],
			default => [false, null],
		};
	}

	/** Nom de domaine DNS (labels, 253 octets max) - insensible a la casse. */
	public static function domain(string $name): bool
	{
		return (bool) preg_match(
			'/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/i', $name);
	}

	/** Domaine d'une adresse email (partie apres le dernier @), '' si absent. */
	public static function emailDomain(string $email): string
	{
		$p = strrpos($email, '@');
		return $p === false ? '' : substr($email, $p + 1);
	}

	/**
	 * '#rrggbb' normalisé en minuscules ; '' pour champ vide (= retour au
	 * défaut du schéma) ; null pour une valeur invalide (à rejeter).
	 */
	public static function hexColor(mixed $v): ?string
	{
		if ($v === '' || $v === null) {
			return '';
		}
		return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)
			? strtolower($v) : null;
	}

	public static function str(mixed $v, int $max): ?string
	{
		if ($v === null) { $v = ''; }
		if (!is_scalar($v)) { return null; }
		$s = trim((string) $v);
		return (strlen($s) <= $max && !preg_match('/[\r\n\x00-\x1F]/', $s)) ? $s : null;
	}

	public static function intRange(mixed $v, int $min, int $max): ?int
	{
		$i = filter_var($v, FILTER_VALIDATE_INT);
		return ($i !== false && $i >= $min && $i <= $max) ? $i : null;
	}

	public static function csvIp(mixed $v): ?string
	{
		if ($v === null) { $v = ''; }
		if (!is_scalar($v)) { return null; }
		$s = trim((string) $v);
		if ($s === '') {
			return '';
		}
		foreach (explode(',', $s) as $entry) {
			$entry = trim($entry);
			[$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				return null;
			}
			// Borne par famille : un /64 IPv4 serait validé mais jamais
			// appliqué par IpMatch::inCidr (bits > 32 > false systématique).
			$maxBits = str_contains($ip, ':') ? 128 : 32;
			if ($bits !== null && (!ctype_digit($bits) || (int) $bits > $maxBits)) {
				return null;
			}
		}
		return $s;
	}

	public static function csvDomain(mixed $v): ?string
	{
		if ($v === null) { $v = ''; }
		if (!is_scalar($v)) { return null; }
		$s = trim((string) $v);
		if ($s === '') {
			return '';
		}
		foreach (explode(',', $s) as $d) {
			if (!preg_match('/^(\*\.)?[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/i', trim($d))) {
				return null;
			}
		}
		return $s;
	}

	public static function filename(mixed $v): ?string
	{
		if ($v === null) { $v = ''; }
		if (!is_scalar($v)) { return null; }
		$s = trim((string) $v);
		return ($s !== '' && preg_match('/^[a-zA-Z0-9._\-]{1,64}$/', $s)) ? $s : null;
	}

	public static function timeZone(mixed $v): ?string
	{
		if ($v === null) { $v = ''; }
		if (!is_scalar($v)) { return null; }
		$s = trim((string) $v);
		return ($s === '' || in_array($s, timezone_identifiers_list(), true)) ? $s : null;
	}
}
