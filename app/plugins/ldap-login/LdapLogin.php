<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Service\LdapConnect;

/**
 * Mapping de login via LDAP (hook auth.login.mapping).
 * Permet de se connecter avec un uid ou un alias : le plugin résout
 * l'entrée LDAP et renvoie l'adresse email réelle utilisée pour l'IMAP.
 * Retourne null si aucune entrée ne correspond > vérification normale.
 */
final class LdapLogin extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('auth.login.mapping', function (string $login, array $domain) {
			return $this->resolve($login);
		});
	}

	public function resolve(string $login): ?array
	{
		if ($login === '' || $this->configTrim('host') === '') {
			return null;
		}
		$link = LdapConnect::open($this->configTrim('host'),
			$this->configInt('port', 389));
		if (!$link) {
			return null;
		}
		try {
			LdapConnect::defaults($link);

			if ($this->configBool('use_tls') && !LdapConnect::startTls($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-login.starttls_denied'));
			}
			if (!LdapConnect::bind($link, $this->configString('bind_dn'),
				$this->configString('bind_pass'))) {
				throw new \RuntimeException($this->t('plugin.ldap-login.bind_denied'));
			}

			$mailAttr = $this->configString('mail_attr', 'mail');
			$filter = str_replace('%u', ldap_escape($login, '', LDAP_ESCAPE_FILTER),
				$this->configString('search_filter', '(|(uid=%u)(mail=%u))'));
			$sr = @ldap_search($link, $this->configString('base_dn'),
				$filter, [$mailAttr], 0, 2);
			if (!$sr) {
				return null;
			}
			$entries = ldap_get_entries($link, $sr);
			$mail = $entries[0][$mailAttr][0] ?? null;
			if (!$mail || !is_string($mail)) {
				return null;
			}
			return ['email' => $mail];
		} catch (\Throwable $e) {
			$this->app->log('warning', 'ldap-login: ' . $e->getMessage());
			return null;
		} finally {
			ldap_close($link);
		}
	}
}
