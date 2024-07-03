<?php

namespace Resque\Tests;

use \Resque\Worker\ResqueWorker;
use \Resque\Job\Status;
use \Resque\JobHandler;
use \Resque\Resque;
use \stdClass;

/**
 * Status tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class JobStatusTest extends ResqueTestCase
{
	/**
	 * @var \Resque\Worker\ResqueWorker
	 */
	protected $worker;

	public function setUp(): void
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new ResqueWorker('jobs');
		$this->worker->setLogger($this->logger);
	}

	/**
	 * Unit test data provider for potential return values from perform().
	 *
	 * @return array
	 */
	public static function performResultProvider(): array
	{
		$data = [];

		$data['boolean'] = [ true ];
		$data['float']   = [ 1.0 ];
		$data['integer'] = [ 100 ];
		$data['string']  = [ 'string' ];
		$data['null']    = [ null ];
		$data['array']   = [[ 'key' => 'value' ]];

		return $data;
	}

	public function testJobStatusCanBeTracked()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', [], true);
		$status = new Status($token);
		$this->assertTrue($status->isTracking());
	}

	public function testJobStatusIsReturnedViaJobInstance()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', [], true);
		$job = JobHandler::reserve('jobs');
		$this->assertEquals(Status::STATUS_WAITING, $job->getStatus());
	}

	public function testQueuedJobReturnsQueuedStatus()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', [], true);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_WAITING, $status->get());
	}

	public function testRunningJobReturnsRunningStatus()
	{
		$token = Resque::enqueue('jobs', 'Failing_Job', [], true);
		$job = $this->worker->reserve();
		$this->worker->workingOn($job);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_RUNNING, $status->get());
	}

	public function testFailedJobReturnsFailedStatus()
	{
		$token = Resque::enqueue('jobs', 'Failing_Job', [], true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_FAILED, $status->get());
	}

	public function testCompletedJobReturnsCompletedStatus()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', [], true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_COMPLETE, $status->get());
	}

	/**
	 * @param mixed $value Potential return value from perform()
	 *
	 * @dataProvider performResultProvider
	 */
	public function testCompletedJobReturnsResult($value)
	{
		$token = Resque::enqueue('jobs', 'Returning_Job', [ 'return' => $value ], true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals($value, $status->result());
	}

	public function testCompletedJobReturnsObjectResultAsArray()
	{
		$value = new stdClass();
		$token = Resque::enqueue('jobs', 'Returning_Job', [ 'return' => $value ], true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals([], $status->result());
	}

	public function testStatusIsNotTrackedWhenToldNotTo()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', [], false);
		$status = new Status($token);
		$this->assertFalse($status->isTracking());
	}

	public function testStatusTrackingCanBeStopped()
	{
		Status::create('test');
		$status = new Status('test');
		$this->assertEquals(Status::STATUS_WAITING, $status->get());
		$status->stop();
		$this->assertFalse($status->get());
	}

	public function testRecreatedJobWithTrackingStillTracksStatus()
	{
		$originalToken = Resque::enqueue('jobs', 'Test_Job', [], true);
		$job = $this->worker->reserve();

		// Mark this job as being worked on to ensure that the new status is still
		// waiting.
		$this->worker->workingOn($job);

		// Now recreate it
		$newToken = $job->recreate();

		// Make sure we've got a new job returned
		$this->assertNotEquals($originalToken, $newToken);

		// Now check the status of the new job
		$newJob = JobHandler::reserve('jobs');
		$this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus());
	}
}
