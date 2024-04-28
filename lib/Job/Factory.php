<?php

namespace Resque\Job;

use Resque\Exceptions\ResqueException;

class Factory implements FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return \Resque\Job\Job
	 * @throws \Resque\Exceptions\ResqueException
	 */
	public function create($className, $args, $queue): Job
	{
		if (!class_exists($className)) {
			throw new ResqueException(
				'Could not find job class ' . $className . '.'
			);
		}

		$instance = new $className();
		$instance->args = $args;
		$instance->queue = $queue;
		return $instance;
	}
}
