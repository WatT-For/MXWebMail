<?php
declare(strict_types=1);

namespace MXWebMail\Sieve;

/**
 * Compilateur "assistant" de filtres : un document de règles JSON > source
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
 *               "arg":"…"?}] }],
 *   "vacation"?:{"enabled":bool,"subject":"…","message":"…","days":1-30,
 *                "from":"AAAA-MM-JJ"?,"to":"AAAA-MM-JJ"?,"addresses":[…]} }
 *
 * La validation du schéma est stricte (whitelist champs/ops/actions) : elle
 * fait foi côté serveur - CHECKSCRIPT reste le filet syntaxique final avant
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
	/** Extension Sieve requise par action (capacité "SIEVE" du serveur). */
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

	public static function marker(array $doc, ?\MXWebMail\Locale\Translator $tr = null): string
	{
		$json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json) || strpbrk($json, "\r\n") !== false) {
			self::fail($tr, 'doc_line');
		}
		return self::MARKER . $json;
	}

	/** Erreur de validation : message traduit si le traducteur est fourni,
	 *  clé "sieve.err.<key>" sinon (tous les appelants de prod le passent). */
	private static function fail(?\MXWebMail\Locale\Translator $tr, string $key,
		array $params = []): never
	{
		throw new \InvalidArgumentException(
			$tr?->t('sieve.err.' . $key, $params) ?? 'sieve.err.' . $key);
	}

	/** @throws \InvalidArgumentException sur schéma invalide */
	public static function compile(array $doc, ?\MXWebMail\Locale\Translator $tr = null): string
	{
		$vacation = isset($doc['vacation']) && is_array($doc['vacation'])
			? self::vacation($doc['vacation'], $tr) : null;
		$rules = self::rules($doc, $vacation !== null && !empty($vacation['enabled']), $tr);
		$require = [];
		$blocks = [];
		$normalized = ['version' => self::VERSION, 'rules' => []];

		foreach ($rules as $rule) {
			$rule = (array) $rule;
			[$test, $conds] = self::conditions($rule, $require, $tr);
			[$cmds, $actions] = self::actions($rule, $require, $tr);
			$blocks[] = 'if ' . $test . " {\n" . implode("\n", $cmds) . "\n}";
			$normalized['rules'][] = [
				'match' => self::match($rule, $tr),
				'conditions' => $conds,
				'actions' => $actions,
			];
		}
		if ($vacation !== null) {
			$normalized['vacation'] = $vacation;
			if (!empty($vacation['enabled'])) {
				$blocks[] = self::vacationBlock($vacation, $require);
			}
		}

		$out = self::marker($normalized, $tr) . "\r\n";
		if ($require) {
			$require = array_values(array_unique($require));
			sort($require);
			$out .= 'require ' . json_encode($require, JSON_UNESCAPED_SLASHES) . ";\r\n\r\n";
		}
		return $out . implode("\r\n\r\n", $blocks) . "\r\n";
	}

	// ─── Normalisation + validation ───

	private static function match(array $rule, ?\MXWebMail\Locale\Translator $tr): string
	{
		$match = (string) ($rule['match'] ?? 'all');
		if (!in_array($match, ['all', 'any'], true)) {
			self::fail($tr, 'match');
		}
		return $match;
	}

	private static function rules(array $doc, bool $allowEmpty = false,
		?\MXWebMail\Locale\Translator $tr = null): array
	{
		$rules = $doc['rules'] ?? null;
		if (!is_array($rules) || count($rules) > 50
			|| (!$allowEmpty && !count($rules))) {
			self::fail($tr, $allowEmpty ? 'rules_max' : 'rules');
		}
		return $rules;
	}

	/**
	 * Normalise+valide le bloc "vacation" du doc (répondeur d'absence) :
	 * enabled, subject ≤200c sans CRLF, message ≤8K (requis si enabled),
	 * days 1-30, bornes AAAA-MM-JJ (from ≤ to), addresses = emails valides.
	 */
	private static function vacation(array $v, ?\MXWebMail\Locale\Translator $tr): array
	{
		$out = [
			'enabled' => (bool) ($v['enabled'] ?? false),
			'subject' => self::str($v['subject'] ?? '', 'subject', 200, $tr),
			'message' => (string) ($v['message'] ?? ''),
			'days'    => (int) ($v['days'] ?? 1),
			'from'    => self::str($v['from'] ?? '', 'from', 10, $tr),
			'to'      => self::str($v['to'] ?? '', 'to', 10, $tr),
			'addresses' => [],
		];
		if ($out['days'] < 1 || $out['days'] > 30) {
			self::fail($tr, 'vacation_days');
		}
		foreach (['from', 'to'] as $k) {
			if ($out[$k] !== ''
				&& !preg_match('/^\d{4}-\d{2}-\d{2}$/', $out[$k])) {
				self::fail($tr, 'vacation_date', ['key' => $k]);
			}
		}
		if ($out['from'] !== '' && $out['to'] !== '' && $out['from'] > $out['to']) {
			self::fail($tr, 'vacation_range');
		}
		if (strlen($out['message']) > 8192
			|| ($out['enabled'] && trim($out['message']) === '')) {
			self::fail($tr, 'vacation_message');
		}
		foreach ((array) ($v['addresses'] ?? []) as $a) {
			$a = self::str($a, 'addresses', 254, $tr);
			if ($a === '' || !filter_var($a, FILTER_VALIDATE_EMAIL)
				|| count($out['addresses']) >= 20) {
				self::fail($tr, 'vacation_addresses');
			}
			$out['addresses'][] = $a;
		}
		return $out;
	}

	/** Commande Sieve vacation, enveloppée d'un garde-fou currentdate si
	 *  des bornes sont définies (extensions date + relational). */
	private static function vacationBlock(array $v, array &$require): string
	{
		$require[] = 'vacation';
		$args = 'vacation :days ' . $v['days'];
		if ($v['subject'] !== '') {
			$args .= ' :subject ' . self::q($v['subject']);
		}
		if ($v['addresses']) {
			$args .= ' :addresses [' . implode(', ',
				array_map(fn($a) => self::q($a), $v['addresses'])) . ']';
		}
		// Corps multiline : text: … CRLF "." seul termine (dot-stuffing si
		// une ligne du corps commence par un point).
		$body = str_replace(["\r\n", "\r"], "\n", $v['message']);
		$body = "\r\n" . preg_replace('/^\./m', '..', $body) . "\r\n.\r\n";
		$cmd = $args . ' text:' . $body . ';';
		$tests = [];
		if ($v['from'] !== '') {
			$tests[] = 'currentdate :value "ge" "date" ' . self::q($v['from']);
		}
		if ($v['to'] !== '') {
			$tests[] = 'currentdate :value "le" "date" ' . self::q($v['to']);
		}
		if (!$tests) {
			return $cmd;
		}
		$require[] = 'date';
		$require[] = 'relational';
		$test = count($tests) === 1 ? $tests[0] : 'allof (' . implode(', ', $tests) . ')';
		return 'if ' . $test . " {\n\t" . $cmd . "\n}";
	}

	/** @return array{0:string,1:array} test Sieve + conditions normalisées */
	private static function conditions(array $rule, array &$require,
		?\MXWebMail\Locale\Translator $tr): array
	{
		$conds = $rule['conditions'] ?? null;
		if (!is_array($conds) || !count($conds) || count($conds) > 20) {
			self::fail($tr, 'conditions');
		}
		$tests = [];
		$out = [];
		foreach ($conds as $c) {
			$tests[] = self::test((array) $c, $n, $require, $tr);
			$out[] = $n;
		}
		if (count($tests) === 1) {
			return [$tests[0], $out];
		}
		return [self::match($rule, $tr) === 'all'
			? 'allof (' . implode(', ', $tests) . ')'
			: 'anyof (' . implode(', ', $tests) . ')', $out];
	}

	/** @return array{0:string[],1:array} commandes + actions normalisées */
	private static function actions(array $rule, array &$require,
		?\MXWebMail\Locale\Translator $tr): array
	{
		$actions = $rule['actions'] ?? null;
		if (!is_array($actions) || !count($actions) || count($actions) > 20) {
			self::fail($tr, 'actions');
		}
		$cmds = [];
		$out = [];
		foreach ($actions as $a) {
			$cmds[] = "\t" . self::command((array) $a, $require, $n, $tr);
			$out[] = $n;
		}
		return [$cmds, $out];
	}

	private static function test(array $c, ?array &$normalized, array &$require,
		?\MXWebMail\Locale\Translator $tr): string
	{
		$field = (string) ($c['field'] ?? '');
		$op = (string) ($c['op'] ?? '');
		$value = self::str($c['value'] ?? '', 'value', 2000, $tr);
		if ($field === 'header') {
			$header = self::str($c['header'] ?? '', 'header', 100, $tr);
			if ($header === '' || str_contains($header, ':')
				|| !preg_match('/^[\x21-\x7E]+$/', $header)) {
				self::fail($tr, 'header');
			}
		} else {
			$header = self::FIELDS[$field]
				?? self::fail($tr, 'field', ['field' => $field]);
		}
		if ($value === '' && $op !== 'is') {
			self::fail($tr, 'value');
		}
		if (!in_array($op, self::OPS, true)) {
			self::fail($tr, 'operator', ['op' => $op]);
		}
		// :regex appartient à l'extension "regex" - la déclarer, sinon un
		// serveur strict (Dovecot) rejette le script au CHECKSCRIPT.
		if ($op === 'regex') {
			$require[] = 'regex';
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
			// avant d'ajouter le "*" de suffixe.
			'starts_with'  => 'header :matches ' . $h . ' "'
				. addcslashes($value, '\\"*?') . '*"',
			'regex'        => "header :regex {$h} {$v}",
		};
	}

	private static function command(array $a, array &$require, ?array &$normalized,
		?\MXWebMail\Locale\Translator $tr): string
	{
		$type = (string) ($a['type'] ?? '');
		if (!in_array($type, self::ACTIONS, true)) {
			self::fail($tr, 'action', ['type' => $type]);
		}
		if (($module = self::ACTION_MODULE[$type] ?? null) !== null) {
			$require[] = $module;
		}
		$arg = self::str($a['arg'] ?? '', 'arg', 2000, $tr);
		$normalized = ['type' => $type];
		if (in_array($type, ['fileinto', 'redirect', 'reject'], true)) {
			if ($arg === '') {
				self::fail($tr, 'arg', ['type' => $type]);
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
	private static function str(mixed $v, string $what, int $max = 2000,
		?\MXWebMail\Locale\Translator $tr = null): string
	{
		$v = (string) $v;
		if (strlen($v) > $max || strpbrk($v, "\r\n\x00") !== false) {
			self::fail($tr, 'invalid', ['what' => $what]);
		}
		return $v;
	}

	private static function q(string $s): string
	{
		return '"' . addcslashes($s, '"\\') . '"';
	}
}
