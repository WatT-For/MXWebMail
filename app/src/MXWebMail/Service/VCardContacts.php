<?php
declare(strict_types=1);

namespace MXWebMail\Service;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Splitter\VCard as VCardSplitter;

/**
 * Conversion vCard ↔ items de contacts - partagée entre l'import/export
 * utilisateur (/api/contacts/import|export), le plugin carddav-contacts et
 * l'import SnappyMail.
 *
 * Item : {name, emails:[{email, type}], properties:{…}, source_uid, frequency}.
 * Les chaînes simples dans 'emails' sont acceptées et normalisées.
 */
final class VCardContacts
{
	/**
	 * Parse un flux .vcf (1..n fiches). Les fiches illisibles sont ignorées
	 * (pas d'exception propagée) : un export massif ne doit pas échouer
	 * entièrement pour une fiche corrompue.
	 * @return array<int, array>
	 */
	public static function parse(string $vcf): array
	{
		$items = [];
		$stream = fopen('php://temp', 'r+b');
		fwrite($stream, $vcf);
		rewind($stream);
		$splitter = new VCardSplitter($stream);
		try {
			while ($card = $splitter->getNext()) {
				try {
					$item = self::fromVCard($card);
				} catch (\Throwable) {
					continue;
				}
				$item !== null && $items[] = $item;
			}
		} catch (\Throwable) {
			// Fiche corrompue en cours de flux : on conserve ce qui est parsable.
		}
		fclose($stream);
		return $items;
	}

	/** Une fiche > item. null si aucune adresse exploitable. */
	public static function fromVCard(VCard $card): ?array
	{
		$emails = [];
		if (isset($card->EMAIL)) {
			foreach ($card->EMAIL as $e) {
				$mail = trim((string) $e);
				if (filter_var($mail, FILTER_VALIDATE_EMAIL)) {
					$emails[] = ['email' => $mail, 'type' => self::firstType($e)];
				}
			}
		}
		if (!$emails) {
			return null; // sans email, inutile pour un webmail
		}

		$properties = [];
		foreach (['ORG' => 'org', 'TEL' => 'tel', 'URL' => 'url',
				'NOTE' => 'note', 'NICKNAME' => 'nick', 'BDAY' => 'bday'] as $prop => $key) {
			$v = isset($card->$prop) ? trim((string) $card->$prop) : '';
			if ($v !== '') {
				$properties[$key] = $v;
			}
		}
		if (isset($card->CATEGORIES)) {
			$cats = array_values(array_filter(array_map(
				fn($c) => trim((string) $c), $card->CATEGORIES->getParts())));
			if ($cats) {
				$properties['categories'] = $cats;
			}
		}

		$name = trim((string) ($card->FN ?? '')) ?: trim((string) ($card->NICKNAME ?? ''));
		return [
			'name'       => $name !== '' ? $name : $emails[0]['email'],
			'emails'     => $emails,
			'properties' => $properties,
			'source_uid' => trim((string) ($card->UID ?? '')),
			'frequency'  => 0,
		];
	}

	/** TYPE=work,home > 'work' (le premier significatif ; 'internet'/'pref' ignorés). */
	private static function firstType(\Sabre\VObject\Property $prop): ?string
	{
		if (!isset($prop['TYPE'])) {
			return null;
		}
		foreach ($prop['TYPE']->getParts() as $t) {
			$t = strtolower(trim((string) $t));
			if ($t !== '' && !in_array($t, ['internet', 'pref', 'x-internet'], true)) {
				return $t;
			}
		}
		return null;
	}

	/**
	 * Exporte des lignes contacts (ContactRepository::all + properties décodé)
	 * en .vcf (vCard 3.0 - interop la plus large).
	 * @param array<int, array> $contacts
	 */
	public static function export(array $contacts): string
	{
		$out = '';
		foreach ($contacts as $c) {
			$card = new VCard();
			$card->VERSION = '3.0';
			$name = trim((string) ($c['name'] ?? ''));
			$props = is_array($c['properties'] ?? null)
				? $c['properties']
				: (json_decode((string) ($c['properties'] ?? ''), true) ?: []);

			$card->add('FN', $name !== '' ? $name : self::firstEmailOf($c));
			if ($name !== '') {
				$parts = preg_split('/\s+/', $name);
				$card->add('N', [array_pop($parts), implode(' ', $parts), '', '', '']);
			}
			$card->add('UID', (string) ($c['source_uid'] ?? '') !== ''
				? (string) $c['source_uid']
				: 'mxwm-contact-' . (int) ($c['id'] ?? 0));
			foreach (self::emailsOf($c) as $e) {
				$card->add('EMAIL', $e['email'],
					$e['type'] !== null ? ['TYPE' => $e['type']] : []);
			}
			foreach (['org' => 'ORG', 'tel' => 'TEL', 'url' => 'URL',
					'note' => 'NOTE', 'nick' => 'NICKNAME', 'bday' => 'BDAY'] as $key => $prop) {
				$v = trim((string) ($props[$key] ?? ''));
				if ($v !== '') {
					$card->add($prop, $v);
				}
			}
			if (!empty($props['categories']) && is_array($props['categories'])) {
				$card->add('CATEGORIES', implode(',', array_map('trim', $props['categories'])));
			}
			$out .= $card->serialize();
		}
		return $out;
	}

	/**
	 * Normalise la liste d'emails d'un item : chaînes ou {email, type}
	 * > [{email, type}] dédupliqués et validés.
	 * @return array<int, array{email:string, type:?string}>
	 */
	public static function normalizeEmails(array $emails): array
	{
		$out = [];
		foreach ($emails as $e) {
			if (is_string($e)) {
				$e = ['email' => $e, 'type' => null];
			}
			if (!is_array($e)) {
				continue;
			}
			$mail = trim((string) ($e['email'] ?? $e['e'] ?? ''));
			if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
				continue;
			}
			$type = isset($e['type']) && $e['type'] !== '' && $e['type'] !== null
				? mb_substr(strtolower((string) $e['type']), 0, 32) : null;
			$out[mb_strtolower($mail)] = ['email' => $mail, 'type' => $type];
		}
		return array_values($out);
	}

	private static function emailsOf(array $contact): array
	{
		// Ligne DB (contact_emails jointes en CSV "emails") ou item complet.
		if (isset($contact['emails']) && is_array($contact['emails'])) {
			return self::normalizeEmails($contact['emails']);
		}
		return self::normalizeEmails(
			preg_split('/[,;]/', (string) ($contact['emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []);
	}

	private static function firstEmailOf(array $contact): string
	{
		return self::emailsOf($contact)[0]['email'] ?? '';
	}
}
