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
		$mode = $this->configString('mode', 'all');
		$src = htmlspecialchars($this->assetUrl($base, 'guard.js'),
			ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "\n\t" . $this->inlineConfig('MXWM_LINKCONFIRM', [
			'mode'        => in_array($mode, ['all', 'suspicious'], true) ? $mode : 'all',
			'whitelist'   => $this->whitelist(),
			'warn_mailto' => $this->configBool('warn_mailto'),
			'remember'    => $this->configBool('remember', true),
		], $nonce)
			. "\n\t" . '<script src="' . $src . '" defer></script>';
	}

	/** Domaines de confiance saisis (séparateurs : espace, , ; retour ligne). */
	private function whitelist(): array
	{
		$out = [];
		foreach (preg_split('/[\s,;]+/',
			mb_strtolower($this->configString('whitelist')),
			-1, PREG_SPLIT_NO_EMPTY) ?: [] as $d) {
			$out[$d] = true;
		}
		return array_keys($out);
	}
}
