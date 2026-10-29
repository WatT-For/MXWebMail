<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Mapping de login via LDAP (hook auth.login.mapping).
 * Permet de se connecter avec un uid ou un alias : le plugin résout
 * l'entrée LDAP et renvoie l'adresse email réelle utilisée pour l'IMAP.
 * Retourne null si aucune entrée ne correspond → vérification normale.
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
		if ($login === '' || !function_exists('ldap_connect') || empty($this->config['host'])) {
			return null;
		}
		$c = $this->config;
		$link = ldap_connect((string) $c['host'], (int) ($c['port'] ?? 389));
		if (!$link) {
			return null;
		}
		try {
			ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
			ldap_set_option($link, LDAP_OPT_NETWORK_TIMEOUT, 5);

			if (!empty($c['use_tls']) && !@ldap_start_tls($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-login.starttls_denied'));
			}
			$bindDn = (string) ($c['bind_dn'] ?? '');
			if (!@ldap_bind($link, $bindDn !== '' ? $bindDn : null,
				$bindDn !== '' ? (string) ($c['bind_pass'] ?? '') : null)) {
				throw new \RuntimeException($this->t('plugin.ldap-login.bind_denied'));
			}

			$mailAttr = (string) ($c['mail_attr'] ?? 'mail');
			$filter = str_replace('%u', ldap_escape($login, '', LDAP_ESCAPE_FILTER),
				(string) ($c['search_filter'] ?? '(|(uid=%u)(mail=%u))'));
			$sr = @ldap_search($link, (string) ($c['base_dn'] ?? ''), $filter, [$mailAttr], 0, 2);
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
			\MXWebMailLib\Log::warning('LDAP', 'login mapping: ' . $e->getMessage());
			return null;
		} finally {
			ldap_close($link);
		}
	}
}
