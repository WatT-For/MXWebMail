<?php
declare(strict_types=1);

namespace MXWebMail\Theme;

use MXWebMail\Application\App;

/**
 * Templates d'interface : app/templates/<name>/{theme.json, config.schema.json, pages/*, assets/*, langs/*}.
 * Héritage par override : un template ne contient que les fichiers qu'il
 * modifie ; tout fichier absent (pages/*, assets/*, config.schema.json) est
 * résolu dans la chaîne name > theme.json "extends" > … > "default"
 * (implicite si extends absent ; "default" est la racine, toujours en
 * dernier recours). theme.json et langs/* ne sont jamais hérités :
 * le manifeste est l'identité du template et les dicts sont namespacés
 * "theme.<name>.*". "default" doit rester complet.
 *
 * Config par template : le schéma (config.schema.json résolu, format plat)
 * décrit les champs ; les valeurs sont stockées en base (table themes).
 * L'interface expose ces valeurs via des variables CSS "--mxwm-*" + des
 * attributs data-* sur <html> (mode sombre). Les clés
 * "X_color" produisent "--mxwm-X" (+ -rgb, -text/-text-rgb pour le contraste
 * et les teintes dérivées -hover/-active/-active-rgb/-active-border/-tint/
 * -focus-rgb, équivalents PHP des shade-color/tint-color de Sass - Bootstrap
 * compile ses composants avec des couleurs littérales que les templates
 * redéclarent vers ces variables) ; les clés "X_color_dark" les déclinent en
 * variante sombre, émise sous "[data-bs-theme=dark]" (vide = défaut du champ ;
 * sans défaut, la couleur claire s'applique aussi en sombre).
 */
final class ThemeManager
{
	private const BASE = 'default';

	/** @var array<string, array> theme.json lus (immuables en cours de requête) */
	private array $manifests = [];

	public function __construct(private App $app) {}

	public function templatesDir(): string
	{
		return $this->app->rootDir() . '/templates';
	}

	private ?array $installedCache = null;

	/** Noms des templates installés (dossiers avec theme.json valide) - mémoïsé par requête. */
	public function installed(): array
	{
		if ($this->installedCache !== null) {
			return $this->installedCache;
		}
		$out = [];
		foreach (glob($this->templatesDir() . '/*/theme.json') ?: [] as $file) {
			$name = basename(dirname($file));
			if (preg_match('/^[a-z0-9][a-z0-9\-]*$/i', $name)) {
				$out[] = $name;
			}
		}
		sort($out);
		return $this->installedCache = $out;
	}

	/** Version du template (manifeste, repli APP_VERSION) - pour le ?v= des assets. */
	public function version(string $name): string
	{
		$v = (string) ($this->manifest($name)['version'] ?? '');
		return $v !== '' ? $v : APP_VERSION;
	}

	/**
	 * Version de busting d'un asset du template : mtime du fichier résolu
	 * (héritage inclus) - une édition du fichier sans bump de theme.json change
	 * l'URL. Repli sur la version du manifeste si le fichier est absent.
	 */
	public function assetVersion(string $name, string $file): string
	{
		$m = @filemtime($this->assetPath($name, $file));
		return $m !== false ? (string) $m : $this->version($name);
	}

	/** Liste [{name, label, version}] des templates utilisables - pour le frontend (appdata > <select> KO). */
	public function list(): array
	{
		$out = [];
		foreach ($this->installed() as $name) {
			if (!$this->isSelectable($name)) {
				continue;
			}
			$out[] = ['name' => $name, 'label' => $this->manifest($name)['label'] ?? $name,
				'version' => $this->version($name)];
		}
		return $out;
	}

	/** Map name => label - pour l'admin (enum de settingsSchema, Object.entries). */
	public function options(): array
	{
		$out = [];
		foreach ($this->list() as $t) {
			$out[$t['name']] = $t['label'];
		}
		return $out;
	}

	/** Liste [{name, label, version, selectable}] - pour l'onglet Templates de l'admin. */
	public function details(): array
	{
		$out = [];
		foreach ($this->installed() as $name) {
			$m = $this->manifest($name);
			$out[] = ['name' => $name, 'label' => $m['label'] ?? $name,
				'version' => $m['version'] ?? '', 'selectable' => $this->isSelectable($name),
				'extends' => $this->chain($name)[1] ?? null];
		}
		return $out;
	}

	public function exists(string $name): bool
	{
		return in_array($name, $this->installed(), true);
	}

	/** Template par défaut : user_defaults.theme si installé, sinon "default". */
	public function defaultTheme(): string
	{
		$defaults = (array) $this->app->config()->get('user_defaults', []);
		$t = (string) ($defaults['theme'] ?? 'default');
		return $this->exists($t) ? $t : 'default';
	}

	/** Proposable aux utilisateurs : installé + (autorisé OU template par défaut). */
	public function isSelectable(string $name): bool
	{
		return $this->exists($name)
			&& ($this->app->themeRepo()->isAllowed($name) || $name === $this->defaultTheme());
	}

	/**
	 * Chaîne d'héritage : [name, extends, …, 'default']. "default" est
	 * toujours en dernier recours, même si la chaîne est cassée (extends
	 * absent/invalide > manifeste vide > default ; cycle/profondeur > 8 >
	 * coupure + base). Les erreurs sont signalées par tools.ps1 check.
	 */
	public function chain(string $name): array
	{
		$seen = [];
		for ($cur = $name; $cur !== '' && !isset($seen[$cur]) && count($seen) < 8;) {
			$seen[$cur] = true;
			$p = $cur === self::BASE ? ''
				: (string) ($this->manifest($cur)['extends'] ?? self::BASE);
			$cur = preg_match('/^[a-z0-9][a-z0-9\-]*$/i', $p) ? $p : '';
		}
		$seen[self::BASE] = true;
		return array_keys($seen);
	}

	/**
	 * Résout un fichier du template dans la chaîne d'héritage (le premier
	 * existant gagne). Si absent partout, retourne le chemin dans le
	 * template lui-même : le is_file de l'appelant échoue naturellement.
	 */
	private function resolve(string $name, string $rel): string
	{
		foreach ($this->chain($name) as $t) {
			$p = $this->templatesDir() . '/' . $t . '/' . $rel;
			if (is_file($p)) {
				return $p;
			}
		}
		return $this->templatesDir() . '/' . $name . '/' . $rel;
	}

	/** Chemin absolu d'une vue du template (pages/<tpl>.php, héritage inclus). */
	public function viewPath(string $name, string $template): string
	{
		return $this->resolve($name, 'pages/' . $template . '.php');
	}

	/** Chemin absolu d'un asset du template (validation ext/traversal côté route). */
	public function assetPath(string $name, string $file): string
	{
		return $this->resolve($name, 'assets/' . $file);
	}

	/** Template effectif : préférence utilisateur (si utilisable) > défaut global. */
	public function current(): string
	{
		$theme = $this->defaultTheme();
		if ($this->app->isLoggedIn()) {
			$v = $this->app->settings()->get((int) $_SESSION['user_id'], 'theme', '');
			if (is_string($v) && $this->isSelectable($v)) {
				$theme = $v;
			}
		}
		return $theme;
	}

	public function manifest(string $name): array
	{
		if (isset($this->manifests[$name])) {
			return $this->manifests[$name];
		}
		$file = $this->templatesDir() . '/' . $name . '/theme.json';
		$m = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		return $this->manifests[$name] = is_array($m) ? $m : [];
	}

	// ─── Config par template ───

	private array $schemas = [];
	private array $configs = [];

	/** Schéma plat des champs configurables - hérité du parent si absent (ou [] si invalide). */
	public function schema(string $name): array
	{
		if (isset($this->schemas[$name])) {
			return $this->schemas[$name];
		}
		$file = $this->resolve($name, 'config.schema.json');
		$s = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		return $this->schemas[$name] = is_array($s) ? $s : [];
	}

	/**
	 * Config effective : défauts du schéma + valeurs en base, sanitisées.
	 * Une valeur invalide stockée retombe sur le défaut du schéma (défensif).
	 */
	public function config(string $name): array
	{
		// Mémoïsé par requête : darkMode/varsCss/themeData lisaient
		// chacun getConfig + schema (3 requêtes + 3 lectures par template).
		// Périmé après un setConfig dans la même requête - aucun endpoint ne
		// fait ce read-after-write (themeConfigSet ne relit pas).
		if (isset($this->configs[$name])) {
			return $this->configs[$name];
		}
		$out = [];
		$saved = $this->app->themeRepo()->getConfig($name);
		foreach ($this->schema($name) as $key => $f) {
			$raw = $saved[$key] ?? $f['default'] ?? null;
			try {
				$out[$key] = $this->coerce($raw, $f, $key);
			} catch (\Throwable) {
				$out[$key] = $f['default'] ?? null;
			}
		}
		return $out;
	}

	/** Valide strictement les valeurs POST (enum/color) ; lève sur valeur invalide. */
	public function validate(string $name, array $raw): array
	{
		$out = [];
		foreach ($this->schema($name) as $key => $f) {
			if (!array_key_exists($key, $raw)) {
				continue;
			}
			$out[$key] = $this->coerce($raw[$key], $f, $key);
		}
		return $out;
	}

	public function darkMode(string $name): string
	{
		$v = $this->config($name)['dark_mode'] ?? 'auto';
		return in_array($v, ['auto', 'light', 'dark'], true) ? (string) $v : 'auto';
	}

	/**
	 * ':root{--mxwm-*}' + bloc '[data-bs-theme=dark]{…}' pour les variantes
	 * *_dark. Chaque clé "X_color" du schéma (type color) produit --mxwm-X
	 * et ses déclinaisons (voir colorVars : -rgb, -text, teintes dérivées) ;
	 * la couleur claire s'applique dans les deux modes. Si "X_color_dark"
	 * est déclarée et a une valeur (enregistrée ou défaut du champ), la
	 * variante est émise sous [data-bs-theme=dark] ; sinon la couleur claire
	 * persiste en sombre.
	 * Ordre d'émission = ordre des clés claires du schéma.
	 */
	public function varsCss(string $name): string
	{
		$c = $this->config($name);
		$schema = $this->schema($name);
		$light = '';
		$dark = '';
		foreach ($schema as $key => $f) {
			if (($f['type'] ?? '') !== 'color' || !str_ends_with($key, '_color')) {
				continue;
			}
			$var = '--mxwm-' . str_replace('_', '-', substr($key, 0, -6));
			$v = $this->validHex($c[$key] ?? null) ?? $this->validHex($f['default'] ?? null);
			if ($v !== null) {
				$light .= $this->colorVars($var, $v);
			}
			// Variante sombre : seulement si la clé *_dark est déclarée et
			// résout une valeur ; vide sans défaut = la claire persiste.
			if (isset($schema[$key . '_dark'])) {
				$d = $this->validHex($c[$key . '_dark'] ?? null)
					?? $this->validHex($schema[$key . '_dark']['default'] ?? null);
				if ($d !== null) {
					$dark .= $this->colorVars($var, $d);
				}
			}
		}
		return ':root{' . $light . '}'
			. ($dark !== '' ? '[data-bs-theme=dark]{' . $dark . '}' : '');
	}

	/** Attributs à poser sur la balise <html> des vues du template. */
	public function htmlAttrs(string $name): string
	{
		return ' data-dark-mode="' . $this->darkMode($name) . '"';
	}

	/** Bloc <style id="theme-config"> à injecter après le <link> du template. */
	public function styleBlock(string $name): string
	{
		return '<style id="theme-config">' . $this->varsCss($name) . '</style>';
	}

	/** Map name => {darkMode, varsCss, config} - pour appdata (switch à chaud). */
	public function themeData(): array
	{
		// Cache disque (storage/cache/themedata.php) : le bloc est identique pour
		// tous les utilisateurs et ne change que sur édition de fichiers
		// (theme.json extends / config.schema.json) ou de la config en base.
		// Signature = mtime des fichiers + config de la table themes.
		$sig = $this->themeDataSignature();
		$cacheFile = $this->app->storageDir() . '/cache/themedata.php';
		if (is_file($cacheFile)) {
			$c = require $cacheFile;
			if (is_array($c) && ($c['sig'] ?? null) === $sig
				&& is_array($c['data'] ?? null)) {
				return $c['data'];
			}
		}
		$out = [];
		foreach ($this->installed() as $name) {
			$out[$name] = [
				'darkMode'  => $this->darkMode($name),
				'varsCss'   => $this->varsCss($name),
				'config'    => $this->config($name),
			];
		}
		@mxwm_atomic_write($cacheFile, '<?php return '
			. var_export(['sig' => $sig, 'data' => $out], true) . ";\n");
		return $out;
	}

	/**
	 * Signature d'invalidation du cache themeData : mtime des theme.json et
	 * config.schema.json résolus (héritage) + configs de la table themes -
	 * un setConfig/setAllowed admin ou un fichier modifié change la signature.
	 */
	private function themeDataSignature(): string
	{
		$parts = [];
		foreach ($this->installed() as $name) {
			$parts[] = $name . ':' . (int) @filemtime($this->templatesDir() . "/{$name}/theme.json")
				. ':' . (int) @filemtime($this->resolve($name, 'config.schema.json'));
		}
		$parts[] = json_encode($this->app->themeRepo()->allConfigs());
		// Le contenu émis dépend aussi du code de varsCss : mtime de ce fichier
		// dans la signature > toute modification des variables invalide le cache.
		$parts[] = 'code:' . (int) @filemtime(__FILE__);
		return sha1(implode('|', $parts));
	}

	/**
	 * Script inline (nonce) à injecter dans <head> : résout data-dark-mode
	 * (auto|light|dark) vers le thème natif Bootstrap data-bs-theme, et suit
	 * les changements de préférence OS. Relit data-dark-mode à chaque appel
	 * pour gérer le changement de template à chaud.
	 */
	public function darkScript(): string
	{
		return <<<'JS'
(function () {
	var el = document.documentElement;
	function apply() {
		var mode = el.getAttribute('data-dark-mode') || 'auto';
		el.setAttribute('data-bs-theme',
			(mode === 'dark' || (mode === 'auto'
				&& window.matchMedia('(prefers-color-scheme: dark)').matches))
				? 'dark' : 'light');
	}
	apply();
	var mql = window.matchMedia('(prefers-color-scheme: dark)');
	if (mql.addEventListener) { mql.addEventListener('change', apply); }
	else if (mql.addListener) { mql.addListener(apply); }
})();
JS;
	}

	// ─── Helpers ───

	private function coerce(mixed $value, array $f, string $key = ''): mixed
	{
		return match ($f['type'] ?? 'string') {
			'bool'    => (bool) $value,
			'integer' => (int) $value,
			'number'  => (float) $value,
			// ''/null accepté : champ vidé = retour au défaut (couleurs claires)
			// ou à la teinte automatique (clés *_dark)
			'color'   => $this->validHex($value)
				?? ($value === '' || $value === null
					? '' : throw new \RuntimeException(
						$this->app->locale()->t('errors.invalid_value', ['key' => $key ?: 'color']), 400)),
			'enum'    => in_array($value, array_keys((array) ($f['options'] ?? [])), true)
				? $value : throw new \RuntimeException(
					$this->app->locale()->t('errors.invalid_value', ['key' => $key ?: 'enum']), 400),
			default   => (string) $value,
		};
	}

	private function validHex(mixed $v): ?string
	{
		return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : null;
	}

	private function hexToRgb(string $hex): string
	{
		$hex = ltrim($hex, '#');
		return implode(', ', [
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		]);
	}

	/**
	 * Déclarations CSS d'une couleur : var + -rgb + -text/-text-rgb (contraste)
	 * + teintes dérivées reproduisant les shade-color/tint-color Sass de
	 * Bootstrap (-hover −15 %, -active −20 %, -active-border −25 %, -tint +50 %
	 * pour les bordures de focus, -focus-rgb +15 % pour les halos de boutons,
	 * -active-rgb = -active en triplet rgb pour les rgba()).
	 */
	private function colorVars(string $var, string $hex): string
	{
		$text = $this->contrast($hex);
		$active = $this->mixTint($hex, 20);
		return $var . ':' . $hex . ';' . $var . '-rgb:' . $this->hexToRgb($hex)
			. ';' . $var . '-text:' . $text . ';' . $var . '-text-rgb:' . $this->hexToRgb($text)
			. ';' . $var . '-hover:' . $this->mixTint($hex, 15)
			. ';' . $var . '-active:' . $active . ';' . $var . '-active-rgb:' . $this->hexToRgb($active)
			. ';' . $var . '-active-border:' . $this->mixTint($hex, 25)
			. ';' . $var . '-tint:' . $this->mixTint($hex, 50, true)
			. ';' . $var . '-focus-rgb:' . $this->hexToRgb($this->mixTint($hex, 15, true)) . ';';
	}

	/** mix() de Sass : $pct % de noir (défaut, shade-color) ou de blanc (tint-color). */
	private function mixTint(string $hex, int $pct, bool $toWhite = false): string
	{
		$hex = ltrim($hex, '#');
		$t = $toWhite ? 255 : 0;
		$out = '#';
		foreach ([0, 2, 4] as $i) {
			$v = (int) round($t * $pct / 100
				+ hexdec(substr($hex, $i, 2)) * (100 - $pct) / 100);
			$out .= str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
		}
		return $out;
	}

	/** Texte lisible sur $hex : noir Bootstrap ou blanc selon la luminance perçue. */
	private function contrast(string $hex): string
	{
		$hex = ltrim($hex, '#');
		$lum = (hexdec(substr($hex, 0, 2)) * 299
			+ hexdec(substr($hex, 2, 2)) * 587
			+ hexdec(substr($hex, 4, 2)) * 114) / 1000;
		return $lum >= 150 ? '#212529' : '#ffffff';
	}
}