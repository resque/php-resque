<?php
class PHP_Error_Job
{
	public function perform()
	{
        /* @phpstan-ignore-next-line */
		callToUndefinedFunction();
	}
}
