<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Suggestions de contacts depuis un annuaire LDAP (hook contacts.provider).
 * Config administrateur : host/port/bind/base_dn/filtre/attributs.
 * Interrogé à la volée - aucune donnée LDAP n'est stockée.
 */
final class LdapContacts extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('contacts.provider', function (string $q) {
			return $this->search($q);
		});
	}

	public function search(string $q): array
	{
		$q = trim($q);
		if (strlen($q) < 2 || !function_exists('ldap_connect') || empty($this->config['host'])) {
			return [];
		}
		try {
			return $this->ldapSearch($q);
		} catch (\Throwable $e) {
			\MXWebMailLib\Log::warning('LDAP', 'contacts: ' . $e->getMessage());
			return [];
		}
	}

	private function ldapSearch(string $q): array
	{
		$c = $this->config;
		$link = ldap_connect((string) $c['host'], (int) ($c['port'] ?? 389));
		if (!$link) {
			throw new \RuntimeException($this->t('plugin.ldap-contacts.connect_failed'));
		}
		try {
			ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
			ldap_set_option($link, LDAP_OPT_NETWORK_TIMEOUT, 5);

			if (!empty($c['use_tls']) && !@ldap_start_tls($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-contacts.starttls_denied'));
			}

			$bindDn = (string) ($c['bind_dn'] ?? '');
			if ($bindDn !== '') {
				if (!@ldap_bind($link, $bindDn, (string) ($c['bind_pass'] ?? ''))) {
					throw new \RuntimeException($this->t('plugin.ldap-contacts.bind_denied'));
				}
			} elseif (!@ldap_bind($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-contacts.anon_bind_denied'));
			}

			$filter = str_replace('%q', ldap_escape($q, '', LDAP_ESCAPE_FILTER),
				(string) ($c['filter'] ?? '(|(mail=*%q*)(cn=*%q*)(sn=*%q*))'));
			$nameAttr = (string) ($c['name_attr'] ?? 'cn');
			$mailAttr = (string) ($c['mail_attr'] ?? 'mail');
			$max = max(1, (int) ($c['max_results'] ?? 10));

			$sr = @ldap_search($link, (string) ($c['base_dn'] ?? ''), $filter,
				[$nameAttr, $mailAttr], 0, $max);
			if (!$sr) {
				throw new \RuntimeException($this->t('plugin.ldap-contacts.search_failed'));
			}

			$out = [];
			foreach (ldap_get_entries($link, $sr) as $entry) {
				if (!is_array($entry) || empty($entry[$mailAttr])) {
					continue;
				}
				$mails = $entry[$mailAttr];
				for ($i = 0; $i < ($mails['count'] ?? 0); $i++) {
					$out[] = [
						'name'   => (string) ($entry[$nameAttr][0] ?? ''),
						'email'  => (string) $mails[$i],
						'source' => 'ldap',
					];
				}
				if (count($out) >= $max) {
					break;
				}
			}
			return array_slice($out, 0, $max);
		} finally {
			ldap_close($link);
		}
	}
}
