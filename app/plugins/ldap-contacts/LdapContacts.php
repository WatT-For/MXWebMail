<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Service\LdapConnect;

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
		if (strlen($q) < 2 || !function_exists('ldap_connect')
			|| $this->configTrim('host') === '') {
			return [];
		}
		try {
			return $this->ldapSearch($q);
		} catch (\Throwable $e) {
			$this->app->log('warning', 'ldap-contacts: ' . $e->getMessage());
			return [];
		}
	}

	private function ldapSearch(string $q): array
	{
		$link = LdapConnect::open($this->configTrim('host'),
			$this->configInt('port', 389));
		if (!$link) {
			throw new \RuntimeException($this->t('plugin.ldap-contacts.connect_failed'));
		}
		try {
			LdapConnect::defaults($link);

			if ($this->configBool('use_tls') && !LdapConnect::startTls($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-contacts.starttls_denied'));
			}

			$bindDn = $this->configString('bind_dn');
			if ($bindDn !== '') {
				if (!LdapConnect::bind($link, $bindDn, $this->configString('bind_pass'))) {
					throw new \RuntimeException($this->t('plugin.ldap-contacts.bind_denied'));
				}
			} elseif (!LdapConnect::bind($link)) {
				throw new \RuntimeException($this->t('plugin.ldap-contacts.anon_bind_denied'));
			}

			$filter = str_replace('%q', ldap_escape($q, '', LDAP_ESCAPE_FILTER),
				$this->configString('filter', '(|(mail=*%q*)(cn=*%q*)(sn=*%q*))'));
			$nameAttr = $this->configString('name_attr', 'cn');
			$mailAttr = $this->configString('mail_attr', 'mail');
			$max = max(1, $this->configInt('max_results', 10));

			$sr = @ldap_search($link, $this->configString('base_dn'), $filter,
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
