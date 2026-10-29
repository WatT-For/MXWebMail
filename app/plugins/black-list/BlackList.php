<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\Wildcard;

/**
 * Liste noire de connexion : refuse le login avant la vérification IMAP
 * si l'email correspond (évite le probing). « exceptions » = emails de
 * la liste qui restent autorisés. Portage du plugin legacy black-list.
 */
final class BlackList extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('auth.login.mapped', function (string $email, array $domain) {
			$blackList = \trim((string) ($this->config['black_list'] ?? ''));
			if ($blackList !== '' && Wildcard::match($email, $blackList)) {
				$exceptions = \trim((string) ($this->config['exceptions'] ?? ''));
				if ($exceptions === '' || !Wildcard::match($email, $exceptions)) {
					$authError = !empty($this->config['auth_error']);
					throw new \RuntimeException(
						$this->t($authError
							? 'errors.bad_credentials'
							: 'plugin.black-list.account_denied'),
						$authError ? 401 : 403);
				}
			}
			return null;
		});
	}
}
