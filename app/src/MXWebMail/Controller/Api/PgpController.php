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




final class PgpController extends BaseController
{
	public function pgpKeys(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->pgp()->all((int) $_SESSION['user_id']));
	}

	public function pgpKeySave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$id = $this->app->pgp()->save(
			(int) $_SESSION['user_id'],
			(string) $request->input('email'),
			(string) $request->input('armored'),
			(bool) $request->input('private', false)
		);
		return JsonResponse::ok(['id' => $id]);
	}

	public function pgpKeyDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->app->pgp()->delete((int) $_SESSION['user_id'], (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Clés publiques connues pour une liste de destinataires (?emails=a,b,c). */
	public function pgpKeysLookup(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$emails = preg_split('/[,;]/', (string) $request->input('emails', ''));
		return JsonResponse::ok($this->app->pgp()->publicKeysFor((int) $_SESSION['user_id'], $emails));
	}
}
