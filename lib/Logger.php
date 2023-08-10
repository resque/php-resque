<?php

namespace Resque;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Resque default logger PSR-3 compliant
 *
 * @package		Resque/Stat
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Logger extends AbstractLogger
{
	public $verbose;

	public function __construct($verbose = false)
	{
		$this->verbose = $verbose;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed   $level    PSR-3 log level constant, or equivalent string
	 * @param string  $message  Message to log, may contain a { placeholder }
	 * @param array   $context  Variables to replace { placeholder }
	 * @return null
	 */
	public function log($level, $message, array $context = array())
	{
		if ($this->verbose) {
			fwrite(
				STDOUT,
				'[' . $level . '] [' . date('H:i:s Y-m-d') . '] ' . $this->interpolate($message, $context) . PHP_EOL
			);
			return;
		}

		if (!($level === LogLevel::INFO || $level === LogLevel::DEBUG)) {
			fwrite(
				STDOUT,
				'[' . $level . '] ' . $this->interpolate($message, $context) . PHP_EOL
			);
		}
	}

	/**
	 * Fill placeholders with the provided context
	 * @author Jordi Boggiano j.boggiano@seld.be
	 *
	 * @param  string  $message  Message to be logged
	 * @param  array   $context  Array of variables to use in message
	 * @return string
	 */
	public function interpolate($message, array $context = array())
	{
		// build a replacement array with braces around the context keys
		$replace = array();
		foreach ($context as $key => $val) {
			$replace['{' . $key . '}'] = $val;
		}

		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}
}
