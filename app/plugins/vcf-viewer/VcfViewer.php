<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Apercu des pieces jointes vCard (.vcf / text/vcard) dans le lecteur +
 * import en un clic dans le carnet (VCardContacts + ContactRepository du
 * coeur - meme chaine que l'import de fichier des parametres).
 * Le bouton du lecteur apparait quand features.vcf_viewer est vrai
 * (template + previewVcf() cote frontend, meme precedente qu'ics-viewer).
 * API : /api/plugin/vcf-viewer/{card|import}?folder=&uid=&mime=
 */
final class VcfViewer extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			$data['features']['vcf_viewer'] = true;
			return $data;
		});
	}

	/** Items contacts d'une PJ .vcf (structure ContactRepository::import). */
	private function fetchVcf(Request $request): array
	{
		$folder = (string) $request->input('folder', 'INBOX');
		$uid = (int) $request->input('uid', 0);
		$mimeIndex = (string) $request->input('mime', '');
		if ($uid <= 0 || $mimeIndex === '' || strpbrk($folder, "\r\n") !== false) {
			throw new \RuntimeException($this->t('plugin.vcf-viewer.bad_params'), 400);
		}
		$raw = null;
		$ok = $this->app->mail()->mimeStream($folder, $uid, $mimeIndex,
			function ($r) use (&$raw) {
				$raw = stream_get_contents($r, 2 * 1024 * 1024);
			});
		if (!$ok || !$raw) {
			throw new \RuntimeException($this->t('plugin.vcf-viewer.attachment_missing'), 404);
		}
		return \MXWebMail\Service\VCardContacts::parse($raw);
	}

	/** Recapitulatif de/des vCard contenues dans une piece jointe. */
	public function apiCard(Request $request, ?array $user): array
	{
		return ['cards' => array_values($this->fetchVcf($request))];
	}

	/** Import des vCards (index optionnel = une seule carte du fichier). */
	public function apiImport(Request $request, ?array $user): array
	{
		$items = $this->fetchVcf($request);
		$index = $request->input('index');
		if ($index !== null && $index !== '') {
			$i = (int) $index;
			$items = isset($items[$i]) ? [$items[$i]] : [];
		}
		if (!$items) {
			throw new \RuntimeException($this->t('plugin.vcf-viewer.none'), 400);
		}
		return $this->app->contacts()->import((int) $user['id'], $items, 'vcard');
	}
}
