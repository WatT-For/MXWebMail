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




/**
 * GET /api/poll : dossiers + derniers messages des dossiers surveillés +
 * page de liste + quota en UNE requête = UNE connexion IMAP. Le tick
 * list_refresh et le boot y sont mutualisés - avant, chaque section était
 * une requête HTTP distincte payant son propre Connect+Login IMAP.
 *
 * Paramètres query (JSON encodés par le client) :
 * - watch : {"<fullName>": <unreadBaseline>} - le dernier message d'un
 *   dossier n'est fetché que si son unread a augmenté vs la baseline
 *   (plafond 20 dossiers).
 * - list  : {folder,page,limit,search,sort,threads,thread_uid} - mêmes
 *   paramètres que GET /api/messages ; absent = pas de section.
 * - quota : "1" = inclure le quota.
 *
 * Résilience par section : folders en échec > erreur globale (comme
 * /api/folders) ; list/quota/latest en échec > null, le client retombe
 * sur les endpoints unitaires ou réessaie au tick suivant.
 */
final class PollController extends BaseController
{
	public function poll(Request $request): JsonResponse
	{
		$this->requireReadOnly();
		$mail = $this->app->mail();
		$folders = $mail->folders();

		$latest = [];
		$watch = json_decode((string) $request->input('watch', ''), true);
		if (is_array($watch)) {
			$byName = [];
			foreach ($folders as $f) {
				$byName[$f['fullName']] = $f;
			}
			foreach (array_slice($watch, 0, 20, true) as $name => $baseline) {
				$name = (string) $name;
				$f = $byName[$name] ?? null;
				if ($f === null || $f['isSelectable'] === false
					|| (int) ($f['unread'] ?? 0) <= (int) $baseline) {
					continue;
				}
				try {
					$latest[$name] = ($meta = $mail->latestMessage($name))
						? ['messages' => [$meta]] : null;
				} catch (\Throwable) {
					$latest[$name] = null;
				}
			}
		}

		$list = null;
		$lp = json_decode((string) $request->input('list', ''), true);
		if (is_array($lp)) {
			try {
				// JSON : une valeur non scalaire (tableau/objet) est ignorée -
				// les défauts de listData s'appliquent (query string = toujours
				// des strings, ce garde n'y change rien).
				$list = $this->listData(array_filter($lp, 'is_scalar'));
			} catch (\Throwable) {
				$list = null;
			}
		}

		$quota = null;
		if ((string) $request->input('quota', '') === '1') {
			try {
				$quota = $mail->quota();
			} catch (\Throwable) {
				$quota = null;
			}
		}

		return JsonResponse::ok([
			'folders' => $folders,
			'latest'  => $latest,
			'list'    => $list,
			'quota'   => $quota,
		]);
	}
}
