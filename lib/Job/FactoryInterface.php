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
	public function create($className, $args, $queue): Job;
}
