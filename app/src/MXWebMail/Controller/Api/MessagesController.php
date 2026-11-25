<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Files;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Service\EmlParser;
use MXWebMail\UserFacingException;




final class MessagesController extends BaseController
{
	use ProxyGuard;

	public function messages(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->listData([
			'folder'     => $request->input('folder', 'INBOX'),
			'page'       => $request->input('page', 1),
			'limit'      => $request->input('limit', 50),
			'search'     => $request->input('search', ''),
			'sort'       => $request->input('sort', ''),
			'threads'    => $request->input('threads', false),
			'thread_uid' => $request->input('thread_uid', 0),
		]));
	}

	public function message(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$folder = $this->folderInput($request);
		$msg = $this->app->mail()->message($folder, (int) $request->params['uid']);
		if (!$msg) {
			throw new UserFacingException($this->t('errors.not_found'), 404, jsonCode: 'not_found');
		}
		// Marquer comme lu : le fetch IMAP est en BODY_PEEK (flag non posé),
		// on force \Seen côté métier - sauf si un délai est configuré
		// (mark_read_delay) : le client pose alors le flag via /flags.
		$delay = (int) ($this->userSettings($this->app->sessionUserId())['mark_read_delay'] ?? 0);
		if (!$msg['isSeen'] && $delay <= 0) {
			try {
				$this->app->mail()->flags($folder, (int) $request->params['uid'], '\Seen', true);
				$msg['isSeen'] = true; // flag posé : la réponse ne doit pas annoncer "non lu"
			} catch (\Throwable) {}
		}
		return JsonResponse::ok($msg);
	}

	public function messageRaw(Request $request): Response
	{
		$this->requireReadOnly();
		[$folder, $uid] = $this->messageRef($request);
		header('Content-Type: message/rfc822');
		header("Content-Disposition: attachment; filename=\"message-{$uid}.eml\"");
		$ok = $this->app->mail()->mimeStream($folder, $uid, '', function ($r) {
			\MailSo\Base\Utils::FpassthruWithTimeLimitReset($r);
		});
		if (!$ok) {
			// Purge les en-têtes attachment posés plus haut : sinon la 404
			// partirait avec Content-Disposition > téléchargement d'un .eml
			// contenant le texte d'erreur. Ciblés pour préserver les
			// en-têtes de sécurité posés en amont par Headers::send().
			header_remove('Content-Type');
			header_remove('Content-Disposition');
			return Response::notFound();
		}
		return new Response('', 200); // body déjà streamé
	}

	/** "Renvoyer tel quel" (bounce) : re-expédition du source brut avec
	 *  en-tetes Resent-* vers un seul destinataire. */
	public function messageBounce(Request $request): JsonResponse
	{
		$this->requireWrite();
		$to = trim($this->noCrlf((string) $request->input('to'), 'to'));
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'to']), 400, jsonCode: 'validation');
		}
		$this->app->mail()->bounce(
			$this->folderInput($request),
			(int) $request->params['uid'],
			$to
		);
		return JsonResponse::ok();
	}

	/** Envoie l'accusé de réception (RFC 3798, action manuelle) puis pose
	 *  le mot-clé $MDNSent sur le message source. */
	public function messageMdnSend(Request $request): JsonResponse
	{
		$this->requireWrite();
		[$folder, $uid] = $this->messageRef($request);
		$msg = $this->app->mail()->message($folder, $uid);
		if (empty($msg['dispositionTo'])) {
			throw new UserFacingException($this->t('errors.no_mdn'), 400, jsonCode: 'no_mdn');
		}
		if ($msg['mdnSent']) {
			return JsonResponse::ok(); // idempotent : accusé déjà parti
		}
		$this->app->mail()->send($this->buildMdnReport($msg));
		try {
			$this->app->mail()->flags($folder, $uid, '$MDNSent', true);
		} catch (\Throwable) {} // l'accusé est parti ; le flag est best-effort
		return JsonResponse::ok();
	}

	/** multipart/report; report-type=disposition-notification (RFC 3798 §3). */
	private function buildMdnReport(array $msg): \MailSo\Mime\Message
	{
		$m = new \MailSo\Mime\Message();
		$m->DoesNotAddDefaultXMailer();
		$m->SetFrom(\MailSo\Mime\Email::Parse($this->app->sessionEmail()));
		$rcpt = new \MailSo\Mime\EmailCollection();
		foreach (preg_split('/[,;]/', (string) $msg['dispositionTo']) as $a) {
			$a = trim($a);
			if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
				$rcpt->append(new \MailSo\Mime\Email($a));
			}
		}
		if (!$rcpt->count()) {
			throw new UserFacingException($this->t('errors.no_mdn'), 400, jsonCode: 'no_mdn');
		}
		$m->SetTo($rcpt);
		$m->SetSubject($this->t('app.mdn_subject', ['s' => (string) ($msg['subject'] ?? '')]));
		$m->SetInReplyTo(str_replace(["\r", "\n"], '', (string) ($msg['messageId'] ?? '')));
		$m->RegenerateMessageId(\MailSo\Smtp\SmtpClient::EhloHelper());
		$m->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'multipart/report; report-type=disposition-notification');

		$text = new \MailSo\Mime\Part();
		$text->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'text/plain; charset=utf-8');
		$text->Headers->AddByName(
			\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING, 'quoted-printable');
		$text->Body = quoted_printable_encode($this->t('app.mdn_body'));

		$report = new \MailSo\Mime\Part();
		$report->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'message/disposition-notification');
		$report->Body = "Reporting-UA: mxwebmail\r\n"
			. 'Final-Recipient: rfc822;' . str_replace(["\r", "\n"], '', $this->app->sessionEmail()) . "\r\n"
			. 'Original-Message-ID: ' . str_replace(["\r", "\n"], '', (string) ($msg['messageId'] ?? '')) . "\r\n"
			. "Disposition: manual-action/MDN-sent-manually; displayed\r\n";

		$m->SubParts->append($text);
		$m->SubParts->append($report);
		return $m;
	}

	/**
	 * Vérifie la signature S/MIME du message : le .eml brut complet est passé
	 * à openssl_pkcs7_verify (multipart/signed et p7m opaque traités
	 * nativement). Sans CA store (smime_ca_path) la signature crypto reste
	 * vérifiable > {valid, trusted:false}.
	 */
	public function messageSmimeVerify(Request $request): JsonResponse
	{
		$this->requireWrite();
		[$folder, $uid] = $this->messageRef($request);
		$msg = $this->app->mail()->message($folder, $uid);
		if (empty($msg['smimeSigned'])) {
			throw new UserFacingException($this->t('errors.not_signed'), 400, jsonCode: 'not_signed');
		}
		if (!function_exists('openssl_pkcs7_verify')) {
			throw new UserFacingException($this->t('errors.no_openssl'), 501, jsonCode: 'no_openssl');
		}
		$tmpDir = $this->app->storageDir() . '/tmp';
		Files::ensureDir($tmpDir);
		$eml = tempnam($tmpDir, 'smime_') . '.eml';
		$signers = tempnam($tmpDir, 'signer_') . '.pem';
		$out = null;
		try {
			$fh = fopen($eml, 'wb');
			$this->app->mail()->mimeStream($folder, $uid, '',
				function ($r) use ($fh) { stream_copy_to_stream($r, $fh); });
			fclose($fh);
			$caPath = trim((string) $this->app->config()->get('smime_ca_path', ''));
			$flags = $caPath !== '' ? 0 : PKCS7_NOVERIFY;
			$ca = $caPath !== '' ? [$caPath] : [];
			$ok = openssl_pkcs7_verify($eml, $flags, $signers, $ca);
			$out = ['valid' => $ok === true, 'trusted' => $caPath !== '', 'signer' => null];
			if ($ok === true && is_file($signers)) {
				$pem = (string) file_get_contents($signers);
				if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
					$x = openssl_x509_parse($m[0]);
					$out['signer'] = is_array($x) ? [
						'email'  => $x['subject']['emailAddress'] ?? null,
						'cn'     => $x['subject']['CN'] ?? null,
						'issuer' => $x['issuer']['O'] ?? ($x['issuer']['CN'] ?? null),
					] : null;
				}
			}
			if ($ok === false) {
				$out['error'] = trim((string) openssl_error_string()) ?: null;
			}
		} finally {
			@unlink($eml);
			@unlink($signers);
		}
		return JsonResponse::ok($out);
	}

	public function attachment(Request $request): Response
	{
		$this->requireReadOnly();
		[$folder, $uid] = $this->messageRef($request);
		$mimeIndex = (string) $request->params['id'];
		$found = $this->attachmentFile($folder, $uid, $mimeIndex,
			(string) $request->input('uv', ''));
		if ($found === null) {
			return Response::notFound();
		}
		// Sans uv le corps vit dans un tmp jetable - supprimé après envoi.
		if (!empty($found['deleteAfter'])) {
			$tmp = $found['path'];
			register_shutdown_function(static function () use ($tmp) {
				@unlink($tmp);
			});
		}
		return $this->attachmentFileResponse($found['meta'], $found['path'],
			(bool) $request->input('download', true), $found['immutable']);
	}

	/**
	 * Aperçu JSON d'une PJ .eml (message/rfc822) : en-têtes + corps
	 * texte/html décodés + méta des PJ imbriquées. ?sub=a[.b] descend dans
	 * un .eml imbriqué (chaque indice pointe une PJ rfc822 du niveau courant).
	 */
	public function attachmentEml(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		[$folder, $uid] = $this->messageRef($request);
		$mimeIndex = (string) $request->params['id'];
		$sub = (string) $request->input('sub', '');
		if ($sub !== '' && !preg_match('/^\d+(\.\d+)*$/', $sub)) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'sub']), 400, jsonCode: 'invalid_value');
		}
		$found = $this->attachmentBody($folder, $uid, $mimeIndex,
			(string) $request->input('uv', ''), self::EML_PREVIEW_MAX);
		if (!$found) {
			throw new UserFacingException($this->t('errors.attachment_missing'), 404, jsonCode: 'not_found');
		}
		$meta = $found['meta'];
		if (strtolower((string) ($meta['mime'] ?? '')) !== 'message/rfc822'
			&& !preg_match('/\.eml$/i', (string) ($meta['name'] ?? ''))) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'mime']), 400, jsonCode: 'invalid_value');
		}
		if (strlen($found['body']) > self::EML_PREVIEW_MAX) {
			throw new UserFacingException($this->t('errors.attachments_too_big',
					['mb' => (int) (self::EML_PREVIEW_MAX / 1048576)]), 413, jsonCode: 'too_big');
		}
		try {
			$root = \MailSo\Mime\Part::FromString($found['body']);
		} catch (\Throwable) {
			$root = null;
		}
		if (!$root) {
			throw new UserFacingException($this->t('errors.preview_failed'), 422, jsonCode: 'eml_parse');
		}
		$node = EmlParser::descend($root, $sub);
		if (!$node) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'sub']), 400, jsonCode: 'invalid_value');
		}
		return JsonResponse::ok(EmlParser::toArray($node, $sub));
	}

	/**
	 * Stream d'une PJ imbriquée dans un .eml (n° {part} du noeud ?sub=) -
	 * téléchargement (download=1) ou image cid: (download=0). Mêmes en-têtes
	 * sandboxés que la PJ de premier niveau.
	 */
	public function attachmentEmlPart(Request $request): Response
	{
		$this->requireReadOnly();
		[$folder, $uid] = $this->messageRef($request);
		$mimeIndex = (string) $request->params['id'];
		$partIdx = (string) $request->params['part'];
		$sub = (string) $request->input('sub', '');
		if (!preg_match('/^\d+$/', $partIdx)
			|| ($sub !== '' && !preg_match('/^\d+(\.\d+)*$/', $sub))) {
			return Response::notFound();
		}
		$found = $this->attachmentBody($folder, $uid, $mimeIndex,
			(string) $request->input('uv', ''), self::EML_PREVIEW_MAX);
		if (!$found || strlen($found['body']) > self::EML_PREVIEW_MAX) {
			return Response::notFound();
		}
		try {
			$root = \MailSo\Mime\Part::FromString($found['body']);
			$node = $root ? EmlParser::descend($root, $sub) : null;
		} catch (\Throwable) {
			$node = null;
		}
		if (!$node) {
			return Response::notFound();
		}
		$atts = $_texts = [];
		EmlParser::walk($node, $atts, $_texts);
		$p = $atts[(int) $partIdx] ?? null;
		if (!$p) {
			return Response::notFound();
		}
		$ct = $p->ContentType();
		$name = \MailSo\Base\Utils::SecureFileName($p->FileName());
		if ($name === '') {
			$name = ($ct === 'message/rfc822' ? "message-{$partIdx}.eml" : "part-{$partIdx}");
		}
		return $this->attachmentResponse([
				'mime' => $ct !== '' ? $ct : 'application/octet-stream',
				'name' => $name,
			], EmlParser::partBody($p),
			(bool) $request->input('download', true), $found['immutable']);
	}

	/**
	 * Prologue commun du cache disque PJ : [dir, cacheKey, meta?, body?].
	 * meta/body remplis uniquement sur hit complet ; body lu si $withBody.
	 * Sur miss le GC opportuniste a déjà tourné (un seul appel par requête).
	 * @return array{0: ?string, 1: ?string, 2: ?array, 3: ?string}
	 */
	private function attachmentCacheProbe(string $folder, int $uid,
		string $mimeIndex, string $uv, bool $withBody): array
	{
		[$dir, $cacheKey] = $this->attachmentCacheKey($folder, $uid, $mimeIndex, $uv);
		if ($cacheKey !== null) {
			$path = $dir . '/' . $cacheKey;
			if (is_file($path) && is_file($path . '.json')) {
				$meta = json_decode((string) file_get_contents($path . '.json'), true);
				if (is_array($meta) && isset($meta['mime'], $meta['name'])) {
					$body = $withBody ? file_get_contents($path) : null;
					if (!$withBody || is_string($body)) {
						return [$dir, $cacheKey, $meta, $body];
					}
				}
			}
			$this->attachmentCacheGc($dir);
		}
		return [$dir, $cacheKey, null, null];
	}

	/** Écrit la meta .json du cache PJ (mime+name) - écriture atomique. */
	private function attachmentCachePutMeta(string $dir, string $cacheKey, array $meta): void
	{
		mxwm_atomic_write($dir . '/' . $cacheKey . '.json',
			json_encode(['mime' => $meta['mime'], 'name' => $meta['name']]));
	}

	/**
	 * [dir, clé] du cache disque PJ (cache/att) - [null, null] sans uv.
	 * La clé inclut compte+email+folder+uv+uid+mimeIndex : le contenu d'une
	 * PJ d'un tel tuple est immuable (cache + immutable navigateur sûrs).
	 */
	private function attachmentCacheKey(string $folder, int $uid, string $mimeIndex, string $uv): array
	{
		if ($uv === '') {
			return [null, null];
		}
		return [
			$this->app->storageDir() . '/cache/att',
			sha1(implode('|', [
				(string) $this->app->sessionAccountId(),
				$this->app->sessionEmail(),
				$folder, $uv, $uid, $mimeIndex])),
		];
	}

	/**
	 * Purge opportuniste (sur miss uniquement) : corps de PJ expirés (7 j).
	 * cache/att est écrit inconditionnellement > GC propre requis ; le GC
	 * cache.gc_hours ne couvre att/ que si cache.enabled = true.
	 */
	private function attachmentCacheGc(string $dir): void
	{
		Files::gcGlob($dir . '/*', 604800);
	}

	/**
	 * Meta d'une PJ via BODYSTRUCTURE : message() fetchait aussi toutes les
	 * parties texte (body_text_limit=0 > corps entier) pour lire
	 * mimeIndex/mime/nom. null si le mimeIndex est absent (404).
	 */
	private function attachmentMeta(string $folder, int $uid, string $mimeIndex): ?array
	{
		$atts = $this->app->mail()->attachmentList($folder, $uid);
		foreach ($atts ?? [] as $att) {
			if ($att['mimeIndex'] === $mimeIndex) {
				return $att;
			}
		}
		$this->app->log('warning', "Attachment 404 uid={$uid} folder={$folder} "
			. "mimeIndex={$mimeIndex} dispo=["
			. implode(',', array_column($atts ?? [], 'mimeIndex')) . ']');
		return null;
	}

	/**
	 * Corps d'une PJ matérialisé en FICHIER (cache disque uv ou tmp jetable) -
	 * jamais en mémoire : le fetch IMAP écrit par chunks de 8 Ko vers le disque
	 * plutôt que vers la sortie, donc la 404 reste propre (rien n'est émis
	 * vers le client tant que le fetch n'a pas réussi).
	 * Retour : ['meta','path','immutable','deleteAfter'?] ou null (absent/échec).
	 */
	private function attachmentFile(string $folder, int $uid, string $mimeIndex, string $uv): ?array
	{
		// uv = UIDVALIDITY exposée par message() : cache disque + immutable
		// navigateur sûrs. Sans uv (liens anciens/clients externes) : tmp jetable.
		[$dir, $cacheKey, $meta] = $this->attachmentCacheProbe($folder, $uid, $mimeIndex, $uv, false);
		if ($meta !== null) {
			return ['meta' => $meta, 'path' => $dir . '/' . $cacheKey, 'immutable' => true];
		}
		$meta = $this->attachmentMeta($folder, $uid, $mimeIndex);
		if ($meta === null) {
			return null;
		}
		$tmpDir = $dir ?? ($this->app->storageDir() . '/tmp');
		is_dir($tmpDir) || @mkdir($tmpDir, 0700, true);
		$tmp = @tempnam($tmpDir, 'att_');
		$fp = $tmp === false ? false : fopen($tmp, 'wb');
		if (!is_resource($fp)) {
			$tmp === false || @unlink($tmp);
			return null;
		}
		$ok = $this->app->mail()->mimeStream($folder, $uid, $mimeIndex,
			function ($r) use ($fp) {
				while (!feof($r)) {
					fwrite($fp, fread($r, 8192));
					// gros corps : même logique que FpassthruWithTimeLimitReset
					set_time_limit(30);
				}
			}, $meta);
		fclose($fp);
		if (!$ok) {
			@unlink($tmp);
			$this->app->log('warning',
				"Attachment mimeStream échoué uid={$uid} folder={$folder} mimeIndex={$mimeIndex}");
			return null;
		}
		if ($cacheKey === null) {
			return ['meta' => $meta, 'path' => $tmp, 'immutable' => false, 'deleteAfter' => true];
		}
		// tmp+rename : un hit concurrent ne lit jamais un corps tronqué (le
		// contenu est immuable > écrasement idempotent). rename() sous Windows
		// n'écrase pas une cible existante > unlink préalable en repli.
		if (!@rename($tmp, $dir . '/' . $cacheKey)) {
			@unlink($dir . '/' . $cacheKey);
			@rename($tmp, $dir . '/' . $cacheKey);
		}
		$this->attachmentCachePutMeta($dir, $cacheKey, $meta);
		return ['meta' => $meta, 'path' => $dir . '/' . $cacheKey, 'immutable' => true];
	}

	/**
	 * Corps d'une PJ (+ meta) : cache disque uv si possible, sinon liste
	 * BODYSTRUCTURE + fetch IMAP. Partagé par attachment(), attachmentEml()
	 * et attachmentEmlPart() - un .eml prévisualisé n'est fetché qu'une fois
	 * (le JSON, les images cid: et les PJ imbriquées tapent le même cache).
	 * $maxLength borne la lecture pour le chemin aperçu : lecture tronquée à
	 * $maxLength+1 puis retour sans écriture de cache (le corps tronqué ne
	 * doit pas corrompre le cache partagé avec le téléchargement).
	 * Retour : ['meta','body','immutable'] ou null (absent).
	 */
	private function attachmentBody(string $folder, int $uid, string $mimeIndex, string $uv, ?int $maxLength = null): ?array
	{
		// Même convention qu'attachmentFile : cache disque uv, sinon fetch IMAP
		// - mais retourne le corps EN MÉMOIRE (requis par le parse MIME .eml).
		[$dir, $cacheKey, $meta, $body] = $this->attachmentCacheProbe($folder, $uid, $mimeIndex, $uv, true);
		if ($meta !== null) {
			return ['meta' => $meta, 'body' => $body, 'immutable' => true];
		}
		$meta = $this->attachmentMeta($folder, $uid, $mimeIndex);
		if (!$meta) {
			return null;
		}
		$body = null;
		$ok = $this->app->mail()->mimeStream($folder, $uid, $mimeIndex,
			function ($r) use (&$body, $maxLength) {
				$body = $maxLength === null
					? stream_get_contents($r)
					: stream_get_contents($r, $maxLength + 1);
			}, $meta);
		if (!$ok || !is_string($body)) {
			$this->app->log('warning',
				"Attachment mimeStream échoué uid={$uid} folder={$folder} mimeIndex={$mimeIndex}");
			return null;
		}
		// Corps tronqué (lecture bornée) : ne pas cacher - le cache partagé
		// sert aussi le téléchargement, un corps tronqué le corromprait.
		$tooBig = $maxLength !== null && strlen($body) > $maxLength;
		if ($cacheKey !== null && !$tooBig) {
			// tmp+rename : un hit concurrent ne lit jamais un corps tronqué.
			mxwm_atomic_write($dir . '/' . $cacheKey, $body);
			$this->attachmentCachePutMeta($dir, $cacheKey, $meta);
		}
		return ['meta' => $meta, 'body' => $body, 'immutable' => $cacheKey !== null && !$tooBig];
	}

	/**
	 * En-têtes communs de livraison d'une PJ : mime, disposition, nosniff,
	 * cache immutable si uv (contenu figé), CSP sandbox sauf PDF.
	 * Contenu utilisateur servi sous notre origine (images cid inline) :
	 * la sandbox CSP interdit tout script même si la PJ est du HTML ouvert
	 * en direct. Exception PDF : les viewers natifs (pdfium) n'exécutent
	 * pas le JS embarqué mais sont neutralisés par la sandbox - sans elle,
	 * l'aperçu en iframe fonctionne (le type bitmap reste inerte).
	 * $length > Content-Length, réservé aux chemins fichier : un corps
	 * bufferisé pourrait être raccourci par gzip() après calcul.
	 */
	private function attachmentHeaders(array $meta, bool $download, bool $immutable, ?int $length = null): array
	{
		$filename = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $meta['name']);
		$headers = [
			'Content-Type'           => (string) $meta['mime'],
			'Content-Disposition'    => ($download ? 'attachment' : 'inline')
				. '; filename="' . addslashes($filename) . '"',
			'X-Content-Type-Options' => 'nosniff',
			// immutable n'est émis que si l'URL portait uv (contenu figé) -
			// sinon pas de cache navigateur : le même uid peut changer de
			// contenu si le dossier est recréé (uidvalidity roulé).
			'Cache-Control'          => $immutable
				? 'private, max-age=31536000, immutable' : 'private, max-age=0',
		];
		if ($length !== null) {
			$headers['Content-Length'] = (string) $length;
		}
		if (strtolower((string) $meta['mime']) !== 'application/pdf') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}
		return $headers;
	}

	/** Réponse PJ streamée depuis un fichier (cache ou tmp) - mémoire constante. */
	private function attachmentFileResponse(array $meta, string $path, bool $download, bool $immutable): Response
	{
		$len = @filesize($path);
		return Response::file($path, 200,
			$this->attachmentHeaders($meta, $download, $immutable,
				$len === false ? null : $len));
	}

	private function attachmentResponse(array $meta, string $body, bool $download, bool $immutable): Response
	{
		return new Response($body, 200, $this->attachmentHeaders($meta, $download, $immutable));
	}

	public function messageFlags(Request $request): JsonResponse
	{
		$this->requireWrite();
		// Whitelist stricte : le flag part tel quel dans la commande
		// UID STORE - un flag libre permettrait l'injection IMAP.
		$flag = (string) $request->input('flag');
		if (!in_array($flag, self::ALLOWED_FLAGS, true)) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'flag']), 400, jsonCode: 'invalid_value');
		}
		$this->app->mail()->flags(
			$this->folderInput($request),
			(int) $request->params['uid'],
			$flag,
			(bool) $request->input('set', true)
		);
		return JsonResponse::ok();
	}

	public function messageSpam(Request $request): JsonResponse
	{
		$this->requireWrite();
		$result = $this->app->mail()->markSpam(
			$this->folderInput($request),
			(int) $request->params['uid'],
			(bool) $request->input('spam', true)
		);
		return JsonResponse::ok($result);
	}

	public function messageMove(Request $request): JsonResponse
	{
		$this->requireWrite();
		$to = $this->validTargetFolder($request, true);
		[$folder, $uid] = $this->messageRef($request);
		$this->app->mail()->move($folder, $to, $uid);
		return JsonResponse::ok();
	}

	public function messageCopy(Request $request): JsonResponse
	{
		$this->requireWrite();
		// Pas de requireDangerous : copier vers la corbeille ne retire
		// pas l'original (contrairement au deplacement).
		$to = $this->validTargetFolder($request, false);
		[$folder, $uid] = $this->messageRef($request);
		if ($to === $folder) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'to']), 400, jsonCode: 'invalid_value');
		}
		$this->app->mail()->copy($folder, $to, $uid);
		return JsonResponse::ok();
	}

	public function messageDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->requireDangerous();
		return JsonResponse::ok($this->app->mail()->delete(
			$this->folderInput($request),
			(int) $request->params['uid']
		));
	}

	/** Suppression groupée : un UID MOVE/EXPUNGE par dossier au lieu d'une
	 *  requête HTTP + commande IMAP par message (bulkDelete frontend). */
	public function messagesDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->requireDangerous();
		$groups = $this->batchItemsByFolder($request);
		$mail = $this->app->mail();
		return $this->runFolderBatch($groups,
			fn($f, $uids) => $mail->delete($f, $uids));
	}

	/** Déplacement groupé : même batching que messagesDelete. Les groupes
	 *  déjà dans la cible sont ignorés (le client les pré-filtre aussi). */
	public function messagesMove(Request $request): JsonResponse
	{
		$this->requireWrite();
		$to = $this->validTargetFolder($request, true);
		$groups = $this->batchItemsByFolder($request);
		unset($groups[$to]); // déjà dans la cible
		$mail = $this->app->mail();
		return $this->runFolderBatch($groups,
			fn($f, $uids) => $mail->move($f, $to, $uids));
	}

	/** Copie groupee : meme contrat que messagesMove mais sans effet de
	 *  bord sur la selection - les originaux restent en place. */
	public function messagesCopy(Request $request): JsonResponse
	{
		$this->requireWrite();
		$to = $this->validTargetFolder($request, false);
		$groups = $this->batchItemsByFolder($request);
		unset($groups[$to]); // déjà dans la cible
		$mail = $this->app->mail();
		return $this->runFolderBatch($groups,
			fn($f, $uids) => $mail->copy($f, $to, $uids));
	}

	/** Flags groupés : un UID STORE par dossier (sequence set) au lieu d'une
	 *  requête HTTP + commande IMAP par message (bulkFlag frontend). */
	public function messagesFlags(Request $request): JsonResponse
	{
		$this->requireWrite();
		$flag = (string) $request->input('flag');
		if (!in_array($flag, self::ALLOWED_FLAGS, true)) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'flag']), 400, jsonCode: 'invalid_value');
		}
		$set = (bool) $request->input('set', true);
		$groups = $this->batchItemsByFolder($request);
		$mail = $this->app->mail();
		return $this->runFolderBatch($groups,
			fn($f, $uids) => $mail->flagsMany($f, $uids, $flag, $set));
	}

	/** Marquage spam groupé : un appel par sélection au lieu d'une requête
	 *  HTTP par message - markSpam par UID sur la connexion partagée (hook
	 *  message.spam émis par message, erreurs isolées par item). */
	public function messagesSpam(Request $request): JsonResponse
	{
		$this->requireWrite();
		$spam = (bool) $request->input('spam', true);
		$groups = $this->batchItemsByFolder($request);
		$mail = $this->app->mail();
		return $this->runUidBatch($groups,
			fn($f, $uid) => $mail->markSpam($f, $uid, $spam));
	}

	/**
	 * Désabonnement (List-Unsubscribe). RFC 8058 : si le message annonce
	 * "List-Unsubscribe-Post: List-Unsubscribe=One-Click" et une cible
	 * https, le POST part côté serveur via le client HTTP interne (cURL
	 * ou socket - aucune dépendance à allow_url_fopen), avec la même
	 * garde SSRF que le proxy d'images, redirects revalidés par hop.
	 * Tout échec du one-click est journalisé puis la cible url/mailto
	 * est renvoyée : le client ouvre alors le lien en dégradé.
	 */
	public function messageUnsubscribe(Request $request): JsonResponse
	{
		$this->requireWrite();
		$folder = $this->folderInput($request);
		$msg = $this->app->mail()->message($folder, (int) $request->params['uid']);
		$u = $msg['unsubscribe'] ?? null;
		if (!$u || (!$u['url'] && !$u['mailto'])) {
			throw new UserFacingException($this->t('errors.unsubscribe_none'), 400, jsonCode: 'unavailable');
		}
		if (!empty($u['post']) && $u['url']) {
			$url = $u['url'];
			$method = 'POST';
			for ($hop = 0; $hop <= 3; $hop++) {
				if ($this->proxyUrlError($url, [])) {
					throw new UserFacingException($this->t('errors.host_blocked'), 403, jsonCode: 'ssrf');
				}
				// Transports en cascade : cURL puis socket natif (indépendant
				// de allow_url_fopen et capable de résoudre les CA via l'OS
				// quand curl n'a pas de bundle - et réciproquement).
				$res = null; $err = '';
				foreach (['curl', 'socket'] as $type) {
					try {
						$http = $this->app->newHttpRequest(
							['timeout' => 8, 'user_agent' => 'MXWebMail-Unsubscribe'], $type);
						// GET : body null (doRequest l'ajouterait à l'URL).
						$res = $http->doRequest($method, $url,
							'POST' === $method ? 'List-Unsubscribe=One-Click' : null,
							'POST' === $method ? ['Content-Type: application/x-www-form-urlencoded'] : []);
						break;
					} catch (\Throwable $e) {
						$err .= ($err !== '' ? ' | ' : '') . "[{$type}] " . $e->getMessage();
					}
				}
				if (!$res) {
					$this->app->log('warning',
						"Désabonnement one-click échoué url={$url} : {$err}");
					break;
				}
				$status = (int) $res->status;
				if ($status >= 300 && $status < 400 && $hop < 3) {
					$loc = $res->getHeader('location');
					$loc = is_array($loc) ? (string) ($loc[0] ?? '') : (string) $loc;
					$next = $loc !== '' ? $this->resolveRedirect($url, $loc) : null;
					if ($next && preg_match('#^https://#i', $next)) {
						$url = $next;
						// 301/302/303 > GET (RFC 7231) ; 307/308 conservent le POST.
						if (in_array($status, [301, 302, 303], true)) {
							$method = 'GET';
						}
						continue;
					}
					break; // redirect non résolvable : l'URL d'origine s'ouvre au navigateur
				}
				if ($status >= 200 && $status < 300) {
					return JsonResponse::ok(['done' => true]);
				}
				$this->app->log('warning',
					"Désabonnement one-click refusé url={$url} : HTTP {$status}");
				break;
			}
		}
		return JsonResponse::ok(['url' => $u['url'], 'mailto' => $u['mailto']]);
	}

	public function attachmentsZip(Request $request): Response
	{
		$this->requireReadOnly();
		[$folder, $uid] = $this->messageRef($request);
		$mail = $this->app->mail();
		// BODYSTRUCTURE seul : pas de fetch des corps texte pour les métas PJ.
		$atts = $mail->attachmentList($folder, $uid);
		if (!$atts) {
			return Response::notFound();
		}
		$maxTotal = 4 * (int) $this->app->config()->get('attachments_max_total', 25) * 1024 * 1024;
		$names = [];
		$total = 0;
		foreach ($atts as $att) {
			$total += (int) ($att['size'] ?? 0);
			$n = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', (string) $att['name']);
			$names[] = $n !== '' ? $n : 'piece-jointe'; // jamais d'entrée zip au nom vide
		}
		if ($total > $maxTotal) {
			throw new UserFacingException($this->t('errors.attachments_too_big', ['mb' => (int) ($maxTotal / 1048576)]), 413, jsonCode: 'too_big');
		}
		// Déduplique les noms : "name (2).ext" - le nom produit est aussi
		// enregistré : une PJ déjà nommée "a (2).txt" n'entre pas en
		// collision avec le doublon généré.
		$seen = [];
		$names = array_map(function ($n) use (&$seen) {
			if (!isset($seen[$n])) { $seen[$n] = 1; return $n; }
			$dot = strrpos($n, '.');
			$base = $dot === false ? $n : substr($n, 0, $dot);
			$ext  = $dot === false ? '' : substr($n, $dot);
			do {
				$c = "{$base} (" . ++$seen[$n] . "){$ext}";
			} while (isset($seen[$c]));
			$seen[$c] = 1;
			return $c;
		}, $names);
		$entries = [];
		foreach ($atts as $i => $att) {
			$entries[$names[$i]] = fn(callable $add) => $mail->mimeStream(
				$folder, $uid, (string) $att['mimeIndex'], $add);
		}
		return \MXWebMail\Http\ZipStreamer::send("attachments-{$uid}",
			$entries, 'Pièces jointes introuvables ou illisibles');
	}

	/**
	 * items[] = {folder, uid} des endpoints batch : validation stricte
	 * (un item malformé invalide toute la requête), regroupés par dossier.
	 * @return array<string, int[]>|JsonResponse
	 */
	private function batchItemsByFolder(Request $request): array|JsonResponse
	{
		$items = $request->input('items', []);
		if (!is_array($items) || !$items) {
			throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'items']), 400, jsonCode: 'bad_uids');
		}
		if (count($items) > 500) {
			throw new UserFacingException($this->t('errors.too_many', ['max' => 500]), 413, jsonCode: 'too_many');
		}
		$byFolder = [];
		foreach ($items as $it) {
			$folder = is_array($it) ? (string) ($it['folder'] ?? '') : '';
			$uid = is_array($it) ? (int) ($it['uid'] ?? 0) : 0;
			if ($folder === '' || $uid < 1) {
				throw new UserFacingException($this->t('errors.invalid_value', ['key' => 'items']), 400, jsonCode: 'bad_uids');
			}
			$byFolder[$this->noCrlf($folder, 'folder')][] = $uid;
		}
		return $byFolder;
	}

	/**
	 * Dossier cible 'to' validé : non vide + existant. Avec $dangerousOnTrash,
	 * une cible corbeille exige requireDangerous - déplacer vers la corbeille
	 * équivaut à supprimer (bloqué quand dangerous_actions=false, comme
	 * messageDelete). La copie n'a pas cette garde : l'original reste en place.
	 */
	private function validTargetFolder(Request $request, bool $dangerousOnTrash): string
	{
		$mail = $this->app->mail();
		$to = $this->noCrlf((string) $request->input('to'), 'to');
		$bad = fn() => throw new UserFacingException(
			$this->t('errors.invalid_value', ['key' => 'to']), 400, jsonCode: 'invalid_value');
		if ($to === '') {
			$bad();
		}
		if ($dangerousOnTrash && $to === $mail->trashFolder()) {
			$this->requireDangerous(); // jette si interdit
		}
		return $mail->folderExists($to) ? $to : $bad();
	}

	/**
	 * Boucle batch par dossier : $op($folder, $uids) par groupe, erreurs
	 * isolées par dossier - {done, errors}.
	 */
	private function runFolderBatch(array $groups, callable $op): JsonResponse
	{
		$done = 0; $errors = [];
		foreach ($groups as $folder => $uids) {
			try {
				$op($folder, $uids);
				$done += count($uids);
			} catch (\Throwable $e) {
				$errors[] = ['folder' => $folder, 'error' => $e->getMessage()];
			}
		}
		return JsonResponse::ok(['done' => $done, 'errors' => $errors]);
	}

	/**
	 * Boucle batch par uid : $op($folder, $uid) par message (opération non
	 * groupable, ex. markSpam émet un hook par message) - {done, results, errors}.
	 */
	private function runUidBatch(array $groups, callable $op): JsonResponse
	{
		$done = 0; $results = []; $errors = [];
		foreach ($groups as $folder => $uids) {
			foreach ($uids as $uid) {
				try {
					$results[] = ['folder' => $folder, 'uid' => $uid,
						'result' => $op($folder, $uid)];
					$done++;
				} catch (\Throwable $e) {
					$errors[] = ['folder' => $folder, 'uid' => $uid,
						'error' => $e->getMessage()];
				}
			}
		}
		return JsonResponse::ok(
			['done' => $done, 'results' => $results, 'errors' => $errors]);
	}

	/** Flags acceptés sur /api/messages/{uid}/flags (= enum IMAP complet). */
	private const ALLOWED_FLAGS = [
		\MailSo\Imap\Enumerations\MessageFlag::SEEN,
		\MailSo\Imap\Enumerations\MessageFlag::ANSWERED,
		\MailSo\Imap\Enumerations\MessageFlag::FLAGGED,
		\MailSo\Imap\Enumerations\MessageFlag::DELETED,
		\MailSo\Imap\Enumerations\MessageFlag::DRAFT,
		\MailSo\Imap\Enumerations\MessageFlag::MDNSENT,
		\MailSo\Imap\Enumerations\MessageFlag::IMPORTANT,
		\MailSo\Imap\Enumerations\MessageFlag::FORWARDED,
		\MailSo\Imap\Enumerations\MessageFlag::JUNK,
		\MailSo\Imap\Enumerations\MessageFlag::NOTJUNK,
		\MailSo\Imap\Enumerations\MessageFlag::PHISHING,
	];

	/** Taille max d'une PJ .eml prévisualisée (fetch + parse en mémoire). */
	private const EML_PREVIEW_MAX = 25 * 1024 * 1024;
}
