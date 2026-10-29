<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Avatar de l'expéditeur dans le lecteur de message.
 * Sources par ordre : cache local → icône de domaine connue (embarquée)
 * → Gravatar → favicon du domaine → identicon généré (initiales).
 * Les images sont mises en cache dans storage/avatars.
 * API : /api/plugin/avatars/get?email= → { dataUrl } ou { svg }.
 */
final class Avatars extends PluginBase
{
	private const CACHE_TTL = 30 * 86400; // 30 jours

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			$data['features']['avatars'] = true;
			return $data;
		});
	}

	/** Retourne l'avatar d'une adresse : {dataUrl:"data:image/…"} ou {svg:"…"}. */
	public function apiGet(Request $request, ?array $user): array
	{
		$email = mb_strtolower(trim((string) $request->input('email', '')));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new \RuntimeException($this->t('plugin.avatars.email_invalid'), 400);
		}

		// 1. Cache local (storage/avatars/<sha1>.<ext>)
		if ($cached = $this->cached($email)) {
			return $cached;
		}

		$domain = substr($email, strrpos($email, '@') + 1);

		// 2. Icône de domaine connue (vendored dans images/services)
		if (!empty($this->config['service'] ?? true)
			&& ($icon = $this->serviceIcon($domain))) {
			return $icon;
		}

		// 3. Gravatar (404 = pas d'avatar)
		if (!empty($this->config['gravatar'])) {
			$found = $this->fetchImage(
				'https://gravatar.com/avatar/' . md5($email) . '?s=80&d=404');
			if ($found) {
				$this->cache($email, $found[0], $found[1]);
				return $found[2];
			}
		}

		// 4. Favicon du domaine
		if (!empty($this->config['favicon']) && $domain !== '') {
			foreach ([
				"https://{$domain}/favicon.ico",
				'https://www.' . self::serviceDomain($domain) . '/favicon.ico',
			] as $url) {
				$found = $this->fetchImage($url);
				if ($found) {
					$this->cache($email, $found[0], $found[1]);
					return $found[2];
				}
			}
		}

		// 5. Identicon : initiales sur couleur dérivée du hash (local, gratuit)
		if (($this->config['identicon'] ?? 'initials') !== 'none') {
			return ['svg' => self::initialsSvg($email)];
		}

		throw new \RuntimeException($this->t('plugin.avatars.not_found'), 404);
	}

	// ─── Sources ───

	private function serviceIcon(string $domain): ?array
	{
		foreach ([$domain, self::serviceDomain($domain)] as $d) {
			$file = __DIR__ . '/images/services/' . $d . '.png';
			if (is_file($file)) {
				return ['dataUrl' => 'data:image/png;base64,'
					. base64_encode((string) file_get_contents($file))];
			}
		}
		return null;
	}

	/** Réduit un sous-domaine au domaine registrable (approximation legacy). */
	private static function serviceDomain(string $domain): string
	{
		$domain = preg_replace('/^(.+\.)?(paypal\.[a-z][a-z])$/D', 'paypal.com', $domain);
		$domain = preg_replace('/^facebookmail\.com$/D', 'facebook.com', $domain);
		$domain = preg_replace('/^dhlparcel\.nl$/D', 'dhl.com', $domain);
		$domain = preg_replace('/^amazon\.nl$/D', 'amazon.com', $domain);
		return (string) preg_replace('/^.+\.([^.]+\.[^.]+)$/D', '$1', $domain);
	}

	/** Fetch image distante. Retourne [mime, binaire, réponse JSON] ou null. */
	private function fetchImage(string $url): ?array
	{
		if (!$this->app->isPublicHost((string) parse_url($url, PHP_URL_HOST))) {
			\MXWebMailLib\Log::warning('AVATARS', 'fetch refusé (hôte non public) : ' . $url);
			return null;
		}
		try {
			$http = \MXWebMailLib\HTTP\Request::factory();
			$http->timeout = 8;
			$http->max_response_kb = 256;
			// doRequest suit les redirects sans re-valider l'IP : jamais de suivi.
			$http->max_redirects = 0;
			$response = $http->doRequest('GET', $url);
			$mime = $response?->getHeader('content-type');
			$mime = is_array($mime) ? $mime[0] : (string) $mime;
			if ($response && $response->status === 200
				&& str_starts_with($mime, 'image/') && $response->body !== '') {
				return [$mime, $response->body,
					['dataUrl' => "data:{$mime};base64," . base64_encode($response->body)]];
			}
		} catch (\Throwable) {
		}
		return null;
	}

	// ─── Cache disque ───

	private function dir(): string
	{
		$dir = $this->app->storageDir() . '/avatars';
		is_dir($dir) || mkdir($dir, 0700, true);
		return $dir;
	}

	private function cached(string $email): ?array
	{
		foreach (glob($this->dir() . '/' . sha1($email) . '.*') ?: [] as $file) {
			// TTL : un avatar en cache > N jours est refetché au prochain
			// affichage — sinon les icônes n'évolueraient jamais et le
			// dossier grossirait sans borne (auto-nettoyant, pas de GC).
			if (filemtime($file) < time() - self::CACHE_TTL) {
				@unlink($file);
				return null;
			}
			$mime = \MXWebMailLib\File\MimeType::fromFilename(basename($file))
				?? 'image/png';
			return ['dataUrl' => "data:{$mime};base64,"
				. base64_encode((string) file_get_contents($file))];
		}
		return null;
	}

	private function cache(string $email, string $mime, string $body): void
	{
		$ext = \MXWebMailLib\File\MimeType::toExtension($mime) ?? '.img';
		file_put_contents($this->dir() . '/' . sha1($email) . $ext, $body, LOCK_EX);
	}

	/** Initiales + couleur stable dérivée du hash de l'email. */
	private static function initialsSvg(string $email): string
	{
		$initials = mb_strtoupper(mb_substr(preg_replace('/^(.).*[_.\-]?(.+)?@.*$/', '$1$2', $email) ?: mb_substr($email, 0, 2), 0, 2));
		$hue = hexdec(substr(sha1($email), 0, 6)) % 360;
		$bg = self::hslToHex($hue, 55, 42);
		return '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">'
			. '<rect width="80" height="80" rx="8" fill="' . $bg . '"/>'
			. '<text x="50%" y="54%" dy=".35em" text-anchor="middle" font-family="sans-serif"'
			. ' font-size="32" font-weight="600" fill="#fff">' . $initials . '</text></svg>';
	}

	private static function hslToHex(int $h, int $s, int $l): string
	{
		$s /= 100;
		$l /= 100;
		$c = (1 - abs(2 * $l - 1)) * $s;
		$x = $c * (1 - abs(fmod($h / 60, 2) - 1));
		$m = $l - $c / 2;
		[$r, $g, $b] = match (intdiv($h, 60) % 6) {
			0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x],
			3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
		};
		return sprintf('#%02x%02x%02x',
			(int) round(($r + $m) * 255),
			(int) round(($g + $m) * 255),
			(int) round(($b + $m) * 255));
	}
}
