<?php
declare(strict_types=1);

namespace MXWebMail\Security;

final class Headers
{
	private static ?string $nonce = null;

	/** Nonce CSP par requête - autorise les <script> inline signés des templates. */
	public static function nonce(): string
	{
		return self::$nonce ??= base64_encode(random_bytes(16));
	}

	/** En-têtes de sécurité appliqués à toutes les réponses. */
	public static function send(bool $isApi = false, string $cspExtra = '', bool $hsts = false): void
	{
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: SAMEORIGIN');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('X-XSS-Protection: 0'); // obsolète, la CSP fait le travail
		if ($hsts) {
			header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
		}

		if (!$isApi) {
			header(self::csp($cspExtra));
		}
	}

	/**
	 * CSP restrictive : scripts depuis self uniquement (modules ES locaux),
	 * images data:/blob: (pièces jointes inline), styles self + inline (Bootstrap/éditeur).
	 * $extra : directives additionnelles ("csp_extra"), ex. "img-src https:".
	 */
	public static function csp(string $extra = ''): string
	{
		$directives = [
			"default-src 'self'",
			// 'unsafe-eval' requis : Knockout compile les data-bind via new Function
			"script-src 'self' 'unsafe-eval' 'nonce-" . self::nonce() . "'",
			"style-src 'self' 'unsafe-inline'",
			// cid: toléré en filet : les refs non réécrites ne chargent rien
			// (cid: n'est pas résolvable hors MIME) mais évitent le bruit console.
			"img-src 'self' data: blob: cid:",
			"font-src 'self' data:",
			"connect-src 'self'",
			"frame-ancestors 'self'",
			"base-uri 'self'",
			"form-action 'self'",
		];
		$extra = trim(preg_replace('/[\r\n]+/', ' ', $extra));
		if ($extra !== '') {
			$directives[] = $extra;
		}
		return "Content-Security-Policy: " . implode('; ', $directives);
	}
}
