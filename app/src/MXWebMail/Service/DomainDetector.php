<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MXWebMail\Application\App;

/**
 * Détection de la configuration mail d'un domaine (format table "domains").
 * Sources, dans l'ordre : DNS SRV (RFC 6186), autoconfig Mozilla (+ ISPDB),
 * heuristique MX, sondage de ports. Quelle que soit la source, ManageSieve
 * (4190) est sondé en repli sur l'hôte IMAP - et sieve.<d> quand le sondage
 * est admis. Chaque sonde est bornée à ~3 s ; les échecs sont mis en cache
 * négatif 24 h (les succès sont persistés en table par l'appelant - pas de
 * cache positif).
 */
final class DomainDetector
{
	private const OK_PORTS = [143, 993, 465, 587, 25, 4190];

	/** Suffixe MX > [imap_host, smtp_host, smtp_port, smtp_encryption]. */
	private const MX_PROVIDERS = [
		'google.com'         => ['imap.gmail.com', 'smtp.gmail.com', 465, 'ssl'],
		'googlemail.com'     => ['imap.gmail.com', 'smtp.gmail.com', 465, 'ssl'],
		'outlook.com'        => ['outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
		'protection.outlook.com' => ['outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
		'yahoodns.net'       => ['imap.mail.yahoo.com', 'smtp.mail.yahoo.com', 465, 'ssl'],
		'ovh.net'            => ['ssl0.ovh.net', 'ssl0.ovh.net', 465, 'ssl'],
		'gandi.net'          => ['mail.gandi.net', 'mail.gandi.net', 465, 'ssl'],
		'infomaniak.com'     => ['mail.infomaniak.com', 'mail.infomaniak.com', 465, 'ssl'],
		'zoho.com'           => ['imap.zoho.com', 'smtp.zoho.com', 465, 'ssl'],
		'messagingengine.com' => ['imap.fastmail.com', 'smtp.fastmail.com', 465, 'ssl'],
	];

	public function __construct(private App $app) {}

	private function cfg(): array
	{
		return (array) $this->app->config()->get('autodetect', []);
	}

	/**
	 * Détecte la config d'un domaine. null si rien de fiable trouvé.
	 * $force = action admin explicite : ignore le cache négatif et active
	 * toutes les sources (ISPDB, sondage) même si désactivées en config.
	 * $source (sortie) reçoit la source gagnante : srv | autoconfig |
	 * mx | probe - 'probe' est la seule preuve "faible" (un hôte
	 * colocalisé avec l'IMAP répond sans être le domaine mail visé).
	 */
	public function detect(string $domain, bool $force = false, ?string &$source = null): ?array
	{
		$source = null;
		$domain = self::normalizeDomain($domain);
		if ($domain === null || (!$force && $this->negCached($domain))) {
			return null;
		}
		$cfg = $this->cfg();
		$res = null;
		if ($res = $this->fromSrv($domain)) {
			$source = 'srv';
		} elseif ($res = $this->fromAutoconfig($domain,
			$force || !empty($cfg['use_ispdb']))) {
			$source = 'autoconfig';
		} elseif ($res = $this->fromMx($domain)) {
			$source = 'mx';
		} elseif (($force || !empty($cfg['probe']))
			&& ($res = $this->fromProbe($domain))) {
			$source = 'probe';
		}
		$res = $res ? $this->normalizeResult($res, $domain) : null;
		// ManageSieve : presque toujours sur l'hôte IMAP (Dovecot). sieve.<d>
		// n'est tenté que quand le sondage est admis (force|probe) - pas de
		// scan non sollicité au login. Sondes TCP bornées à ~3 s chacune.
		if ($res !== null && empty($res['sieve_host'])) {
			$candidates = ($force || !empty($cfg['probe']))
				? ["sieve.{$domain}", $res['imap_host']] : [$res['imap_host']];
			foreach (array_unique($candidates) as $h) {
				if (self::bannerSieve($h)) {
					$res['sieve_host'] = $h;
					$res['sieve_port'] = 4190;
					break;
				}
			}
		}
		if ($res === null) {
			$source = null;
			$this->negCache($domain);
		}
		return $res;
	}

	/**
	 * Domaines mail probables d'un hôte HTTP : l'hôte lui-même puis ses
	 * parents en retirant le label le plus à gauche (un vhost peut être
	 * un sous-domaine du domaine mail, ex. w.mxrelay.fr > mxrelay.fr).
	 * Borné à 4 candidats ; hôte vide, IP ou sans point > [].
	 */
	public static function hostCandidates(string $httpHost): array
	{
		$host = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '',
			strtolower(trim($httpHost))));
		if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
			return [];
		}
		$out = [];
		for ($d = $host; substr_count($d, '.') >= 1 && count($out) < 4;
			$d = substr($d, strpos($d, '.') + 1)) {
			$out[] = $d;
		}
		return $out;
	}

	private static function normalizeDomain(string $domain): ?string
	{
		$domain = strtolower(trim($domain));
		$ascii = function_exists('idn_to_ascii') && $domain !== ''
			? idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $domain;
		$domain = is_string($ascii) ? $ascii : $domain;
		return preg_match('/^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$/', $domain)
			&& str_contains($domain, '.') ? $domain : null;
	}

	// ─── DNS SRV (RFC 6186) ───

	private function fromSrv(string $d): ?array
	{
		$imap = self::srvFirst("_imaps._tcp.{$d}", 'ssl')
			?? self::srvFirst("_imap._tcp.{$d}", 'tls');
		if (!$imap) {
			return null;
		}
		$smtp = self::srvFirst("_submissions._tcp.{$d}", 'ssl')
			?? self::srvFirst("_submission._tcp.{$d}", 'tls');
		$sieve = self::srvFirst("_sieve._tcp.{$d}", 'none');
		$out = [
			'imap_host' => $imap[0], 'imap_port' => $imap[1], 'imap_encryption' => $imap[2],
			'smtp_host' => $smtp[0] ?? '', 'smtp_port' => $smtp[1] ?? 465,
			'smtp_encryption' => $smtp[2] ?? 'ssl',
		];
		if ($sieve) {
			$out['sieve_host'] = $sieve[0];
			$out['sieve_port'] = $sieve[1];
		}
		return $out;
	}

	/** Premier enregistrement SRV (déjà trié par priorité) > [host, port, enc]. */
	private static function srvFirst(string $name, string $enc): ?array
	{
		$recs = \MXWebMailLib\DNS::SRV($name);
		$rec = $recs[0] ?? null;
		return $rec && !empty($rec['target']) && (int) ($rec['port'] ?? 0) > 0
			? [$rec['target'], (int) $rec['port'], $enc] : null;
	}

	// ─── Autoconfig Mozilla ───

	private function fromAutoconfig(string $d, bool $ispdb): ?array
	{
		foreach (["https://autoconfig.{$d}/mail/config-v1.1.xml",
			"https://{$d}/.well-known/autoconfig/mail/config-v1.1.xml"] as $url) {
			$xml = $this->fetch($url);
			if ($xml !== null && ($res = $this->parseAutoconfig($xml))) {
				return $res;
			}
		}
		if ($ispdb) {
			$xml = $this->fetch("https://autoconfig.thunderbird.net/v1.1/{$d}");
			if ($xml !== null && ($res = $this->parseAutoconfig($xml))) {
				return $res;
			}
		}
		return null;
	}

	private function fetch(string $url): ?string
	{
		try {
			$req = \MXWebMailLib\HTTP\Request::factory();
			$req->timeout = 3;
			$req->max_response_kb = 64;
			$req->verify_peer = true;
			$ca = (string) (((array) $this->app->config()->get('ssl'))['cafile'] ?? '');
			if ($ca !== '') {
				$req->setCABundleFile($ca);
			}
			$res = $req->doRequest('GET', $url);
			return $res && $res->status === 200 && is_string($res->body)
				? $res->body : null;
		} catch (\Throwable) {
			return null;
		}
	}

	/** Parse clientConfig Mozilla - namespaces ignorés via local-name(). */
	private function parseAutoconfig(string $xml): ?array
	{
		$prev = libxml_use_internal_errors(true);
		$doc = new \DOMDocument();
		$ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_use_internal_errors($prev);
		if (!$ok) {
			return null;
		}
		$xp = new \DOMXPath($doc);
		$text = fn(string $q, ?\DOMNode $ctx = null): string =>
			trim((string) ($xp->query($q, $ctx)->item(0)->textContent ?? ''));
		$pick = function (string $kind, string $type) use ($xp, $text): ?array {
			$nodes = $xp->query("//*[local-name()='{$kind}'][@type='{$type}']");
			if (!$nodes || !$nodes->length) {
				return null;
			}
			$n = $nodes->item(0);
			$host = $text(".//*[local-name()='hostname']", $n);
			$port = (int) $text(".//*[local-name()='port']", $n);
			$socket = strtolower($text(".//*[local-name()='socketType']", $n));
			$user = $text(".//*[local-name()='username']", $n);
			if ($host === '') {
				return null;
			}
			return [
				'host' => $host,
				'port' => $port,
				'enc' => $socket === 'starttls' ? 'tls' : ($socket === 'plain' ? 'none' : 'ssl'),
				'shortLogin' => strtoupper($user) === '%EMAILLOCALPART%',
			];
		};
		$imap = $pick('incomingServer', 'imap');
		if (!$imap) {
			return null;
		}
		$smtp = $pick('outgoingServer', 'smtp');
		$out = [
			'imap_host' => $imap['host'], 'imap_port' => $imap['port'],
			'imap_encryption' => $imap['enc'],
			'smtp_host' => $smtp['host'] ?? '', 'smtp_port' => $smtp['port'] ?? 465,
			'smtp_encryption' => $smtp['enc'] ?? 'ssl',
		];
		if ($imap['shortLogin']) {
			$out['options'] = ['imap' => ['shortLogin' => true]];
		}
		return $out;
	}

	// ─── Heuristique MX ───

	private function fromMx(string $d): ?array
	{
		foreach (\MXWebMailLib\DNS::MX($d) as $mx) {
			$mx = strtolower((string) $mx);
			foreach (self::MX_PROVIDERS as $suffix => [$imap, $smtp, $sport, $senc]) {
				if ($mx === $suffix || str_ends_with($mx, '.' . $suffix)) {
					return [
						'imap_host' => $imap, 'imap_port' => 993, 'imap_encryption' => 'ssl',
						'smtp_host' => $smtp, 'smtp_port' => $sport, 'smtp_encryption' => $senc,
					];
				}
			}
		}
		return null;
	}

	// ─── Sondage de ports (bannières) ───

	private function fromProbe(string $d): ?array
	{
		$imap = null;
		foreach (["imap.{$d}", "mail.{$d}", $d] as $h) {
			if (self::banner($h, 993, true, '*')) {
				$imap = [$h, 993, 'ssl'];
				break;
			}
			if (self::banner($h, 143, false, '*')) {
				$imap = [$h, 143, 'tls'];
				break;
			}
		}
		if (!$imap) {
			return null;
		}
		$smtp = null;
		foreach (["smtp.{$d}", "mail.{$d}", $d] as $h) {
			if (self::banner($h, 465, true, '2')) {
				$smtp = [$h, 465, 'ssl'];
				break;
			}
			if (self::banner($h, 587, false, '2')) {
				$smtp = [$h, 587, 'tls'];
				break;
			}
		}
		return [
			'imap_host' => $imap[0], 'imap_port' => $imap[1], 'imap_encryption' => $imap[2],
			'smtp_host' => $smtp[0] ?? '', 'smtp_port' => $smtp[1] ?? 465,
			'smtp_encryption' => $smtp[2] ?? 'ssl',
		];
	}

	/** Connecte et vérifie que la bannière commence par $prefix (* OK / 220). */
	private static function banner(string $host, int $port, bool $ssl, string $prefix): bool
	{
		$errno = 0;
		$err = '';
		$ctx = stream_context_create(['ssl' => [
			'verify_peer' => true, 'verify_peer_name' => true,
		]]);
		$fp = @stream_socket_client(
			($ssl ? 'ssl' : 'tcp') . "://{$host}:{$port}",
			$errno, $err, 3, STREAM_CLIENT_CONNECT, $ctx);
		if (!$fp) {
			return false;
		}
		stream_set_timeout($fp, 3);
		$line = (string) fgets($fp, 512);
		fclose($fp);
		return str_starts_with(trim($line), $prefix);
	}

	/**
	 * Bannière ManageSieve (RFC 5804) : le serveur émet ses capacités en
	 * lignes ""CLE" ["val"]" (IMPLEMENTATION, SIEVE, SASL, STARTTLS…)
	 * terminées par OK/NO/BYE - un refus identifie quand même le service.
	 */
	private static function bannerSieve(string $host, int $port = 4190): bool
	{
		$errno = 0;
		$err = '';
		$fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $err, 3,
			STREAM_CLIENT_CONNECT);
		if (!$fp) {
			return false;
		}
		stream_set_timeout($fp, 3);
		$data = '';
		for ($i = 0; $i < 8 && !feof($fp); $i++) {
			$line = fgets($fp, 512);
			if ($line === false) {
				break;
			}
			$data .= $line;
			// Statut final lu ou lecture expirée > inutile d'attendre
			if (preg_match('/^(OK|NO|BYE)\b/i', $line)
				|| stream_get_meta_data($fp)['timed_out']) {
				break;
			}
		}
		fclose($fp);
		return self::sieveGreetingOk($data);
	}

	/** Bannière ManageSieve valide : ≥ 1 capacité ""CLE"" + statut OK/NO/BYE. */
	private static function sieveGreetingOk(string $data): bool
	{
		$capa = $status = false;
		foreach (preg_split('/\r?\n/', $data) as $line) {
			if (preg_match(
				'/^"(IMPLEMENTATION|SIEVE|SASL|STARTTLS|VERSION|NOTIFY|OWNER)"/i',
				$line)) {
				$capa = true;
			} elseif (preg_match('/^(OK|NO|BYE)\b/i', $line)) {
				$status = true;
				break;
			}
		}
		return $capa && $status;
	}

	// ─── Normalisation du résultat / cache négatif ───

	private function normalizeResult(array $r, string $domain): ?array
	{
		$hostOk = fn(string $h): bool => (bool) preg_match(
			'/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $h);
		if (empty($r['imap_host']) || !$hostOk((string) $r['imap_host'])) {
			return null;
		}
		$out = [
			'name' => $domain,
			'imap_host' => (string) $r['imap_host'],
			'imap_port' => self::port($r['imap_port'] ?? 993, 993),
			'imap_encryption' => self::enc($r['imap_encryption'] ?? 'ssl'),
			'smtp_host' => (string) ($r['smtp_host'] ?? ''),
			'smtp_port' => self::port($r['smtp_port'] ?? 465, 465),
			'smtp_encryption' => self::enc($r['smtp_encryption'] ?? 'ssl'),
			'sieve_host' => ($r['sieve_host'] ?? '') !== '' && $hostOk((string) $r['sieve_host'])
				? (string) $r['sieve_host'] : null,
			'sieve_port' => self::port($r['sieve_port'] ?? 4190, 4190),
			'options' => ['detected' => true] + (array) ($r['options'] ?? []),
			'active' => 1,
		];
		if ($out['smtp_host'] !== '' && !$hostOk($out['smtp_host'])) {
			$out['smtp_host'] = '';
		}
		return $out;
	}

	private static function port(mixed $p, int $default): int
	{
		$p = (int) $p;
		return in_array($p, self::OK_PORTS, true) ? $p : $default;
	}

	private static function enc(string $e): string
	{
		return in_array($e, ['ssl', 'tls', 'none'], true) ? $e : 'ssl';
	}

	/** Section "cache" - enabled=false court-circuite le cache négatif. */
	private function cacheCfg(): array
	{
		$c = $this->app->config()->get('cache', []);
		return is_array($c) ? $c : [];
	}

	private function negFile(): string
	{
		$dir = $this->app->storageDir() . '/cache';
		is_dir($dir) || mkdir($dir, 0700, true);
		return $dir . '/domain-detect.json';
	}

	private function negCached(string $d): bool
	{
		if (!($this->cacheCfg()['enabled'] ?? true)) {
			return false;
		}
		$file = $this->negFile();
		if (!is_file($file)) {
			return false;
		}
		$fp = fopen($file, 'rb');
		if (!$fp) {
			return false;
		}
		flock($fp, LOCK_SH);
		$all = json_decode((string) stream_get_contents($fp), true) ?: [];
		flock($fp, LOCK_UN);
		fclose($fp);
		return (int) ($all[$d] ?? 0) > time();
	}

	private function negCache(string $d): void
	{
		if (!($this->cacheCfg()['enabled'] ?? true)) {
			return;
		}
		$ttl = (int) ($this->cacheCfg()['domain_detect_ttl'] ?? 86400);
		$fp = fopen($this->negFile(), 'c+');
		if (!$fp) {
			return;
		}
		try {
			flock($fp, LOCK_EX);
			$all = json_decode((string) stream_get_contents($fp), true) ?: [];
			$all = array_filter($all, fn($ts) => (int) $ts > time());
			$all[$d] = time() + $ttl;
			rewind($fp);
			ftruncate($fp, 0);
			fwrite($fp, json_encode($all));
			fflush($fp);
		} finally {
			flock($fp, LOCK_UN);
			fclose($fp);
		}
	}
}
