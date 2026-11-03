<?php
declare(strict_types=1);

namespace MXWebMail\Locale;

/**
 * Traduction minimaliste : dictionnaires PHP "clé => texte".
 * - Cœur      : app/src/MXWebMail/Locale/<lang>.php
 * - Plugins   : app/plugins/<name>/langs/<lang>.php (clés "plugin.<name>.*")
 * - Templates : app/templates/<name>/langs/<lang>.php (clés "theme.<name>.*")
 *
 * Fallback : langue de base 'en' > clé brute si absente (pass-through :
 * un texte non catalogué - ex. label de plugin sans dictionnaire - est
 * affiché tel quel).
 */
final class Translator
{
	public const FALLBACK = 'en';

	private array $dict = [];
	private array $files = [];

	/**
	 * @param string[] $dirs dossiers contenant des fichiers <lang>.php
	 * @param ?string $cacheFile dict fusionné mis en cache (revalidé par
	 *        filemtime de chaque source : 48 stats ≪ 48 require + fusion).
	 */
	public function __construct(private string $lang, array $dirs, ?string $cacheFile = null)
	{
		$files = [];
		foreach (array_unique([self::FALLBACK, $lang]) as $l) {
			foreach ($dirs as $dir) {
				$file = rtrim($dir, '/') . "/{$l}.php";
				if (is_file($file)) {
					$files[$file] = (int) filemtime($file);
				}
			}
		}
		$this->files = $files;
		if ($cacheFile !== null && is_file($cacheFile)) {
			$c = require $cacheFile;
			if (is_array($c) && ($c['files'] ?? null) === $files
				&& is_array($c['dict'] ?? null)) {
				$this->dict = $c['dict'];
				return;
			}
		}
		foreach (array_keys($files) as $file) {
			// array_replace : la langue active écrase le fallback,
			// les plugins écrasent le cœur en dernier.
			$this->dict = array_replace($this->dict, (array) require $file);
		}
		if ($cacheFile !== null) {
			// Écriture atomique : un require concurrent ne doit jamais
			// charger un cache tronqué ou périmé (opcache_invalidate inclus).
			@mxwm_atomic_write($cacheFile, '<?php return '
				. var_export(['files' => $files, 'dict' => $this->dict], true) . ";\n");
		}
	}

	public function lang(): string
	{
		return $this->lang;
	}

	public function dict(): array
	{
		return $this->dict;
	}

	/**
	 * Signature de contenu des sources (chemin => mtime) : change dès qu'un
	 * fichier de langue cœur/plugin/template bouge - utilisée en ?v= de
	 * l'URL du dict servi (/assets/i18n/{lang}.js) pour un cache immutable sûr.
	 */
	public function signature(): string
	{
		return substr(sha1(json_encode($this->files)), 0, 16);
	}

	/** Traduction de $key ; {param} interpolés via strtr ; clé brute si inconnue. */
	public function t(string $key, array $params = []): string
	{
		$text = (string) ($this->dict[$key] ?? $key);
		if ($params !== []) {
			$repl = [];
			foreach ($params as $k => $v) {
				$repl['{' . $k . '}'] = (string) $v;
			}
			$text = strtr($text, $repl);
		}
		return $text;
	}

	/** Langues disponibles dans un dossier de dictionnaires (<lang>.php). */
	public static function languages(string $dir): array
	{
		$out = [];
		foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $f) {
			$lang = basename($f, '.php');
			// Le dossier Locale contient aussi Translator.php (la classe).
			if ($lang === 'Translator') {
				continue;
			}
			$out[] = $lang;
		}
		sort($out);
		return $out;
	}

	/**
	 * Meilleure langue d'après Accept-Language ("fr-FR,fr;q=0.9,en;q=0.8"),
	 * restreinte aux $available ; fallback sur FALLBACK.
	 */
	public static function detect(array $available, ?string $header = null): string
	{
		$header ??= (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
		$prefs = [];
		foreach (explode(',', $header) as $i => $part) {
			$part = trim($part);
			if ($part === '' || $part === '*') {
				continue;
			}
			$q = 1.0;
			if (($p = strpos($part, ';')) !== false) {
				sscanf(substr($part, $p), '; q=%f', $q);
				$part = substr($part, 0, $p);
			}
			// À q égal, l'ordre d'apparition dans l'en-tête fait foi
			$prefs[strtolower(trim($part))] = $q * 100 - $i;
		}
		arsort($prefs);
		foreach (array_keys($prefs) as $tag) {
			foreach ($available as $lang) {
				if ($tag === $lang || str_starts_with($tag, $lang . '-')) {
					return $lang;
				}
			}
		}
		return in_array(self::FALLBACK, $available, true)
			? self::FALLBACK
			: ($available[0] ?? self::FALLBACK);
	}
}
