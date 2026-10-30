<?php
declare(strict_types=1);

namespace Plugin;

use MailSo\Base\Http;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Http\Router;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * « Pièce jointe par lien » : les fichiers trop gros pour le SMTP sont
 * hébergés sous storage/filelink/<token> et le corps du message reçoit un
 * lien de téléchargement expirable. Sécurité : token 128 bits non
 * listable, TTL, download toujours « attachment » + nosniff (un .html
 * uploadé ne s'exécute jamais sur l'origine du webmail), noindex.
 */
final class Filelink extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data, ?array $user) {
			if ($user) {
				$data['features']['filelink'] = [
					'max_size' => $this->maxSize()];
			}
			return $data;
		});
		$hooks->on('app.routes', function (Router $router) {
			$router->get('/filelink/{token}', [$this, 'download']);
		});
	}

	private function dir(): string
	{
		$dir = $this->app->storageDir() . '/filelink';
		is_dir($dir) || mkdir($dir, 0750, true);
		return $dir;
	}

	private function maxSize(): int
	{
		return max(1, (int) ($this->config['max_size'] ?? 100));
	}

	private function ttl(): int
	{
		return max(1, (int) ($this->config['ttl_days'] ?? 30)) * 86400;
	}

	/** POST /api/plugin/filelink/upload — fichier → {url,name,size}. */
	public function apiUpload(Request $request, ?array $user): array
	{
		$f = $request->files['file'] ?? null;
		if (!$f || !is_uploaded_file((string) $f['tmp_name'])) {
			throw new \RuntimeException(
				$this->t('plugin.filelink.error_upload'), 400);
		}
		$size = (int) $f['size'];
		if ($size <= 0 || $size > $this->maxSize() * 1048576) {
			throw new \RuntimeException(
				$this->t('plugin.filelink.error_too_big',
					['mb' => $this->maxSize()]), 400);
		}
		$this->gc();
		$token = bin2hex(random_bytes(16));
		$dest = $this->dir() . '/' . $token;
		if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
			throw new \RuntimeException(
				$this->t('plugin.filelink.error_upload'), 500);
		}
		$name = trim(basename(str_replace('\\', '/',
			(string) ($f['name'] ?? 'fichier')))) ?: 'fichier';
		file_put_contents($dest . '.json', json_encode([
			'name' => $name, 'size' => $size,
			'mime' => (string) ($f['type'] ?? ''),
			'ts'   => time(), 'user_id' => (int) $user['id'],
		]));
		// URL absolue : le lien part dans le corps du mail chez le
		// destinataire — scheme + host courants + sous-chemin éventuel.
		$http = Http::SingletonInstance();
		$base = ($http->IsSecure() ? 'https' : 'http') . '://'
			. $http->GetHost(false, false) . Request::basePath();
		return ['url' => $base . '/filelink/' . $token,
			'name' => $name, 'size' => $size];
	}

	/** GET /filelink/{token} — public, stream attachment forcé. */
	public function download(Request $request): Response
	{
		$token = (string) ($request->params['token'] ?? '');
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			return $this->gone();
		}
		$file = $this->dir() . '/' . $token;
		$meta = is_file($file . '.json')
			? json_decode((string) file_get_contents($file . '.json'), true)
			: null;
		// Ouverture avant en-têtes : un fichier disparu entre is_file et
		// l'ouverture (GC concurrente, suppression) doit rendre 410, pas 500.
		$in = is_file($file) ? @fopen($file, 'rb') : false;
		if ($in === false || !is_array($meta)
			|| (int) ($meta['ts'] ?? 0) < time() - $this->ttl()) {
			is_resource($in) && fclose($in);
			return $this->gone();
		}
		$name = str_replace(['"', "\r", "\n"], '', (string) $meta['name']);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('Content-Length: ' . (int) (fstat($in)['size'] ?? 0));
		header('X-Content-Type-Options: nosniff');
		header('X-Robots-Tag: noindex');
		$out = fopen('php://output', 'wb');
		stream_copy_to_stream($in, $out);
		fclose($in);
		fclose($out);
		return new Response('');
	}

	private function gone(): Response
	{
		return new Response(
			$this->t('plugin.filelink.expired'), 410,
			['Content-Type' => 'text/plain; charset=utf-8']);
	}

	/** GC opportuniste : fichiers + métas plus vieux que le TTL. */
	private function gc(): void
	{
		$cut = time() - $this->ttl();
		foreach (glob($this->dir() . '/*.json') ?: [] as $meta) {
			if (filemtime($meta) < $cut) {
				@unlink($meta);
				@unlink(substr($meta, 0, -5));
			}
		}
	}
}
