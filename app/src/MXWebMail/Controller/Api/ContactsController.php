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




final class ContactsController extends BaseController
{
	public function contacts(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$this->requireContacts();
		$q = $this->noCrlf((string) $request->input('q', ''), 'q');
		$userId = (int) $_SESSION['user_id'];
		$limit = (int) $this->app->config()->get('contacts_suggestions_limit', 20);
		$data = $q === ''
			? $this->app->contacts()->all($userId)
			: $this->app->contacts()->search($userId, $q, $limit);

		// Hook : des plugins peuvent fournir des suggestions externes (ldap, carddav…)
		foreach ($this->app->hooks()->collect('contacts.provider', $q, $userId) as $extra) {
			foreach ((array) $extra as $c) {
				$data[] = $c;
			}
		}
		return JsonResponse::ok($data);
	}

	public function contactSave(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$id = $this->app->contacts()->save((int) $_SESSION['user_id'], (array) $request->json());
		return JsonResponse::ok(['id' => $id]);
	}

	public function contactDelete(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$this->app->contacts()->delete((int) $_SESSION['user_id'], (int) $request->input('id'));
		return JsonResponse::ok();
	}

	/**
	 * Import de contacts (multipart 'file') : déduplication sur les emails
	 * existants. Formats : .vcf, .csv, ou carnet RainLoop/SnappyMail
	 * (AddressBook.sqlite - détecté par signature, pas par extension).
	 */
	public function contactsImport(Request $request): JsonResponse
	{
		$this->requireAuth();
		$this->requireCsrf();
		$this->requireContacts();
		$f = $request->files['file'] ?? null;
		if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
			return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
		}
		if ((int) ($f['size'] ?? 0) > 5 * 1024 * 1024) {
			return JsonResponse::error(
				$this->t('errors.file_too_big', ['mb' => 5]), 'too_big', 400);
		}
		$tmp = (string) $f['tmp_name'];
		$source = '';
		if (\MXWebMail\Service\RainLoopContacts::isSqlite(
				(string) file_get_contents($tmp, false, null, 0, 16))) {
			// Lecture seule : le fichier uploadé n'est jamais modifié. Un
			// sqlite non-RainLoop (tables absentes) > PDOException > 400.
			try {
				$pdo = new \PDO('sqlite:' . $tmp, null, null, [
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY,
				]);
				$items = \MXWebMail\Service\RainLoopContacts::itemsForUser(
					$pdo, (string) ($this->app->currentUser()['username'] ?? ''));
				if (!$items
					&& \MXWebMail\Service\RainLoopContacts::ownerCount($pdo) > 1) {
					// RuntimeException : non interceptée par le catch PDOException.
					throw new \RuntimeException(
						$this->t('settings.contacts_import_multi'), 400);
				}
			} catch (\PDOException) {
				throw new \RuntimeException(
					$this->t('settings.contacts_import_bad_sqlite'), 400);
			}
			$source = 'rainloop';
		} else {
			$raw = file_get_contents($tmp);
			if ($raw === false || trim($raw) === '') {
				return JsonResponse::error($this->t('errors.bad_upload'), 'bad_upload');
			}
			// .csv > parseur CSV (mapping heuristique) ; tout le reste = vCard.
			$items = str_ends_with(strtolower((string) ($f['name'] ?? '')), '.csv')
				? \MXWebMail\Service\CsvContacts::parse($raw)
				: \MXWebMail\Service\VCardContacts::parse($raw);
		}
		if (!$items) {
			throw new \RuntimeException($this->t('settings.contacts_import_none'), 400);
		}
		$report = $this->app->contacts()->import(
			(int) $_SESSION['user_id'], $items, $source);
		return JsonResponse::ok($report);
	}

	/** Export du carnet complet en .vcf (vCard 3.0). */
	public function contactsExport(Request $request): Response
	{
		$this->requireReadOnly();
		$this->requireContacts();
		$vcf = \MXWebMail\Service\VCardContacts::export(
			$this->app->contacts()->exportRows((int) $_SESSION['user_id']));
		return new Response($vcf, 200, [
			'Content-Type'        => 'text/vcard; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="contacts.vcf"',
		]);
	}

	private function requireContacts(): void
	{
		if (!$this->app->config()->get('contacts_enable', true)) {
			throw new \RuntimeException($this->t('errors.contacts_off'), 403);
		}
	}
}
