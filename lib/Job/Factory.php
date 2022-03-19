<?php

namespace Resque\Job;

use \Resque\Exceptions\ResqueException;

class Factory implements FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return \Resque\Job\JobInterface
	 * @throws \Resque\Exceptions\ResqueException
	 */
	public function create($className, $args, $queue)
	{
		if (!class_exists($className)) {
			throw new ResqueException(
				'Could not find job class ' . $className . '.'
			);
		}

		if (!method_exists($className, 'perform')) {
			throw new ResqueException(
				'Job class ' . $className . ' does not contain a perform method.'
			);
		}

		$instance = new $className();
		$instance->args = $args;
		$instance->queue = $queue;
		return $instance;
	}
}
