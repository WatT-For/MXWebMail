<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Confirmation avant ouverture d'un lien contenu dans le corps d'un mail
 * (anti-hameçonnage). Zéro couture cœur : le hook app.head injecte la
 * config (window.MXWM_LINKCONFIRM - global dédié : window.MXWM est
 * ré-assigné plus bas dans la page) puis guard.js en defer, qui délègue
 * le clic sur les <a> de #mail-body / #eml-preview-body et ouvre une
 * modale Bootstrap avec la destination réelle + signaux suspects.
 */
final class LinkConfirmation extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.head', fn(string $nonce, string $base) => $this->head($nonce, $base));
	}

	/** HTML injecté dans le <head> de la page application (connectée). */
	private function head(string $nonce, string $base): string
	{
		if (!$this->app->isLoggedIn()) {
			return '';
		}
		$mode = (string) ($this->config['mode'] ?? 'all');
		// JSON_HEX_* : la config admin ne peut pas casser le script inline.
		$payload = json_encode([
			'mode'        => in_array($mode, ['all', 'suspicious'], true) ? $mode : 'all',
			'whitelist'   => $this->whitelist(),
			'warn_mailto' => !empty($this->config['warn_mailto']),
			'remember'    => (bool) ($this->config['remember'] ?? true),
		], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
		$b = htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "\n\t" . '<script nonce="' . $nonce . '">'
				. 'window.MXWM_LINKCONFIRM=' . $payload . ';</script>'
			. "\n\t" . '<script src="' . $b . '/assets/plugin/link-confirmation/guard.js?v='
				. rawurlencode($this->version()) . '" defer></script>';
	}

	/** Domaines de confiance saisis (séparateurs : espace, , ; retour ligne). */
	private function whitelist(): array
	{
		$out = [];
		foreach (preg_split('/[\s,;]+/',
			mb_strtolower((string) ($this->config['whitelist'] ?? '')),
			-1, PREG_SPLIT_NO_EMPTY) ?: [] as $d) {
			$out[$d] = true;
		}
		return array_keys($out);
	}
}
