<?php

namespace Resque\Tests;

use \Resque\Logger;

/**
 * Logger tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class LoggerTest extends ResqueTestCase
{
	public function testLogInterpolate()
	{
		$logger   = new Logger();
		$actual   = $logger->interpolate('string {replace}', array('replace' => 'value'));
		$expected = 'string value';

		$this->assertEquals($expected, $actual);
	}

	public function testLogInterpolateMutiple()
	{
		$logger   = new Logger();
		$actual   = $logger->interpolate(
			'string {replace1} {replace2}',
			array('replace1' => 'value1', 'replace2' => 'value2')
		);
		$expected = 'string value1 value2';

		$this->assertEquals($expected, $actual);
	}
}
