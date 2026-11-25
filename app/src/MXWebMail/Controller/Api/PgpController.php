<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Api;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;




final class PgpController extends BaseController
{
	public function pgpKeys(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		return JsonResponse::ok($this->app->pgp()->all($this->app->sessionUserId()));
	}

	public function pgpKeySave(Request $request): JsonResponse
	{
		$this->requireWrite();
		$id = $this->app->pgp()->save(
			$this->app->sessionUserId(),
			(string) $request->input('email'),
			(string) $request->input('armored'),
			(bool) $request->input('private', false)
		);
		return JsonResponse::ok(['id' => $id]);
	}

	public function pgpKeyDelete(Request $request): JsonResponse
	{
		$this->requireWrite();
		$this->app->pgp()->delete($this->app->sessionUserId(), (int) $request->params['id']);
		return JsonResponse::ok();
	}

	/** Clés publiques connues pour une liste de destinataires (?emails=a,b,c). */
	public function pgpKeysLookup(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$emails = preg_split('/[,;]/', (string) $request->input('emails', ''));
		return JsonResponse::ok($this->app->pgp()->publicKeysFor($this->app->sessionUserId(), $emails));
	}
}
