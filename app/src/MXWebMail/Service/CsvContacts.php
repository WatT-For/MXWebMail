<?php
declare(strict_types=1);

namespace MXWebMail\Service;

/**
 * Conversion CSV > items de contacts, miroir de VCardContacts::parse
 * (même format de sortie : {name, emails, properties, source_uid, frequency})
 * pour réutiliser ContactRepository::import() tel quel.
 *
 * Heuristique de mapping (pas de mapping interactif) :
 * - ligne d'en-tête reconnue (email|e-mail|courriel|mail, name|nom,
 *   phone|tel|téléphone, org|société|company…) > colonnes nommées ;
 * - sans en-tête > colonne contenant des emails valides = emails,
 *   première colonne = nom.
 * Séparateur auto-détecté (, ; ou tab).
 */
final class CsvContacts
{
	/** @return array<int, array> */
	public static function parse(string $csv): array
	{
		$rows = [];
		$stream = fopen('php://temp', 'r+b');
		fwrite($stream, $csv);
		rewind($stream);
		$delimiter = self::detectDelimiter($stream);
		while (($r = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
			if (count(array_filter($r, fn($v) => trim((string) $v) !== ''))) {
				$rows[] = $r;
			}
		}
		fclose($stream);
		if (!$rows) {
			return [];
		}

		$map = self::columnMap($rows[0]);
		$hasHeader = $map !== null;
		if (!$hasHeader) {
			$map = self::guessColumns($rows[0]);
		}
		if ($map['email'] === null) {
			return [];
		}
		if ($hasHeader) {
			array_shift($rows);
		}

		$items = [];
		foreach ($rows as $r) {
			$emails = [];
			foreach (preg_split('/[,;]/', (string) ($r[$map['email']] ?? '')) as $m) {
				$m = trim($m);
				if (filter_var($m, FILTER_VALIDATE_EMAIL)) {
					$emails[] = ['email' => $m, 'type' => null];
				}
			}
			if (!$emails) {
				continue; // sans email, inutile pour un webmail (miroir vCard)
			}
			$properties = [];
			foreach (['tel', 'org', 'note'] as $k) {
				if ($map[$k] !== null && trim((string) ($r[$map[$k]] ?? '')) !== '') {
					$properties[$k] = trim((string) $r[$map[$k]]);
				}
			}
			$name = $map['name'] !== null ? trim((string) ($r[$map['name']] ?? '')) : '';
			$items[] = [
				'name'       => $name !== '' ? $name : $emails[0]['email'],
				'emails'     => $emails,
				'properties' => $properties,
				'source_uid' => '',
				'frequency'  => 0,
			];
		}
		return $items;
	}

	/** Séparateur le plus fréquent de la première ligne. */
	private static function detectDelimiter($stream): string
	{
		$first = (string) fgets($stream);
		rewind($stream);
		$counts = [',' => substr_count($first, ','),
			';' => substr_count($first, ';'),
			"\t" => substr_count($first, "\t")];
		arsort($counts);
		return (string) array_key_first($counts);
	}

	/** En-tête reconnue > indices de colonnes ; null sinon. */
	private static function columnMap(array $header): ?array
	{
		$map = ['name' => null, 'email' => null, 'tel' => null, 'org' => null, 'note' => null];
		$seen = false;
		foreach ($header as $i => $h) {
			$h = strtolower(trim((string) $h));
			if (class_exists('Normalizer')) {
				$h = \Normalizer::normalize($h, \Normalizer::FORM_D) ?: $h;
				$h = (string) preg_replace('/\p{M}/u', '', $h);
			}
			if (preg_match('/^(e-?mail|mail|courriel|mel|email_?address)$/', $h)) {
				$map['email'] ??= $i; $seen = true;
			} elseif (preg_match('/^(name|nom|full_?name|display_?name|prenom|first_?name)$/', $h)) {
				$map['name'] ??= $i; $seen = true;
			} elseif (preg_match('/^(phone|tel|telephone|mobile|portable)$/', $h)) {
				$map['tel'] ??= $i;
			} elseif (preg_match('/^(org|societe|company|entreprise|organization)$/', $h)) {
				$map['org'] ??= $i;
			} elseif ($h === 'note' || $h === 'notes' || $h === 'comment') {
				$map['note'] ??= $i;
			}
		}
		return $seen ? $map : null;
	}

	/** Sans en-tête : colonne email = celle qui contient des adresses
	 *  valides ; nom = première colonne différente. */
	private static function guessColumns(array $row): array
	{
		$map = ['name' => null, 'email' => null, 'tel' => null, 'org' => null, 'note' => null];
		foreach ($row as $i => $v) {
			if ($map['email'] === null
				&& filter_var(trim((string) $v), FILTER_VALIDATE_EMAIL)) {
				$map['email'] = $i;
			}
		}
		$map['name'] = $map['email'] === 0 ? 1 : 0;
		return $map;
	}
}
