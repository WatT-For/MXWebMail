<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Security\CredentialCrypt;
use MXWebMail\Security\Csrf;
use MXWebMail\Security\TotpRequiredException;
use MXWebMail\Service\MailService;




final class ComposeController extends BaseController
{
	public function composeSend(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$message = $this->buildMessage($request);
		if (!$message) {
			return JsonResponse::error($this->t('errors.empty_body'), 'empty');
		}
		// Le cumul To+Cc+Bcc est la référence - SMTP envoie à GetRcpt() :
		// exiger un To explicite refuserait un envoi Cc/Cci-seul pourtant valide.
		if (!count($message->GetRcpt())) {
			return JsonResponse::error($this->t('errors.no_rcpt'), 'no_rcpt');
		}
		$this->app->mail()->send($message);
		// Marque le message d'origine \Answered - non bloquant (l'envoi a
		// réussi, un échec de marquage ne doit pas faire échouer la réponse).
		$replyUid = (int) $request->input('replyUid', 0);
		$replyFolder = $this->noCrlf((string) $request->input('replyFolder', ''), 'replyFolder');
		if ($replyUid > 0 && $replyFolder !== '') {
			try {
				$this->app->mail()->flags($replyFolder, $replyUid, '\Answered', true);
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Marquage \\Answered échoué: ' . $e->getMessage());
			}
		}
		// Marque le(s) message(s) d'origine $Forwarded - non bloquant.
		// Cas simple (forwardUid/Folder) et multi-transfert (forwardItems)
		// alimentent la même table, un UID STORE par dossier via flagsMany.
		$fwdTargets = [];
		$fwdUid = (int) $request->input('forwardUid', 0);
		$fwdFolder = $this->noCrlf((string) $request->input('forwardFolder', ''), 'forwardFolder');
		$fwdTotal = 0;
		if ($fwdUid > 0 && $fwdFolder !== '') { $fwdTargets[$fwdFolder][] = $fwdUid; $fwdTotal = 1; }
		foreach ((array) $request->input('forwardItems', []) as $it) {
			// Cap strict 50, testé avant l'ajout : le total (forwardUid simple +
			// forwardItems) ne dépasse jamais 50 ; un item malformé ne consomme
			// pas le budget.
			if ($fwdTotal >= 50) { break; }
			$f = is_array($it) ? $this->noCrlf((string) ($it['folder'] ?? ''), 'forwardItems') : '';
			$u = (int) ($it['uid'] ?? 0);
			if ($f !== '' && $u > 0) { $fwdTargets[$f][] = $u; $fwdTotal++; }
		}
		foreach ($fwdTargets as $f => $uids) {
			try {
				$this->app->mail()->flagsMany($f, array_unique($uids), '$Forwarded', true);
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Marquage $Forwarded echoue: ' . $e->getMessage());
			}
		}
		// Libère les streams des PJ (fopen dans buildMessage) : unlink
		// impossible sous Windows tant que $message vit.
		$rcpts = array_merge(
			(array) ($message->GetTo() ?? []),
			(array) ($message->GetCc() ?? []),
			(array) ($message->GetBcc() ?? []));
		unset($message);
		$this->cleanupTmpAttachments((array) $request->input('attachments', []));
		// Brouillon rouvert puis envoyé : il est consommé. Non bloquant -
		// l'envoi a réussi, un échec de suppression ne doit pas faire
		// échouer la réponse.
		if (($ref = $this->draftRef($request)) !== null) {
			try {
				$this->app->mail()->deletePermanent($ref[0], $ref[1]);
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Suppression brouillon consommé échouée: ' . $e->getMessage());
			}
		}

		// Fréquence des destinataires pour l'autocomplete contacts
		$autosave = $this->userSettings((int) $_SESSION['user_id'])['contacts_autosave'] ?? true;
		if (!$autosave || !$this->app->config()->get('contacts_enable', true)) {
			return JsonResponse::ok();
		}
		// Dédup par adresse : même destinataire en To+Cc > une seule écriture.
		$seen = [];
		foreach ($rcpts as $rcpt) {
			$email = $rcpt->GetEmail(true);
			$k = strtolower($email);
			if ($email === '' || isset($seen[$k])) {
				continue;
			}
			$seen[$k] = true;
			try {
				$this->app->contacts()->touchRecipient(
					(int) $_SESSION['user_id'], $email, $rcpt->GetDisplayName());
			} catch (\Throwable $e) {
				$this->app->log('warning', 'Autosave contact échoué : ' . $e->getMessage());
			}
		}
		return JsonResponse::ok();
	}

	public function composeUpload(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		if (empty($request->files['file'])) {
			return JsonResponse::error($this->t('errors.no_file'), 'no_file');
		}
		$f = $request->files['file'];
		if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		// La borne effective est le min des deux limites : un fichier qui
		// dépasserait le total autorisé par message ne pourrait jamais partir.
		$maxSize = min(
			(int) $this->app->config()->get('attachment_max_size', 25),
			(int) $this->app->config()->get('attachments_max_total', 25)
		) * 1024 * 1024;
		if ($f['size'] > $maxSize) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => (int) ($maxSize / 1048576)]), 'too_big');
		}
		$dir = $this->app->storageDir() . '/tmp';
		is_dir($dir) || mkdir($dir, 0700, true);
		// Purge opportuniste des PJ abandonnées : attach_* + .meta > 24 h
		foreach (glob($dir . '/attach_*') ?: [] as $stale) {
			if (filemtime($stale) < time() - 86400) {
				@unlink($stale);
			}
		}
		// Nom assaini : basename (chemins forgés) + caractères de contrôle -
		// il part tel quel dans le paramètre filename= des en-têtes MIME.
		$name = basename(str_replace('\\', '/', (string) $f['name']));
		$name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));
		if ($name === '') {
			$name = 'attachment';
		}
		$token = bin2hex(random_bytes(16));
		$dest = $dir . '/attach_' . $token;
		if (!move_uploaded_file($f['tmp_name'], $dest)) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		// Hook : scan AV de la PJ (plugin attachments-clamav) - un listener
		// qui jette annule l'upload et nettoie le fichier.
		try {
			$this->app->hooks()->run('attachment.upload',
				$dest, $name, (int) $_SESSION['user_id']);
		} catch (\Throwable $e) {
			@unlink($dest);
			throw $e;
		}
		// Associe au user pour le cleanup
		file_put_contents($dest . '.meta', json_encode(['name' => $name, 'user' => $_SESSION['user_id']]));
		return JsonResponse::ok(['token' => $token, 'name' => $name, 'size' => $f['size']]);
	}

	/**
	 * Supprime une PJ temporaire (icône × de la modale compose, PJ
	 * abandonnées au reset). Idempotent : token absent/inconnu > ok quand
	 * même, le client enchaîne des deletes de fichiers déjà partis.
	 */
	public function composeUploadDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$tmp = $this->tmpAttachmentPath((string) $request->params['token']);
		if ($tmp) {
			@unlink($tmp['path']);
			@unlink($tmp['path'] . '.meta');
		}
		return JsonResponse::ok();
	}

	public function composeSaveDraft(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Un brouillon n'exige qu'un corps (sujet optionnel, comme tout
		// webmail standard) - buildMessage retourne null sinon.
		$message = $this->buildMessage($request);
		if (!$message) {
			return JsonResponse::error($this->t('errors.empty_body'), 'empty');
		}
		$drafts = $this->app->mail()->specialFolder('drafts');
		if ($drafts === null) {
			return JsonResponse::error(
				$this->t('errors.folder_role_none', ['role' => 'drafts']), 'role_none');
		}
		// Brouillon d'une réponse : la cible du flag \Answered (posé à
		// l'envoi) est persistée dans le message via X-Draft-Info - en-tête
		// interne, jamais écrit pour un envoi réel (buildMessage ne l'ajoute pas).
		$replyUid = (int) $request->input('replyUid', 0);
		$replyFolder = $this->noCrlf((string) $request->input('replyFolder', ''), 'replyFolder');
		if ($replyUid > 0 && $replyFolder !== '') {
			$message->Headers->AddByName(
				\MailSo\Mime\Enumerations\Header::X_DRAFT_INFO,
				"type=reply;uid={$replyUid};folder=" . base64_encode($replyFolder));
		} elseif (($fwdUid = (int) $request->input('forwardUid', 0)) > 0
			&& ($fwdFolder = $this->noCrlf((string) $request->input('forwardFolder', ''), 'forwardFolder')) !== '') {
			// Brouillon d'un transfert simple : meme mecanisme, type=forward.
			// (Le multi-transfert ne persiste pas ses cibles - un seul
			// en-tete X-Draft-Info, limitation acceptee.)
			$message->Headers->AddByName(
				\MailSo\Mime\Enumerations\Header::X_DRAFT_INFO,
				"type=forward;uid={$fwdUid};folder=" . base64_encode($fwdFolder));
		}
		// Ré-enregistrement d'un brouillon ouvert : REPLACE dans le dossier
		// où il vit (RFC 8508 ou append+expunge ciblé), APPEND dans Drafts
		// sinon - plus de doublon à chaque "Brouillon".
		$ref = $this->draftRef($request);
		$uid = $this->app->mail()->replaceMessage(
			$message->ToString(), $ref ? $ref[0] : $drafts, $ref ? $ref[1] : 0,
			['\\Seen', '\\Draft']);
		unset($message); // libère les streams des PJ (fopen dans buildMessage)
		// Pas de cleanupTmpAttachments : le client référence encore les PJ par
		// token pour les enregistrements suivants (autosave) et l'envoi - les
		// tmp sont purgés à l'envoi, à la suppression explicite ou après 24 h.
		return JsonResponse::ok(['uid' => $uid, 'folder' => $ref ? $ref[0] : $drafts]);
	}

	public function responses(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->cannedResponses((int) $_SESSION['user_id']));
	}

	public function responseSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$uid = (int) $_SESSION['user_id'];
		$data = (array) $request->json();
		$id = (int) ($data['id'] ?? 0);
		$name = trim((string) ($data['name'] ?? ''));
		$body = (string) ($data['body'] ?? '');
		if ($name === '' || mb_strlen($name) > 100 || trim($body) === ''
			|| strlen($body) > 65536) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'validation', 400);
		}
		$list = $this->cannedResponses($uid);
		foreach ($list as $r) {
			if (strcasecmp((string) $r['name'], $name) === 0 && (int) $r['id'] !== $id) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'duplicate', 400);
			}
		}
		if ($id) {
			$found = false;
			foreach ($list as &$r) {
				if ((int) $r['id'] === $id) {
					$r = ['id' => $id, 'name' => $name, 'body' => $body];
					$found = true;
					break;
				}
			}
			unset($r);
			if (!$found) {
				return JsonResponse::error($this->t('errors.not_found'), 'not_found', 404);
			}
		} else {
			if (count($list) >= 50) {
				return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'name']), 'limit', 400);
			}
			$id = max([0, ...array_map(fn($r) => (int) $r['id'], $list)]) + 1;
			$list[] = ['id' => $id, 'name' => $name, 'body' => $body];
		}
		$this->app->settings()->set($uid, 'canned_responses', array_values($list));
		return JsonResponse::ok(['id' => $id]);
	}

	public function responseDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$uid = (int) $_SESSION['user_id'];
		$id = (int) $request->params['id'];
		$list = array_values(array_filter($this->cannedResponses($uid),
			fn($r) => (int) $r['id'] !== $id));
		$this->app->settings()->set($uid, 'canned_responses', $list);
		return JsonResponse::ok();
	}

	/** Construit le Mime\Message depuis la requête (partagé send/save-draft). */
	private function buildMessage(Request $request): ?\MailSo\Mime\Message
	{
		$message = new \MailSo\Mime\Message();
		$message->DoesNotAddDefaultXMailer();
		try {
			$message->SetFrom(\MailSo\Mime\Email::Parse((string) $request->input('from', $_SESSION['email'])));
		} catch (\ValueError) {
			throw new \RuntimeException($this->t('errors.invalid_value', ['key' => 'from']), 400);
		}
		// L'expéditeur doit être l'adresse du compte ou une identité déclarée :
		// sans contrôle serveur, un client pourrait usurper n'importe quel From.
		$fromEmail = strtolower($message->GetFrom()?->GetEmail(true) ?? '');
		// GetEmail(true) décode l'IDN : les deux formes (stockée brute et
		// UTF-8) sont admises - une identité saisie en punycode doit matcher.
		$allowedFrom = [];
		$identity = null;
		foreach ($this->app->identities()->allForUser((int) $_SESSION['user_id']) as $i) {
			$ie = strtolower((string) $i['email']);
			$iu = strtolower(\MXWebMailLib\IDN::emailToUtf8((string) $i['email']));
			$allowedFrom[] = $ie;
			$allowedFrom[] = $iu;
			if ($identity === null && in_array($fromEmail, [$ie, $iu], true)) {
				$identity = $i;
			}
		}
		$allowedFrom[] = strtolower((string) $_SESSION['email']);
		$allowedFrom[] = strtolower(\MXWebMailLib\IDN::emailToUtf8((string) $_SESSION['email']));
		if ($fromEmail === '' || !in_array($fromEmail, $allowedFrom, true)) {
			throw new \RuntimeException($this->t('errors.from_denied'), 403);
		}
		// Champs de l'identité appliqués : nom affiché du From et Reply-To.
		if ($identity) {
			$idName = str_replace(["\r", "\n"], ' ', trim((string) ($identity['name'] ?? '')));
			if ($idName !== '') {
				$message->SetFrom(new \MailSo\Mime\Email(
					$message->GetFrom()->GetEmail(), $idName));
			}
			$idReplyTo = trim((string) ($identity['reply_to'] ?? ''));
			if ($idReplyTo !== '') {
				$rt = new \MailSo\Mime\EmailCollection($idReplyTo);
				if ($rt->count()) {
					$message->SetReplyTo($rt);
				}
			}
		}
		// Parse strict : le parseur de collection ignore silencieusement les
		// segments invalides - sans contrôle, "a@b.com, mauvaise" partirait à
		// a@b.com en laissant croire que "mauvaise" était destinataire.
		[$oTo, $badTo] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('to', ''));
		[$oCc, $badCc] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('cc', ''));
		[$oBcc, $badBcc] = \MailSo\Mime\EmailCollection::ParseStrict((string) $request->input('bcc', ''));
		$rejected = array_merge($badTo, $badCc, $badBcc);
		if ($rejected) {
			throw new \RuntimeException(
				$this->t('errors.bad_rcpt', ['list' => implode(', ', array_slice($rejected, 0, 5))]), 400);
		}
		$message->SetTo($oTo);
		$message->SetCc($oCc);
		// Le Bcc de l'identité est fusionné avec celui du formulaire, sans doublon
		// (un brouillon rouvert peut déjà contenir le Bcc d'identité).
		if ($identity && ($idBcc = trim((string) ($identity['bcc'] ?? ''))) !== '') {
			$aHave = array();
			foreach ($oBcc as $oE) { $aHave[] = strtolower($oE->GetEmail()); }
			foreach (new \MailSo\Mime\EmailCollection($idBcc) as $oE) {
				if (!\in_array(strtolower($oE->GetEmail()), $aHave, true)) {
					$oBcc->append($oE);
					$aHave[] = strtolower($oE->GetEmail());
				}
			}
		}
		$message->SetBcc($oBcc);
		// CR/LF neutralisés à la source : ces valeurs partent dans des
		// en-têtes MIME (Subject, In-Reply-To, References).
		$message->SetSubject(str_replace(["\r", "\n"], ' ', (string) $request->input('subject', '')));
		if ($request->input('priority') === true || $request->input('priority') === 'high') {
			$message->SetPriority(\MailSo\Mime\Enumerations\MessagePriority::HIGH);
		}
		// Accusé de réception (RFC 3798) : From validé, jamais l'input brut.
		if ($request->input('mdn') === true || $request->input('mdn') === '1'
			|| $request->input('mdn') === 1) {
			$message->Headers->AddByName(
				\MailSo\Mime\Enumerations\Header::DISPOSITION_NOTIFICATION_TO,
				$fromEmail);
		}
		$message->SetInReplyTo(str_replace(["\r", "\n"], '', (string) $request->input('inReplyTo', '')));
		$message->SetReferences(str_replace(["\r", "\n"], '', (string) $request->input('references', '')));
		$message->RegenerateMessageId($_SESSION['domain'] ?? '');

		$html = (string) $request->input('html', '');
		$plain = (string) $request->input('plain', '');
		// Le marqueur de position du curseur de signature ne doit jamais partir dans un mail
		$html = (string) preg_replace(
			'#<span[^>]*class="[^"]*mxwm-sig-caret[^"]*"[^>]*>.*?</span>#s', '', $html);
		// Sorties by-ref de BuildHtml : typées array, un null > TypeError.
		$foundCids = [];
		$foundData = [];
		$foundLoc = [];
		if ($html !== '') {
			// multipart/alternative : plain + html (quoted-printable)
			$alt = new \MailSo\Mime\Part();
			$alt->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE, 'multipart/alternative');
			$message->SubParts->append($alt);

			$html = \MailSo\Base\HtmlUtils::BuildHtml($html, $foundCids, $foundData, $foundLoc);
			$alt->addPlain($plain !== '' ? $plain : \MailSo\Base\HtmlUtils::ConvertHtmlToPlain($html));

			$htmlPart = new \MailSo\Mime\Part();
			$htmlPart->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TYPE, 'text/html; charset=utf-8');
			$htmlPart->Headers->AddByName(\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING, 'quoted-printable');
			$htmlPart->Body = \MailSo\Base\StreamWrappers\Binary::CreateStream(
				\MailSo\Base\ResourceRegistry::CreateMemoryResourceFromString(
					preg_replace('/\\r?\\n/su', "\r\n", trim($html))
				),
				'convert.quoted-printable-encode'
			);
			$alt->SubParts->append($htmlPart);
		} elseif ($plain !== '') {
			$message->addPlain($plain);
		} else {
			return null;
		}

		// Pièces jointes : tokens tmp uploadés (string), ou références à une
		// PJ d'un message existant {folder, uid, part} - brouillon rouvert ou
		// transfert. Le nom/mime/cid sont résolus côté serveur (meta du
		// message), jamais depuis le client ; la limite totale est vérifiée
		// sur les tailles réelles.
		$attachLimit = (int) $this->app->config()->get('attachments_max_total', 25) * 1024 * 1024;
		$attachTotal = 0;
		$msgCache = [];
		foreach ((array) $request->input('attachments', []) as $att) {
			if (is_string($att)) {
				$tmp = $this->tmpAttachmentPath($att);
				// Token inconnu ou fichier purgé (24 h) : refus explicite -
				// un "continue" enverrait le message sans la PJ affichée.
				if (!$tmp || !is_file($tmp['path'])) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$size = filesize($tmp['path']);
				$attachTotal += $size;
				if ($attachTotal > $attachLimit) {
					throw new \RuntimeException(
						$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
				}
				$mime = \MXWebMailLib\File\MimeType::fromFilename($tmp['name']) ?? 'application/octet-stream';
				$message->Attachments()->append(
					new \MailSo\Mime\Attachment(fopen($tmp['path'], 'rb'), $tmp['name'], $size, false, false, '', [], '', $mime)
				);
				continue;
			}
			if (!is_array($att)) {
				continue;
			}
			$folder = $this->noCrlf((string) ($att['folder'] ?? ''), 'attachments');
			$uid = (int) ($att['uid'] ?? 0);
			if (!empty($att['eml'])) {
				// Transfert en pièce jointe : le message source complet est
				// attaché en message/rfc822 (nom = sujet côté serveur).
				if ($folder === '' || $uid <= 0) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$cacheKey = $folder . "\0" . $uid;
				if (!array_key_exists($cacheKey, $msgCache)) {
					$msgCache[$cacheKey] = $this->app->mail()->message($folder, $uid);
				}
				$src = $msgCache[$cacheKey];
				if (!$src) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$stream = null;
				$ok = $this->app->mail()->mimeStream($folder, $uid, '', function ($r) use (&$stream) {
					$stream = fopen('php://temp', 'w+b');
					stream_copy_to_stream($r, $stream);
					rewind($stream);
				});
				if (!$ok || !is_resource($stream)) {
					throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
				}
				$size = fstat($stream)['size'] ?? 0;
				$attachTotal += $size;
				if ($attachTotal > $attachLimit) {
					throw new \RuntimeException(
						$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
				}
				$fname = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]/', '', (string) ($src['subject'] ?? '')));
				$message->Attachments()->append(new \MailSo\Mime\Attachment(
					$stream, ($fname !== '' ? $fname : 'message') . '.eml', $size,
					false, false, '', [], '', 'message/rfc822'));
				continue;
			}
			$partId = (string) ($att['part'] ?? '');
			// folder vide > message('') lèverait une exception non mappée (500)
			if ($folder === '' || $uid <= 0 || !preg_match('/^\d+(\.\d+)*$/', $partId)) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			$cacheKey = $folder . "\0" . $uid;
			if (!array_key_exists($cacheKey, $msgCache)) {
				$msgCache[$cacheKey] = $this->app->mail()->message($folder, $uid);
			}
			$meta = null;
			foreach ($msgCache[$cacheKey]['attachments'] ?? [] as $a) {
				if ($a['mimeIndex'] === $partId) { $meta = $a; break; }
			}
			if (!$meta) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			// Le flux IMAP ne survit pas au fetch > copie dans php://temp
			// (rewind exigé : Attachment re-stream depuis le début).
			$stream = null;
			$ok = $this->app->mail()->mimeStream($folder, $uid, $partId, function ($r) use (&$stream) {
				$stream = fopen('php://temp', 'w+b');
				stream_copy_to_stream($r, $stream);
				rewind($stream);
			});
			if (!$ok || !is_resource($stream)) {
				throw new \RuntimeException($this->t('errors.attachment_missing'), 400);
			}
			$size = fstat($stream)['size'] ?? 0;
			$attachTotal += $size;
			if ($attachTotal > $attachLimit) {
				throw new \RuntimeException(
					$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
			}
			// PJ inline à cid > linked : le multipart/related est recréé à
			// la sérialisation et les <img src="cid:"> du corps restent
			// fonctionnels à la re-sauvegarde.
			$cid = (string) ($meta['cid'] ?? '');
			$message->Attachments()->append(
				new \MailSo\Mime\Attachment(
					$stream, $meta['name'], $size, (bool) $meta['isInline'],
					$cid !== '', $cid, [], (string) ($meta['location'] ?? ''), (string) $meta['mime'])
			);
		}
		// <img src="data:…"> réécrites en "cid:hash" par BuildHtml : chaque
		// data-URI devient une PJ inline liée (multipart/related), sinon le
		// cid resterait orphelin chez le destinataire - image cassée.
		$imgIdx = 0;
		foreach ($foundData as $hash => $dataUrl) {
			if (!preg_match('#^data:([^;,]+)((?:;[^;,]*)*),(.*)$#is', (string) $dataUrl, $m)
				|| !str_starts_with(strtolower($m[1]), 'image/')) {
				continue;
			}
			$raw = stripos($m[2], ';base64') !== false
				? base64_decode((string) preg_replace('/\s+/', '', $m[3]), true)
				: rawurldecode($m[3]);
			if (!is_string($raw) || $raw === '') {
				continue;
			}
			$attachTotal += strlen($raw);
			if ($attachTotal > $attachLimit) {
				throw new \RuntimeException(
					$this->t('errors.attachments_too_big', ['mb' => (int) ($attachLimit / 1048576)]), 400);
			}
			$stream = fopen('php://temp', 'w+b');
			if (!is_resource($stream)) {
				continue;
			}
			fwrite($stream, $raw);
			rewind($stream);
			$ext = strtolower((string) preg_replace('/[^a-z0-9]/i', '',
				substr($m[1], strpos($m[1], '/') + 1))) ?: 'bin';
			$message->Attachments()->append(
				new \MailSo\Mime\Attachment(
					$stream, 'image_' . (++$imgIdx) . '.' . $ext, strlen($raw),
					true, true, '<' . $hash . '>', [], '', strtolower(trim($m[1])))
			);
		}
		return $message;
	}

	private function cannedResponses(int $uid): array
	{
		$raw = $this->app->settings()->get($uid, 'canned_responses', []);
		return is_array($raw) ? array_values($raw)
			: (json_decode((string) $raw, true) ?: []);
	}

	/** Supprime les tmp des PJ après envoi réussi. */
	private function cleanupTmpAttachments(array $tokens): void
	{
		foreach ($tokens as $token) {
			if (!is_string($token)) {
				continue; // msgref : rien à purger côté tmp
			}
			$tmp = $this->tmpAttachmentPath($token);
			if ($tmp) {
				@unlink($tmp['path']);
				@unlink($tmp['path'] . '.meta');
			}
		}
	}

	/**
	 * Référence du brouillon à remplacer/consommer : [folder, uid] si la
	 * cible est bien un brouillon, null sinon. Entrée client - sans
	 * contrôle, une requête forgée pourrait remplacer ou purger un message
	 * arbitraire. Accepté directement dans le dossier Drafts ; ailleurs
	 * le message doit porter le flag \Draft.
	 */
	private function draftRef(Request $request): ?array
	{
		$uid = (int) $request->input('draftUid', 0);
		$folder = trim((string) $request->input('draftFolder', ''));
		if ($uid <= 0 || $folder === '') {
			return null;
		}
		$folder = $this->noCrlf($folder, 'draftFolder');
		if ($folder === $this->app->mail()->specialFolder('drafts')) {
			return [$folder, $uid];
		}
		try {
			$msg = $this->app->mail()->message($folder, $uid);
		} catch (\Throwable) {
			return null;
		}
		return ($msg && ($msg['isDraft'] ?? false)) ? [$folder, $uid] : null;
	}

	private function tmpAttachmentPath(string $token): ?array
	{
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			return null;
		}
		$path = $this->app->storageDir() . '/tmp/attach_' . $token;
		$meta = is_file($path . '.meta') ? json_decode((string) file_get_contents($path . '.meta'), true) : null;
		if (!$meta || $meta['user'] !== ($_SESSION['user_id'] ?? null)) {
			return null;
		}
		return ['path' => $path, 'name' => $meta['name'] ?? 'attachment'];
	}
}
