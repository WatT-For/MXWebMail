<?php
declare(strict_types=1);

namespace MXWebMail\Config;

/**
 * Parse/build des DSN MySQL - point unique pour la forme
 * "mysql:host=H;port=P;dbname=N;charset=utf8mb4" lue et écrite par
 * l'installeur et les réglages admin.
 */
final class Dsn
{
	/**
	 * Paramètres trouvés dans un DSN MySQL ('host' => string,
	 * 'port' => int, 'dbname' => string) - les clés absentes du DSN
	 * sont absentes du résultat (l'appelant pose ses défauts via ??).
	 */
	public static function parseMysql(string $dsn): array
	{
		$out = [];
		foreach (['host', 'port', 'dbname'] as $p) {
			if (preg_match('/\b' . $p . '=([^;]*)/', $dsn, $m) && $m[1] !== '') {
				$out[$p] = $p === 'port' ? (int) $m[1] : $m[1];
			}
		}
		return $out;
	}

	/**
	 * DSN PDO MySQL. $dbname vide = connexion "serveur" utilisée pour
	 * tester les credentials et CREATE DATABASE avant de choisir la base.
	 */
	public static function mysql(string $host, int $port, string $dbname = ''): string
	{
		return 'mysql:host=' . $host . ';port=' . $port
			. ($dbname !== '' ? ';dbname=' . $dbname : '')
			. ';charset=utf8mb4';
	}
}
