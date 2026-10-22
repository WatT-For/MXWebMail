<?php
declare(strict_types=1);

namespace MXWebMail\Security;

/**
 * Chiffrement des credentials en session (mot de passe IMAP/SMTP).
 * Clé maître dans app/storage/app.key (générée au premier usage, chmod 600).
 * Algorithme : XSalsa20-Poly1305 (sodium secretbox, libsodium).
 */
final class CredentialCrypt
{
	private static ?string $key = null;

	private static function key(string $storageDir): string
	{
		if (self::$key === null) {
			$keyFile = $storageDir . '/app.key';
			if (!is_file($keyFile)) {
				if (!is_dir($storageDir)) {
					mkdir($storageDir, 0700, true);
				}
				file_put_contents($keyFile, sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
				chmod($keyFile, 0600);
			}
			self::$key = sodium_hex2bin(trim((string) file_get_contents($keyFile)));
		}
		return self::$key;
	}

	public static function encrypt(string $plain, string $storageDir): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = sodium_crypto_secretbox($plain, $nonce, self::key($storageDir));
		return sodium_bin2hex($nonce . $cipher);
	}

	public static function decrypt(string $encoded, string $storageDir): ?string
	{
		// PHP 8 : sodium_hex2bin lève SodiumException sur hex invalide
		// (jamais false) — un credential corrompu doit rendre null, pas 500.
		try {
			$raw = sodium_hex2bin($encoded);
		} catch (\SodiumException) {
			return null;
		}
		if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			return null;
		}
		$nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key($storageDir));
		return $plain === false ? null : $plain;
	}
}
