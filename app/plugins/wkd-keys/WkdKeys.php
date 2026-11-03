<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Récupération des clés publiques PGP des correspondants :
 *  - WKD (Web Key Directory) : formes "advanced" (openpgpkey.<d>) puis
 *    "direct" (<d>) - RFC draft-koch-openpgp-webkey-service ;
 *  - récolte des en-têtes Autocrypt (keydata=) des messages reçus ;
 *  - secours keys.openpgp.org (optionnel).
 * Anti-SSRF : hôtes validés via App::isPublicHost(), pas de suivi de
 * redirection, timeout et taille capés.
 */
final class WkdKeys extends PluginBase
{
	/** Alphabet z-base-32 (spécification WKD / zbase32.org). */
	private const ZB32 = 'ybndrfg8ejkmcpqxot1uwisza345h769';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data, ?array $user) {
			if ($user) {
				$data['features']['wkd_keys'] = true;
			}
			return $data;
		});
		$hooks->on('message.view', function (&$data, $msg) {
			// config.schema défaut true ; getConfig() = sauvegardé seul > ?? true
			if ((bool) ($this->config['autocrypt'] ?? true)) {
				$this->harvestAutocrypt($msg);
			}
		});
	}

	/**
	 * GET /api/plugin/wkd-keys/lookup?email=a@b.c >
	 * {found:true, armored, source} ou {found:false}.
	 * L'utilisateur authentifié consulte pour SES destinataires - pas de
	 * donnée privée exposée (les clés publiques le sont par nature).
	 */
	public function apiLookup(Request $request, ?array $user): array
	{
		$email = strtolower(trim((string) $request->input('email', '')));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new \RuntimeException($this->t('plugin.wkd-keys.bad_email'), 400);
		}
		// 1. Magasin Autocrypt récolté (message.view) - gratuit, aucun réseau.
		$uid = (int) ($user['id'] ?? $_SESSION['user_id'] ?? 0);
		$stored = $this->app->settings()->get(
			$uid, 'plugin.wkd.ac.' . $email, null);
		if (is_string($stored) && $stored !== '') {
			return ['found' => true, 'armored' => $stored, 'source' => 'autocrypt'];
		}
		// 2. WKD : advanced puis direct.
		[$local, $domain] = explode('@', $email, 2);
		$l = rawurlencode($local);
		$h = self::zhash($local);
		foreach ([
				"https://openpgpkey.{$domain}/.well-known/openpgpkey/{$domain}/hu/{$h}?l={$l}",
				"https://{$domain}/.well-known/openpgpkey/hu/{$h}?l={$l}"] as $url) {
			if (!$this->app->isPublicHost(
					(string) parse_url($url, PHP_URL_HOST))) {
				continue;
			}
			$key = $this->fetch($url);
			if ($key !== null) {
				return ['found' => true, 'armored' => $key, 'source' => 'wkd'];
			}
		}
		// 3. Secours keyserver (optionnel).
		if (!empty($this->config['keyserver_fallback'])
			&& $this->app->isPublicHost('keys.openpgp.org')) {
			$key = $this->fetch('https://keys.openpgp.org/vks/v1/by-email/'
				. rawurlencode($email));
			if ($key !== null) {
				return ['found' => true, 'armored' => $key, 'source' => 'keyserver'];
			}
		}
		return ['found' => false];
	}

	/**
	 * Récolte Autocrypt : si le message porte "Autocrypt: addr=X;
	 * keydata=Y" avec X == From, la clé est mémorisée pour l'utilisateur
	 * (dernier gagne). La garde addr==From empêche de planter une clé
	 * sous une adresse qui n'est pas l'expéditeur.
	 */
	private function harvestAutocrypt($msg): void
	{
		try {
			$hdr = (string) $msg->Headers->ValueByName('Autocrypt');
			if ($hdr === '') {
				return;
			}
			$addr = '';
			$keydata = '';
			foreach (explode(';', $hdr) as $p) {
				$kv = explode('=', trim($p), 2);
				if (count($kv) !== 2) {
					continue;
				}
				if (strtolower($kv[0]) === 'addr') {
					$addr = strtolower(trim($kv[1]));
				} elseif (strtolower($kv[0]) === 'keydata') {
					$keydata = trim($kv[1]);
				}
			}
			$from = '';
			if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/',
					(string) $msg->From(), $m)) {
				$from = strtolower($m[0]);
			}
			if ($addr === '' || $addr !== $from || $keydata === '') {
				return;
			}
			$bin = base64_decode($keydata, true);
			if ($bin === false || strlen($bin) > 200 * 1024) {
				return;
			}
			$this->app->settings()->set(
				(int) ($_SESSION['user_id'] ?? 0),
				'plugin.wkd.ac.' . $addr, self::armor($bin));
		} catch (\Throwable) { /* header exotique : silencieux */ }
	}

	/** GET HTTPS capée (timeout 8s, 512 Ko, pas de redirect). */
	private function fetch(string $url): ?string
	{
		try {
			$http = \MXWebMailLib\HTTP\Request::factory();
			$http->timeout = 8;
			$http->max_response_kb = 512;
			$http->max_redirects = 0;
			$r = $http->doRequest('GET', $url);
			if ($r && $r->status === 200 && $r->body !== '') {
				return str_starts_with($r->body, '-----BEGIN')
					? $r->body : self::armor($r->body);
			}
		} catch (\Throwable $e) {
			$this->app->log('debug', 'wkd-keys: ' . $e->getMessage());
		}
		return null;
	}

	private static function armor(string $bin): string
	{
		return "-----BEGIN PGP PUBLIC KEY BLOCK-----\r\n\r\n"
			. chunk_split(base64_encode($bin), 64, "\r\n")
			. "-----END PGP PUBLIC KEY BLOCK-----\r\n";
	}

	/** z-base-32 de SHA-1(local part minuscule) - 32 caractères. */
	private static function zhash(string $local): string
	{
		$bin = sha1(mb_strtolower($local), true);
		$out = '';
		$buf = 0;
		$bits = 0;
		foreach (str_split($bin) as $b) {
			$buf = ($buf << 8) | ord($b);
			$bits += 8;
			while ($bits >= 5) {
				$bits -= 5;
				$out .= self::ZB32[($buf >> $bits) & 31];
			}
		}
		if ($bits) {
			$out .= self::ZB32[($buf << (5 - $bits)) & 31];
		}
		return $out;
	}
}
