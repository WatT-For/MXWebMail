<?php

/*
 * This file is part of MailSo.
 *
 * (c) 2014 Usenko Timur
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MailSo\Log\Drivers;

/**
 * @category MailSo
 * @package Log
 * @subpackage Drivers
 */
class File extends \MailSo\Log\Driver
{
	private string $sLoggerFileName;

	function __construct(string $sLoggerFileName)
	{
		parent::__construct();

		$sLogFileDir = \dirname($sLoggerFileName);
		if (!\is_dir($sLogFileDir)) {
			\mkdir($sLogFileDir, 0755, true);
		}
		$this->sLoggerFileName = $sLoggerFileName;
	}

	protected function writeImplementation($mDesc) : bool
	{
		if (\is_array($mDesc)) {
			$mDesc = \implode("\n\t", $mDesc);
		}
		// Rotation par taille (logs.max_size Mio / logs.keep) — même règle
		// que App::log()/logAuth() pour app.log et le journal d'auth.
		$cfg = \MXWebMail\API::Config();
		\MXWebMail\Application\App::rotateLog(
			$this->sLoggerFileName,
			\max(1, (int) $cfg->Get('logs', 'max_size', 5)) * 1048576,
			\min(20, \max(0, (int) $cfg->Get('logs', 'keep', 3))));
		return \error_log($mDesc . "\n", 3, $this->sLoggerFileName);
	}

	protected function clearImplementation() : bool
	{
		return \unlink($this->sLoggerFileName);
	}
}
