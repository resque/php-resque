<?php

namespace Resque\Job;

use Resque\Exceptions\ResqueException;

class Factory implements FactoryInterface
{
	/**
	 * @param class-string<\Resque\Job\Job> $className
	 * @param array        							 $args
	 * @param string       							 $queue
	 *
	 * @return \Resque\Job\Job
	 * @throws \Resque\Exceptions\ResqueException
	 */
	public function create(string $className, array $args, string $queue): Job
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
