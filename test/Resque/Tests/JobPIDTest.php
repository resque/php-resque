<?php

namespace Resque\Tests;

use \Resque\Worker\ResqueWorker;
use \Resque\Job\PID;
use \Resque\Resque;

/**
 * PID tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class JobPIDTest extends ResqueTestCase
{
	/**
	 * @var \Resque\Worker\ResqueWorker
	 */
	protected $worker;

	public function setUp()
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new ResqueWorker('jobs');
		$this->worker->setLogger($this->logger);
	}

	public function testQueuedJobDoesNotReturnPID()
	{
		$this->logger->expects($this->never())
					 ->method('log');

		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$this->assertEquals(0, PID::get($token));
	}

	public function testRunningJobReturnsPID()
	{
		// Cannot use InProgress_Job on non-forking OS.
		if(!function_exists('pcntl_fork')) return;

		$token = Resque::enqueue('jobs', 'InProgress_Job', null, true);
		$this->worker->work(0);
		$this->assertNotEquals(0, PID::get($token));
	}

	public function testFinishedJobDoesNotReturnPID()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$this->worker->work(0);
		$this->assertEquals(0, PID::get($token));
	}
}
