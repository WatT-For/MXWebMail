<?php
declare(strict_types=1);

namespace MXWebMail\Compat;

use MXWebMail\Application\App;

final class ActionsBridge
{
	private ?\MailSo\Cache\CacheClient $cacher = null;

	public function __construct(private App $app) {}

	public function Plugins(): PluginsBridge
	{
		return new PluginsBridge($this->app->hooks());
	}

	/** Compat : SensitiveString masque les secrets dans les logs MailSo. */
	public function logMask(#[\SensitiveParameter] string $word): void
	{
		\MXWebMail\API::Logger()->AddSecret($word);
	}

	/**
	 * Compat : cache fichier (utilisé par MXWebMailLib\DNS notamment).
	 * cache.enabled=false → client sans driver (Get/Set no-op natifs).
	 * GC : au plus une fois par cache.gc_hours heures (marqueur _gc.ts).
	 */
	public function Cacher(): \MailSo\Cache\CacheClient
	{
		if ($this->cacher === null) {
			$this->cacher = (new \MailSo\Cache\CacheClient())->SetCacheIndex('_default_');
			$cfg = $this->app->config()->get('cache', []);
			$cfg = is_array($cfg) ? $cfg : [];
			if ($cfg['enabled'] ?? true) {
				$dir = $this->app->storageDir() . '/cache';
				$this->cacher->SetDriver(new \MailSo\Cache\Drivers\File($dir));
				$gcHours = (int) ($cfg['gc_hours'] ?? 168);
				if ($gcHours > 0) {
					$marker = $dir . '/_gc.ts';
					$last = is_file($marker) ? (int) file_get_contents($marker) : 0;
					if ($last <= time() - $gcHours * 3600) {
						// RecTimeDirRemove purge tout le dossier et le rmdir s'il
						// est vide — le recréer avant d'écrire le marqueur.
						$this->cacher->GC($gcHours);
						is_dir($dir) || mkdir($dir, 0700, true);
						file_put_contents($marker, (string) time(), LOCK_EX);
					}
				}
			}
		}
		return $this->cacher;
	}
}
