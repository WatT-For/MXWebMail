<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Aperçu des pièces jointes iCalendar (.ics / text/calendar).
 * Le parsing se fait côté serveur via Sabre VObject (vendored) -
 * pas de lib JS externe. Le reader affiche un bouton "Calendrier"
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

	/** VCALENDAR d'une pièce jointe .ics - partagé apiEvent/apiReply. */
	private function fetchIcs(Request $request): \Sabre\VObject\Component\VCalendar
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
		return $vcal;
	}

	/** Récapitulatif du/des VEVENT contenus dans une pièce jointe. */
	public function apiEvent(Request $request, ?array $user): array
	{
		$vcal = $this->fetchIcs($request);

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

	/**
	 * Réponse iTIP à une invitation (METHOD:REQUEST) : ACCEPTED /
	 * DECLINED / TENTATIVE - VCALENDAR REPLY envoyé par SMTP à
	 * l'organisateur. Répondre sans client agenda.
	 */
	public function apiReply(Request $request, ?array $user): array
	{
		$partstat = strtoupper(trim((string) $request->input('partstat', '')));
		if (!\in_array($partstat, ['ACCEPTED', 'DECLINED', 'TENTATIVE'], true)) {
			throw new \RuntimeException($this->t('plugin.ics-viewer.bad_params'), 400);
		}
		$vcal = $this->fetchIcs($request);
		if (strtoupper((string) ($vcal->METHOD ?? '')) !== 'REQUEST') {
			throw new \RuntimeException($this->t('plugin.ics-viewer.reply_no_request'), 400);
		}
		$src = null;
		foreach ($vcal->select('VEVENT') as $v) {
			$src = $v;
			break;
		}
		if (!$src || !isset($src->UID) || !isset($src->ORGANIZER)) {
			throw new \RuntimeException($this->t('plugin.ics-viewer.reply_malformed'), 400);
		}
		$organizer = preg_replace('/^mailto:/i', '', (string) $src->ORGANIZER);
		$email = (string) ($_SESSION['email'] ?? $user['username'] ?? '');
		if (!filter_var($organizer, FILTER_VALIDATE_EMAIL) || $email === '') {
			throw new \RuntimeException($this->t('plugin.ics-viewer.reply_malformed'), 400);
		}

		// VCALENDAR REPLY : VEVENT copié (identité + horaires) + ATTENDEE.
		$reply = new \Sabre\VObject\Component\VCalendar();
		$reply->METHOD = 'REPLY';
		$ve = $reply->add('VEVENT');
		foreach (['UID', 'DTSTAMP', 'SEQUENCE', 'DTSTART', 'DTEND',
				'SUMMARY', 'LOCATION'] as $p) {
			if (isset($src->{$p})) {
				$ve->add($p, (string) $src->{$p});
			}
		}
		$ve->add('ORGANIZER', 'mailto:' . $organizer);
		$att = $ve->add('ATTENDEE', 'mailto:' . $email);
		$att['PARTSTAT'] = $partstat;

		$message = new \MailSo\Mime\Message();
		$message->DoesNotAddDefaultXMailer();
		$message->SetFrom(\MailSo\Mime\Email::Parse($email));
		$message->SetTo(new \MailSo\Mime\EmailCollection($organizer));
		$message->SetSubject($this->t(
			'plugin.ics-viewer.reply_subject_' . strtolower($partstat),
			['v' => isset($src->SUMMARY) ? (string) $src->SUMMARY : '']));
		$part = new \MailSo\Mime\Part();
		$part->Headers->AddByName(
			\MailSo\Mime\Enumerations\Header::CONTENT_TYPE,
			'text/calendar; method=REPLY; charset=utf-8');
		$part->Headers->AddByName(
			\MailSo\Mime\Enumerations\Header::CONTENT_TRANSFER_ENCODING,
			'quoted-printable');
		$part->Body = quoted_printable_encode($reply->serialize());
		$message->SubParts->append($part);

		$this->app->mail()->send($message);
		$this->app->log('info',
			"ics-viewer: réponse {$partstat} envoyée à {$organizer}");
		return ['replied' => $partstat];
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
		// ISO 8601 : le frontend formate selon la locale (toLocaleString).
		return $dt ? $dt->format(\DateTimeInterface::ATOM) : null;
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
