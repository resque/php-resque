<?php
declare(ticks = 1);

/**
 * ResqueScheduler worker to handle scheduling of delayed tasks.
 *
 * @package		ResqueScheduler
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @copyright	(c) 2012 Chris Boulton
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class ResqueScheduler_Worker
{
	const LOG_NONE = 0;
	const LOG_NORMAL = 1;
	const LOG_VERBOSE = 2;
	
	/**
	 * @var int Current log level of this worker.
	 */
	public $logLevel = 0;
	
	/**
	 * @var int Interval to sleep for between checking schedules.
	 */
	protected $interval = 5;

	/**
	 * @var boolean True if on the next iteration, the worker should shutdown.
	 */
	private $shutdown = false;

	/**
	 * @var boolean True if this worker is paused.
	 */
	private $paused = false;

	/**
	* The primary loop for a worker.
	*
	* Every $interval (seconds), the scheduled queue will be checked for jobs
	* that should be pushed to Resque.
	*
	* @param int $interval How often to check schedules.
	*/
	public function work($interval = null)
	{
		if ($interval !== null) {
			$this->interval = $interval;
		}

		$this->updateProcLine('Starting');
		$this->registerSigHandlers();

		while (true) {
			if($this->shutdown) {
				break;
			}
			if(!$this->paused) {
				$this->handleDelayedItems();
			}
			$this->sleep();
		}
	}
	
	/**
	 * Handle delayed items for the next scheduled timestamp.
	 *
	 * Searches for any items that are due to be scheduled in Resque
	 * and adds them to the appropriate job queue in Resque.
	 *
	 * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
	 */
	public function handleDelayedItems($timestamp = null)
	{
		while (($oldestJobTimestamp = ResqueScheduler::nextDelayedTimestamp($timestamp)) !== false) {
			$this->updateProcLine('Processing Delayed Items');
			$this->enqueueDelayedItemsForTimestamp($oldestJobTimestamp);
		}
	}
	
	/**
	 * Schedule all of the delayed jobs for a given timestamp.
	 *
	 * Searches for all items for a given timestamp, pulls them off the list of
	 * delayed jobs and pushes them across to Resque.
	 *
	 * @param DateTime|int $timestamp Search for any items up to this timestamp to schedule.
	 */
	public function enqueueDelayedItemsForTimestamp($timestamp)
	{
		$item = null;
		while ($item = ResqueScheduler::nextItemForTimestamp($timestamp)) {
			$this->log('queueing ' . $item['class'] . ' in ' . $item['queue'] .' [delayed]');
			
			Resque_Event::trigger('beforeDelayedEnqueue', array(
				'queue' => $item['queue'],
				'class' => $item['class'],
				'args'  => $item['args'],
			));

			$payload = array_merge(array($item['queue'], $item['class']), $item['args']);
			call_user_func_array('Resque::enqueue', $payload);
		}
	}
	
	/**
	 * Sleep for the defined interval.
	 */
	protected function sleep()
	{
		sleep($this->interval);
	}
	
	/**
	 * Update the status of the current worker process.
	 *
	 * On supported systems (with the PECL proctitle module installed), update
	 * the name of the currently running process to indicate the current state
	 * of a worker.
	 *
	 * @param string $status The updated process title.
	 */
	private function updateProcLine($status)
	{
		if(function_exists('setproctitle')) {
			setproctitle('resque-scheduler-' . ResqueScheduler::VERSION . ': ' . $status);
		}
	}
	
	/**
	 * Output a given log message to STDOUT.
	 *
	 * @param string $message Message to output.
	 */
	public function log($message)
	{
		if($this->logLevel == self::LOG_NORMAL) {
			fwrite(STDOUT, "*** " . $message . "\n");
		}
		else if($this->logLevel == self::LOG_VERBOSE) {
			fwrite(STDOUT, "** [" . strftime('%T %Y-%m-%d') . "] " . $message . "\n");
		}
	}

	/**
	 * Register signal handlers that a worker should respond to.
	 *
	 * TERM: Shutdown after the current timestamp was processed.
	 * INT: Shutdown after the current timestamp was processed.
	 * QUIT: Shutdown after the current timestamp was processed.
	 */
	private function registerSigHandlers()
	{
		if(!function_exists('pcntl_signal')) {
			return;
		}

		pcntl_signal(SIGTERM, array($this, 'shutdown'));
		pcntl_signal(SIGINT, array($this, 'shutdown'));
		pcntl_signal(SIGQUIT, array($this, 'shutdown'));
		pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
		pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));

		$this->log('Registered signals');
	}

	public function shutdown()
	{
		$this->log('Shutting down');
		$this->shutdown = true;
	}

	/**
	 * Signal handler callback for USR2, pauses processing.
	 */
	public function pauseProcessing()
	{
		$this->log('USR2 received; pausing processing');
		$this->paused = true;
	}

	/**
	 * Signal handler callback for CONT, resume processing.
	 */
	public function unPauseProcessing()
	{
		$this->log('CONT received; resuming processing');
		$this->paused = false;
	}
}
