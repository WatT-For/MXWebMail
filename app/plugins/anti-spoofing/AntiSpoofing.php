<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Détection d'usurpation d'adresse / de domaine dans le lecteur de message.
 * - imap.message-headers : ajoute Return-Path (enveloppe) au FETCH IMAP.
 * - message.view         : calcule un verdict heuristique et l'injecte dans
 *   la réponse JSON du message (clé « security ») — jamais bloquant, le
 *   frontend affiche un bandeau indicatif.
 */
final class AntiSpoofing extends PluginBase
{
	private const DEFAULT_PROTECTED = 'paypal.com,amazon.com,google.com,microsoft.com,apple.com';
	private const DEFAULT_BRANDS    = 'paypal,amazon,google,microsoft,apple,banque,impots,securite-sociale';

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('imap.message-headers', function (array &$headers) {
			$headers[] = \MailSo\Mime\Enumerations\Header::RETURN_PATH;
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
			&& !self::isVerv($returnPath)) {
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

		// 4. Nom d'affichage imitant une marque protégée
		if ($this->flag('check_display') && $fromName !== '' && $fromDomain !== '') {
			if ($this->matchBrand($fromName, $brands) !== null && !$this->isProtected($fromDomain, $protected)) {
				$alerts[] = $this->alert('display_name_spoof', 'danger', [
					'name' => $fromName, 'email' => $fromEmail,
				]);
			}
		}

		// 5. Domaine leurre (typosquat / homoglyphe)
		if ($this->flag('check_lookalike')) {
			$domains = [];
			if ($fromDomain !== '') {
				$domains[] = $fromDomain;
			}
			if ($replyToEmail !== '') {
				$domains[] = \MailSo\Base\Utils::getEmailAddressDomain($replyToEmail);
			}
			foreach (array_unique($domains) as $domain) {
				$target = $this->lookalikeOf($domain, $protected);
				if ($target !== null) {
					$alerts[] = $this->alert('lookalike_domain', 'danger', [
						'domain' => $domain, 'protected' => $target,
					]);
					break;
				}
			}
		}

		return ['level' => self::level($alerts), 'alerts' => $alerts];
	}

	private function alert(string $type, string $level, array $params): array
	{
		return [
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
			$raw = $default; // champ vide → liste par défaut (pas de désactivation)
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
		foreach ($this->app->domains()->all() as $d) {
			$domains[] = (string) ($d['name'] ?? '');
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

	/** « <x@y> » → « x@y » ; « <> » (bounce) ou vide → ''. */
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

	/** Return-Path de type VERP (bounce+… / +…=) → légitime pour les listes de diffusion. */
	private static function isVerv(string $returnPath): bool
	{
		return strpbrk(\MailSo\Base\Utils::getEmailAddressLocalPart($returnPath), '+=') !== false;
	}

	private function isProtected(string $domain, array $protected): bool
	{
		return in_array(strtolower($domain), $protected, true);
	}

	/** Première marque protégée présente dans le nom affiché, sinon null. */
	private function matchBrand(string $displayName, array $brands): ?string
	{
		foreach ($brands as $brand) {
			if ($brand !== '' && mb_stripos($displayName, $brand) !== false) {
				return $brand;
			}
		}
		return null;
	}

	/** Domaine protégé dont $domain est un leurre, sinon null. */
	private function lookalikeOf(string $domain, array $protected): ?string
	{
		$domain = self::normalizeDomain($domain);
		if ($domain === '') {
			return null;
		}
		foreach ($protected as $p) {
			$p = self::normalizeDomain($p);
			if ($p === '' || $p === $domain) {
				continue;
			}
			if (self::isLookalike($domain, $p)) {
				return $p;
			}
		}
		return null;
	}

	private static function isLookalike(string $domain, string $protected): bool
	{
		foreach (['secure', 'login', 'account', 'verify', 'support', 'security', 'signin', 'web', 'mail'] as $w) {
			if ($domain === "{$w}-{$protected}" || $domain === "{$protected}-{$w}"
				|| $domain === "{$w}{$protected}" || $domain === "{$protected}{$w}") {
				return true;
			}
		}
		$hom = ['o' => '0', 'l' => '1', 'i' => '1', 'e' => '3', 'a' => '4', 's' => '5', 'b' => '8', 'g' => '6', 't' => '7'];
		if (strtr($domain, $hom) === strtr($protected, $hom)) {
			return true;
		}
		return levenshtein($domain, $protected) <= 2;
	}

	/** minuscules + homoglyphes (Cyrillique…) → latin. */
	private static function normalizeDomain(string $d): string
	{
		$d = mb_strtolower(trim($d));
		return strtr($d, [
			'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
			'у' => 'y', 'х' => 'x', 'і' => 'i', 'ј' => 'j', 'ѕ' => 's',
			'ԁ' => 'd', 'ɡ' => 'g', 'ӏ' => 'l',
		]);
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