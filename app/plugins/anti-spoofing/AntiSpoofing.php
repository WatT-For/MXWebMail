<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Détection d'usurpation d'adresse / de domaine dans le lecteur de message.
 * - imap.message-headers : ajoute Return-Path (enveloppe) et Received-SPF
 *   au FETCH IMAP.
 * - message.view         : calcule un verdict heuristique et l'injecte dans
 *   la réponse JSON du message (clé "security") - jamais bloquant, le
 *   frontend affiche un bouclier indicatif.
 */
final class AntiSpoofing extends PluginBase
{
	private const DEFAULT_PROTECTED = 'paypal.com,amazon.com,google.com,microsoft.com,apple.com';
	private const DEFAULT_BRANDS    = 'paypal,amazon,google,microsoft,apple,banque,impots,securite-sociale';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('imap.message-headers', function (array &$headers) {
			$headers[] = \MailSo\Mime\Enumerations\Header::RETURN_PATH;
			$headers[] = \MailSo\Mime\Enumerations\Header::RECEIVED_SPF;
		});

		$hooks->on('message.view', function (array &$data, \MailSo\Mail\Message $msg) {
			$data['security'] = $this->analyze($msg);
		});
	}

	private function analyze(\MailSo\Mail\Message $msg): array
	{
		$alerts = [];

		// Listes calculées une seule fois (évite un re-parse/re-SELECT par test).
		$protected = $this->protectedDomains();
		$brands    = $this->csv('brands', self::DEFAULT_BRANDS);

		$first = self::firstEmail($msg->From());
		$fromEmail = $first?->GetEmail() ?? '';
		$fromName  = $first?->GetDisplayName() ?? '';
		$fromDomain = $fromEmail !== ''
			? \MailSo\Base\Utils::getEmailAddressDomain($fromEmail) : '';

		$headers = $msg->Headers;
		$returnPath = $headers
			? self::normalizeReturnPath((string) $headers->ValueByName(
				\MailSo\Mime\Enumerations\Header::RETURN_PATH))
			: '';

		// 1. Enveloppe (Return-Path) ≠ expéditeur affiché (From)
		if ($this->flag('check_envelope')
			&& $fromDomain !== '' && $returnPath !== ''
			&& !self::isVerp($returnPath)) {
			$rpDomain = \MailSo\Base\Utils::getEmailAddressDomain($returnPath);
			if ($rpDomain !== '' && strcasecmp($rpDomain, $fromDomain) !== 0) {
				$alerts[] = $this->alert('envelope_mismatch', 'warning', [
					'from' => $fromEmail, 'return_path' => $returnPath,
				]);
			}
		}

		$replyToEmail = '';
		if ($this->flag('check_reply_to') || $this->flag('check_lookalike')) {
			$replyToEmail = self::firstEmail($msg->oReplyTo)?->GetEmail() ?? '';
		}

		// 2. Reply-To ≠ From
		if ($this->flag('check_reply_to') && $replyToEmail !== '' && $fromDomain !== '') {
			$rtDomain = \MailSo\Base\Utils::getEmailAddressDomain($replyToEmail);
			if ($rtDomain !== '' && strcasecmp($rtDomain, $fromDomain) !== 0) {
				$alerts[] = $this->alert('reply_to_mismatch', 'warning', [
					'from' => $fromEmail, 'reply_to' => $replyToEmail,
				]);
			}
		}

		// 3. Sender ≠ From (envoi pour le compte de)
		if ($this->flag('check_sender')) {
			$senderEmail = self::firstEmail($msg->oSender)?->GetEmail() ?? '';
			if ($senderEmail !== '' && strcasecmp($senderEmail, $fromEmail) !== 0) {
				$alerts[] = $this->alert('sender_mismatch', 'info', [
					'from' => $fromEmail, 'sender' => $senderEmail,
				]);
			}
		}

		// 4. Nom d'affichage imitant une marque protégée - comparaison à deux
		//    étages : texte plié (accents) puis squelette de confusables qui
		//    démasque les homoglyphes ("AppIe TV" > "Apple TV").
		//    Supprimé quand le domaine du From est (sous-)domaine protégé.
		if ($this->flag('check_display') && $fromName !== '' && $fromDomain !== ''
			&& !$this->isProtected($fromDomain, $protected)) {
			$match = $this->matchBrand($fromName, $brands);
			if ($match !== null) {
				$alerts[] = $this->alert(
					$match['tricked'] ? 'display_name_homoglyph' : 'display_name_spoof',
					'danger',
					['name' => $fromName, 'email' => $fromEmail]);
			}
		}

		// 5. Domaines leurres : sous-domaine piégé, typosquat/homoglyphe sur le
		//    FQDN et sur le label registrable, marque réemployée sous un autre
		//    domaine. Les (sous-)domaines protégés sont exclus en amont.
		if ($this->flag('check_lookalike')) {
			$domains = [];
			if ($fromDomain !== '') {
				$domains[] = $fromDomain;
			}
			if ($replyToEmail !== '') {
				$domains[] = \MailSo\Base\Utils::getEmailAddressDomain($replyToEmail);
			}
			foreach (array_unique($domains) as $domain) {
				if ($domain === '' || $this->isProtected($domain, $protected)) {
					continue;
				}
				$hit = $this->lookalikeCheck($domain, $protected);
				if ($hit !== null) {
					$alerts[] = $this->alert($hit[0], $hit[1], [
						'domain' => $domain, 'protected' => $hit[2],
					]);
					break;
				}
			}
		}

		// 6. Authentication-Results du MTA, parsé par MailSo. Chaque résultat
		//    n'est évalué que s'il est ALIGNÉ avec le domaine du From (logique
		//    DMARC) : un dkim=fail sur la signature du transporteur ou un
		//    spf=fail sur un mailfrom relayé (SRS) sont des non-signaux.
		$authOk = null;
		if ($this->flag('check_auth_results')) {
			$passed = ['SPF' => false, 'DKIM' => false, 'DMARC' => false];
			$fails = 0;
			$dkimAlignedPass = $dkimAlignedFail = false;
			$dkimThirdPartyFail = null;
			foreach (['spf' => $msg->SPF, 'dkim' => $msg->DKIM, 'dmarc' => $msg->DMARC]
					as $mech => $rows) {
				foreach ((array) $rows as $row) {
					$status = strtolower((string) ($row[0] ?? ''));
					if ($status === '') {
						continue;
					}
					$domain = self::authDomain((string) ($row[1] ?? ''));
					$aligned = $domain !== '' && $fromDomain !== ''
						&& self::orgDomain($domain) === self::orgDomain($fromDomain);
					switch ($mech) {
						case 'dkim':
							if (!$aligned) {
								// Signature tierce (ESP, relais) : son échec ne dit
								// rien de l'expéditeur > info, jamais warning.
								if ($domain !== '' && \in_array($status, ['fail', 'permerror'], true)) {
									$dkimThirdPartyFail ??= $domain;
								}
								break;
							}
							if ($status === 'pass') {
								$dkimAlignedPass = true;
							} elseif (\in_array($status, ['fail', 'permerror'], true)) {
								$dkimAlignedFail = true;
							}
							break;
						case 'spf':
							if (!$aligned) {
								break;
							}
							if ($status === 'pass') {
								$passed['SPF'] = true;
							} elseif ($status === 'fail') {
								$fails++;
								$alerts[] = $this->alert('auth_spf_fail', 'warning', []);
							} elseif ($status === 'softfail') {
								$alerts[] = $this->alert('auth_spf_softfail', 'info', []);
							}
							break;
						case 'dmarc':
							// Verdict déjà aligné par le MTA - pas de test d'alignement.
							if ($status === 'pass') {
								$passed['DMARC'] = true;
							} elseif ($status === 'fail') {
								$fails++;
								$alerts[] = $this->alert('auth_dmarc_fail', 'danger', []);
							}
							break;
					}
				}
			}
			// Verdicts publies hors Authentication-Results : tags de tests
			// SpamAssassin (X-Spam-Status) et symboles rspamd (X-Spamd-Result)
			// - certains MTA (Postfix + opendkim + SpamAssassin…) n'ecrivent
			// que dkim= dans A-R. Seuls les mecanismes sans aucune ligne A-R
			// sont completes (jamais de contradiction avec un verdict A-R
			// explicite) et uniquement en positif : aucune alerte n'est
			// generee depuis ces sources (domaine non verifiable, tags
			// heuristiques de scoring).
			$spamStatus = $headers
				? $headers->ValueByName(\MailSo\Mime\Enumerations\Header::X_SPAM_STATUS) : '';
			$spamdResult = $headers
				? $headers->ValueByName(\MailSo\Mime\Enumerations\Header::X_SPAMD_RESULT) : '';
			if ($spamStatus !== '' || $spamdResult !== '') {
				if (!$msg->SPF
					&& (preg_match('/\bSPF_PASS\b/i', $spamStatus)
						|| preg_match('/\bR_SPF_ALLOW\b/i', $spamdResult))) {
					$passed['SPF'] = true;
				}
				// DMARC est aligne avec le From par construction.
				if (!$msg->DMARC
					&& (preg_match('/\bDMARC_PASS\b/i', $spamStatus)
						|| preg_match('/\bDMARC_POLICY_ALLOW(?:_WITH_FAILURES)?\b/i', $spamdResult))) {
					$passed['DMARC'] = true;
				}
				if (!$msg->DKIM) {
					if (preg_match('/\bDKIM_VALID_AU\b/i', $spamStatus)) {
						// DKIM_VALID_AU : signature du domaine auteur > alignee.
						$dkimAlignedPass = true;
					} elseif ($fromDomain !== ''
						&& preg_match('/\bR_DKIM_ALLOW\b(?:\([^)]*\))?\[([^:\]]+)/i',
							$spamdResult, $m)
						&& self::orgDomain(trim($m[1])) === self::orgDomain($fromDomain)) {
						$dkimAlignedPass = true;
					}
				}
			}
			$passed['DKIM'] = $dkimAlignedPass;
			// Une signature alignée valide suffit : fail aligné signalé
			// seulement si aucune signature alignée ne passe.
			if ($dkimAlignedFail && !$dkimAlignedPass) {
				$fails++;
				$alerts[] = $this->alert('auth_dkim_fail', 'warning', []);
			}
			if ($dkimThirdPartyFail !== null) {
				$alerts[] = $this->alert('auth_dkim_fail_thirdparty', 'info', [
					'domain' => $dkimThirdPartyFail,
				]);
			}
			// Mécanismes réellement vérifiés > indicateur de confiance honnête
			// (plus de "SPF, DKIM et DMARC" quand un seul était présent).
			$mechs = implode(', ', array_keys(array_filter($passed)));
			if ($mechs !== '' && $fails === 0) {
				$authOk = $this->t('plugin.anti-spoofing.alert.auth_pass', ['mechs' => $mechs]);
			}
		}

		return ['level' => self::level($alerts), 'alerts' => $alerts, 'auth_ok' => $authOk];
	}

	private function alert(string $type, string $level, array $params): array
	{
		return [
			'type'  => $type,
			'level' => $level,
			'text'  => $this->t("plugin.anti-spoofing.alert.{$type}", $params),
		];
	}

	// ─── Helpers ───

	/** Bool de config (défaut true : getConfig() renvoie [] tant que l'admin n'a rien sauvegardé). */
	private function flag(string $key): bool
	{
		return (bool) ($this->config[$key] ?? true);
	}

	private function csv(string $key, string $default): array
	{
		$raw = trim((string) ($this->config[$key] ?? ''));
		if ($raw === '') {
			$raw = $default; // champ vide > liste par défaut (pas de désactivation)
		}
		return array_values(array_filter(array_map('trim', explode(',', $raw))));
	}

	/**
	 * Domaines protégés : liste admin + domaine de l'utilisateur connecté +
	 * domaines configurés sur le serveur (normalisés, dédoublonnés).
	 * Les domaines dynamiques ne sont pas persistés : calculés à la volée.
	 */
	private function protectedDomains(): array
	{
		$domains = $this->csv('protected_domains', self::DEFAULT_PROTECTED);
		$email = (string) ($_SESSION['email'] ?? '');
		if ($email !== '') {
			$domains[] = \MailSo\Base\Utils::getEmailAddressDomain($email);
		}
		try {
			foreach ($this->app->domains()->all() as $d) {
				$domains[] = (string) ($d['name'] ?? '');
			}
		} catch (\Throwable) {
			// Repo indisponible : la liste statique suffit à l'analyse.
		}
		return array_values(array_unique(array_filter(array_map(
			static fn(string $d): string => strtolower(trim($d)), $domains))));
	}

	private static function firstEmail(?\MailSo\Mime\EmailCollection $col): ?\MailSo\Mime\Email
	{
		if (!$col || !count($col)) {
			return null;
		}
		foreach ($col as $email) {
			return $email;
		}
		return null;
	}

	/** "<x@y>" > "x@y" ; "<>" (bounce) ou vide > ''. */
	private static function normalizeReturnPath(string $v): string
	{
		$v = trim($v);
		if ($v === '' || $v === '<>') {
			return '';
		}
		if (preg_match('/<([^>]*)>/', $v, $m)) {
			$v = trim($m[1]);
		}
		return strtolower($v);
	}

	/** Return-Path de type VERP/SRS (bounce+… / +…=) > légitime pour les listes. */
	private static function isVerp(string $returnPath): bool
	{
		return strpbrk(\MailSo\Base\Utils::getEmailAddressLocalPart($returnPath), '+=') !== false;
	}

	/**
	 * Le domaine est le domaine protégé ou un de ses sous-domaines -
	 * "accountprotection.microsoft.com" est Microsoft, pas un imposteur.
	 * Volontairement strict (pas de comparaison par label) : "apple.xyz"
	 * reste suspect, un squatteur peut enregistrer la marque sous un autre TLD.
	 */
	private function isProtected(string $domain, array $protected): bool
	{
		$domain = strtolower($domain);
		foreach ($protected as $p) {
			if ($p !== '' && ($domain === $p || str_ends_with($domain, ".{$p}"))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Marque trouvée dans le nom affiché : ['brand'=>…, 'tricked'=>bool] ou null.
	 * Étage 1 - texte plié (minuscules + accents) à borne de mot : "pineapple"
	 * ne matche pas "apple", "Impôts" matche "impots".
	 * Étage 2 - squelette de confusables (0>o, i|1>l, rn>m…) : n'atteint la
	 * marque que par des caractères trompeurs > 'tricked'.
	 */
	private function matchBrand(string $displayName, array $brands): ?array
	{
		$namePlain = self::textKey(self::foldText($displayName));
		$nameSkel  = self::textKey(self::skeletonize(self::foldText($displayName)));
		foreach ($brands as $brand) {
			$b = self::textKey(self::foldText($brand));
			if ($b !== '' && self::wordMatch($namePlain, $b)) {
				return ['brand' => $brand, 'tricked' => false];
			}
		}
		foreach ($brands as $brand) {
			$b = self::textKey(self::skeletonize(self::foldText($brand)));
			if ($b !== '' && self::wordMatch($nameSkel, $b)) {
				return ['brand' => $brand, 'tricked' => true];
			}
		}
		return null;
	}

	/** Match à borne de mot (les deux chaînes sont déjà normalisées ASCII). */
	private static function wordMatch(string $haystack, string $needle): bool
	{
		return (bool) preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $haystack);
	}

	/** Minuscules + pliage des accents latins et homoglyphes cyrilliques. */
	private static function foldText(string $s): string
	{
		return strtr(mb_strtolower($s), [
			'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
			'ç' => 'c', 'ć' => 'c', 'č' => 'c',
			'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
			'ñ' => 'n', 'ń' => 'n',
			'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
			'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
			'ý' => 'y', 'ÿ' => 'y',
			'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
			'ł' => 'l', 'ś' => 's', 'š' => 's', 'ż' => 'z', 'ź' => 'z', 'ž' => 'z',
			// Cyrillique homoglyphe (même table que normalizeDomain).
			'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
			'у' => 'y', 'х' => 'x', 'і' => 'i', 'ј' => 'j', 'ѕ' => 's',
			'ԁ' => 'd', 'ɡ' => 'g', 'ӏ' => 'l',
		]);
	}

	/**
	 * Squelette de confusables : ramène les caractères ambigus à leur forme
	 * canonique ("AppIe" > "apple", "micr0$oft" > "microsoft").
	 * Appliqué AVANT le repli des séparateurs pour capter le leetspeak.
	 */
	private static function skeletonize(string $s): string
	{
		$s = str_replace(['rn', 'vv'], ['m', 'w'], $s);
		return strtr($s, [
			'0' => 'o', '1' => 'l', 'i' => 'l', 'l' => 'l', '|' => 'l',
			'!' => 'l', '¡' => 'l', '3' => 'e', '4' => 'a', '@' => 'a',
			'5' => 's', '$' => 's', '6' => 'b', '8' => 'b', '7' => 't',
			'9' => 'g', '€' => 'e',
		]);
	}

	/** Non-alphanumérique > espace unique, pour le match à borne de mot. */
	private static function textKey(string $s): string
	{
		return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
	}

	/**
	 * Premier signal de leurre pour $domain, sinon null.
	 * [type, niveau, domaine protégé ciblé] - dangers évalués avant warnings.
	 */
	private function lookalikeCheck(string $domain, array $protected): ?array
	{
		$domain = self::normalizeDomain($domain);
		if ($domain === '') {
			return null;
		}
		$labels = explode('.', $domain);
		$sld = explode('.', self::orgDomain($domain))[0] ?? '';
		foreach ($protected as $p) {
			if ($p === '' || $p === $domain) {
				continue;
			}
			// Domaine protégé embarqué comme sous-domaine d'un domaine hostile :
			// "microsoft.com.evil.com", "apple.com.verify.net".
			if (str_starts_with($domain, "{$p}.") || str_contains($domain, ".{$p}.")) {
				return ['lookalike_subdomain', 'danger', $p];
			}
			// Typosquat / homoglyphe / mot-chevile sur le FQDN puis sur le
			// label registrable ("apple-security.xyz", "paypa1.net") - la
			// comparaison floue du label est bornée aux longueurs proches pour
			// ne pas accuser un label légitime plus long ("onmicrosoft") - les
			// motifs exacts (marque-mot) sont évalués sans borne.
			// L'égalité stricte des labels ("apple.fr" vs "apple.com") est
			// volontairement exclue : signal ambigu > warning en second passage.
			$pLabel = explode('.', self::orgDomain($p))[0] ?? '';
			if (self::isLookalike($domain, $p)
				|| ($sld !== '' && $pLabel !== '' && $sld !== $pLabel
					&& (self::isLookalikeWords($sld, $pLabel)
						|| (abs(strlen($sld) - strlen($pLabel)) <= 1
							&& self::isLookalike($sld, $pLabel))))) {
				return ['lookalike_domain', 'danger', $p];
			}
		}
		foreach ($protected as $p) {
			$pLabel = explode('.', self::orgDomain($p))[0] ?? '';
			if (strlen($pLabel) < 3 || self::orgDomain($domain) === self::orgDomain($p)) {
				continue;
			}
			// Marque réemployée : label entier ("microsoft.evil.com") ou en
			// préfixe/suffixe du label registrable ("securemicrosoft.com") -
			// signal ambigu (squatteur ou domaine légitime) > warning.
			if (in_array($pLabel, $labels, true)
				|| str_starts_with($sld, $pLabel) || str_ends_with($sld, $pLabel)) {
				return ['lookalike_tld', 'warning', $p];
			}
		}
		return null;
	}

	private static function isLookalike(string $domain, string $protected): bool
	{
		if (self::isLookalikeWords($domain, $protected)) {
			return true;
		}
		$hom = ['o' => '0', 'l' => '1', 'i' => '1', 'e' => '3', 'a' => '4', 's' => '5', 'b' => '8', 'g' => '6', 't' => '7'];
		if (strtr($domain, $hom) === strtr($protected, $hom)) {
			return true;
		}
		return levenshtein($domain, $protected) <= 2;
	}

	/**
	 * Motifs mot-chevile exacts : "secure-brand", "brand-login", "webbrand"…
	 * Liste courte de termes de leurre - évaluable sans borne de longueur
	 * (un label long legitime ne peut pas matcher un motif exact).
	 */
	private static function isLookalikeWords(string $a, string $b): bool
	{
		foreach (['secure', 'login', 'account', 'verify', 'support', 'security', 'signin', 'web', 'mail'] as $w) {
			if ($a === "{$w}-{$b}" || $a === "{$b}-{$w}"
				|| $a === "{$w}{$b}" || $a === "{$b}{$w}") {
				return true;
			}
		}
		return false;
	}

	/** minuscules + homoglyphes (Cyrillique…) > latin. */
	private static function normalizeDomain(string $d): string
	{
		$d = mb_strtolower(trim($d));
		return strtr($d, [
			'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
			'у' => 'y', 'х' => 'x', 'і' => 'i', 'ј' => 'j', 'ѕ' => 's',
			'ԁ' => 'd', 'ɡ' => 'g', 'ӏ' => 'l',
		]);
	}

	/**
	 * Domaine d'organisation (approximation eTLD+1) : derniers 2 labels, ou 3
	 * si l'avant-dernier est un suffixe public à 2 niveaux courant (co, com…)
	 * sous un TLD à 2 lettres. Sert à l'alignement DKIM/SPF, pas à isProtected.
	 */
	private static function orgDomain(string $d): string
	{
		$labels = array_values(array_filter(explode('.', strtolower(trim($d, '.')))));
		$n = count($labels);
		if ($n <= 2) {
			return implode('.', $labels);
		}
		$take = strlen($labels[$n - 1]) === 2
			&& in_array($labels[$n - 2], ['co', 'com', 'org', 'net', 'gov', 'gouv', 'edu', 'ac'], true)
			? 3 : 2;
		return implode('.', array_slice($labels, -$take));
	}

	/**
	 * Domaine d'une ligne Authentication-Results : "header.i=@x",
	 * "smtp.mailfrom=local@x", "header.from=x" > "x" normalisé.
	 */
	private static function authDomain(string $v): string
	{
		$v = ltrim(strtolower(trim($v, " \t\"")), '@');
		if (str_contains($v, '@')) {
			$v = \MailSo\Base\Utils::getEmailAddressDomain($v);
		}
		return $v;
	}

	private static function level(array $alerts): string
	{
		$order = ['info' => 1, 'warning' => 2, 'danger' => 3];
		$level = 'none';
		foreach ($alerts as $a) {
			if (($order[$a['level']] ?? 0) > ($order[$level] ?? 0)) {
				$level = $a['level'];
			}
		}
		return $level;
	}
}
