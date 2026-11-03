<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Http\Router;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * PWA : rend le webmail installable comme application.
 * Injecte <link rel=manifest> + theme-color + apple-touch-icon + le script
 * d'enregistrement du service worker dans le <head> de la page application,
 * et sert le manifest dynamique + un service worker minimal qui n'intercepte
 * rien (aucune mise en cache des mails). Icônes par défaut embarquées (assets/)
 * ou surchargées par une URL admin.
 * Routes publiques : /pwa/manifest.webmanifest, /pwa/sw.js.
 */
final class Pwa extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.head', fn(string $nonce, string $base) => $this->head($base));
		$hooks->on('app.routes', fn(Router $router) => $this->routes($router));
	}

	/** HTML injecté dans le <head> de la page application (connectée). */
	private function head(string $base): string
	{
		if (!$this->app->isLoggedIn()) {
			return '';
		}
		$b = htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "\n\t" . '<link rel="manifest" href="' . $b . '/pwa/manifest.webmanifest">'
			. "\n\t" . '<meta name="theme-color" content="'
				. htmlspecialchars($this->themeColor(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
			. "\n\t" . '<link rel="apple-touch-icon" href="' . $this->iconHref($b) . '">'
			. "\n\t" . '<script src="' . $b . '/assets/plugin/pwa/register.js?v='
				. rawurlencode($this->version()) . '" defer></script>';
	}

	private function routes(Router $router): void
	{
		$router->get('/pwa/manifest.webmanifest', [$this, 'manifest']);
		$router->get('/pwa/sw.js', [$this, 'serviceWorker']);
	}

	/**
	 * Manifest Web App (JSON dynamique) : start_url/scope dépendent du base path
	 * (installations en sous-dossier). Public - aucune donnée sensible.
	 */
	public function manifest(Request $request): Response
	{
		$base = Request::basePath() . '/';
		$iconUrl = trim((string) ($this->config['icon_url'] ?? ''));
		$icons = [];
		if (self::isIconUrl($iconUrl)) {
			// Surcharge admin : URL externe utilisée telle quelle pour tout le manifest.
			$icons[] = [
				'src' => $iconUrl, 'sizes' => 'any',
				'type' => 'image/png', 'purpose' => 'any',
			];
		} else {
			foreach ([
				['icon-192.png',          '192x192', 'any'],
				['icon-512.png',          '512x512', 'any'],
				['icon-maskable-512.png', '512x512', 'maskable'],
			] as [$file, $sizes, $purpose]) {
				$icons[] = [
					'src' => $base . 'assets/plugin/pwa/' . $file
						. '?v=' . rawurlencode($this->version()),
					'sizes' => $sizes, 'type' => 'image/png', 'purpose' => $purpose,
				];
			}
		}

		$name = trim((string) ($this->config['name'] ?? ''));
		if ($name === '') {
			$name = (string) $this->app->config()->get('app_name');
		}
		$short = trim((string) ($this->config['short_name'] ?? ''));
		$display = (string) ($this->config['display'] ?? 'standalone');
		if (!in_array($display, ['standalone', 'fullscreen', 'minimal-ui', 'browser'], true)) {
			$display = 'standalone';
		}

		$manifest = [
			'id'               => $base,
			'name'             => $name,
			'short_name'       => $short !== '' ? $short : $name,
			'start_url'        => $base,
			'scope'            => $base,
			'display'          => $display,
			'background_color' => $this->backgroundColor(),
			'theme_color'      => $this->themeColor(),
			'icons'            => $icons,
		];
		return new Response(
			(string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			200,
			['Content-Type' => 'application/manifest+json',
			 'Cache-Control' => 'public, max-age=300']
		);
	}

	/**
	 * Service worker minimal : satisfait le critère d'installabilité Chrome
	 * (un listener `fetch` doit exister) sans intercepter ni mettre en cache
	 * quoi que ce soit - aucun respondWith, le navigateur gère tout nativement
	 * (un respondWith(fetch()) transformerait toute coupure réseau transitoire
	 * en erreur console + échec opaque sur tous les onglets de l'origine).
	 * Service-Worker-Allowed élargit le scope par défaut ({base}/pwa/) à la
	 * racine de l'app ({base}/).
	 */
	public function serviceWorker(Request $request): Response
	{
		$base = Request::basePath() . '/';
		$sw = <<<'JS'
/* PWA - service worker minimal (installabilité uniquement, aucune interception). */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
JS;
		return new Response($sw, 200, [
			'Content-Type'           => 'text/javascript; charset=utf-8',
			'Service-Worker-Allowed' => $base,
			'Cache-Control'          => 'no-cache',
		]);
	}

	// ─── Config helpers ───

	/** href de l'icône tactile (URL admin prioritaire, sinon PNG par défaut 192). */
	private function iconHref(string $escapedBase): string
	{
		$iconUrl = trim((string) ($this->config['icon_url'] ?? ''));
		if (self::isIconUrl($iconUrl)) {
			return htmlspecialchars($iconUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
		return $escapedBase . '/assets/plugin/pwa/icon-192.png?v='
			. rawurlencode($this->version());
	}

	private static function isIconUrl(string $url): bool
	{
		return $url !== '' && (bool) preg_match('#^https?://#i', $url);
	}

	private function themeColor(): string
	{
		return self::hex($this->config['theme_color'] ?? '') ?? '#0d6efd';
	}

	private function backgroundColor(): string
	{
		return self::hex($this->config['background_color'] ?? '') ?? '#ffffff';
	}

	private static function hex(mixed $v): ?string
	{
		return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)
			? strtolower($v) : null;
	}
}
