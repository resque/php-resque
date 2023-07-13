<?php

/**
 * Resque job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Job
{
	/**
	 * @var string The name of the queue that this job belongs to.
	 */
	public $queue;

	/**
	 * @var Resque_Worker Instance of the Resque worker running this job.
	 */
	public $worker;

	/**
	 * @var array Array containing details of the job.
	 */
	public $payload;

	/**
	 * @var object|Resque_JobInterface Instance of the class performing work for this job.
	 */
	private $instance;

	/**
	 * @var Resque_Job_FactoryInterface
	 */
	private $jobFactory;

	/**
	 * Instantiate a new instance of a job.
	 *
	 * @param string $queue The queue that the job belongs to.
	 * @param array $payload array containing details of the job.
	 */
	public function __construct($queue, $payload)
	{
		$this->queue = $queue;
		$this->payload = $payload;
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $monitor Set to true to be able to monitor the status of a job.
	 * @param string $id Unique identifier for tracking the job. Generated if not supplied.
	 * @param string $prefix The prefix needs to be set for the status key
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public static function create($queue, $class, $args = null, $monitor = false, $id = null, $prefix = "")
	{
		if (is_null($id)) {
			$id = Resque::generateJobId();
		}

		if ($args !== null && !is_array($args)) {
			throw new InvalidArgumentException(
				'Supplied $args must be an array.'
			);
		}
		Resque::push($queue, array(
			'class'	     => $class,
			'args'	     => array($args),
			'id'	     => $id,
			'prefix'     => $prefix,
			'queue_time' => microtime(true),
		));

		if ($monitor) {
			Resque_Job_Status::create($id, $prefix);
		}

		return $id;
	}

	/**
	 * Find the next available job from the specified queue and return an
	 * instance of Resque_Job for it.
	 *
	 * @param string $queue The name of the queue to check for a job in.
	 * @return false|object Null when there aren't any waiting jobs, instance of Resque_Job when a job was found.
	 */
	public static function reserve($queue)
	{
		$payload = Resque::pop($queue);
		if (!is_array($payload)) {
			return false;
		}

		return new Resque_Job($queue, $payload);
	}

	/**
	 * Find the next available job from the specified queues using blocking list pop
	 * and return an instance of Resque_Job for it.
	 *
	 * @param array             $queues
	 * @param int               $timeout
	 * @return false|object Null when there aren't any waiting jobs, instance of Resque_Job when a job was found.
	 */
	public static function reserveBlocking(array $queues, $timeout = null)
	{
		$item = Resque::blpop($queues, $timeout);

		if (!is_array($item)) {
			return false;
		}

		return new Resque_Job($item['queue'], $item['payload']);
	}

	/**
	 * Update the status of the current job.
	 *
	 * @param int $status Status constant from Resque_Job_Status indicating the current status of a job.
	 */
	public function updateStatus($status, $result = null)
	{
		if (empty($this->payload['id'])) {
			return;
		}

		$statusInstance = new Resque_Job_Status($this->payload['id'], $this->getPrefix());
		$statusInstance->update($status, $result);
	}

	/**
	 * Return the status of the current job.
	 *
	 * @return int|null The status of the job as one of the Resque_Job_Status constants or null if job is not being tracked.
	 */
	public function getStatus()
	{
		if (empty($this->payload['id'])) {
			return null;
		}

		$status = new Resque_Job_Status($this->payload['id'], $this->getPrefix());
		return $status->get();
	}

	/**
	 * Get the arguments supplied to this job.
	 *
	 * @return array Array of arguments.
	 */
	public function getArguments()
	{
		if (!isset($this->payload['args'])) {
			return array();
		}

		return $this->payload['args'][0];
	}

	/**
	 * Get the instantiated object for this job that will be performing work.
	 * @return Resque_JobInterface Instance of the object that this job belongs to.
	 * @throws Resque_Exception
	 */
	public function getInstance()
	{
		if (!is_null($this->instance)) {
			return $this->instance;
		}

		$this->instance = $this->getJobFactory()->create($this->payload['class'], $this->getArguments(), $this->queue);
		$this->instance->job = $this;
		return $this->instance;
	}

	/**
	 * Actually execute a job by calling the perform method on the class
	 * associated with the job with the supplied arguments.
	 *
	 * @return bool
	 * @throws Resque_Exception When the job's class could not be found or it does not contain a perform method.
	 */
	public function perform()
	{
		$result = true;
		try {
			Resque_Event::trigger('beforePerform', $this);

			$instance = $this->getInstance();
			if (is_callable([$instance, 'setUp'])) {
				$instance->setUp();
			}

			$result = $instance->perform();

			if (is_callable([$instance, 'tearDown'])) {
				$instance->tearDown();
			}

			Resque_Event::trigger('afterPerform', $this);
		}
		// beforePerform/setUp have said don't perform this job. Return.
		catch (Resque_Job_DontPerform $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * Mark the current job as having failed.
	 *
	 * @param $exception
	 */
	public function fail($exception)
	{
		Resque_Event::trigger('onFailure', array(
			'exception' => $exception,
			'job' => $this,
		));

		$this->updateStatus(Resque_Job_Status::STATUS_FAILED);
		if ($exception instanceof Error) {
			Resque_Failure::createFromError(
				$this->payload,
				$exception,
				$this->worker,
				$this->queue
			);
		} else {
			Resque_Failure::create(
				$this->payload,
				$exception,
				$this->worker,
				$this->queue
			);
		}
		
		if(!empty($this->payload['id'])) {
			Resque_Job_PID::del($this->payload['id']);
		}

		Resque_Stat::incr('failed');
		Resque_Stat::incr('failed:' . $this->worker);
	}

	/**
	 * Re-queue the current job.
	 * @return string
	 */
	public function recreate()
	{
		$monitor = false;
		if (!empty($this->payload['id'])) {
			$status = new Resque_Job_Status($this->payload['id'], $this->getPrefix());
			if ($status->isTracking()) {
				$monitor = true;
			}
		}

		return self::create($this->queue, $this->payload['class'], $this->getArguments(), $monitor, null, $this->getPrefix());
	}

	/**
	 * Generate a string representation used to describe the current job.
	 *
	 * @return string The string representation of the job.
	 */
	public function __toString()
	{
		$name = array(
			'Job{' . $this->queue . '}'
		);
		if (!empty($this->payload['id'])) {
			$name[] = 'ID: ' . $this->payload['id'];
		}
		$name[] = $this->payload['class'];
		if (!empty($this->payload['args'])) {
			$name[] = json_encode($this->payload['args']);
		}
		return '(' . implode(' | ', $name) . ')';
	}

	/**
	 * @param Resque_Job_FactoryInterface $jobFactory
	 * @return Resque_Job
	 */
	public function setJobFactory(Resque_Job_FactoryInterface $jobFactory)
	{
		$this->jobFactory = $jobFactory;

		return $this;
	}

	/**
	 * @return Resque_Job_FactoryInterface
	 */
	public function getJobFactory()
	{
		if ($this->jobFactory === null) {
			$this->jobFactory = new Resque_Job_Factory();
		}
		return $this->jobFactory;
	}

	/**
	 * @return string
	 */
	private function getPrefix()
	{
		if (isset($this->payload['prefix'])) {
			return $this->payload['prefix'];
		}

		return '';
	}
}
