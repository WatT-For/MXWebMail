<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Scan antiviral ClamAV (clamd, protocole zINSTREAM) des pièces jointes
 * uploadées (compose + import .eml/.mbox via le hook attachment.upload).
 * FOUND > rejet de l'upload. clamd indisponible > on_error (allow = log
 * + passage, deny = 503 fail-closed).
 */
final class AttachmentsClamav extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('attachment.upload',
			fn(string $path, string $name, int $userId) => $this->scan($path, $name));
	}

	private function scan(string $path, string $name): void
	{
		$socket = (string) ($this->config['socket'] ?? 'tcp://127.0.0.1:3310');
		if ($socket === '') {
			return;
		}
		$timeout = max(1, (int) ($this->config['timeout'] ?? 15));
		try {
			$this->clamd($socket, $timeout, $path, $name);
		} catch (\RuntimeException $e) {
			if ($e->getCode() === 422) {
				throw $e; // virus trouvé : toujours bloquant
			}
			if (($this->config['on_error'] ?? 'allow') === 'deny') {
				throw $e;
			}
			$this->app->log('warning', 'attachments-clamav: ' . $e->getMessage());
		}
	}

	private function clamd(string $socket, int $timeout, string $path, string $name): void
	{
		$fp = @fsockopen($socket, -1, $errno, $errstr, $timeout);
		if (!$fp) {
			throw new \RuntimeException(
				$this->t('plugin.attachments-clamav.unavailable'), 503);
		}
		stream_set_timeout($fp, $timeout);
		try {
			fwrite($fp, "zINSTREAM\0");
			$in = fopen($path, 'rb');
			if (!$in) {
				// Un stream vide donnerait "stream: OK" - l'échec de lecture
				// locale suit on_error comme toute erreur clamd (pas un pass).
				throw new \RuntimeException(
					$this->t('plugin.attachments-clamav.read_failed',
						['name' => $name]), 502);
			}
			while (($chunk = fread($in, 8192)) !== false && $chunk !== '') {
				fwrite($fp, pack('N', strlen($chunk)) . $chunk);
			}
			$in && fclose($in);
			fwrite($fp, pack('N', 0));
			$resp = trim((string) fgets($fp, 4096));
		} finally {
			fclose($fp);
		}
		if (str_ends_with($resp, 'FOUND')) {
			$this->app->log('warning',
				"attachments-clamav: \"{$name}\" > {$resp}");
			throw new \RuntimeException(
				$this->t('plugin.attachments-clamav.virus_found',
					['name' => $name]), 422);
		}
		if (!str_ends_with($resp, 'OK')) {
			throw new \RuntimeException(
				$this->t('plugin.attachments-clamav.bad_response',
					['msg' => $resp]), 502);
		}
	}
}
