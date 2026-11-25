<?php
declare(strict_types=1);

namespace MXWebMail\Http;

/**
 * Réponse zip streamée partagée : en-têtes de téléchargement, ajout de
 * chaque entrée par son fetcher, bloc ERREURS.txt listant les échecs.
 * Utilisé par attachmentsZip (PJ d'un message) et emlZip (messages .eml).
 *
 * $entries : itérable "nom d'entrée" => callable(callable $add): bool.
 * Le fetcher doit appeler $add(ressource_flux) pour écrire l'entrée et
 * retourner son succès - typiquement
 * fn($add) => $mail->mimeStream($folder, $uid, $mimeIndex, $add).
 */
final class ZipStreamer
{
	public static function send(string $downloadName, iterable $entries, string $errorLabel): Response
	{
		$zip = new \MXWebMailLib\Stream\ZIP();
		$zip->pushHttpHeaders($downloadName);
		$failed = [];
		foreach ($entries as $name => $fetch) {
			if (!$fetch(fn($r) => $zip->addFromStream($r, $name))) {
				$failed[] = $name;
			}
		}
		if ($failed) {
			$zip->addFromString('ERREURS.txt',
				$errorLabel . " :\n" . implode("\n", $failed) . "\n");
		}
		$zip->close();
		return new Response('', 200);
	}
}
