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




final class FoldersController extends BaseController
{
	public function folders(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->folders());
	}

	public function folderCreate(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel - deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$before = array_column($mail->folders(), 'fullName');
		$mail->folderCreate(
			$this->noCrlf((string) $request->input('name'), 'name'),
			$this->noCrlf((string) $request->input('parent', ''), 'parent')
		);
		// Créé via l'interface > exempté du classement par nom usuel (diff
		// des fullNames : fiable quel que soit le délimiteur/l'encodage).
		$mail->markUserFolders(array_values(array_diff(
			array_column($mail->folders(), 'fullName'), $before)));
		return JsonResponse::ok();
	}

	public function folderDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireDangerous();
		$mail = $this->app->mail();
		$mail->folderDelete($this->requireNonSystemFolder($request));
		$mail->pruneUserFolders();
		return JsonResponse::ok();
	}

	public function folderRename(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$mail = $this->app->mail();
		$name = $this->requireNonSystemFolder($request);
		$delimiter = $this->folderDelimiter($mail, $name);
		$to = $this->noCrlf((string) $request->input('newName'), 'newName');
		$mail->folderRename($name, $to);
		$mail->renameUserFolder($name, $to, $delimiter);
		return JsonResponse::ok();
	}

	public function folderSubscribe(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderSubscribe($this->folderParam($request), (bool) $request->input('subscribe', true));
		return JsonResponse::ok();
	}

	public function folderClear(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireDangerous();
		$this->app->mail()->folderClear($this->requireNonSystemFolder($request, true));
		return JsonResponse::ok();
	}

	public function folderMarkRead(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Pas de requireNonSystemFolder : non destructif et utile sur INBOX/système.
		$this->app->mail()->folderMarkRead($this->folderParam($request));
		return JsonResponse::ok();
	}

	public function folderAcl(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->folderAcl($this->folderParam($request)));
	}

	public function folderAclSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderSetAcl(
			$this->requireNonSystemFolder($request),
			$this->noCrlf((string) $request->input('identifier'), 'identifier'),
			$this->noCrlf((string) $request->input('rights'), 'rights')
		);
		return JsonResponse::ok();
	}

	public function folderAclDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->folderDeleteAcl(
			$this->requireNonSystemFolder($request),
			$this->noCrlf((string) $request->input('identifier'), 'identifier')
		);
		return JsonResponse::ok();
	}

	/** Assignations des dossiers système du compte actif (auto par défaut). */
	public function foldersSpecialGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel - deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$map = $mail->specialFolderMap();
		$out = [];
		foreach (MailService::SPECIAL_ROLES as $role) {
			// assigned = override stocké ('none'|fullName) ou 'auto' ;
			// detected = dossier de la seule détection - l'override
			// explicite ne doit pas se présenter comme le choix "auto".
			$out[$role] = [
				'assigned' => $map[$role] ?? 'auto',
				'detected' => $mail->detectedSpecialFolder($role),
			];
		}
		return JsonResponse::ok($out);
	}

	public function foldersSpecialSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		// Une seule instance : App::mail() retourne un MailService neuf à
		// chaque appel - deux appels = deux connexions IMAP.
		$mail = $this->app->mail();
		$existing = array_column($mail->folders(), 'fullName');
		$body = $request->json();
		// Un JSON en liste écraserait toutes les assignations (clés rejetées) :
		// refusé plutôt que destructif.
		if (!is_array($body) || ($body !== [] && array_is_list($body))) {
			throw new \InvalidArgumentException($this->t('errors.invalid_value', ['key' => 'special_folders']), 400);
		}
		$map = [];
		foreach ($body as $role => $value) {
			if (!in_array($role, MailService::SPECIAL_ROLES, true) || !is_string($value)) {
				continue;
			}
			if ($value === 'none' || in_array($value, $existing, true)) {
				$map[$role] = $value;
			}
			// 'auto' et valeurs invalides > ignorés (non stocké = auto)
		}
		$mail->setSpecialFolderMap($map);
		return JsonResponse::ok();
	}

	/** Dossiers surveillés pour les notifications (INBOX toujours incluse). */
	public function foldersNotifyGet(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->mail()->notifyFolderList());
	}

	public function foldersNotifySet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->setNotifyFolderList(
			array_map(fn($f) => $this->noCrlf((string) $f, 'folder'), (array) $request->input('folders', [])));
		return JsonResponse::ok();
	}

	/** Dossiers repliés dans la colonne latérale (état UI par compte). */
	public function foldersCollapsedSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->setCollapsedFolders(
			array_map(fn($f) => $this->noCrlf((string) $f, 'folder'), (array) $request->input('folders', [])));
		return JsonResponse::ok();
	}

	/** Dossiers masqués de la colonne latérale (état UI par compte). */
	public function foldersHiddenSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->setHiddenFolders(
			array_map(fn($f) => $this->noCrlf((string) $f, 'folder'), (array) $request->input('folders', [])));
		return JsonResponse::ok();
	}

	/** Dernier dossier consulté, restauré à l'ouverture (état UI par compte). */
	public function foldersLastSet(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->mail()->setLastFolder(
			$this->noCrlf((string) $request->input('folder', ''), 'folder'));
		return JsonResponse::ok();
	}

	/** Import dans un dossier (IMAP APPEND) : .eml (1 message) ou .mbox
	 *  (détection par contenu : 1ʳᵉ ligne "From" - extension non fiable). */
	public function folderImport(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$mail = $this->app->mail();
		$folder = $this->folderParam($request);
		if (!in_array($folder, array_column($mail->folders(), 'fullName'), true)) {
			return JsonResponse::error($this->t('errors.not_found'), 'not_found', 404);
		}
		$f = $request->files['file'] ?? null;
		if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		$maxSize = (int) $this->app->config()->get('attachment_max_size', 25) * 1024 * 1024;
		$isMbox = str_starts_with((string) file_get_contents((string) $f['tmp_name'], false, null, 0, 5), 'From ');
		if ($isMbox) {
			$maxSize = max($maxSize,
				(int) $this->app->config()->get('import_max_size', 100) * 1024 * 1024);
		}
		if ((int) ($f['size'] ?? 0) > $maxSize) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => (int) ($maxSize / 1048576)]), 'too_big', 400);
		}
		// Hook : scan AV (attachments-clamav) - le tmp_name est purgé par
		// PHP en fin de requête, rien à nettoyer si le hook jette.
		$this->app->hooks()->run('attachment.upload', (string) $f['tmp_name'],
			basename(str_replace('\\', '/', (string) ($f['name'] ?? ''))),
			(int) $_SESSION['user_id']);
		if ($isMbox) {
			return JsonResponse::ok($this->importMbox($mail, (string) $f['tmp_name'], $folder));
		}
		$raw = file_get_contents((string) $f['tmp_name']);
		if ($raw === false || trim($raw) === '') {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		return JsonResponse::ok(['uid' => $mail->appendMessage($raw, $folder, ['\\Seen'])]);
	}

	/** Toutes les PJ d'un message en un zip streamé (MXWebMailLib\Stream\ZIP). */
	/** Zip des .eml des messages cochés : GET ?uids=1,2,3 (cap 100, streamé). */
	public function emlZip(Request $request): Response
	{
		$this->requireReadOnly();
		$folder = $this->folderParam($request);
		$uids = array_values(array_unique(array_filter(array_map('intval',
			explode(',', (string) $request->input('uids', ''))))));
		if (!$uids) {
			return JsonResponse::error($this->t('errors.invalid_value', ['key' => 'uids']), 'bad_uids', 400);
		}
		if (count($uids) > 100) {
			return JsonResponse::error(
				$this->t('errors.too_many', ['max' => 100]), 'too_many', 413);
		}
		$mail = $this->app->mail();
		// Valide le dossier AVANT les en-têtes : MessageMimeStream fait le même
		// FolderExamine en interne, mais après émission du Content-Disposition -
		// un dossier invalide produirait un zip vide/tronqué téléchargé.
		try {
			$mail->client()->ImapClient()->FolderExamine($folder);
		} catch (\Throwable) {
			return Response::notFound();
		}
		$zip = new \MXWebMailLib\Stream\ZIP();
		$zip->pushHttpHeaders('messages');
		$failed = [];
		foreach ($uids as $uid) {
			// uid disparu ou illisible : entrée signalée dans ERREURS.txt.
			if (!$mail->mimeStream($folder, $uid, '',
				function ($r) use ($zip, $uid) { $zip->addFromStream($r, "message-{$uid}.eml"); })) {
				$failed[] = $uid;
			}
		}
		if ($failed) {
			$zip->addFromString('ERREURS.txt',
				"Messages introuvables ou illisibles : " . implode(', ', $failed) . "\n");
		}
		$zip->close();
		return new Response('', 200);
	}

	/**
	 * Bloque les actions sensibles sur les dossiers "par défaut" :
	 * $inboxOnly = true > seule INBOX est protégée (vidage) ;
	 * sinon tous les dossiers système (renommage, ACL, suppression).
	 */
	private function requireNonSystemFolder(Request $request, bool $inboxOnly = false): string
	{
		$name = $this->folderParam($request);
		$flags = $this->app->mail()->folderFlags($name);
		if ($flags[$inboxOnly ? 'inbox' : 'system']) {
			throw new \RuntimeException($this->t('errors.folder_protected'), 403);
		}
		return $name;
	}

	/** Délimiteur de hiérarchie d'un dossier existant ('' si introuvable). */
	private function folderDelimiter(MailService $mail, string $fullName): string
	{
		foreach ($mail->folders() as $f) {
			if ($f['fullName'] === $fullName) {
				return (string) ($f['delimiter'] ?? '');
			}
		}
		return '';
	}

	/**
	 * Découpe un fichier mbox et APPEND chaque message (lecture en stream -
	 * pas de chargement complet d'un gros fichier en mémoire).
	 * Séparateur : ligne "From" ; ">From" est dés-échappé. La date de la
	 * ligne "From" devient l'INTERNALDATE du message quand elle est parsable.
	 * @return array{imported:int, failed:int}
	 */
	private function importMbox(\MXWebMail\Service\MailService $mail, string $path, string $folder): array
	{
		$handle = fopen($path, 'rb');
		if (!is_resource($handle)) {
			throw new \RuntimeException($this->t('errors.bad_upload'), 400);
		}
		$imported = 0; $failed = 0; $skipped = 0;
		$msg = ''; $date = 0; $hasMsg = false;
		$atCap = fn() => ($imported + $failed + $skipped) >= 2000;
		$flush = function () use (&$msg, &$date, &$hasMsg, &$imported, &$failed, &$skipped, $atCap, $mail, $folder) {
			if ($hasMsg) {
				if ($atCap()) {
					$skipped++; // plafond de sécurité : compté, non importé
				} elseif (trim($msg) !== '') {
					try {
						$mail->appendMessage($msg, $folder, ['\\Seen'], $date);
						$imported++;
					} catch (\Throwable) { $failed++; }
				}
			}
			$msg = ''; $date = 0; $hasMsg = false;
		};
		while (($line = fgets($handle)) !== false) {
			if (str_starts_with($line, 'From ')) {
				$flush();
				$hasMsg = true;
				$ts = strtotime(substr($line, 5));
				$date = $ts !== false ? $ts : 0;
				continue;
			}
			// Un-escaping ">From" (mboxrd) : > supplémentaire retiré.
			if (preg_match('/^>+From /', $line)) {
				$line = substr($line, 1);
			}
			// Au-delà du plafond le corps n'est plus accumulé (mémoire
			// bornée) - le message reste compté via $hasMsg.
			if (!$atCap()) {
				$msg .= $line;
			}
		}
		$flush();
		fclose($handle);
		return ['imported' => $imported, 'failed' => $failed, 'skipped' => $skipped];
	}
}
