<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Aperçu des pièces jointes iCalendar (.ics / text/calendar).
 * Le parsing se fait côté serveur via Sabre VObject (vendored) —
 * pas de lib JS externe. Le reader affiche un bouton « Calendrier »
 * sur les PJ concernées (features.ics_viewer).
 * API : /api/plugin/ics-viewer/event?folder=&uid=&mime=
 */
final class IcsViewer extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			$data['features']['ics_viewer'] = true;
			return $data;
		});
	}

	/** Récapitulatif du/des VEVENT contenus dans une pièce jointe. */
	public function apiEvent(Request $request, ?array $user): array
	{
		$folder = (string) $request->input('folder', 'INBOX');
		$uid = (int) $request->input('uid', 0);
		$mimeIndex = (string) $request->input('mime', '');
		if ($uid <= 0 || $mimeIndex === '') {
			throw new \RuntimeException($this->t('plugin.ics-viewer.bad_params'), 400);
		}

		$raw = null;
		$ok = $this->app->mail()->mimeStream($folder, $uid, $mimeIndex,
			function ($r) use (&$raw) {
				$raw = stream_get_contents($r, 2 * 1024 * 1024);
			});
		if (!$ok || !$raw) {
			throw new \RuntimeException($this->t('plugin.ics-viewer.attachment_missing'), 404);
		}

		try {
			$vcal = \Sabre\VObject\Reader::read(
				$raw, \Sabre\VObject\Reader::OPTION_FORGIVING);
		} catch (\Throwable) {
			throw new \RuntimeException($this->t('plugin.ics-viewer.invalid_ics'), 400);
		}

		$events = [];
		foreach ($vcal->select('VEVENT') as $v) {
			$events[] = [
				'summary'     => isset($v->SUMMARY) ? (string) $v->SUMMARY : '',
				'description' => isset($v->DESCRIPTION) ? (string) $v->DESCRIPTION : '',
				'location'    => isset($v->LOCATION) ? (string) $v->LOCATION : '',
				'status'      => isset($v->STATUS) ? (string) $v->STATUS : '',
				'organizer'   => $this->organizer($v),
				'start'       => $this->dt($v, 'DTSTART'),
				'end'         => $this->dt($v, 'DTEND'),
				'allDay'      => isset($v->DTSTART) && !$v->DTSTART->hasTime(),
			];
		}
		if (!$events) {
			throw new \RuntimeException($this->t('plugin.ics-viewer.no_events'), 404);
		}
		return [
			'method' => isset($vcal->METHOD) ? (string) $vcal->METHOD : '',
			'events' => $events,
		];
	}

	private function dt(\Sabre\VObject\Component $v, string $prop): ?string
	{
		if (!isset($v->{$prop})) {
			return null;
		}
		try {
			$dt = $v->{$prop}->getDateTime();
		} catch (\Throwable) {
			return null; // date illisible (TZID inconnu, valeur malformée)
		}
		return $dt ? $dt->format('d/m/Y H:i') : null;
	}

	private function organizer(\Sabre\VObject\Component $v): string
	{
		if (!isset($v->ORGANIZER)) {
			return '';
		}
		$cn = (string) ($v->ORGANIZER['CN'] ?? '');
		$addr = preg_replace('/^mailto:/i', '', (string) $v->ORGANIZER);
		return $cn !== '' ? "{$cn} <{$addr}>" : $addr;
	}
}
