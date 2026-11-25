<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use MailSo\Base\Enumerations\Charset;
use MailSo\Base\Utils;
use MailSo\Mime\Enumerations\Header;
use MailSo\Mime\Enumerations\Parameter;
use MailSo\Mime\Part;

/**
 * Parseur .eml autonome : convertit un arbre MailSo\Mime\Part (message
 * embarqué ou fichier importé) en payload JSON identique à message()
 * (from/to/cc, corps texte/html, PJ indexées). Extrait de
 * MessagesController - utilisé pour l'aperçu et le téléchargement des
 * PJ message/rfc822 imbriquées (chemin ?sub= "a.b").
 */
final class EmlParser
{
	/**
	 * Parcours d'un noeud MIME : multipart/* > récursion ; leaf text/* sans
	 * disposition attachment > $texts ; tout autre leaf > $atts (ordre =
	 * index `n` partagé avec la route {part} - source unique d'indexation).
	 */
	public static function walk(Part $part, array &$atts, array &$texts): void
	{
		$ct = $part->ContentType();
		if (str_starts_with($ct, 'multipart/')) {
			foreach ($part->SubParts as $sub) {
				self::walk($sub, $atts, $texts);
			}
			return;
		}
		$isText = $ct === '' || $ct === 'text/plain' || $ct === 'text/html';
		if ($isText && !str_starts_with(strtolower($part->Headers->ValueByName(
				Header::CONTENT_DISPOSITION)), 'attachment')) {
			$texts[] = $part;
			return;
		}
		$atts[] = $part;
	}

	/** Descend les PJ rfc822 du chemin ?sub= ("a.b" = PJ a du noeud courant,
	 *  puis PJ b du niveau suivant). null = indice ou type invalide. */
	public static function descend(Part $root, string $sub): ?Part
	{
		$node = $root;
		foreach ($sub === '' ? [] : explode('.', $sub) as $idx) {
			$atts = $_texts = [];
			self::walk($node, $atts, $_texts);
			$p = $atts[(int) $idx] ?? null;
			if (!$p || $p->ContentType() !== 'message/rfc822') {
				return null;
			}
			$node = Part::FromString(self::partBody($p));
			if (!$node) {
				return null;
			}
		}
		return $node;
	}

	/** Corps décodé (Content-Transfer-Encoding) d'une partie. Body est un
	 *  stream dont le pointeur est en fin après Parser::writeBody - rewind
	 *  obligatoire avant lecture. */
	public static function partBody(Part $part): string
	{
		$body = '';
		if (is_resource($part->Body)) {
			rewind($part->Body);
			$body = (string) stream_get_contents($part->Body);
		}
		return Utils::DecodeEncodingValue($body,
			$part->Headers->ValueByName(Header::CONTENT_TRANSFER_ENCODING));
	}

	/** Corps texte décodé + converti UTF-8, capé à 256 Ko (comme le cap
	 *  frontend de l'aperçu texte). */
	public static function partText(Part $part): string
	{
		$text = self::partBody($part);
		$text = Utils::ConvertEncoding($text,
			Utils::NormalizeCharset($part->HeaderCharset() ?: 'utf-8', true),
			Charset::UTF_8);
		$text = Utils::Utf8Clear($text);
		// format=flowed : Mime\Part::IsFlowedFormat() est inutilisable ici
		// (il appelle ContentTransferEncoding(), absent de Mime\Part) -
		// test manuel identique à Imap\BodyStructure.
		$cte = strtolower($part->Headers->ValueByName(Header::CONTENT_TRANSFER_ENCODING));
		if ('flowed' === strtolower(trim($part->Headers->ParameterValue(
				Header::CONTENT_TYPE, Parameter::FORMAT)))
			&& !in_array($cte, ['base64', 'quoted-printable'], true)) {
			$text = Utils::DecodeFlowedFormat($text);
		}
		if (strlen($text) > 262144) {
			$text = mb_substr($text, 0, 262144) . "\n…";
		}
		return $text;
	}

	/** Payload JSON d'un noeud .eml - mêmes shapes que message() pour
	 *  from/to/cc (EmailCollection > [{name,email}]). */
	public static function toArray(Part $root, string $subPath = ''): array
	{
		$h = $root->Headers;
		$atts = $texts = [];
		self::walk($root, $atts, $texts);
		$out = [
			'sub'       => $subPath,
			'subject'   => Utils::Utf8Clear(
				$h->ValueByName(Header::SUBJECT, true)),
			'from'      => $h->GetAsEmailCollection(Header::FROM_),
			'to'        => $h->GetAsEmailCollection(Header::TO_),
			'cc'        => $h->GetAsEmailCollection(Header::CC),
			'date'      => \MailSo\Base\DateTimeHelper::ParseRFC2822DateString(
				$h->ValueByName(Header::DATE)),
			'bodyHtml'  => '',
			'bodyPlain' => '',
			'attachments' => [],
		];
		foreach ($texts as $p) {
			$text = self::partText($p);
			if ($p->ContentType() === 'text/html') {
				$out['bodyHtml'] .= ($out['bodyHtml'] === '' ? '' : '<br>') . $text;
			} else {
				$out['bodyPlain'] .= ($out['bodyPlain'] === '' ? '' : "\n") . $text;
			}
		}
		// Même convention que messageFull (trim du plain assemblé).
		$out['bodyPlain'] = trim($out['bodyPlain']);
		foreach ($atts as $i => $p) {
			$ct = $p->ContentType();
			$name = Utils::SecureFileName($p->FileName());
			if ($name === '') {
				$name = ($ct === 'message/rfc822' ? "message-{$i}.eml" : "part-{$i}");
			}
			$cid = trim($p->Headers->ValueByName(Header::CONTENT_ID));
			$loc = trim($p->Headers->ValueByName(Header::CONTENT_LOCATION));
			$disp = strtolower($p->Headers->ValueByName(Header::CONTENT_DISPOSITION));
			$eml = $ct === 'message/rfc822';
			$out['attachments'][] = [
				'n'        => $i,
				'name'     => $name,
				'mime'     => $ct !== '' ? $ct : 'application/octet-stream',
				'size'     => strlen(self::partBody($p)),
				'cid'      => $cid !== '' ? $cid : null,
				'location' => $loc !== '' ? $loc : null,
				'isInline' => $disp === 'inline' || $cid !== '',
				'eml'      => $eml,
				'sub'      => $eml ? ($subPath === '' ? (string) $i : "{$subPath}.{$i}") : null,
			];
		}
		return $out;
	}
}
