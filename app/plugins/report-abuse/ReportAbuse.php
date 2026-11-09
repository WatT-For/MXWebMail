<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Signalement de hameconnage : transfert des mails coches en .eml joints
 * a une adresse abuse fixee par l'admin (le destinataire n'est jamais
 * choisi par l'utilisateur - pas de relais). Option : marquage spam via
 * MailService::markSpam (emet message.spam > apprentissage spam-learn).
 * API : POST /api/plugin/report-abuse/report {items:[{folder,uid}]}
 */
final class ReportAbuse extends PluginBase
{
	private const MAX_ITEMS = 20;

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			if (trim((string) ($this->config['abuse_address'] ?? '')) !== '') {
				$data['messageToolbar'][] = [
					'plugin' => 'report-abuse', 'js' => 'report-abuse.js'];
			}
			return $data;
		});
	}

	public function apiReport(Request $request, ?array $user): array
	{
		$abuse = trim((string) ($this->config['abuse_address'] ?? ''));
		if ($abuse === '' || !filter_var($abuse, FILTER_VALIDATE_EMAIL)) {
			throw new \RuntimeException($this->t('plugin.report-abuse.not_configured'), 400);
		}
		$items = (array) $request->input('items', []);
		if (!$items || count($items) > self::MAX_ITEMS) {
			throw new \RuntimeException($this->t('plugin.report-abuse.bad_items'), 400);
		}
		$mail = $this->app->mail();
		$prefix = (string) ($this->config['subject_prefix'] ?? '');
		$markSpam = !empty($this->config['mark_spam']);
		$reported = $failed = 0;
		foreach ($items as $it) {
			$folder = str_replace(["\r", "\n"], '', (string) ($it['folder'] ?? ''));
			$uid = (int) ($it['uid'] ?? 0);
			if ($folder === '' || $uid <= 0) {
				$failed++;
				continue;
			}
			try {
				$this->forward($mail, $folder, $uid, $abuse, $prefix);
				if ($markSpam) {
					try { $mail->markSpam($folder, $uid, true); } catch (\Throwable) {}
				}
				$reported++;
			} catch (\Throwable $e) {
				$this->app->log('warning', 'report-abuse: ' . $e->getMessage());
				$failed++;
			}
		}
		if ($reported === 0) {
			throw new \RuntimeException($this->t('plugin.report-abuse.failed'), 502);
		}
		return ['reported' => $reported, 'failed' => $failed];
	}

	/** Transfert du message source en PJ message/rfc822 vers l'adresse abuse. */
	private function forward(\MXWebMail\Service\MailService $mail,
		string $folder, int $uid, string $abuse, string $prefix): void
	{
		$src = $mail->message($folder, $uid);
		$stream = null;
		$ok = $mail->mimeStream($folder, $uid, '', function ($r) use (&$stream) {
			$stream = fopen('php://temp', 'w+b');
			stream_copy_to_stream($r, $stream);
			rewind($stream);
		});
		if (!$ok || !is_resource($stream)) {
			throw new \RuntimeException($this->t('errors.attachment_missing'));
		}
		$size = fstat($stream)['size'] ?? 0;
		$fname = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\:*?"<>|]/', '',
			(string) ($src['subject'] ?? '')));
		$msg = new \MailSo\Mime\Message();
		$msg->DoesNotAddDefaultXMailer();
		$msg->SetFrom(\MailSo\Mime\Email::Parse((string) ($_SESSION['email'] ?? '')));
		$msg->SetTo(new \MailSo\Mime\EmailCollection($abuse));
		$subject = trim($prefix . ' ' . (string) ($src['subject'] ?? ''));
		$msg->SetSubject(str_replace(["\r", "\n"], ' ', $subject));
		$msg->addPlain($this->t('plugin.report-abuse.body', [
			'account' => (string) ($_SESSION['email'] ?? ''),
			'folder'  => $folder,
			'date'    => date(\DateTimeInterface::RFC2822),
		]));
		$msg->Attachments()->append(new \MailSo\Mime\Attachment(
			$stream, ($fname !== '' ? $fname : 'message') . '.eml', $size,
			false, false, '', [], '', 'message/rfc822'));
		$mail->send($msg);
	}
}
