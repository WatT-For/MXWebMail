<?php
declare(strict_types=1);

namespace MXWebMail\Plugin;

use MXWebMail\Application\App;
use MXWebMail\Files;
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
		$admin = $adminSurface || $this->app->isAdminSession();
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
						$this->migratePlugin($name, dirname($manifestFile));
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
	 * Migrations d'un plugin : plugins/<name>/migrations.php retourne soit
	 * array<int, string[]> (version => requêtes SQL portables), soit une
	 * Closure recevant le Database pour dispatcher par driver (pk SQLite vs
	 * AUTO_INCREMENT, CREATE INDEX IF NOT EXISTS refusé par MySQL pur).
	 * Version suivie dans meta.plugin_<name>_schema_version - même
	 * mécanisme que le schéma cœur, exécuté au chargement du plugin
	 * (paresseux : 1 SELECT meta par requête, DDL seulement au changement
	 * de version). Un plugin désactivé n'est pas migré : ses tables
	 * restent, la migration reprendra à la réactivation.
	 */
	private function migratePlugin(string $name, string $dir): void
	{
		$file = $dir . '/migrations.php';
		if (!is_file($file)) {
			return;
		}
		$list = require $file;
		if ($list instanceof \Closure) {
			$list = $list($this->app->db());
		}
		if (is_array($list) && $list !== []) {
			$this->app->db()->migrateList($list, "plugin_{$name}_schema_version");
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
		return Files::phpCache($this->app->storageDir() . '/cache/plugins.php',
			$mtimes, function () use ($mtimes) {
				$manifests = [];
				foreach ($mtimes as $f => $_) {
					$manifests[$f] = json_decode((string) file_get_contents($f), true);
				}
				return $manifests;
			});
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
		[$plugin, $method] = $this->resolveApiMethod($name, $action, false);
		return $plugin->$method($request, $this->app->currentUser());
	}

	/**
	 * Dispatch borné côté admin : /api/admin/plugin/{name}/{action}
	 * > Plugin::apiAdmin<Action>. La session admin (requireAdmin) est
	 * vérifiée en amont par AdminController ; pas de $user ici.
	 */
	public function callAdminApi(string $name, string $action, \MXWebMail\Http\Request $request): mixed
	{
		[$plugin, $method] = $this->resolveApiMethod($name, $action, true);
		return $plugin->$method($request);
	}

	/**
	 * [plugin, méthode] pour le dispatch API - action bornée camelCase.
	 * Non-admin : le préfixe apiAdmin* reste interdit (ces méthodes ne
	 * sont atteignables que via callAdminApi, requireAdmin en amont).
	 */
	private function resolveApiMethod(string $name, string $action, bool $admin): array
	{
		$plugin = $this->loaded[$name] ?? null;
		if (!$plugin) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_unknown'), 404);
		}
		if (!preg_match('/^[a-z][a-z0-9\-]*$/i', $action)) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_bad_action'), 400);
		}
		$method = ($admin ? 'apiAdmin' : 'api')
			. str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $action)));
		if (!method_exists($plugin, $method)
			|| (!$admin && 0 === \strncasecmp($method, 'apiAdmin', 8))) {
			throw new \RuntimeException($this->app->locale()->t('errors.plugin_no_action'), 404);
		}
		return [$plugin, $method];
	}

	/**
	 * Liste les plugins présents sur disque avec leur état. Itère le cache
	 * manifests() (revalidé par mtime) plutôt que de relire chaque
	 * plugin.json à chaque appel.
	 */
	public function available(): array
	{
		$out = [];
		foreach ($this->manifests() as $file => $m) {
			$m = is_array($m) ? $m : [];
			$name = $m['name'] ?? basename(dirname($file));
			$out[] = [
				'name'         => $name,
				'label'        => (string) ($m['label'] ?? ''),
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
