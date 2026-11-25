<?php
declare(strict_types=1);

namespace MXWebMail\Service;

/**
 * Ouverture LDAP normalisée pour les plugins (ldap-login, ldap-contacts,
 * change-password) : ldap_connect host+port ou URI, protocol v3,
 * options réseau standard, start_tls et bind. Les erreurs restent levées
 * par l'appelant (messages i18n propres à chaque plugin).
 */
final class LdapConnect
{
	/**
	 * Connexion + protocol version 3. $host peut être un nom d'hôte
	 * (avec $port) ou une URI ldap(s)://... - null si l'extension est
	 * absente ou la connexion refusée.
	 */
	public static function open(string $host, int $port = 389): ?\LDAP\Connection
	{
		if (!function_exists('ldap_connect')) {
			return null;
		}
		$link = str_contains($host, '://')
			? @ldap_connect($host)
			: @ldap_connect($host, $port);
		if (!$link) {
			return null;
		}
		ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
		return $link;
	}

	/** Options réseau standard : pas de referral suivi + timeout borné. */
	public static function defaults(\LDAP\Connection $link, int $timeout = 5): void
	{
		ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
		ldap_set_option($link, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
	}

	public static function startTls(\LDAP\Connection $link): bool
	{
		return @ldap_start_tls($link);
	}

	/** $dn vide = bind anonyme ; false si le bind est refusé. */
	public static function bind(\LDAP\Connection $link,
		string $dn = '', string $pass = ''): bool
	{
		return $dn === ''
			? @ldap_bind($link)
			: @ldap_bind($link, $dn, $pass);
	}
}
