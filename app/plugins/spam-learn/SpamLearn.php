<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Apprentissage antispam : sur marquage spam/ham d'un message, transmet
 * le .eml à un programme d'apprentissage (rspamc, sa-learn) ou le copie
 * dans un dossier dovecot-antispam. Jamais bloquant : toute erreur est
 * loggée et avalée — l'action spam doit rester fiable.
 */
final class SpamLearn extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('message.spam', function (string $folder, int $uid, bool $spam) {
			try {
				$this->learn($folder, $uid, $spam);
			} catch (\Throwable $e) {
				$this->app->log('warning', 'spam-learn: ' . $e->getMessage());
			}
		});
	}

	private function learn(string $folder, int $uid, bool $spam): void
	{
		$driver = (string) ($this->config['driver'] ?? 'none');
		if ($driver === 'folder') {
			$this->learnFolder($folder, $uid, $spam);
		} elseif ($driver === 'exec') {
			$this->learnExec($folder, $uid, $spam);
		}
	}

	/** Copie IMAP vers le dossier d'apprentissage (créé + souscrit si absent). */
	private function learnFolder(string $folder, int $uid, bool $spam): void
	{
		$target = trim((string) ($this->config[$spam ? 'folder_spam' : 'folder_ham'] ?? ''));
		if ($target === '') {
			return;
		}
		$mail = $this->app->mail();
		$names = array_map(
			fn(array $f) => strtolower((string) $f['fullName']), $mail->folders());
		if (!\in_array(strtolower($target), $names, true)) {
			$mail->folderCreate($target);
			$mail->folderSubscribe($target, true);
		}
		$mail->copy($folder, $target, $uid);
	}

	/** Le .eml (capé à max_bytes Ko) est pipé dans la commande whitelistée. */
	private function learnExec(string $folder, int $uid, bool $spam): void
	{
		$cmd = trim((string) ($this->config[$spam ? 'command_spam' : 'command_ham'] ?? ''));
		// Binaire + args uniquement : pas de ; | & > < $ ( ` — interdit tout
		// chaînage/substitution shell.
		if ($cmd === ''
			|| !preg_match('/^[\w\/.-]+(\s+[\w\/.=-]+)*$/', $cmd)) {
			throw new \RuntimeException('spam-learn: commande invalide');
		}
		$max = max(1, (int) ($this->config['max_bytes'] ?? 5120)) * 1024;
		$proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
		if (!is_resource($proc)) {
			throw new \RuntimeException('spam-learn: proc_open échoué');
		}
		$this->app->mail()->mimeStream($folder, $uid, '',
			function ($r) use ($pipes, $max) {
				stream_copy_to_stream($r, $pipes[0], $max);
			});
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]);
		$err = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$code = proc_close($proc);
		if ($code !== 0) {
			throw new \RuntimeException(
				"spam-learn: sortie {$code} — " . trim((string) $err . (string) $out));
		}
	}
}
