<?php

namespace Resque\Tests;

use \Resque\Worker\ResqueWorker;
use \Resque\Stat;
use \Resque\Resque;
use \Resque\JobHandler;

/**
 * ResqueWorker tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class WorkerTest extends ResqueTestCase
{
	public function testWorkerRegistersInList()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		// Make sure the worker is in the list
		$this->assertTrue((bool)$this->redis->sismember('resque:workers', (string)$worker));
	}

	public function testGetAllWorkers()
	{
		$num = 3;
		// Register a few workers
		for($i = 0; $i < $num; ++$i) {
			$worker = new ResqueWorker('queue_' . $i);
			$worker->setLogger($this->logger);
			$worker->registerWorker();
		}

		// Now try to get them
		$this->assertEquals($num, count(ResqueWorker::all()));
	}

	public function testGetWorkerById()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		$newWorker = ResqueWorker::find((string)$worker);
		$this->assertEquals((string)$worker, (string)$newWorker);
	}

	public function testInvalidWorkerDoesNotExist()
	{
		$this->assertFalse(ResqueWorker::exists('blah'));
	}

	public function testWorkerCanUnregister()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->registerWorker();
		$worker->unregisterWorker();

		$this->assertFalse(ResqueWorker::exists((string)$worker));
		$this->assertEquals(array(), ResqueWorker::all());
		$this->assertEquals(array(), $this->redis->smembers('resque:workers'));
	}

	public function testPausedWorkerDoesNotPickUpJobs()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$worker->work(0);
		$this->assertEquals(0, Stat::get('processed'));
	}

	public function testResumedWorkerPicksUpJobs()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$this->assertEquals(0, Stat::get('processed'));
		$worker->unPauseProcessing();
		$worker->work(0);
		$this->assertEquals(1, Stat::get('processed'));
	}

	public function testWorkerCanWorkOverMultipleQueues()
	{
		$worker = new ResqueWorker(array(
			'queue1',
			'queue2'
		));
		$worker->setLogger($this->logger);
		$worker->registerWorker();
		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerWorksQueuesInSpecifiedOrder()
	{
		$worker = new ResqueWorker(array(
			'high',
			'medium',
			'low'
		));
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		// Queue the jobs in a different order
		Resque::enqueue('low', 'Test_Job_1');
		Resque::enqueue('high', 'Test_Job_2');
		Resque::enqueue('medium', 'Test_Job_3');

		// Now check we get the jobs back in the right order
		$job = $worker->reserve();
		$this->assertEquals('high', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('medium', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('low', $job->queue);
	}

	public function testWildcardQueueWorkerWorksAllQueues()
	{
		$worker = new ResqueWorker('*');
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerDoesNotWorkOnUnknownQueues()
	{
		$worker = new ResqueWorker('queue1');
		$worker->setLogger($this->logger);
		$worker->registerWorker();
		Resque::enqueue('queue2', 'Test_Job');

		$this->assertFalse($worker->reserve());
	}

	public function testWorkerClearsItsStatusWhenNotWorking()
	{
		Resque::enqueue('jobs', 'Test_Job');
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$job = $worker->reserve();
		$worker->workingOn($job);
		$worker->doneWorking();
		$this->assertEquals(array(), $worker->job());
	}

	public function testWorkerRecordsWhatItIsWorkingOn()
	{
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new JobHandler('jobs', $payload);
		$worker->workingOn($job);

		$job = $worker->job();
		$this->assertEquals('jobs', $job['queue']);
		if(!isset($job['run_at'])) {
			$this->fail('Job does not have run_at time');
		}
		$this->assertEquals($payload, $job['payload']);
	}

	public function testWorkerErasesItsStatsWhenShutdown()
	{
		Resque::enqueue('jobs', 'Test_Job');
		Resque::enqueue('jobs', 'Invalid_Job');

		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$worker->work(0);
		$worker->work(0);

		$this->assertEquals(0, $worker->getStat('processed'));
		$this->assertEquals(0, $worker->getStat('failed'));
	}

	public function testWorkerCleansUpDeadWorkersOnStartup()
	{
		// Register a good worker
		$goodWorker = new ResqueWorker('jobs');
		$goodWorker->setLogger($this->logger);
		$goodWorker->registerWorker();
		$workerId = explode(':', $goodWorker);

		// Register some bad workers
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$worker->setId($workerId[0].':1:jobs');
		$worker->registerWorker();

		$worker = new ResqueWorker(array('high', 'low'));
		$worker->setLogger($this->logger);
		$worker->setId($workerId[0].':2:high,low');
		$worker->registerWorker();

		$this->assertEquals(3, count(ResqueWorker::all()));

		$goodWorker->pruneDeadWorkers();

		// There should only be $goodWorker left now
		$this->assertEquals(1, count(ResqueWorker::all()));
	}

	public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
	{
		// Register a bad worker on this machine
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$workerId = explode(':', $worker);
		$worker->setId($workerId[0].':1:jobs');
		$worker->registerWorker();

		// Register some other false workers
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$worker->setId('my.other.host:1:jobs');
		$worker->registerWorker();

		$this->assertEquals(2, count(ResqueWorker::all()));

		$worker->pruneDeadWorkers();

		// my.other.host should be left
		$workers = ResqueWorker::all();
		$this->assertEquals(1, count($workers));
		$this->assertEquals((string)$worker, (string)$workers[0]);
	}

	public function testWorkerFailsUncompletedJobsOnExit()
	{
		$worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new JobHandler('jobs', $payload);

		$worker->workingOn($job);
		$worker->unregisterWorker();

		$this->assertEquals(1, Stat::get('failed'));
	}

    public function testBlockingListPop()
    {
        $worker = new ResqueWorker('jobs');
		$worker->setLogger($this->logger);
        $worker->registerWorker();

        Resque::enqueue('jobs', 'Test_Job_1');
        Resque::enqueue('jobs', 'Test_Job_2');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Test_Job_' . $i, $job->payload['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }

    public function testWorkerFailsSegmentationFaultJob()
    {
        Resque::enqueue('jobs', 'Test_Infinite_Recursion_Job');

        $worker = new ResqueWorker('jobs');
        $worker->setLogger($this->logger);
        $worker->work(0);

        $this->assertEquals(1, Stat::get('failed'));
    }
}
