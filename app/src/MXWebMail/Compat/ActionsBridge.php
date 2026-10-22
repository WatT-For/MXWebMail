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

	/** Compat : cache fichier (utilisé par MXWebMailLib\DNS notamment). */
	public function Cacher(): \MailSo\Cache\CacheClient
	{
		return $this->cacher ??= (new \MailSo\Cache\CacheClient())
			->SetDriver(new \MailSo\Cache\Drivers\File($this->app->storageDir() . '/cache'))
			->SetCacheIndex('_default_');
	}
}
