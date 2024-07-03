<?php

namespace Resque\Job;

interface FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return \Resque\Job\Job
	 */
	public function create(string $className, array $args, string $queue): Job;
}
