<?php
declare(strict_types=1);

namespace MXWebMail\Config;

/**
 * Écriture de app/config/config.php depuis l'admin.
 * Merge sur le fichier existant (les clés non fournies sont préservées),
 * les sous-tableaux (ssl, logs, user_defaults) sont fusionnés clé par clé.
 */
final class ConfigWriter
{
	public function __construct(private string $rootDir) {}

	public function writable(): bool
	{
		$dir = $this->rootDir . '/config';
		$file = $dir . '/config.php';
		return is_writable($dir) && (!is_file($file) || is_writable($file));
	}

	/** @throws \RuntimeException si l'écriture échoue */
	public function write(array $overrides, ?Config $config = null): void
	{
		if (!$this->writable()) {
			throw new \RuntimeException('config/ non accessible en écriture', 500);
		}
		$file = $this->rootDir . '/config/config.php';
		$current = is_file($file) ? require $file : [];
		if (!is_array($current)) {
			$current = [];
		}
		foreach ($overrides as $key => $value) {
			if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
				// Merge profond partagé avec Config (B15) : cohérent par
				// construction - un futur sous-niveau n'est pas écrasé.
				$current[$key] = Config::mergeDeep($current[$key], $value);
			} else {
				$current[$key] = $value;
			}
			$config?->set($key, $current[$key]);
		}
		$php = "<?php\n// Configuration MXWebMail - régénéré par l'admin le "
			. date('Y-m-d H:i:s') . "\nreturn " . var_export($current, true) . ";\n";
		// mxwm_atomic_write : tmp+rename + opcache_invalidate du .php -
		// sans l'invalidation, config.php reste périmé sous
		// opcache.validate_timestamps=0 jusqu'au redémarrage de PHP.
		if (!mxwm_atomic_write($file, $php)) {
			throw new \RuntimeException("Écriture de config.php impossible", 500);
		}
		@chmod($file, 0600);
	}
}
