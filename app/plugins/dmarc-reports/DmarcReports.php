<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Service\AuthService;

/**
 * Releve et agrege les rapports DMARC (rua) d'une boite IMAP dediee -
 * page admin de niveau superieur (admin.mounts, section "page").
 * Connexion IMAP propre (hors session utilisateur) : host/port/encryption/
 * user/pass en config. Parse les PJ XML (direct, .gz via gzdecode ; .zip
 * seulement si ZipArchive est dispo). Agregats en table
 * plugin_dmarc_reports (dedup UNIQUE org+report_id+source_ip+domain).
 */
final class DmarcReports extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('admin.mounts', fn() => [
			'js' => 'dmarc-reports.js', 'plugin' => 'dmarc-reports',
			'section' => 'page', 'tab' => 'dmarc-reports',
			'label' => 'plugin.dmarc-reports.tab', 'icon' => 'fa-shield']);
	}

	private function pdo(): \PDO
	{
		$pdo = $this->app->db()->pdo();
		$pk = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
			? 'INTEGER PRIMARY KEY' : 'INTEGER AUTO_INCREMENT PRIMARY KEY';
		$pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dmarc_reports (
			id          $pk,
			org         VARCHAR(255) NOT NULL,
			report_id   VARCHAR(255) NOT NULL,
			domain      VARCHAR(255) NOT NULL,
			date_begin  INTEGER NOT NULL,
			date_end    INTEGER NOT NULL,
			source_ip   VARCHAR(45) NOT NULL,
			count       INTEGER NOT NULL,
			disposition VARCHAR(32) NOT NULL,
			dkim        VARCHAR(32) NOT NULL,
			spf         VARCHAR(32) NOT NULL,
			UNIQUE (org, report_id, source_ip, domain))");
		return $pdo;
	}

	private function configured(): bool
	{
		return trim((string) ($this->config['host'] ?? '')) !== ''
			&& trim((string) ($this->config['user'] ?? '')) !== '';
	}

	public function apiAdminStatus(Request $r): array
	{
		$this->pdo();
		$row = $this->app->db()->pdo()->query(
			'SELECT COUNT(*) c, COUNT(DISTINCT report_id) r,
				MAX(date_end) last FROM plugin_dmarc_reports')->fetch(\PDO::FETCH_ASSOC);
		return ['configured' => $this->configured(),
			'rows' => (int) $row['c'], 'reports' => (int) $row['r'],
			'last' => (int) ($row['last'] ?? 0)];
	}

	public function apiAdminList(Request $r): array
	{
		$this->pdo();
		$group = $r->input('group') === 'source_ip' ? 'source_ip' : 'domain';
		$rows = $this->app->db()->pdo()->query(
			"SELECT $group k, SUM(count) c, COUNT(*) n,
				MAX(date_end) last
				FROM plugin_dmarc_reports GROUP BY $group ORDER BY c DESC LIMIT 200")
			->fetchAll(\PDO::FETCH_ASSOC);
		return ['group' => $group, 'rows' => $rows];
	}

	public function apiAdminReset(Request $r): array
	{
		$this->pdo()->exec('DELETE FROM plugin_dmarc_reports');
		return ['rows' => 0];
	}

	public function apiAdminFetch(Request $r): array
	{
		if (!$this->configured()) {
			throw new \RuntimeException($this->t('plugin.dmarc-reports.not_configured'), 400);
		}
		$s = AuthService::imapSettings([
			'imap_host' => (string) $this->config['host'],
			'imap_port' => (int) ($this->config['port'] ?? 993),
			'imap_encryption' => (string) ($this->config['encryption'] ?? 'ssl')]);
		$s->username = (string) $this->config['user'];
		$s->passphrase = (string) ($this->config['pass'] ?? '');
		$imap = new \MailSo\Imap\ImapClient();
		$fetched = $parsed = $skipped = 0;
		try {
			$imap->Connect($s);
			$imap->Login($s);
			$folder = trim((string) ($this->config['folder'] ?? 'INBOX')) ?: 'INBOX';
			$imap->FolderSelect($folder);
			// UNSEEN : les messages traites sont marques \Seen - 'ALL'
			// re-livrerait le meme premier lot a chaque appel (et le JS
			// boucle tant que fetched > 0 : boucle infinie quand
			// delete_processed est off).
			$uids = array_slice($imap->MessageSearch('UNSEEN', true), 0,
				max(1, (int) ($this->config['batch_size'] ?? 50)));
			$delete = !empty($this->config['delete_processed']);
			$dest = trim((string) ($this->config['processed_folder'] ?? 'DMARC-Processed'));
			foreach ($uids as $uid) {
				$fetched++;
				$ok = true;
				try {
					$this->process($imap, (int) $uid)
						? $parsed++ : $skipped++;
				} catch (\Throwable $e) {
					$ok = false;
					$skipped++;
					$this->app->log('warning', "dmarc-reports uid {$uid}: " . $e->getMessage());
				}
				// \Seen hors du try de process() : un message en echec ne
				// doit pas rester UNSEEN - il serait re-livre a chaque
				// lot (message empoisonne). Deplace seulement sans
				// exception ($ok) : un echec reste dans le dossier.
				try {
					$imap->MessageStoreFlag(new \MailSo\Imap\SequenceSet([(int) $uid]),
						['\Seen'], \MailSo\Imap\Enumerations\StoreAction::ADD_FLAGS_SILENT);
					if ($ok && $delete && $dest !== '') {
						$this->ensureFolder($imap, $dest);
						$imap->MessageMove($folder, $dest,
							new \MailSo\Imap\SequenceSet([(int) $uid]));
					}
				} catch (\Throwable $e) {
					$this->app->log('warning',
						"dmarc-reports uid {$uid} flag/move: " . $e->getMessage());
				}
			}
		} finally {
			try { $imap->Disconnect(); } catch (\Throwable) {}
		}
		return ['fetched' => $fetched, 'parsed' => $parsed, 'skipped' => $skipped];
	}

	private function ensureFolder(\MailSo\Imap\ImapClient $imap, string $name): void
	{
		try { $imap->FolderCreate($name, true); } catch (\Throwable) {}
	}

	/** Cherche et parse la PJ rapport d'un message. false = pas de rapport. */
	private function process(\MailSo\Imap\ImapClient $imap, int $uid): bool
	{
		$resp = $imap->Fetch([\MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE],
			(string) $uid, true);
		$struct = $resp[0]->GetFetchValue(
			\MailSo\Imap\Enumerations\FetchType::BODYSTRUCTURE);
		$xml = $this->xmlFromParts($imap, $uid, $struct);
		return $xml !== null && $this->ingest($xml);
	}

	/** Extrait le XML des parts candidates (gzip/zip/xml) via BODYSTRUCTURE. */
	private function xmlFromParts(\MailSo\Imap\ImapClient $imap, int $uid, $struct): ?string
	{
		foreach ($struct->SearchByContentTypes(
			['application/gzip', 'application/zip', 'application/xml', 'text/xml',
				'application/x-gzip', 'application/octet-stream']) as $part) {
			$id = (string) $part->PartID();
			$raw = $imap->FetchMessagePart($uid, $id);
			// Part MIME brute : split headers / corps, decode selon CTE.
			$p = strpos($raw, "\r\n\r\n");
			$head = strtolower($p === false ? '' : substr($raw, 0, $p));
			$body = $p === false ? $raw : substr($raw, $p + 4);
			if (str_contains($head, 'content-transfer-encoding: base64')) {
				$body = base64_decode($body, true) ?: '';
			} elseif (str_contains($head, 'content-transfer-encoding: quoted-printable')) {
				$body = quoted_printable_decode($body);
			}
			$name = strtolower((string) $part->FileName());
			if (str_ends_with($name, '.gz') || str_contains($head, 'gzip')) {
				$body = (string) gzdecode($body);
			} elseif (str_ends_with($name, '.zip')) {
				if (!class_exists('ZipArchive')) { continue; }
				$tmp = tempnam(sys_get_temp_dir(), 'dz');
				file_put_contents($tmp, $body);
				$za = new \ZipArchive();
				if ($za->open($tmp) === true) {
					$body = (string) $za->getFromIndex(0);
					$za->close();
				}
				@unlink($tmp);
			}
			if (str_contains($body, '<feedback')) {
				return $body;
			}
		}
		return null;
	}

	/** Parse le XML DMARC et insere les records (dedup UNIQUE). */
	private function ingest(string $xml): bool
	{
		$doc = new \DOMDocument();
		if (!@$doc->loadXML($xml, LIBXML_NONET)) {
			return false;
		}
		$x = new \DOMXPath($doc);
		// XPath insensible a l'espace de noms : un rapport peut porter
		// xmlns="urn:ietf:params:xml:ns:dmarc" (RFC 7489) ou aucun - les
		// node-tests non prefixes ne matchent que des elements sans ns.
		$q = fn(string $p, ?\DOMNode $c = null): \DOMNodeList =>
			$x->query(self::xp($p, $c !== null), $c);
		$txt = fn(string $p, ?\DOMNode $c = null): string =>
			trim((string) ($q($p, $c)->item(0)->nodeValue ?? ''));
		$org = $txt('feedback/report_metadata/org_name');
		$rid = $txt('feedback/report_metadata/report_id');
		$records = $q('feedback/record');
		if (!$records->length && $org === '' && $rid === '') {
			return false; // contenu meconnaissable : pas un rapport exploitable
		}
		$org = $org ?: 'unknown';
		$rid = $rid ?: 'unknown';
		$dom = $txt('feedback/report_metadata/policy_published/domain');
		$b = (int) $txt('feedback/report_metadata/date_range/begin');
		$e = (int) $txt('feedback/report_metadata/date_range/end');
		$mysql = $this->app->db()->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite';
		$ins = $this->app->db()->pdo()->prepare(
			($mysql ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO')
			. ' plugin_dmarc_reports (org, report_id, domain, date_begin,
				date_end, source_ip, count, disposition, dkim, spf)
			VALUES (?,?,?,?,?,?,?,?,?,?)');
		foreach ($records as $rec) {
			$ins->execute([$org, $rid, $dom, $b, $e,
				$txt('row/source_ip', $rec), (int) $txt('row/count', $rec),
				$txt('row/policy_evaluated/disposition', $rec),
				$txt('row/policy_evaluated/dkim', $rec),
				$txt('row/policy_evaluated/spf', $rec)]);
		}
		return true;
	}

	/**
	 * Chemin > XPath local-name() (ignore les espaces de noms). Absolu par
	 * defaut ('/' en tete : un '*' nu au niveau document ne selectionne pas
	 * la racine) ; $rel = relatif au noeud de contexte (enfants directs).
	 */
	private static function xp(string $path, bool $rel = false): string
	{
		return ($rel ? '' : '/') . implode('/', array_map(
			static fn(string $seg) => "*[local-name()='{$seg}']",
			explode('/', $path)));
	}
}
