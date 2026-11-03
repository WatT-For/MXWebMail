<?php
declare(strict_types=1);

namespace MXWebMail\Plugin;

use MXWebMail\Application\App;
use MXWebMail\Repository\PluginRepository;

/**
 * Charge les plugins activés depuis app/plugins/<name>/.
 * Un plugin = plugin.json (manifeste) + <Name>Plugin.php (classe héritant PluginBase).
 * Contexte borné : le plugin reçoit App et HookRegistry, jamais plus.
 */
final class PluginManager
{
	private array $loaded = [];

	public function __construct(
		private App $app,
		private HookRegistry $hooks,
		private PluginRepository $repo
	) {}

	/**
	 * Charge les plugins activés. Un plugin marqué non "utilisable par les
	 * utilisateurs" (plugins.user_allowed = 0) n'est chargé qu'en contexte
	 * admin : surface admin (page + /api/admin/*, flag $adminSurface passé par
	 * App::run - les hooks pré-login admin en ont besoin) ou session admin.
	 */
	public function loadEnabled(bool $adminSurface = false): void
	{
		$admin = $adminSurface || !empty($_SESSION['admin']);
		foreach ($this->manifests() as $manifestFile => $manifest) {
			$manifest = is_array($manifest) ? $manifest : null;
			$name = $manifest['name'] ?? basename(dirname($manifestFile));
			if (!$manifest || !$this->repo->isEnabled($name) || isset($this->loaded[$name])
				|| (!$admin && !$this->repo->isUserAllowed($name))) {
				continue;
			}
			$classFile = dirname($manifestFile) . '/' . ($manifest['main'] ?? '');
			if (!is_file($classFile)) {
				continue;
			}
			// Un plugin activé défaillant ne doit pas casser tout le site
			// (loadEnabled tourne à chaque requête) : on log et on ignore.
			try {
				require_once $classFile;
				$class = 'Plugin\\' . ($manifest['class'] ?? $name);
				if (class_exists($class)) {
					$plugin = new $class($this->app, $manifest, $this->repo->getConfig($name));
					if ($plugin instanceof PluginBase) {
						$plugin->register($this->hooks);
						$this->loaded[$name] = $plugin;
					}
				}
			} catch (\Throwable $e) {
				$this->app->log('error', "plugin {$name}: " . $e->getMessage());
			}
		}
	}

	/**
	 * Manifestes plugins présents (path => manifest décodé) - cache
	 * storage/cache/plugins.php revalidé par filemtime de chaque
	 * plugin.json : 22 stats ≪ 22 lectures + décodages à chaque requête.
	 * Un manifeste modifié (install/update) change son mtime ; un plugin
	 * ajouté/supprimé change la liste des fichiers > revalidation naturelle,
	 * aucune invalidation explicite requise.
	 */
	private function manifests(): array
	{
		$dir = $this->app->rootDir() . '/plugins';
		if (!is_dir($dir)) {
			return [];
		}
		$mtimes = [];
		foreach (glob($dir . '/*/plugin.json') ?: [] as $f) {
			$mtimes[$f] = (int) @filemtime($f);
		}
		$cacheFile = $this->app->storageDir() . '/cache/plugins.php';
		if (is_file($cacheFile)) {
			$c = require $cacheFile;
			if (is_array($c) && ($c['mtimes'] ?? null) === $mtimes
				&& is_array($c['manifests'] ?? null)) {
				return $c['manifests'];
			}
		}
		$manifests = [];
		foreach ($mtimes as $f => $_) {
			$manifests[$f] = json_decode((string) file_get_contents($f), true);
		}
		// Écriture atomique (tmp+rename+opcache_invalidate) : un require
		// concurrent ne doit jamais charger un cache tronqué ou périmé.
		@mxwm_atomic_write($cacheFile, '<?php return '
			. var_export(['mtimes' => $mtimes, 'manifests' => $manifests], true) . ";\n");
		return $manifests;
	}

	public function hooks(): HookRegistry
	{
		return $this->hooks;
	}

	public function loaded(): array
	{
		return $this->loaded;
	}

	/** Map nom > version des plugins chargés - pour le ?v= des assets plugin. */
	public function versions(): array
	{
		$out = [];
		foreach ($this->loaded as $name => $plugin) {
			$out[$name] = $plugin->version();
		}
		return $out;
	}

	/**
	 * Dispatch API borné : /api/plugin/{name}/{action} > Plugin::api<Action>.
	 * Uniquement si le plugin est chargé (donc activé) et la méthode existe.
	 * Le namespace admin est séparé : une action "admin-*" résoudrait en
	 * apiAdmin* (même préfixe api) - elle est rejetée ici, ces méthodes ne
	 * sont atteignables que via callAdminApi (requireAdmin en amont).
	 */
	public function callApi(string $name, string $action, \MXWebMail\Http\Request $request): mixed
	{
		$plugin = $this->loaded[$name] ?? null;
		if (!$plugin) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_unknown'), 404);
		}
		if (!preg_match('/^[a-z][a-z0-9\-]*$/i', $action)) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_bad_action'), 400);
		}
		$method = 'api' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $action)));
		if (!method_exists($plugin, $method) || 0 === \strncasecmp($method, 'apiAdmin', 8)) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_no_action'), 404);
		}
		return $plugin->$method($request, $this->app->currentUser());
	}

	/**
	 * Dispatch borné côté admin : /api/admin/plugin/{name}/{action}
	 * > Plugin::apiAdmin<Action>. La session admin (requireAdmin) est
	 * vérifiée en amont par AdminController ; pas de $user ici.
	 */
	public function callAdminApi(string $name, string $action, \MXWebMail\Http\Request $request): mixed
	{
		$plugin = $this->loaded[$name] ?? null;
		if (!$plugin) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_unknown'), 404);
		}
		if (!preg_match('/^[a-z][a-z0-9\-]*$/i', $action)) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_bad_action'), 400);
		}
		$method = 'apiAdmin' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $action)));
		if (!method_exists($plugin, $method)) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_no_action'), 404);
		}
		return $plugin->$method($request);
	}

	/** Liste les plugins présents sur disque avec leur état. */
	public function available(): array
	{
		$dir = $this->app->rootDir() . '/plugins';
		$out = [];
		foreach (glob($dir . '/*/plugin.json') ?: [] as $file) {
			$m = json_decode((string) file_get_contents($file), true) ?: [];
			$name = $m['name'] ?? basename(dirname($file));
			$out[] = [
				'name'         => $name,
				'description'  => $m['description'] ?? '',
				'version'      => $m['version'] ?? '',
				'enabled'      => $this->repo->isEnabled($name),
				'user_allowed' => $this->repo->isUserAllowed($name),
				'admin_only'   => self::isAdminOnly($m),
				'has_schema'   => is_file(dirname($file) . '/config.schema.json'),
			];
		}
		return $out;
	}

	/**
	 * Plugin sans surface utilisateur : tous les hooks déclarés au manifeste
	 * sont des hooks admin.* (ex. admin.mounts) - le switch "Accès
	 * utilisateurs" de l'onglet Extensions est alors sans objet (masqué).
	 */
	private static function isAdminOnly(array $manifest): bool
	{
		$hooks = array_filter((array) ($manifest['hooks'] ?? []), 'is_string');
		return $hooks !== [] && [] === array_filter(
			$hooks, fn(string $h) => !str_starts_with($h, 'admin.'));
	}
}
