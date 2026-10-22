<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Ajoute X-Originating-IP aux messages sortants (header lu par certains
 * filtres anti-spam). check_proxy → IP cliente via trusted_proxies.
 * Portage du plugin legacy add-x-originating-ip-header.
 */
final class AddXOriginatingIpHeader extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('message.before-send', function (\MailSo\Mime\Message $message) {
			$ip = !empty($this->config['check_proxy'])
				? $this->app->clientIp()
				: (string) ($_SERVER['REMOTE_ADDR'] ?? '');
			if ($ip === '') {
				return;
			}
			if (\in_array($ip, ['localhost', '::1', '[::1]'], true)) {
				$ip = '127.0.0.1';
			}
			$message->SetCustomHeader(
				\MailSo\Mime\Enumerations\Header::X_ORIGINATING_IP, $ip);
		});
	}
}
