<?php

namespace Resque\Job;

interface JobInterface
{
	/**
	 * @return bool
	 */
	public function perform();
}
