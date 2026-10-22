<?php
declare(strict_types=1);

namespace MXWebMail\Plugin;

use MXWebMail\Application\App;

/**
 * Classe de base d'un plugin. Le plugin reçoit un contexte borné :
 * App (config, db, storage) + son manifeste + sa config propre.
 */
abstract class PluginBase
{
	public function __construct(
		protected App $app,
		protected array $manifest,
		protected array $config
	) {}

	/** Enregistre les hooks : $hooks->on('settings.tabs.user', ...) */
	abstract public function register(HookRegistry $hooks): void;

	/** Version déclarée dans plugin.json — utilisée pour le ?v= des assets. */
	public function version(): string
	{
		return (string) ($this->manifest['version'] ?? '');
	}

	/**
	 * Traduction dans la langue résolue de la requête.
	 * Les dictionnaires du plugin sont dans langs/<lang>.php
	 * (clés « plugin.<name>.* »), fusionnés avec le dictionnaire cœur.
	 */
	protected function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}
}
