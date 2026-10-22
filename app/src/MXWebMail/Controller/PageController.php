<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Application\App;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\Csrf;

/**
 * Rendu des pages HTML (login, app, admin) + assets de plugins.
 * Tout le reste passe par l'API JSON.
 */
final class PageController
{
	public function __construct(private App $app) {}

	public function home(Request $request): Response
	{
		// Mode maintenance : page dédiée 503 pour tous (session conservée —
		// l'utilisateur retrouve l'app à la fin). L'admin reste sur /{admin_path}.
		if ($this->app->config()->get('maintenance', false)) {
			return $this->render('maintenance', [], 503,
				['Retry-After' => '300', 'Cache-Control' => 'no-store']);
		}
		if ($this->app->isLoggedIn()) {
			return $this->render('app');
		}
		return $this->render('login');
	}

	public function admin(Request $request): Response
	{
		$list = trim((string) $this->app->config()->get('admin_ip_allowlist', ''));
		if ($list !== ''
			&& !\MXWebMail\Security\IpMatch::inList($this->app->clientIp(), $list)) {
			return Response::html('<h1>403 — ' . $this->translate('errors.forbidden') . '</h1>', 403);
		}
		if (empty($_SESSION['admin'])) {
			// TOTP affiché d'emblée seulement si le compte admin local l'exige
			// (hook admin.totp-required du plugin two-factor-auth — désactivé =
			// non exigé) ; sinon le champ apparaît sur erreur 'totp_required'.
			return $this->render('admin-login', ['totpEnabled' =>
				$this->app->config()->get('admin_user', '') !== ''
				&& $this->app->config()->get('admin_password_hash', '') !== ''
				&& (bool) $this->app->hooks()->first('admin.totp-required')]);
		}
		return $this->render('admin');
	}

	/** Traduction échappée pour les templates : <?= $t('cle', ['p' => $v]) ?>. */
	private function translate(string $key, array $params = []): string
	{
		return htmlspecialchars(
			$this->app->locale()->t($key, $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** Assets statiques des plugins : /assets/plugin/{name}/{file} — extensions whitelistées. */
	public function pluginAsset(Request $request): Response
	{
		session_write_close(); // asset public : pas besoin de la session
		$name = preg_replace('/[^a-z0-9\-]/i', '', $request->params['name'] ?? '');
		$file = $request->params['file'] ?? '';
		$allowed = ['js' => 'text/javascript', 'css' => 'text/css', 'png' => 'image/png',
			'svg' => 'image/svg+xml', 'woff2' => 'font/woff2'];
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		// Plugin activé requis (comme themeAsset vérifie themes()->exists()) —
		// sinon les fichiers d'un plugin désactivé restent servis. En cas de panne
		// DB on sert quand même : ce sont des fichiers statiques publics et la
		// whitelist d'extensions ci-dessus est déjà appliquée.
		$enabled = true;
		try {
			$enabled = $this->app->pluginRepo()->isEnabled($name);
		} catch (\Throwable $e) {
		}
		if (!$name || !isset($allowed[$ext]) || !$enabled) {
			return Response::notFound();
		}
		// Anti path traversal : le nom de fichier ne doit pas contenir de chemin
		if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
			return Response::notFound();
		}
		$path = $this->app->rootDir() . "/plugins/{$name}/js/{$file}";
		if (!is_file($path)) {
			$path = $this->app->rootDir() . "/plugins/{$name}/assets/{$file}";
		}
		if (!is_file($path)) {
			return Response::notFound();
		}
		return new Response(
			(string) file_get_contents($path),
			200,
			['Content-Type' => $allowed[$ext], 'Cache-Control' => 'public, max-age=3600']
		);
	}

	/**
	 * /favicon.ico : icône uploadée dans app/storage/branding (config 'favicon'),
	 * sinon l'icône MXWebMail par défaut. Public — aucune donnée sensible.
	 */
	public function favicon(Request $request): Response
	{
		session_write_close(); // asset public : pas besoin de la session
		$types = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml',
			'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
		$path = '';
		$name = basename((string) $this->app->config()->get('favicon', ''));
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if ($name !== '' && isset($types[$ext])) {
			$candidate = $this->app->storageDir() . '/branding/' . $name;
			if (is_file($candidate)) {
				$path = $candidate;
			}
		}
		if ($path === '') {
			$path = $this->app->installDir() . '/public/assets/img/favicon.png';
			$ext = 'png';
		}
		if (!is_file($path)) {
			return Response::notFound();
		}
		$headers = ['Content-Type' => $types[$ext], 'Cache-Control' => 'public, max-age=300'];
		// Un SVG servi en direct est same-origin : couper tout script embarqué
		// (les en-têtes CSP cumulatifs se combinent avec ceux de la réponse).
		if ($ext === 'svg') {
			$headers['Content-Security-Policy'] = "script-src 'none'";
		}
		return new Response((string) file_get_contents($path), 200, $headers);
	}

	/** Assets d'un template : /assets/theme/{name}/{file} — extensions whitelistées. */
	public function themeAsset(Request $request): Response
	{
		session_write_close(); // asset public : pas besoin de la session
		$name = preg_replace('/[^a-z0-9\-]/i', '', $request->params['name'] ?? '');
		$file = $request->params['file'] ?? '';
		$allowed = ['js' => 'text/javascript', 'css' => 'text/css', 'png' => 'image/png',
			'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'woff' => 'font/woff'];
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (!$name || !isset($allowed[$ext]) || !$this->app->themes()->exists($name)) {
			return Response::notFound();
		}
		// Anti path traversal : le nom de fichier ne doit pas contenir de chemin
		if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
			return Response::notFound();
		}
		$path = $this->app->themes()->assetPath($name, $file);
		if (!is_file($path)) {
			return Response::notFound();
		}
		return new Response(
			(string) file_get_contents($path),
			200,
			['Content-Type' => $allowed[$ext], 'Cache-Control' => 'public, max-age=3600']
		);
	}

	private function render(string $template, array $vars = [], int $status = 200,
		array $extraHeaders = []): Response
	{
		$theme = $this->app->themes()->current();
		$file = $this->app->themes()->viewPath($theme, $template);
		if (!is_file($file)) {
			return Response::html("<h1>Template manquant : {$theme}/{$template}</h1>", 500);
		}
		$vars['app'] = $this->app;
		$vars['csrf'] = Csrf::token();
		$vars['nonce'] = \MXWebMail\Security\Headers::nonce();
		// Base URL pour les liens/assets (gère l'installation en sous-dossier)
		$vars['base'] = Request::basePath();
		// Thème actif — pour construire les URLs d'assets du template
		$vars['theme'] = $theme;
		// Config du template : attributs <html> + variables CSS (mode sombre, couleurs, icônes)
		$vars['themeHtmlAttrs'] = $this->app->themes()->htmlAttrs($theme);
		$vars['themeHead']      = $this->app->themes()->styleBlock($theme);
		$vars['themeScript']    = '<script nonce="' . $vars['nonce'] . '">'
			. $this->app->themes()->darkScript() . '</script>';
		// HTML des plugins à injecter dans <head> (manifest PWA, meta…). Chaque
		// plugin écoute « app.head » et retourne une chaîne à concaténer.
		$vars['pluginHead'] = implode("\n",
			$this->app->hooks()->collect('app.head', $vars['nonce'], $vars['base']));
		// Traduction : $t (échappé) côté PHP, $lang + $dict → window.MXWM.i18n
		$locale = $this->app->locale();
		$vars['lang'] = $locale->lang();
		$vars['dict'] = $locale->dict();
		$vars['t'] = $this->translate(...);
		// JSON du bootstrap JS — flags HEX_* : une traduction ne peut pas
		// casser le <script> inline en injectant </script>.
		$vars['mxwmJson'] = json_encode([
			'base' => $vars['base'], 'csrf' => $vars['csrf'],
			'i18n' => ['lang' => $vars['lang'], 'dict' => $vars['dict']],
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			| JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
		extract($vars, EXTR_SKIP);
		ob_start();
		require $file;
		return new Response((string) ob_get_clean(), $status,
			['Content-Type' => 'text/html; charset=utf-8'] + $extraHeaders);
	}
}
