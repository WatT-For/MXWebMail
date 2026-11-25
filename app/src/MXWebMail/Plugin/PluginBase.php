<?php
declare(strict_types=1);

namespace MXWebMail\Plugin;

use MXWebMail\Application\App;
use MXWebMail\Locale\Translates;

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

	/** Version déclarée dans plugin.json - utilisée pour le ?v= des assets. */
	public function version(): string
	{
		return (string) ($this->manifest['version'] ?? '');
	}

	/** Nom du plugin déclaré au manifeste. */
	protected function name(): string
	{
		return (string) ($this->manifest['name'] ?? '');
	}

	// ─── Accès config : remplace les casts $this->config[...] répétés ───

	/** Chaîne brute : (string) ($this->config[$key] ?? $default). */
	protected function configString(string $key, string $default = ''): string
	{
		return (string) ($this->config[$key] ?? $default);
	}

	/** Chaîne trimée : hôtes, noms et listes saisis par l'admin. */
	protected function configTrim(string $key, string $default = ''): string
	{
		return trim($this->configString($key, $default));
	}

	protected function configInt(string $key, int $default = 0): int
	{
		return (int) ($this->config[$key] ?? $default);
	}

	/** Bool : !empty($config[$key]) avec $default=false, (bool)(... ?? $d) sinon. */
	protected function configBool(string $key, bool $default = false): bool
	{
		return (bool) ($this->config[$key] ?? $default);
	}

	/** CSV "a, b, c" > ['a','b','c'] ; $default si le champ est vide. */
	protected function configCsv(string $key, string $default = ''): array
	{
		$raw = $this->configTrim($key);
		if ($raw === '') {
			$raw = trim($default);
		}
		return $raw === '' ? [] : array_values(
			array_filter(array_map('trim', explode(',', $raw))));
	}

	// ─── Déclarations standardisées (app.data / admin.mounts) ───

	/** Onglet Paramètres utilisateur (app.data > pluginTabs). */
	protected function userTab(string $js, string $icon): array
	{
		return ['id' => $this->name(), 'label' => "plugin.{$this->name()}.tab",
			'icon' => $icon, 'js' => $js, 'plugin' => $this->name()];
	}

	/** Sous-onglet admin (admin.mounts) ; $tab peut différer du nom (ex. 2fa). */
	protected function adminMount(string $section, string $tab, string $js,
		?string $icon = null): array
	{
		$out = ['js' => $js, 'plugin' => $this->name(), 'section' => $section,
			'tab' => $tab, 'label' => "plugin.{$this->name()}.tab"];
		if ($icon !== null) {
			$out['icon'] = $icon;
		}
		return $out;
	}

	/** Entrée feature (app.data > features) : [$name => $payload]. */
	protected function feature(string $name, mixed $payload = true): array
	{
		return [$name => $payload];
	}

	/** Entrée barre d'outils message (app.data > messageToolbar). */
	protected function toolbarJs(string $js): array
	{
		return ['plugin' => $this->name(), 'js' => $js];
	}

	// ─── Réglages utilisateur du plugin ───
	// Convention "plugin.<name>.<key>" pour les nouvelles clés - les clés
	// historiques (plugin.2fa.*, plugin.carddav.*, plugin.mailbox_detect.*)
	// gardent leur forme : les renommer orphelinerait les données stockées.

	protected function userSetting(int $userId, string $key, mixed $default = null): mixed
	{
		return $this->app->settings()->get($userId,
			'plugin.' . $this->name() . '.' . $key, $default);
	}

	/** $value null = suppression de la clé (convention settings). */
	protected function userSettingSet(int $userId, string $key, mixed $value): void
	{
		$this->app->settings()->set($userId,
			'plugin.' . $this->name() . '.' . $key, $value);
	}

	/** PDO applicatif (tables plugin_*). */
	protected function pdo(): \PDO
	{
		return $this->app->db()->pdo();
	}

	/**
	 * Garde admin de défense en profondeur : /api/admin/plugin/* ne repose
	 * pas uniquement sur le routage - $_SESSION['admin'] est revérifié.
	 * Convention : tout apiAdmin* l'appelle en première instruction.
	 */
	protected function requireAdmin(): void
	{
		if (!$this->app->isAdminSession()) {
			throw new \RuntimeException($this->t('errors.admin_required'), 403);
		}
	}

	/**
	 * URL versionnée d'un asset du plugin (le ?v= casse le cache à chaque
	 * version). $base = base de l'app (arg du hook app.head ou
	 * Request::basePath). URL brute : échapper selon le contexte
	 * d'insertion (htmlspecialchars pour un attribut HTML).
	 */
	protected function assetUrl(string $base, string $file): string
	{
		return rtrim($base, '/') . '/assets/plugin/'
			. (string) ($this->manifest['name'] ?? '') . '/' . ltrim($file, '/')
			. '?v=' . rawurlencode($this->version());
	}

	/**
	 * <script nonce> posant window.<$global> = <config json>. JSON_HEX_*
	 * garantit qu'aucune valeur de config (même "</script>") ne casse le
	 * script inline ; JSON_UNESCAPED_SLASHES garde les URL lisibles.
	 */
	protected function inlineConfig(string $global, array $payload, string $nonce): string
	{
		return '<script nonce="' . $nonce . '">window.' . $global . '='
			. json_encode($payload,
				JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)
			. ';</script>';
	}

	/** Premier Email d'une collection From/ReplyTo/Sender - null si vide. */
	protected static function firstEmail(?\MailSo\Mime\EmailCollection $col): ?\MailSo\Mime\Email
	{
		if ($col && count($col)) {
			foreach ($col as $email) {
				return $email;
			}
		}
		return null;
	}

	/**
	 * Traduction dans la langue résolue de la requête.
	 * Les dictionnaires du plugin sont dans langs/<lang>.php
	 * (clés "plugin.<name>.*"), fusionnés avec le dictionnaire cœur.
	 */
	use Translates;
}
