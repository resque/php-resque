<?php

namespace Resque\Job;

use Resque\JobHandler;

/**
 * Base Resque Job class.
 *
 * @package		Resque\Job
 * @author		Heinz Wiesinger <pprkut@liwjatan.org>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
abstract class Job
{
	/**
	 * Job arguments
	 * @var array
	 */
	public $args;

	/**
	 * Associated JobHandler instance
	 * @var JobHandler
	 */
	public $job;

	/**
	 * Name of the queue the job was in
	 * @var string
	 */
	public $queue;

	/**
	 * (Optional) Job setup
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		// no-op
	}

	/**
	 * (Optional) Job teardown
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		// no-op
	}

	/**
	 * Main method of the Job
	 *
	 * @return mixed|void
	 */
	abstract public function perform();
}
