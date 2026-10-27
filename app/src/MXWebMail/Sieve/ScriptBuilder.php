<?php
declare(strict_types=1);

namespace MXWebMail\Sieve;

/**
 * Compilateur « assistant » de filtres : un document de règles JSON → source
 * Sieve. Le document est sérialisé en tête du script via le marqueur
 * self::MARKER, ce qui rend le script auto-porteur (ré-éditable dans
 * l'assistant, lisible tel quel par tout client ManageSieve).
 *
 * Document :
 * { "version":1, "rules":[{ "match":"all|any",
 *   "conditions":[{"field":"from|to|cc|subject|header",
 *                  "op":"contains|is|not_contains|starts_with|regex",
 *                  "value":"…", "header":"X-…"?}],
 *   "actions":[{"type":"fileinto|redirect|discard|keep|seen|reject|stop",
 *               "arg":"…"?}] }] }
 *
 * La validation du schéma est stricte (whitelist champs/ops/actions) : elle
 * fait foi côté serveur — CHECKSCRIPT reste le filet syntaxique final avant
 * PUTSCRIPT (ApiController::filterSave).
 */
final class ScriptBuilder
{
	public const MARKER = '# MXWM-RULES ';
	private const VERSION = 1;

	private const FIELDS = [
		'from' => 'From', 'to' => 'To', 'cc' => 'Cc', 'subject' => 'Subject',
	];
	private const OPS = ['contains', 'is', 'not_contains', 'starts_with', 'regex'];
	private const ACTIONS = ['fileinto', 'redirect', 'discard', 'keep', 'seen', 'reject', 'stop'];
	/** Extension Sieve requise par action (capacité « SIEVE » du serveur). */
	private const ACTION_MODULE = [
		'fileinto' => 'fileinto', 'seen' => 'imap4flags', 'reject' => 'reject',
	];

	/** Source gérée : première ligne = marqueur + JSON décodable. */
	public static function parseMarker(string $source): ?array
	{
		$line = strtok($source, "\r\n");
		if (!is_string($line) || !str_starts_with($line, self::MARKER)) {
			return null;
		}
		$doc = json_decode(substr($line, strlen(self::MARKER)), true);
		return is_array($doc) && isset($doc['rules']) ? $doc : null;
	}

	public static function marker(array $doc): string
	{
		$json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json) || strpbrk($json, "\r\n") !== false) {
			throw new \InvalidArgumentException('doc non sérialisable sur une ligne');
		}
		return self::MARKER . $json;
	}

	/** @throws \InvalidArgumentException sur schéma invalide */
	public static function compile(array $doc): string
	{
		$rules = self::rules($doc);
		$require = [];
		$blocks = [];
		$normalized = ['version' => self::VERSION, 'rules' => []];

		foreach ($rules as $rule) {
			$rule = (array) $rule;
			[$test, $conds] = self::conditions($rule);
			[$cmds, $actions] = self::actions($rule, $require);
			$blocks[] = 'if ' . $test . " {\n" . implode("\n", $cmds) . "\n}";
			$normalized['rules'][] = [
				'match' => self::match($rule),
				'conditions' => $conds,
				'actions' => $actions,
			];
		}

		$out = self::marker($normalized) . "\r\n";
		if ($require) {
			$require = array_values(array_unique($require));
			sort($require);
			$out .= 'require ' . json_encode($require, JSON_UNESCAPED_SLASHES) . ";\r\n\r\n";
		}
		return $out . implode("\r\n\r\n", $blocks) . "\r\n";
	}

	// ─── Normalisation + validation ───

	private static function match(array $rule): string
	{
		$match = (string) ($rule['match'] ?? 'all');
		if (!in_array($match, ['all', 'any'], true)) {
			throw new \InvalidArgumentException('match invalide');
		}
		return $match;
	}

	private static function rules(array $doc): array
	{
		$rules = $doc['rules'] ?? null;
		if (!is_array($rules) || !count($rules) || count($rules) > 50) {
			throw new \InvalidArgumentException('rules : 1 à 50 règles requises');
		}
		return $rules;
	}

	/** @return array{0:string,1:array} test Sieve + conditions normalisées */
	private static function conditions(array $rule): array
	{
		$conds = $rule['conditions'] ?? null;
		if (!is_array($conds) || !count($conds) || count($conds) > 20) {
			throw new \InvalidArgumentException('conditions : 1 à 20 par règle');
		}
		$tests = [];
		$out = [];
		foreach ($conds as $c) {
			$tests[] = self::test((array) $c, $n);
			$out[] = $n;
		}
		if (count($tests) === 1) {
			return [$tests[0], $out];
		}
		return [self::match($rule) === 'all'
			? 'allof (' . implode(', ', $tests) . ')'
			: 'anyof (' . implode(', ', $tests) . ')', $out];
	}

	/** @return array{0:string[],1:array} commandes + actions normalisées */
	private static function actions(array $rule, array &$require): array
	{
		$actions = $rule['actions'] ?? null;
		if (!is_array($actions) || !count($actions) || count($actions) > 20) {
			throw new \InvalidArgumentException('actions : 1 à 20 par règle');
		}
		$cmds = [];
		$out = [];
		foreach ($actions as $a) {
			$cmds[] = "\t" . self::command((array) $a, $require, $n);
			$out[] = $n;
		}
		return [$cmds, $out];
	}

	private static function test(array $c, ?array &$normalized): string
	{
		$field = (string) ($c['field'] ?? '');
		$op = (string) ($c['op'] ?? '');
		$value = self::str($c['value'] ?? '', 'value');
		if ($field === 'header') {
			$header = self::str($c['header'] ?? '', 'header', 100);
			if ($header === '' || str_contains($header, ':')
				|| !preg_match('/^[\x21-\x7E]+$/', $header)) {
				throw new \InvalidArgumentException('nom d’en-tête invalide');
			}
		} else {
			$header = self::FIELDS[$field]
				?? throw new \InvalidArgumentException("champ inconnu : {$field}");
		}
		if ($value === '' && $op !== 'is') {
			throw new \InvalidArgumentException('value requise');
		}
		if (!in_array($op, self::OPS, true)) {
			throw new \InvalidArgumentException("opérateur inconnu : {$op}");
		}

		$normalized = ['field' => $field, 'op' => $op, 'value' => $value];
		if ($field === 'header') {
			$normalized['header'] = $header;
		}
		$h = self::q($header);
		$v = self::q($value);
		return match ($op) {
			'contains'     => "header :contains {$h} {$v}",
			'is'           => "header :is {$h} {$v}",
			'not_contains' => "not header :contains {$h} {$v}",
			// :matches = glob : les métacaractères de la valeur sont échappés
			// avant d'ajouter le « * » de suffixe.
			'starts_with'  => 'header :matches ' . $h . ' "'
				. addcslashes($value, '\\"*?') . '*"',
			'regex'        => "header :regex {$h} {$v}",
		};
	}

	private static function command(array $a, array &$require, ?array &$normalized): string
	{
		$type = (string) ($a['type'] ?? '');
		if (!in_array($type, self::ACTIONS, true)) {
			throw new \InvalidArgumentException("action inconnue : {$type}");
		}
		if (($module = self::ACTION_MODULE[$type] ?? null) !== null) {
			$require[] = $module;
		}
		$arg = self::str($a['arg'] ?? '', 'arg', 2000);
		$normalized = ['type' => $type];
		if (in_array($type, ['fileinto', 'redirect', 'reject'], true)) {
			if ($arg === '') {
				throw new \InvalidArgumentException("arg requis : {$type}");
			}
			$normalized['arg'] = $arg;
			return $type . ' ' . self::q($arg) . ';';
		}
		// setflag seul n'annule pas le keep implicite : le message reste
		// délivré en INBOX, marqué \Seen.
		return match ($type) {
			'discard' => 'discard;',
			'keep'    => 'keep;',
			'seen'    => 'setflag "\\\\Seen";',
			'stop'    => 'stop;',
		};
	}

	/** Rejette CR/LF/NUL dans les valeurs compilées (intégrité du script). */
	private static function str(mixed $v, string $what, int $max = 2000): string
	{
		$v = (string) $v;
		if (strlen($v) > $max || strpbrk($v, "\r\n\x00") !== false) {
			throw new \InvalidArgumentException("{$what} invalide");
		}
		return $v;
	}

	private static function q(string $s): string
	{
		return '"' . addcslashes($s, '"\\') . '"';
	}
}
