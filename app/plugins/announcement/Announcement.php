<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Bannière d'annonce administrateur affichée aux utilisateurs (et
 * optionnellement sur la page de login - pertinent pour la maintenance).
 * Zéro couture cœur : config via config.schema.json, injection par
 * <script nonce> via app.head. Le texte est posé en textContent -
 * jamais de HTML.
 */
final class Announcement extends PluginBase
{
	private const LEVELS = ['info', 'success', 'warning', 'danger'];

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.head', function (string $nonce, string $base) {
			if (empty($this->config['enabled'])
				|| trim((string) ($this->config['text'] ?? '')) === '') {
				return '';
			}
			if (!$this->app->isLoggedIn() && empty($this->config['show_on_login'])) {
				return '';
			}
			$level = (string) ($this->config['level'] ?? 'info');
			if (!in_array($level, self::LEVELS, true)) {
				$level = 'info';
			}
			// JSON_HEX_TAG : un "</script>" dans le texte admin ne peut
			// pas casser le script inline.
			$payload = json_encode([
				'text'        => (string) $this->config['text'],
				'level'       => $level,
				'dismissible' => !empty($this->config['dismissible']),
			], JSON_HEX_TAG);
			return "\n\t" . '<script nonce="' . $nonce . '">'
				. 'document.addEventListener("DOMContentLoaded",function(){'
				. 'var a=' . $payload . ';'
				. 'var d=document.createElement("div");'
				. 'd.className="alert alert-"+a.level+" rounded-0 mb-0 text-center";'
				. 'd.setAttribute("role","alert");'
				. 'var s=document.createElement("span");s.textContent=a.text;d.appendChild(s);'
				. 'if(a.dismissible){var b=document.createElement("button");'
				. 'b.type="button";b.className="btn-close float-end";'
				. 'b.setAttribute("aria-label","Close");'
				. 'b.onclick=function(){d.remove()};d.appendChild(b)}'
				. '(document.querySelector(".app-shell")||document.body).prepend(d)'
				. '});</script>';
		});
	}
}
