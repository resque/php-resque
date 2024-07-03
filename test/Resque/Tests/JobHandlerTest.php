<?php

namespace Resque\Tests;

use \Resque\Worker\ResqueWorker;
use \Resque\Resque;
use \Resque\Redis;
use \Resque\JobHandler;
use \Resque\Stat;
use \Resque\Job\Job;
use \Resque\Job\FactoryInterface;
use \Test_Job_With_SetUp;
use \Test_Job_With_TearDown;
use \CredisException;
use \stdClass;

/**
 * JobHandler tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class JobHandlerTest extends ResqueTestCase
{
	protected $worker;

	public function setUp(): void
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new ResqueWorker('jobs');
		$this->worker->setLogger($this->logger);
		$this->worker->registerWorker();
	}

	public function testJobCanBeQueued()
	{
		$this->assertTrue((bool)Resque::enqueue('jobs', 'Test_Job'));
	}

	public function testRedisErrorThrowsExceptionOnJobCreation()
	{
		$this->expectException('\Resque\Exceptions\RedisException');

		$mockCredis = $this->getMockBuilder('Credis_Client')
			->setMethods(['connect', '__call'])
			->getMock();
		$mockCredis->expects($this->any())->method('__call')
			->will($this->throwException(new CredisException('failure')));

		Resque::setBackend(function($database) use ($mockCredis) {
			return new Redis('localhost:6379', $database, $mockCredis);
		});
		Resque::enqueue('jobs', 'This is a test');
	}

	public function testQeueuedJobCanBeReserved()
	{
		Resque::enqueue('jobs', 'Test_Job');

		$job = JobHandler::reserve('jobs');
		if($job == false) {
			$this->fail('Job could not be reserved.');
		}
		$this->assertEquals('jobs', $job->queue);
		$this->assertEquals('Test_Job', $job->payload['class']);
	}

	public function testQueuedJobReturnsExactSamePassedInArguments()
	{
		$args = array(
			'int' => 123,
			'numArray' => array(
				1,
				2,
			),
			'assocArray' => array(
				'key1' => 'value1',
				'key2' => 'value2'
			),
		);
		Resque::enqueue('jobs', 'Test_Job', $args);
		$job = JobHandler::reserve('jobs');

		$this->assertEquals($args, $job->getArguments());
	}

	public function testAfterJobIsReservedItIsRemoved()
	{
		Resque::enqueue('jobs', 'Test_Job');
		JobHandler::reserve('jobs');
		$this->assertFalse(JobHandler::reserve('jobs'));
	}

	public function testRecreatedJobMatchesExistingJob()
	{
		$args = array(
			'int' => 123,
			'numArray' => array(
				1,
				2,
			),
			'assocArray' => array(
				'key1' => 'value1',
				'key2' => 'value2'
			),
		);

		Resque::enqueue('jobs', 'Test_Job', $args);
		$job = JobHandler::reserve('jobs');

		// Now recreate it
		$job->recreate();

		$newJob = JobHandler::reserve('jobs');
		$this->assertEquals($job->payload['class'], $newJob->payload['class']);
		$this->assertEquals($job->getArguments(), $newJob->getArguments());
	}


	public function testFailedJobExceptionsAreCaught()
	{
		$payload = array(
			'class' => 'Failing_Job',
			'args' => null
		);
		$job = new JobHandler('jobs', $payload);
		$job->worker = $this->worker;

		$this->worker->perform($job);

		$this->assertEquals(1, Stat::get('failed'));
		$this->assertEquals(1, Stat::get('failed:'.$this->worker));
	}

	public function testJobWithoutPerformMethodThrowsException()
	{
		$this->expectException('\Resque\Exceptions\ResqueException');

		Resque::enqueue('jobs', 'Test_Job_Without_Perform_Method');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}

	public function testInvalidJobThrowsException()
	{
		$this->expectException('Resque\Exceptions\ResqueException');

		Resque::enqueue('jobs', 'Invalid_Job');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}

	public function testJobWithSetUpCallbackFiresSetUp()
	{
		$payload = array(
			'class' => 'Test_Job_With_SetUp',
			'args' => array(array(
				'somevar',
				'somevar2',
			)),
		);
		$job = new JobHandler('jobs', $payload);
		$job->perform();

		$this->assertTrue(Test_Job_With_SetUp::$called);
	}

	public function testJobWithTearDownCallbackFiresTearDown()
	{
		$payload = array(
			'class' => 'Test_Job_With_TearDown',
			'args' => array(array(
				'somevar',
				'somevar2',
			)),
		);
		$job = new JobHandler('jobs', $payload);
		$job->perform();

		$this->assertTrue(Test_Job_With_TearDown::$called);
	}

	public function testNamespaceNaming() {
		$fixture = array(
			array('test' => 'more:than:one:with:', 'assertValue' => 'more:than:one:with:'),
			array('test' => 'more:than:one:without', 'assertValue' => 'more:than:one:without:'),
			array('test' => 'resque', 'assertValue' => 'resque:'),
			array('test' => 'resque:', 'assertValue' => 'resque:'),
		);

		foreach($fixture as $item) {
			Redis::prefix($item['test']);
			$this->assertEquals(Redis::getPrefix(), $item['assertValue']);
		}
	}

	public function testJobWithNamespace()
	{
		Redis::prefix('php');
		$queue = 'jobs';
		$payload = array('another_value');
		Resque::enqueue($queue, 'Test_Job_With_TearDown', $payload);

		$this->assertEquals(Resque::queues(), array('jobs'));
		$this->assertEquals(Resque::size($queue), 1);

		Redis::prefix('resque');
		$this->assertEquals(Resque::size($queue), 0);
	}

	public function testDequeueAll()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		$this->assertEquals(Resque::size($queue), 2);
		$this->assertEquals(Resque::dequeue($queue), 2);
		$this->assertEquals(Resque::size($queue), 0);
	}

	public function testDequeueMakeSureNotDeleteOthers()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		$other_queue = 'other_jobs';
		Resque::enqueue($other_queue, 'Test_Job_Dequeue');
		Resque::enqueue($other_queue, 'Test_Job_Dequeue');
		$this->assertEquals(Resque::size($queue), 2);
		$this->assertEquals(Resque::size($other_queue), 2);
		$this->assertEquals(Resque::dequeue($queue), 2);
		$this->assertEquals(Resque::size($queue), 0);
		$this->assertEquals(Resque::size($other_queue), 2);
	}

	public function testDequeueSpecificItem()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue1');
		Resque::enqueue($queue, 'Test_Job_Dequeue2');
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue2');
		$this->assertEquals(Resque::dequeue($queue, $test), 1);
		$this->assertEquals(Resque::size($queue), 1);
	}

	public function testDequeueSpecificMultipleItems()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue1');
		Resque::enqueue($queue, 'Test_Job_Dequeue2');
		Resque::enqueue($queue, 'Test_Job_Dequeue3');
		$this->assertEquals(Resque::size($queue), 3);
		$test = array('Test_Job_Dequeue2', 'Test_Job_Dequeue3');
		$this->assertEquals(Resque::dequeue($queue, $test), 2);
		$this->assertEquals(Resque::size($queue), 1);
	}

	public function testDequeueNonExistingItem()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue1');
		Resque::enqueue($queue, 'Test_Job_Dequeue2');
		Resque::enqueue($queue, 'Test_Job_Dequeue3');
		$this->assertEquals(Resque::size($queue), 3);
		$test = array('Test_Job_Dequeue4');
		$this->assertEquals(Resque::dequeue($queue, $test), 0);
		$this->assertEquals(Resque::size($queue), 3);
	}

	public function testDequeueNonExistingItem2()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue1');
		Resque::enqueue($queue, 'Test_Job_Dequeue2');
		Resque::enqueue($queue, 'Test_Job_Dequeue3');
		$this->assertEquals(Resque::size($queue), 3);
		$test = array('Test_Job_Dequeue4', 'Test_Job_Dequeue1');
		$this->assertEquals(Resque::dequeue($queue, $test), 1);
		$this->assertEquals(Resque::size($queue), 2);
	}

	public function testDequeueItemID()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		$qid = Resque::enqueue($queue, 'Test_Job_Dequeue');
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue' => $qid);
		$this->assertEquals(Resque::dequeue($queue, $test), 1);
		$this->assertEquals(Resque::size($queue), 1);
	}

	public function testDequeueWrongItemID()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		$qid = Resque::enqueue($queue, 'Test_Job_Dequeue');
		$this->assertEquals(Resque::size($queue), 2);
		#qid right but class name is wrong
		$test = array('Test_Job_Dequeue1' => $qid);
		$this->assertEquals(Resque::dequeue($queue, $test), 0);
		$this->assertEquals(Resque::size($queue), 2);
	}

	public function testDequeueWrongItemID2()
	{
		$queue = 'jobs';
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		$qid = Resque::enqueue($queue, 'Test_Job_Dequeue');
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue' => 'r4nD0mH4sh3dId');
		$this->assertEquals(Resque::dequeue($queue, $test), 0);
		$this->assertEquals(Resque::size($queue), 2);
	}

	public function testDequeueItemWithArg()
	{
		$queue = 'jobs';
		$arg = array('foo' => 1, 'bar' => 2);
		Resque::enqueue($queue, 'Test_Job_Dequeue9');
		Resque::enqueue($queue, 'Test_Job_Dequeue9', $arg);
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue9' => $arg);
		$this->assertEquals(Resque::dequeue($queue, $test), 1);
		#$this->assertEquals(Resque::size($queue), 1);
	}

	public function testDequeueSeveralItemsWithArgs()
	{
		// GIVEN
		$queue = 'jobs';
		$args = array('foo' => 1, 'bar' => 10);
		$removeArgs = array('foo' => 1, 'bar' => 2);
		Resque::enqueue($queue, 'Test_Job_Dequeue9', $args);
		Resque::enqueue($queue, 'Test_Job_Dequeue9', $removeArgs);
		Resque::enqueue($queue, 'Test_Job_Dequeue9', $removeArgs);
		$this->assertEquals(Resque::size($queue), 3);

		// WHEN
		$test = array('Test_Job_Dequeue9' => $removeArgs);
		$removedItems = Resque::dequeue($queue, $test);

		// THEN
		$this->assertEquals($removedItems, 2);
		$this->assertEquals(Resque::size($queue), 1);
		$item = Resque::pop($queue);
		$this->assertIsArray($item['args']);
		$this->assertEquals(10, $item['args'][0]['bar'], 'Wrong items were dequeued from queue!');
	}

	public function testDequeueItemWithUnorderedArg()
	{
		$queue = 'jobs';
		$arg = array('foo' => 1, 'bar' => 2);
		$arg2 = array('bar' => 2, 'foo' => 1);
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		Resque::enqueue($queue, 'Test_Job_Dequeue', $arg);
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue' => $arg2);
		$this->assertEquals(Resque::dequeue($queue, $test), 1);
		$this->assertEquals(Resque::size($queue), 1);
	}

	public function testDequeueItemWithiWrongArg()
	{
		$queue = 'jobs';
		$arg = array('foo' => 1, 'bar' => 2);
		$arg2 = array('foo' => 2, 'bar' => 3);
		Resque::enqueue($queue, 'Test_Job_Dequeue');
		Resque::enqueue($queue, 'Test_Job_Dequeue', $arg);
		$this->assertEquals(Resque::size($queue), 2);
		$test = array('Test_Job_Dequeue' => $arg2);
		$this->assertEquals(Resque::dequeue($queue, $test), 0);
		$this->assertEquals(Resque::size($queue), 2);
	}

	public function testUseDefaultFactoryToGetJobInstance()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => null
		);
		$job = new JobHandler('jobs', $payload);
		$instance = $job->getInstance();
		$this->assertInstanceOf('Resque\Tests\Some_Job_Class', $instance);
	}

	public function testUseFactoryToGetJobInstance()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => array(array())
		);
		$job = new JobHandler('jobs', $payload);
		$factory = new Some_Stub_Factory();
		$job->setJobFactory($factory);
		$instance = $job->getInstance();
		$this->assertInstanceOf('Resque\Job\Job', $instance);
	}

	public function testDoNotUseFactoryToGetInstance()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => array(array())
		);
		$job = new JobHandler('jobs', $payload);
		$factory = $this->getMockBuilder('Resque\Job\FactoryInterface')
			->getMock();
		$testJob = $this->getMockBuilder('Resque\Job\Job')
			->getMock();
		$factory->expects(self::never())->method('create')->will(self::returnValue($testJob));
		$instance = $job->getInstance();
		$this->assertInstanceOf('Resque\Job\Job', $instance);
	}

	public function testJobStatusIsNullIfIdMissingFromPayload()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => null
		);
		$job = new JobHandler('jobs', $payload);
		$this->assertEquals(null, $job->getStatus());
	}

	public function testJobCanBeRecreatedFromLegacyPayload()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => null
		);
		$job = new JobHandler('jobs', $payload);
		$job->recreate();
		$newJob = JobHandler::reserve('jobs');
		$this->assertEquals('jobs', $newJob->queue);
		$this->assertEquals('Resque\Tests\Some_Job_Class', $newJob->payload['class']);
		$this->assertNotNull($newJob->payload['id']);
	}

	public function testJobHandlerSetsPopTime()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => null
		);

		$now = microtime(true);

		$job = new JobHandler('jobs', $payload);
		$instance = $job->getInstance();

		$this->assertIsFloat($job->popTime);
		$this->assertTrue($job->popTime >= $now);
	}

	public function testJobHandlerSetsStartAndEndTimeForSuccessfulJob()
	{
		$payload = array(
			'class' => 'Resque\Tests\Some_Job_Class',
			'args' => null
		);

		$job = new JobHandler('jobs', $payload);
		$job->perform();

		$this->assertIsFloat($job->startTime);
		$this->assertTrue($job->startTime >= $job->popTime);

		$this->assertIsFloat($job->endTime);
		$this->assertTrue($job->endTime >= $job->startTime);
	}

	public function testJobHandlerSetsStartAndEndTimeForFailedJob()
	{
		$payload = array(
			'class' => 'Failing_Job',
			'args' => null
		);
		$job = new JobHandler('jobs', $payload);
		$job->worker = $this->worker;

		$this->worker->perform($job);

		$this->assertIsFloat($job->startTime);
		$this->assertTrue($job->startTime >= $job->popTime);

		$this->assertIsFloat($job->endTime);
		$this->assertTrue($job->endTime >= $job->startTime);
	}
}

class Some_Job_Class extends Job
{

	/**
	 * @return bool
	 */
	public function perform()
	{
		return true;
	}
}

class Some_Stub_Factory implements FactoryInterface
{

	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return Resque\Job\Job
	 */
	public function create($className, $args, $queue): Job
	{
		return new Some_Job_Class();
	}
}
