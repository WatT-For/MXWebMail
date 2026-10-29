<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Page admin de reporting (lecture seule) : taille de storage/,
 * utilisateurs actifs/inactifs, compteurs des tables, enregistrements
 * orphelins, plugins installés, environnement. Pas de quota IMAP par
 * utilisateur — les credentials ne vivent que dans les sessions.
 */
final class AdminStats extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('admin.mounts', fn() => [
			'js'      => 'stats.js',
			'plugin'  => 'admin-stats',
			'section' => 'page',
			'tab'     => 'admin-stats',
			'label'   => 'plugin.admin-stats.tab',
			'icon'    => 'fa-chart-pie',
		]);
	}

	public function apiAdminStats(Request $request): array
	{
		if (empty($_SESSION['admin'])) {
			throw new \RuntimeException('Forbidden', 403);
		}
		$pdo = $this->app->db()->pdo();
		return [
			'storage'  => $this->storage(),
			'users'    => $this->users($pdo),
			'tables'   => $this->tables($pdo),
			'orphans'  => $this->orphans($pdo),
			'plugins'  => $this->plugins(),
			'env'      => [
				'app_version' => APP_VERSION,
				'php'         => PHP_VERSION,
				'db_driver'   => $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
			],
		];
	}

	/** Taille récursive par sous-dossier de storage/ (octets). */
	private function storage(): array
	{
		$out = [];
		foreach (glob($this->app->storageDir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
			$size = 0;
			foreach (new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($dir,
						\FilesystemIterator::SKIP_DOTS)) as $f) {
				$size += $f->getSize();
			}
			$out[] = ['name' => basename($dir), 'bytes' => $size];
		}
		usort($out, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
		return $out;
	}

	private function users(\PDO $pdo): array
	{
		$cut = time() - 30 * 86400;
		$row = $pdo->query(
			'SELECT COUNT(*) AS total,
			        SUM(CASE WHEN last_login_at IS NOT NULL
			             AND last_login_at > ' . $cut . ' THEN 1 ELSE 0 END) AS active30,
			        SUM(disabled) AS disabled
			 FROM users')->fetch(\PDO::FETCH_ASSOC);
		return [
			'total'    => (int) ($row['total'] ?? 0),
			'active30' => (int) ($row['active30'] ?? 0),
			'disabled' => (int) ($row['disabled'] ?? 0),
		];
	}

	private function tables(\PDO $pdo): array
	{
		$out = [];
		foreach (['users', 'accounts', 'contacts', 'identities', 'pgp_keys',
				'auth_tokens', 'user_settings', 'plugins'] as $t) {
			try {
				$out[] = ['name' => $t,
					'rows' => (int) $pdo->query("SELECT COUNT(*) FROM {$t}")
						->fetchColumn()];
			} catch (\Throwable) { /* table absente → ignorée */ }
		}
		return $out;
	}

	/** Lignes dont le user_id ne correspond à aucun compte existant. */
	private function orphans(\PDO $pdo): array
	{
		$out = [];
		foreach (['accounts', 'contacts', 'identities', 'pgp_keys',
				'user_settings'] as $t) {
			try {
				// identities → accounts → users (pas de user_id direct) :
				// orphelin = identité sans compte OU compte sans utilisateur.
				$join = $t === 'identities'
					? 'LEFT JOIN accounts a ON a.id = t.account_id
					   LEFT JOIN users u ON u.id = a.user_id
					   WHERE a.id IS NULL OR u.id IS NULL'
					: 'LEFT JOIN users u ON u.id = t.user_id WHERE u.id IS NULL';
				$out[] = ['name' => $t,
					'rows' => (int) $pdo->query("SELECT COUNT(*) FROM {$t} t {$join}")
						->fetchColumn()];
			} catch (\Throwable) { /* colonne/table absente → ignorée */ }
		}
		return $out;
	}

	private function plugins(): array
	{
		$repo = $this->app->plugins();
		$out = [];
		foreach ($repo->available() as $p) {
			// enabled/user_allowed sont déjà calculés par available()
			// (PluginManager n'expose pas isEnabled/isUserAllowed).
			$out[] = [
				'name'         => (string) ($p['name'] ?? ''),
				'version'      => (string) ($p['version'] ?? ''),
				'enabled'      => $p['enabled'] ?? false,
				'user_allowed' => $p['user_allowed'] ?? false,
			];
		}
		return $out;
	}
}
