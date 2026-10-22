<?php

namespace MXWebMailLib;

abstract class Log
{
	// Same as RFC 5424 section 6.2.1 decimal Severity level indicator
	// http://tools.ietf.org/html/rfc5424#section-6.2.1

	public static function debug    (string $prefix, string $msg) { self::log(\LOG_DEBUG, $prefix, $msg); }

	public static function info     (string $prefix, string $msg) { self::log(\LOG_INFO, $prefix, $msg); }

	public static function notice   (string $prefix, string $msg) { self::log(\LOG_NOTICE, $prefix, $msg); }

	public static function warning  (string $prefix, string $msg) { self::log(\LOG_WARNING, $prefix, $msg); }

	public static function error    (string $prefix, string $msg)  { self::log(\LOG_ERR, $prefix, $msg); }

	public static function critical (string $prefix, string $msg)  { self::log(\LOG_CRIT, $prefix, $msg); }

	public static function alert    (string $prefix, string $msg)  { self::log(\LOG_ALERT, $prefix, $msg); }

	public static function emergency(string $prefix, string $msg)  { self::log(\LOG_EMERG, $prefix, $msg); }

	private static
		$levels = [
			\LOG_EMERG   => 'EMERGENCY',
			\LOG_ALERT   => 'ALERT',
			\LOG_CRIT    => 'CRITICAL',
			\LOG_ERR     => 'ERROR',
			\LOG_WARNING => 'WARNING',
			\LOG_NOTICE  => 'NOTICE',
			\LOG_INFO    => 'INFO',
			\LOG_DEBUG   => 'DEBUG',
		];

	protected static function log(int $level, string $prefix, string $msg)
	{
		if (\MXWebMail\API::Logger()->IsEnabled()) {
			\MXWebMail\API::Logger()->Write($msg, $level, $prefix);
			return;
		}
		// Logger MailSo inactif : repli sur syslog() si demandé ('logs.syslog').
		static $log_level = null;
		if ($log_level === null) {
			// 0 = LOG_EMERG … 7 = LOG_DEBUG
			$log_level = \max(3, (int) \MXWebMail\API::Config()->Get('logs', 'level', \LOG_WARNING));
		}
		if ($level <= $log_level
			&& \MXWebMail\API::Config()->Get('logs', 'syslog', false)
			&& \function_exists('syslog')
			&& \openlog('mxwebmail', \LOG_ODELAY, \LOG_USER)) {
			\syslog($level, "{$prefix} {$msg}");
			\closelog();
		}
	}
}
