<?php
declare(strict_types=1);

/**
 * MXWebMail — bootstrap : autoloader maison, remplace Composer.
 *
 * - PSR-4 : MXWebMail\ → app/src/MXWebMail, Sabre\ → app/src/Sabre (vendoré)
 * - Classmap insensible à la casse : MailSo\ → app/src/MailSo, MXWebMailLib\ → app/src/Lib
 *   (code hérité : noms de fichiers en minuscules, multi-classes par fichier).
 *   Générée par scan token_get_all, cachée dans app/storage/cache/classmap.php,
 *   reconstruite automatiquement sur miss (une fois par requête).
 * - require : functions.php de sabre/uri et sabre/xml (autoload "files" Composer).
 */

$root = dirname(__DIR__);

// Fichiers de fonctions sabre (hors classes → pas d'autoload possible).
foreach ([
	'src/Sabre/Uri/functions.php',
	'src/Sabre/Xml/Deserializer/functions.php',
	'src/Sabre/Xml/Serializer/functions.php',
] as $f) {
	is_file("$root/$f") && require_once "$root/$f";
}

spl_autoload_register(function (string $class) use ($root): void {
	static $psr4 = [
		'MXWebMail\\' => 'src/MXWebMail/',
		'Sabre\\'     => 'src/Sabre/',
	];
	static $map = null, $rebuilt = false;

	foreach ($psr4 as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = "$root/$dir" . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			is_file($file) && require $file;
			return;
		}
	}

	if (!str_starts_with($class, 'MailSo\\') && !str_starts_with($class, 'MXWebMailLib\\')) {
		return;
	}

	if ($map === null) {
		$cache = "$root/storage/cache/classmap.php";
		$map = is_file($cache) ? require $cache : [];
	}
	$lc = strtolower($class);
	if (isset($map[$lc])) {
		require "$root/{$map[$lc]}";
		return;
	}

	// Miss : rescan une fois — couvre les classes ajoutées depuis le dernier cache.
	if (!$rebuilt) {
		$rebuilt = true;
		$map = mxwm_build_classmap($root);
		if (isset($map[$lc])) {
			require "$root/{$map[$lc]}";
		}
	}
});

/**
 * Scanne src/MailSo + src/Lib et produit la classmap (clés en minuscules,
 * chemins relatifs à la racine). Écrit le cache et le retourne.
 */
function mxwm_build_classmap(string $root): array
{
	$map = [];
	foreach (['src/MailSo', 'src/Lib'] as $dir) {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)
		);
		foreach ($it as $f) {
			if ($f->getExtension() !== 'php') {
				continue;
			}
			foreach (mxwm_declared_types((string) $f) as $type) {
				$rel = str_replace('\\', '/', substr((string) $f, strlen($root) + 1));
				$map[strtolower($type)] = $rel;
			}
		}
	}
	$cacheDir = "$root/storage/cache";
	is_dir($cacheDir) || @mkdir($cacheDir, 0755, true);
	// Écriture atomique (tmp + rename) : un require pendant la régénération
	// ne doit jamais charger un classmap tronqué.
	if (is_writable($cacheDir)) {
		mxwm_atomic_write("$cacheDir/classmap.php",
			"<?php return " . var_export($map, true) . ";\n");
	}
	return $map;
}

/**
 * Écriture atomique tmp + rename : un lecteur pendant l'écriture ne doit
 * jamais voir de contenu tronqué (classmap, caches « <?php return »,
 * PJ/images cachées). opcache_invalidate : un fichier PHP dont le contenu
 * change peut rester périmé sous opcache.validate_timestamps=0.
 */
function mxwm_atomic_write(string $path, string $content): bool
{
	$dir = dirname($path);
	is_dir($dir) || @mkdir($dir, 0755, true);
	$tmp = $path . '.' . getmypid() . '.tmp';
	if (file_put_contents($tmp, $content) === false || !@rename($tmp, $path)) {
		@unlink($tmp);
		return false;
	}
	if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) {
		@opcache_invalidate($path, true);
	}
	return true;
}

/** Types (FQCN) déclarés dans un fichier : class/interface/trait/enum. */
function mxwm_declared_types(string $file): array
{
	$tok = token_get_all((string) file_get_contents($file));
	$ns = '';
	$types = [];
	$n = count($tok);
	for ($i = 0; $i < $n; $i++) {
		$t = $tok[$i];
		if (!is_array($t)) {
			continue;
		}
		if ($t[0] === T_NAMESPACE) {
			$ns = '';
			for ($j = $i + 1; $j < $n && $tok[$j] !== ';' && $tok[$j] !== '{'; $j++) {
				is_array($tok[$j]) && in_array($tok[$j][0], [T_STRING, T_NAME_QUALIFIED], true)
					&& $ns .= $tok[$j][1];
			}
			continue;
		}
		if (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
			// ::class et new class (anonyme) → ignorer
			for ($k = $i - 1; $k >= 0 && is_array($tok[$k]) && $tok[$k][0] === T_WHITESPACE; $k--);
			if ($k >= 0 && ($tok[$k] === ':' || (is_array($tok[$k]) && $tok[$k][0] === T_NEW))) {
				continue;
			}
			for ($j = $i + 1; $j < $n; $j++) {
				if (is_array($tok[$j]) && $tok[$j][0] === T_STRING) {
					$types[] = "$ns\\{$tok[$j][1]}";
					break;
				}
				if (!is_array($tok[$j]) || $tok[$j][0] !== T_WHITESPACE) {
					break;
				}
			}
		}
	}
	return $types;
}
