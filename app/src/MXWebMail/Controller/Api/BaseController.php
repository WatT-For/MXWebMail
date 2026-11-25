<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;
use MXWebMail\UserFacingException;




abstract class BaseController extends \MXWebMail\Controller\BaseController
{
	/** Critères IMAP SORT acceptés en ?sort= sur /api/messages (RFC 5256). */
	protected const SORT_CRITERIA = [
		'REVERSE DATE', 'DATE', 'ARRIVAL', 'REVERSE ARRIVAL',
		'FROM', 'REVERSE FROM', 'TO', 'REVERSE TO', 'CC',
		'SUBJECT', 'REVERSE SUBJECT', 'SIZE', 'REVERSE SIZE',
	];

	/**
	 * requireAuth + session_write_close : pour les endpoints en lecture
	 * seule (GET). Le fichier de session resterait sinon verrouillé durant
	 * les appels IMAP/Sieve/HTTP - poll, images cid:, quota et actions de
	 * l'onglet se sérialiseraient les uns derrière les autres.
	 * Csrf::token() avant la fermeture : créé normalement au rendu de page,
	 * mais garanti ici - sinon il régénèrerait à chaque requête et tous les
	 * POST échoueraient en 403. $_SESSION reste lisible après fermeture ;
	 * seule la persistance s'arrête.
	 */
	protected function requireReadOnly(): void
	{
		$this->requireAuth();
		if (session_status() === PHP_SESSION_ACTIVE) {
			Csrf::token();
			session_write_close();
		}
	}

	protected function requireAuth(): void
	{
		if (!$this->app->isLoggedIn()) {
			throw new UserFacingException($this->t('errors.unauthenticated'), 401, jsonCode: 'auth');
		}
	}

	/** requireAuth + requireCsrf : prérequis commun des endpoints d'écriture (POST). */
	protected function requireWrite(): void
	{
		$this->requireAuth();
		$this->requireCsrf();
	}

	protected function requireDangerous(): void
	{
		if (!$this->app->config()->get('dangerous_actions', true)) {
			throw new UserFacingException($this->t('errors.dangerous_off'), 403, jsonCode: 'forbidden');
		}
	}

	/**
	 * Rejette CR/LF dans les paramètres IMAP/Sieve : ces caractères
	 * termineraient la commande en cours sur le socket (injection).
	 */
	protected function noCrlf(string $value, string $key): string
	{
		if (strpbrk($value, "\r\n") !== false) {
			throw new UserFacingException(
				$this->t('errors.invalid_value', ['key' => $key]), 400, jsonCode: 'validation');
		}
		return $value;
	}

	/**
	 * Dossier IMAP du corps de requête (input 'folder', défaut INBOX) -
	 * à distinguer de folderParam() : la route {name} est encodée
	 * base64url, pas le corps JSON/query.
	 */
	protected function folderInput(Request $request): string
	{
		return $this->noCrlf((string) $request->input('folder', 'INBOX'), 'folder');
	}

	/**
	 * [folder, uid] d'une route message /api/messages/{uid}/... : dossier
	 * du corps de requête (défaut INBOX) + uid du paramètre de route.
	 * @return array{0: string, 1: int}
	 */
	protected function messageRef(Request $request): array
	{
		return [$this->folderInput($request), (int) $request->params['uid']];
	}

	/**
	 * Convention : le {name} des routes dossiers est TOUJOURS encodé en
	 * base64url par le frontend (les noms de dossiers peuvent contenir '/').
	 */
	protected function folderParam(Request $request): string
	{
		$raw = (string) ($request->params['name'] ?? '');
		if ($raw !== '') {
			// Round-trip strict : un nom composé de caractères base64 ("INBOX")
			// envoyé brut ne doit pas être décodé en binaire - il reste tel quel.
			$dec = base64_decode(strtr($raw, '-_', '+/'), true);
			if (is_string($dec) && rtrim(base64_encode($dec), '=') === strtr($raw, '-_', '+/')) {
				return $this->noCrlf($dec, 'folder');
			}
			return $this->noCrlf($raw, 'folder');
		}
		return $this->noCrlf((string) $request->input('name'), 'folder');
	}

	/**
	 * Page de messages normalisée - partagée par GET /api/messages et la
	 * section "list" de GET /api/poll. $p = paramètres bruts (query ou JSON
	 * décodé) : folder, page, limit, search, sort, threads, thread_uid.
	 */
	protected function listData(array $p): array
	{
		$mail = $this->app->mail();
		$folder = $this->noCrlf((string) ($p['folder'] ?? 'INBOX'), 'folder');
		$page = max(1, (int) ($p['page'] ?? 1));
		$limit = min(200, max(1, (int) ($p['limit'] ?? 50)));
		$search = $this->noCrlf((string) ($p['search'] ?? ''), 'search');
		$sort = (string) ($p['sort'] ?? '');
		if (!in_array($sort, self::SORT_CRITERIA, true)) {
			$sort = 'REVERSE DATE';
		}
		$threads = !empty($p['threads']);
		$threadUid = (int) ($p['thread_uid'] ?? 0);
		// folder=* : recherche multi-dossiers (fusion triée date desc).
		$data = $folder === '*'
			? $mail->searchAllFolders($search, ($page - 1) * $limit, $limit)
			: $mail->messages($folder, ($page - 1) * $limit, $limit,
				$search, $sort, $threads, $threadUid);
		$data['page'] = $page;
		$data['limit'] = $limit;
		return $data;
	}
}
