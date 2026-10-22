<?php
declare(strict_types=1);

namespace MXWebMail;

use MXWebMail\Application\App;
use MXWebMail\Compat\ActionsBridge;
use MXWebMail\Compat\ConfigBridge;

/**
 * Façade de compatibilité pour le MailSo vendored (non modifié).
 * MailSo appelle encore l'ancienne façade \MXWebMail\API :
 *  - API::Config()->Get('ssl', 'verify_certificate', true)   (Net\SSLContext, Smtp\Settings)
 *  - API::Actions()->Plugins()->RunHook('imap.message-headers', [&$headers])
 * Toute nouvelle fonctionnalité ne doit PAS utiliser cette façade —
 * elle n'existe que pour garder MailSo byte-identique (resync upstream).
 */
final class API
{
	private static ?App $app = null;
	private static ?ConfigBridge $config = null;
	private static ?ActionsBridge $actions = null;

	public static function init(App $app): void
	{
		self::$app = $app;
	}

	private static function app(): App
	{
		// Fallback autonome : MailSo utilisable hors boot App (scripts CLI, setup).
		return self::$app ??= new App(dirname(__DIR__, 2));
	}

	public static function Config(): ConfigBridge
	{
		return self::$config ??= new ConfigBridge(self::app()->config());
	}

	public static function Actions(): ActionsBridge
	{
		return self::$actions ??= new ActionsBridge(self::app());
	}

	/**
	 * Logger MailSo (MXWebMailLib\Log y délègue). Écrit app/storage/logs/mail.log.
	 * Niveau/config : config.php section 'logs' — enabled (def. true), level (def. WARNING).
	 */
	public static function Logger(): \MailSo\Log\Logger
	{
		static $logger = null;
		if ($logger === null) {
			$logger = new \MailSo\Log\Logger();
			$cfg = self::app()->config();
			$logs = $cfg->get('logs');
			$enabled = is_array($logs) ? ($logs['enabled'] ?? true) : (bool) $cfg->get('logs_enabled', true);
			if ($enabled) {
				$file = new \MailSo\Log\Drivers\File(self::app()->storageDir() . '/logs/mail.log');
				if (is_array($logs) && ($logs['time_zone'] ?? '') !== '') {
					try { $file->SetTimeZone((string) $logs['time_zone']); } catch (\Throwable) {}
				}
				$logger->append($file);
				if (is_array($logs) && isset($logs['level'])) {
					$logger->SetLevel((int) $logs['level']);
				}
			}
		}
		return $logger;
	}
}
