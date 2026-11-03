<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\Wildcard;

/**
 * Liste blanche de connexion : refuse le login avant la vérification IMAP
 * si l'email ne correspond pas (évite le probing). Exception levée dans
 * auth.login.mapped (email résolu post-mapping) - jamais de session établie.
 * Portage du plugin legacy white-list (même sémantique : "exceptions"
 * = les emails hors liste qui sont explicitement rejetés quand la
 * liste d'exceptions est renseignée).
 */
final class WhiteList extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('auth.login.mapped', function (string $email, array $domain) {
			$whiteList = \trim((string) ($this->config['white_list'] ?? ''));
			if ($whiteList !== '' && !Wildcard::match($email, $whiteList)) {
				$exceptions = \trim((string) ($this->config['exceptions'] ?? ''));
				if ($exceptions === '' || Wildcard::match($email, $exceptions)) {
					$authError = !empty($this->config['auth_error']);
					throw new \RuntimeException(
						$this->t($authError
							? 'errors.bad_credentials'
							: 'plugin.white-list.account_denied'),
						$authError ? 401 : 403);
				}
			}
			return null;
		});
	}
}
