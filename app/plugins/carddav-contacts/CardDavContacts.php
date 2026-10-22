<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\CredentialCrypt;
use MXWebMailLib\DAV\Client as DavClient;

/**
 * Synchronisation CardDAV → contacts locaux (table contacts, source='carddav').
 * Config par utilisateur dans user_settings : url / user / pass (chiffré sodium).
 * API : /api/plugin/carddav-contacts/{config|save|test|sync}
 */
final class CardDavContacts extends PluginBase
{
	private const K_URL  = 'plugin.carddav.url';
	private const K_USER = 'plugin.carddav.user';
	private const K_PASS = 'plugin.carddav.pass';
	private const K_SYNC = 'plugin.carddav.last_sync';
	private const SOURCE = 'carddav';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			$data['pluginTabs'][] = [
				'id'     => 'carddav-contacts',
				'label'  => 'plugin.carddav-contacts.tab',
				'icon'   => 'fa-book',
				'js'     => 'carddav.js',
				'plugin' => 'carddav-contacts',
			];
			return $data;
		});
	}

	// ─── API ───

	public function apiConfig(Request $request, ?array $user): array
	{
		$uid = (int) $user['id'];
		$s = $this->app->settings();
		return [
			'url'      => (string) $s->get($uid, self::K_URL),
			'user'     => (string) $s->get($uid, self::K_USER),
			'hasPass'  => $s->get($uid, self::K_PASS) !== null,
			'lastSync' => (int) ($s->get($uid, self::K_SYNC) ?? 0),
		];
	}

	public function apiSave(Request $request, ?array $user): array
	{
		$uid = (int) $user['id'];
		$s = $this->app->settings();
		$url = trim((string) $request->input('url', ''));
		$login = trim((string) $request->input('user', ''));
		$pass = (string) $request->input('password', '');

		// Validation immédiate : HTTPS + hôte public (la config est utilisée
		// pour des requêtes authentifiées — pas de http ni d'IP privée).
		if ($url !== '') {
			$this->assertDavUrl($url);
		}

		$s->set($uid, self::K_URL, $url !== '' ? $url : null);
		$s->set($uid, self::K_USER, $login !== '' ? $login : null);
		// Mot de passe vide = conserver l'existant ; "reset" = effacer
		if ($pass === 'reset') {
			$s->set($uid, self::K_PASS, null);
		} elseif ($pass !== '') {
			$s->set($uid, self::K_PASS,
				CredentialCrypt::encrypt($pass, $this->app->storageDir()));
		}
		return ['ok' => true];
	}

	/** Test de connexion : découvre le carnet et compte les fiches. */
	public function apiTest(Request $request, ?array $user): array
	{
		[$client, $path] = $this->connect((int) $user['id']);
		return ['addressbook' => $path, 'cards' => count($this->listCards($client, $path))];
	}

	/** Synchronise toutes les fiches dans les contacts locaux (source=carddav). */
	public function apiSync(Request $request, ?array $user): array
	{
		$uid = (int) $user['id'];
		[$client, $path] = $this->connect($uid);

		$items = [];
		foreach ($this->listCards($client, $path) as $card) {
			$resp = $client->request('GET', $card['href']);
			if ($resp->status !== 200 || !is_string($resp->body)) {
				continue;
			}
			$contact = $this->parseVCard($resp->body, $card['uid']);
			if ($contact) {
				$items[] = $contact;
			}
		}

		$n = $this->app->contacts()->syncFrom($uid, self::SOURCE, $items);
		$this->app->settings()->set($uid, self::K_SYNC, (string) time());
		return ['synced' => $n];
	}

	// ─── CardDAV ───

	/** @return array{0:DavClient,1:string} client + chemin du carnet */
	private function connect(int $userId): array
	{
		$s = $this->app->settings();
		$url = (string) $s->get($userId, self::K_URL);
		$login = (string) $s->get($userId, self::K_USER);
		$encPass = $s->get($userId, self::K_PASS);
		if ($url === '' || $encPass === null) {
			throw new \RuntimeException($this->t('plugin.carddav-contacts.not_configured'), 400);
		}
		$pass = CredentialCrypt::decrypt($encPass, $this->app->storageDir());

		$client = $this->davClient($url, $login, $pass);
		$path = $client->urlPath;
		if (!$this->isAddressbook($client, $path)) {
			$path = $this->discoverAddressbook($client, $path);
		}
		if ($path === null) {
			throw new \RuntimeException($this->t('plugin.carddav-contacts.no_addressbook'), 400);
		}
		$client->urlPath = $path;
		return [$client, $path];
	}

	private function davClient(string $url, string $user, string $pass): DavClient
	{
		$parts = $this->assertDavUrl($url);
		$base = $parts['scheme'] . '://' . $parts['host']
			. (isset($parts['port']) ? ':' . $parts['port'] : '');

		$client = new DavClient([
			'baseUri'  => $base,
			'userName' => $user,
			'password' => $pass,
		]);
		$client->urlPath = isset($parts['path']) ? rtrim($parts['path'], '/') . '/' : '/';
		return $client;
	}

	/**
	 * Normalise et valide l'URL saisie : HTTPS obligatoire (les credentials
	 * Basic/Digest transitent en clair sinon) et hôte public uniquement —
	 * l'URL vient des réglages utilisateur, une IP privée/réservée serait
	 * un SSRF authentifié. Retourne les parts de l'URL normalisée.
	 */
	private function assertDavUrl(string $url): array
	{
		if (!preg_match('/^https?:\/\//i', $url)) {
			$url = 'https://' . $url;
		}
		$parts = parse_url($url) ?: [];
		if (($parts['scheme'] ?? '') !== 'https'
			|| empty($parts['host'])
			|| !self::isPublicHost((string) $parts['host'])) {
			throw new \RuntimeException($this->t('plugin.carddav-contacts.bad_url'), 400);
		}
		$parts['scheme'] = 'https';
		return $parts;
	}

	/** L'hôte résout-il vers une/des IP publiques ? (cf. plugin avatars) */
	private static function isPublicHost(string $host): bool
	{
		$h = trim($host, '[]');
		if ($h === '') {
			return false;
		}
		if (filter_var($h, FILTER_VALIDATE_IP)) {
			return (bool) filter_var($h, FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}
		// A + AAAA : gethostbyname (IPv4 seul) refuserait un hôte IPv6-only.
		$ips = [];
		foreach (dns_get_record($h, DNS_A + DNS_AAAA) ?: [] as $r) {
			if (isset($r['ip'])) {
				$ips[] = $r['ip'];
			}
			if (isset($r['ipv6'])) {
				$ips[] = $r['ipv6'];
			}
		}
		if (!$ips) {
			return false; // échec DNS → fail-closed
		}
		foreach ($ips as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return false; // une seule IP privée/réservée suffit à refuser
			}
		}
		return true;
	}

	private function isAddressbook(DavClient $client, string $path): bool
	{
		try {
			// Depth 0 → propFind renvoie directement les props de la ressource
			$props = $client->propFind($path, ['{DAV:}resourcetype'], 0);
			$rt = $props['{DAV:}resourcetype'] ?? [];
			return is_array($rt)
				&& in_array('{urn:ietf:params:xml:ns:carddav}addressbook', $rt, true);
		} catch (\Throwable) {
			return false;
		}
	}

	/** Découverte : current-user-principal → addressbook-home-set → carnets. */
	private function discoverAddressbook(DavClient $client, string $path): ?string
	{
		$props = [
			'{DAV:}current-user-principal',
			'{DAV:}resourcetype',
			'{DAV:}displayname',
			'{urn:ietf:params:xml:ns:carddav}addressbook-home-set',
		];
		try {
			$resp = $client->propFind('/.well-known/carddav', $props, 0)
				?: $client->propFind($path, $props, 0);
		} catch (\Throwable) {
			return null;
		}

		$principal = (string) ($resp['{DAV:}current-user-principal']['{DAV:}href'] ?? '');
		$homeSet = (string) ($resp['{urn:ietf:params:xml:ns:carddav}addressbook-home-set']['{DAV:}href'] ?? '');

		if (!$homeSet && $principal) {
			try {
				$p = $client->propFind($principal, $props, 0);
				$homeSet = (string) ($p['{urn:ietf:params:xml:ns:carddav}addressbook-home-set']['{DAV:}href'] ?? '');
			} catch (\Throwable) {}
		}
		if (!$homeSet) {
			return null;
		}
		// homeSet peut être une URL absolue
		if (preg_match('#^https?://#i', $homeSet)) {
			$homeSet = (string) parse_url($homeSet, PHP_URL_PATH);
		}

		try {
			foreach ($client->propFind(rtrim($homeSet, '/') . '/', $props, 1) as $href => $item) {
				if (is_array($item['{DAV:}resourcetype'] ?? null)
					&& in_array('{urn:ietf:params:xml:ns:carddav}addressbook', $item['{DAV:}resourcetype'], true)) {
					return $href;
				}
			}
		} catch (\Throwable) {}
		return null;
	}

	/** Liste les fiches .vcf du carnet : [{href, uid, etag}]. */
	private function listCards(DavClient $client, string $path): array
	{
		$resp = $client->propFind($path, ['{DAV:}getetag', '{DAV:}resourcetype'], 1);
		$cards = [];
		foreach ($resp as $href => $item) {
			$href = rtrim(trim((string) $href), '/');
			if ($href === '' || empty($item['{DAV:}getetag'])) {
				continue;
			}
			if (is_array($item['{DAV:}resourcetype'] ?? null)
				&& in_array('{DAV:}collection', $item['{DAV:}resourcetype'], true)) {
				continue; // c'est le carnet lui-même
			}
			$file = urldecode(basename($href));
			$cards[] = [
				'href' => $href,
				'uid'  => preg_replace('/\.vcf$/i', '', $file),
				'etag' => trim($item['{DAV:}getetag'], '"\''),
			];
		}
		return $cards;
	}

	private function parseVCard(string $vcf, string $uid): ?array
	{
		try {
			$card = \Sabre\VObject\Reader::read($vcf);
		} catch (\Throwable) {
			return null;
		}
		$emails = [];
		if (isset($card->EMAIL)) {
			foreach ($card->EMAIL as $e) {
				$mail = trim((string) $e);
				if (filter_var($mail, FILTER_VALIDATE_EMAIL)) {
					$emails[] = $mail;
				}
			}
		}
		if (!$emails) {
			return null; // sans email, inutile pour un webmail
		}
		return [
			'name'       => trim((string) ($card->FN ?? $card->NICKNAME ?? '')) ?: $emails[0],
			'emails'     => array_values(array_unique($emails)),
			'source_uid' => (string) ($card->UID ?? $uid),
			'properties' => ['org' => (string) ($card->ORG ?? ''), 'tel' => (string) ($card->TEL ?? '')],
		];
	}
}
